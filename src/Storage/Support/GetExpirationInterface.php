<?php

namespace Fozzy\Cache\Storage\Support;

/**
 * Interface ExpirationInterface
 *
 * Defines an interface for a storage adapter that supports checking the expiration of an item.
 *
 * @package Fozzy\Cache
 */
interface GetExpirationInterface
{
    /**
     * Return the time in unix time at which a cache entry will expire.
     *
     * @param string $key
     * @return int
     */
    public function getExpiration($key);
}
