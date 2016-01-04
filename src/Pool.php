<?php

namespace Fozzy\Cache;

use DateTime;
use Fozzy\Cache\Storage\Support;
use Fozzy\Cache\Storage\StorageInterface;

/**
 * Class Pool
 *
 * @package Fozzy\Cache
 */
class Pool implements PoolInterface
{
    /**
     * @var \SplObjectStorage
     */
    protected $deferred;

    /**
     * @var bool
     */
    protected $autoPersist = true;

    /**
     * @param StorageInterface $storage
     * @param bool             $autoPersist     Should the deffered cache items be auto-persisted?
     */
    public function __construct(StorageInterface $storage, $autoPersist = true)
    {
        $this->storage     = $storage;
        $this->deferred    = new \SplObjectStorage();
        $this->autoPersist = $autoPersist;
    }

    /**
     * Destructor - persist automatically if enabled.
     */
    public function __destruct()
    {
        if ($this->autoPersist) {
            $this->commit();
        }
    }

    /**
     * @param string $key
     * @param null   $ttl
     * @return ItemInterface
     */
    public function getItem($key, $ttl = null)
    {
        if ($this->storage->has($key)) {
            $data = $this->storage->get($key);

            // Stored as item
            if ($data instanceof Item) {

                // Reattach the storage
                $data->setStorage($this->storage);

                // Return - its an item already
                return $data;

            } else {

                // Not an item - create one
                $item = new Item($key, $this->storage, $ttl, [], $data);

                // Support expiration checking? if so - update the expiration
                if ($this->storage instanceof Support\GetExpirationInterface) {
                    $item->setExpiration(
                        (new DateTime)->setTimestamp($this->storage->getExpiration($key))
                    );
                }
            }
        } else {

            // No item exists - create new blank one
            return new Item($key, $this->storage, $ttl);
        }

        return $item;
    }

    /**
     * @param array $keys
     * @return ItemInterface[]
     */
    public function getItems(array $keys = array())
    {
        $items = [];
        foreach ($keys as $key) {
            $items[] = $this->getItem($key, $this->storage);
        }

        return $items;
    }

    /**
     * Deletes all items in the pool
     *
     * @return boolean
     */
    public function clear()
    {
        $this->storage->clear();
    }

    /**
     * @param ItemInterface $item
     * @return static
     */
    public function save(ItemInterface $item)
    {
        $this->storage->save(
            $item->getKey(),
            $item,
            $item->getOption(Option::TTL)
        );
    }

    /**
     * @param array $keys
     * @return mixed
     */
    public function deleteItems(array $keys)
    {
        foreach ($keys as $key) {
            $this->storage->delete($key);
        }

        return true;
    }

    /**
     * @param ItemInterface $item
     * @return $this
     */
    public function saveDeferred(ItemInterface $item)
    {
        $this->deferred->attach($item);

        return $this;
    }

    /**
     * Saves any deferred cache items.
     *
     * @return mixed
     */
    public function commit()
    {
        foreach ($this->deferred as $item) {
            $this->deferred->detach($item);
            $item->save();
        }
    }
}

