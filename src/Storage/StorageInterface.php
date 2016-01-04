<?php

namespace Fozzy\Cache\Storage;

/**
 * Interface StorageInterface
 *
 * @package Fozzy\Cache
 */
interface StorageInterface
{
    /**
     * @param string $key
     * @return bool
     */
    public function has($key);

    /**
     * @param string $key
     * @return mixed
     */
    public function get($key);

    /**
     * @param string $key
     * @param mixed  $data
     * @param int    $ttl       Time to live
     * @return bool
     */
    public function save($key, $data, $ttl);

    /**
     * @param string $key
     * @return bool
     */
    public function delete($key);

    /**
     * Empties this storage and clears all data.
     *
     * @return bool
     */
    public function clear();
}
