<?php

namespace Fozzy\Cache;

/**
 * Interface PoolInterface
 *
 * @package Fozzy\Cache
 */
interface PoolInterface
{
    /**
     * @param string $key
     * @return ItemInterface
     */
    public function getItem($key);

    /**
     * @param array $keys
     * @return ItemInterface[]
     */
    public function getItems(array $keys = array());

    /**
     * Deletes all items in the pool
     *
     * @return boolean
     */
    public function clear();

    /**
     * @param ItemInterface $item
     * @return static
     */
    public function save(ItemInterface $item);

    /**
     * @param array $keys
     * @return mixed
     */
    public function deleteItems(array $keys);

    /**
     * Sets a cache item to be persisted later
     *
     * @param ItemInterface $item
     * @return static
     */
    public function saveDeferred(ItemInterface $item);

    /**
     * Saves any deferred cache items.
     *
     * @return mixed
     */
    public function commit();
}
