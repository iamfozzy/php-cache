<?php

namespace Fozzy\Cache\Storage\Support;

/**
 * Interface AgeInterface
 *
 * @package Fozzy\Cache
 */
interface GetAgeInterface
{
    /**
     * Returns the age of this item in seconds
     *
     * @param string $key
     * @return int
     */
    public function getAge($key);
}
