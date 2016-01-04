<?php

namespace Fozzy\Cache\Storage\Support;

/**
 * Interface ClearByNamespaceInterface
 *
 * If a class supports clearing by namespace then a '/' = namespace.
 *
 * If you store an object with key 'top/middle/bottom'
 * You may clearByNamespace('top') to clear all top/* cache.
 *
 * @package Fozzy\Cache\Storage\Support
 */
interface ClearByNamespaceInterface
{
    public function clearByNamespace($namespace);
}
