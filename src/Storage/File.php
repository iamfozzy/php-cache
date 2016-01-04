<?php

namespace Fozzy\Cache\Storage;

use Fozzy\Cache\Exception\RuntimeException;

/**
 * Class File
 *
 * File storage adapter.
 *
 * Supports:
 *  Locking
 *  Expiration checking
 *  Age checking
 *
 * @package Fozzy\Cache\Storage
 */
class File implements
    StorageInterface,
    Support\LockableInterface,
    Support\GetExpirationInterface,
    Support\GetAgeInterface,
    Support\ClearByNamespaceInterface
{
    /**
     * @var string
     */
    protected $directory;

    /**
     * @var string
     */
    protected $suffix;

    /**
     * @var string      Temporary file suffix
     */
    protected $tmp = '.tmp';

    /**
     * @var array       File handles are stored here to prevent multiple fopens()
     */
    protected $fileHandles = [];

    /**
     * @param string $directory Cache directory
     * @param string $suffix    Cache file suffix
     * @param string $tmp       Temporary filename extension
     * @throws RuntimeException
     */
    public function __construct($directory, $suffix = '.cache', $tmp = '.tmp')
    {
        $this->directory = rtrim($directory, '/\\');
        $this->suffix    = $suffix;
        $this->tmp       = $tmp;

        if (!is_writable($directory)) {
            throw new RuntimeException(sprintf(
                'Directory %s is not writable.', $directory
            ));
        }
    }

    /**
     * Cleanup on Destruction
     */
    public function __destruct()
    {
        // Ensure all file handles are closed
        foreach ($this->fileHandles as $handle) {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
    }

    /**
     * @param string $key
     * @return bool
     */
    public function has($key)
    {
        $filename = $this->getFilename($key);

        if (!file_exists($filename)) {
            return false;
        }

        return $this->getExpiration($key) > time();
    }

    /**
     * @param string $key
     * @return string
     */
    protected function getFilename($key)
    {
        return $this->directory
            . DIRECTORY_SEPARATOR
            . $key
            . $this->suffix;
    }

    /**
     * @param string $key
     * @return bool|int
     */
    public function getExpiration($key)
    {
        $filename   = $this->getFilename($key);
        $file       = $this->getFileHandle($filename, 'r');
        fseek($file, 0);
        $expiration = (int) fgets($file);

        return $expiration;
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function get($key)
    {
        if (!$this->has($key)) {
            return false;
        }

        $data       = '';
        $filename   = $this->getFilename($key);
        $file       = $this->getFileHandle($filename, 'r');

        // Reset to beginning + skip first line
        fseek($file, 0);
        fgets($file);

        while (($buffer = fgets($file)) !== false) {
            $data .= $buffer;
        }

        // Close the file handle now - no need after get() to keep it open.
        $this->closeFileHandle($filename, 'r');

        return unserialize($data);
    }

    /**
     * @param string $key
     * @param mixed  $data
     * @param int    $ttl Time to live
     * @return bool
     */
    public function save($key, $data, $ttl)
    {
        $filename = $this->getFilename($key);
        $tempFile = $this->getFileHandle($filename . $this->tmp, 'w');
        $expires  = time() + $ttl;
        $contents = $expires . PHP_EOL . serialize($data);

        fwrite($tempFile, $contents);
        fflush($tempFile);

        $this->unlock($key);
        fclose($tempFile);

        // Rename
        return rename($filename . $this->tmp, $filename);
    }

    /**
     * @param string $key
     * @return bool
     */
    public function delete($key)
    {
        return @unlink($this->getFilename($key));
    }

    /**
     * Empties this storage and clears all data.
     *
     * @return void
     */
    public function clear()
    {
        $this->recursiveRemove($this->directory, true);
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function lock($key)
    {
        $filename = $this->getFilename($key) . $this->tmp;

        return flock($this->getFileHandle($filename, 'w'), LOCK_EX | LOCK_NB);
    }

    /**
     * Checks for an open file handle
     *
     * @param string $filename
     * @param string $options
     * @return void
     */
    protected function closeFileHandle($filename, $options = 'r')
    {
        $key = $filename . '.' . $options;

        if (isset($this->fileHandles[$key])) {
            fclose($this->fileHandles[$key]);
            unset($this->fileHandles[$key]);
        }
    }

    /**
     * @param string $filename
     * @param string $options
     * @return mixed
     */
    protected function getFileHandle($filename, $options = 'r')
    {
        $key = $filename . '.' . $options;

        if (!isset($this->fileHandles[$key])
            || (isset($this->fileHandles[$key]) && !is_resource($this->fileHandles[$key]))
        ) {
            $this->prepareDirectory($filename);
            $this->fileHandles[$key] = fopen($filename, $options);
        }

        return $this->fileHandles[$key];
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function unlock($key)
    {
        $filename = $this->getFilename($key) . $this->tmp;

        return flock($this->getFileHandle($filename, 'w'), LOCK_UN);
    }

    /**
     * Returns the age of this item in seconds
     *
     * @see Support\AgeInterface
     * @param string $key
     * @return int
     */
    public function getAge($key)
    {
        $filename   = $this->getFilename($key);
        $file       = fopen($filename, 'r');
        $expiration = (int) fgets($file);

        return $expiration - filemtime($filename);
    }

    /**
     * Removes files or directories.
     *
     * @param string|array $files
     * @param bool         $keepTopLevel - Should the top level folder be kept?
     */
    protected function recursiveRemove($files, $keepTopLevel = false)
    {
        if (!$files instanceof \Traversable) {
            $files = is_array($files) ? $files : array($files);
        }

        $files = iterator_to_array($files);
        $files = array_reverse($files);
        foreach ($files as $file) {
            if (!file_exists($file) && !is_link($file)) {
                continue;
            }

            if (is_dir($file) && !is_link($file)) {
                $this->recursiveRemove(new \FilesystemIterator($file), false);

                if (!$keepTopLevel) {
                    @rmdir($file);
                }
            } else {
                @unlink($file);
            }
        }
    }

    /**
     * @param $namespace
     */
    public function clearByNamespace($namespace)
    {
        $files = glob($this->directory . DIRECTORY_SEPARATOR . trim($namespace, '\\/') . '/*' . $this->suffix);

        $this->recursiveRemove($files, false);
    }

    /**
     * Passed a file path will create any required directories.
     *
     * @param string $path
     * @return bool
     */
    protected function prepareDirectory($path)
    {
        $path = dirname($path);

        if (file_exists($path)){
            return true;
        }

        return mkdir($path, 0700, true);
    }
}
