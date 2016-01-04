<?php

namespace Fozzy\Cache;

use Fozzy\Cache\Exception\RuntimeException;
use DateTime;
use Fozzy\Cache\Storage\StorageInterface;

/**
 * Class Item
 */
interface ItemInterface
{
    /**
     * Return the cache data
     *
     * @return mixed
     */
    public function get();

    /**
     * @return array
     */
    public function getOptions();

    /**
     * @param string $key
     * @return mixed
     */
    public function getOption($key);

    /**
     * @param string $key
     * @param mixed  $value
     * @return $this
     */
    public function setOption($key, $value);

    /**
     * Returns the time in unix time when this expires.
     *
     * @return int
     */
    public function getExpiration();

    /**
     * Sets the expiration time for this cache item.
     *
     * @param \DateTime $time
     *   The point in time after which the item MUST be considered expired.
     *   If null is passed explicitly, a default value MAY be used. If none is set,
     *   the value should be stored permanently or for as long as the
     *   implementation allows.
     *
     * @return static
     *   The called object.
     */
    public function expiresAt(DateTime $time);

    /**
     * Sets the expiration time for this cache item.
     *
     * @param int|\DateInterval $time
     *   The period of time from the present after which the item MUST be considered
     *   expired. An integer parameter is understood to be the time in seconds until
     *   expiration.
     *
     * @return static
     *   The called object.
     */
    public function expiresAfter($time);

    /**
     * This checks if an item should be regenerated.
     *
     * Instead of checking $item->exists() or $item->expired() to determine if you want to
     * regenerate you should use $item->regenerate() - which will then lock the item.
     *
     * This returns true when:
     *      The item doesn't exist
     *      The item has expired
     *          if storage supports locking: only if we can get a lock
     *          otherwise: always
     *
     * @throws RuntimeException
     * @return bool
     */
    public function isHit();

    /**
     * Checks if the cache item exists.
     *
     * @param bool $reset
     * @return mixed
     */
    public function exists($reset = false);

    /**
     * Has this item expired?
     *
     * @return bool
     */
    public function expired();

    /**
     * Set the cache data
     *
     * @param mixed $data
     * @return self
     */
    public function set($data);

    /**
     * Persist data to store.
     *
     * @param null  $ttl       TTL for item
     * @return bool            Succesful save or not?
     */
    public function save($ttl = null);

    /**
     * @return StorageInterface
     */
    public function getStorage();

    /**
     * @param StorageInterface $storage
     */
    public function setStorage(StorageInterface $storage);

    /**
     * @return string
     */
    public function getKey();

    /**
     * Set the TTL of this item
     *
     * @param int $ttl
     * @return self
     */
    public function setTtl($ttl);
}
