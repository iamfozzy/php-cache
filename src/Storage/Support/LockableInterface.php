<?php

namespace Fozzy\Cache\Storage\Support;

/**
 * Interface LockableInterface
 *
 * Defines an interface for storage adapters that support locking() of the files
 * while they are being regenerated.
 *
 * These should be locks that allow multiple reads but no writing. When the file contents
 * is changed it should be changed atomically.
 *
 * @package Fozzy\Cache
 */
interface LockableInterface
{
    /**
     * @param string $key
     * @return mixed
     */
    public function lock($key);

    /**
     * @param string $key
     * @return mixed
     */
    public function unlock($key);
}
