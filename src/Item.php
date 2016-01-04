<?php

namespace Fozzy\Cache;

use DateInterval;
use DateTime;
use Fozzy\Cache\Exception\InvalidArgumentException;
use Fozzy\Cache\Exception\RuntimeException;
use Fozzy\Cache\Storage\StorageInterface;
use Fozzy\Cache\Storage\Support;

/**
 * Class Item
 */
class Item implements ItemInterface
{
    /**
     * @var StorageInterface
     */
    protected $storage;

    /**
     * @var mixed
     */
    protected $data;

    /**
     * @var null|DateTime       If null then will be calculated using the TTL, otherwise, this expiration will be used.
     */
    protected $expiration = null;

    /**
     * @var bool
     */
    protected $exists;

    /**
     * Default Options
     *
     * @see Option
     * @var array
     */
    protected $options = [
        Option::TTL                => 7200,
        Option::WIGGLE             => 300,
        Option::LOCK_RETRIES       => 20,
        Option::LOCK_SLEEP_TIMEOUT => 5000,
        Option::LOCK_TIMEOUT       => 60000
    ];

    /**
     * @param string           $key
     * @param StorageInterface $storage StorageInterface
     * @param int|null         $ttl     TTL. Can also be contained within $options.
     * @param array            $options Options to override defaults.
     * @param null             $data
     */
    public function __construct($key, StorageInterface $storage = null, $ttl = null, $options = [], $data = null)
    {
        $this->key     = $key;
        $this->storage = $storage;
        $this->options = array_merge($this->options, $options, [Option::TTL => $ttl]);
        $this->getExpiration();
    }

    /**
     * Returns the time in unix time when this expires.
     *
     * @return DateTime
     */
    public function getExpiration()
    {
        if (null === $this->expiration) {
            $this->expiration = new DateTime();
            $this->expiration->setTimestamp(
                time() + $this->options[Option::TTL]
            );
        }

        return $this->expiration;
    }

    /**
     * Sets the time at which this should expire.
     *
     * Internally - this takes the expiration in unix time, subtracts the current time and sets
     * the TTL of this item to the difference.
     *
     * @param DateTime $time
     * @return $this
     */
    public function setExpiration(DateTime $time)
    {
        $this->expiration           = $time;
        $this->options[Option::TTL] = $this->expiration - time();

        return $this;
    }

    /**
     * Return the cache data
     *
     * @return mixed
     */
    public function get()
    {
        return $this->data;
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @param string $key
     * @return mixed
     * @throws InvalidArgumentException
     */
    public function getOption($key)
    {
        if (!array_key_exists($key, $this->options)) {
            throw new InvalidArgumentException(sprintf(
                'Option %s is not a valid option', $key
            ));
        }

        return $this->options[$key];
    }

    /**
     * @param string $key
     * @param mixed  $value
     * @return $this
     * @throws InvalidArgumentException
     */
    public function setOption($key, $value)
    {
        if (!array_key_exists($key, $this->options)) {
            throw new InvalidArgumentException(sprintf(
                'Option %s is not a valid option', $key
            ));
        }

        $this->options[$key] = $value;

        return $this;
    }

    /**
     * @param DateTime $time
     * @return Item|static
     */
    public function expiresAt(DateTime $time)
    {
        return $this->setExpiration($time);
    }

    /**
     * When this item is unserializeed, it will keep its options and any meta data (expiration)
     * attached to it, however, you will need to re-attach Storage.
     *
     * @return array
     */
    public function __sleep()
    {
        return ['options', 'data', 'expiration'];
    }

    /**
     * This checks if an item will hit the cache or not.
     *
     * This will (for one process) lock the cache file for regenerating.
     * This will cause any other process to serve the cache while it is expired if another
     * process is already regenerating the cache.
     * If another process is regenerating but a cache does not exist or has reached its 'lifetime' + 'wiggle'
     * then any further processes will wait during isHit for the cache to be written.
     *
     * @throws RuntimeException
     * @return bool
     */
    public function isHit()
    {
        if ($this->exists()) {

            // Not expired - serve cached
            if (!$this->expired()) {
                return true;
            }

            // However - if we can't lock - use the expired
            if (!$this->lock()) {
                return true;
            }
        } else {
            if (!$this->lock()) {

                // Sleep until the cache exists
                $lockCount = 0;
                while ($lockCount++ < $this->options[Option::LOCK_RETRIES]) {

                    // Retry until readable or until we reach max failed attempts
                    if ($this->exists(true)) {
                        return true;
                    }

                    usleep($this->options[Option::LOCK_SLEEP_TIMEOUT]);
                }

                // No
                throw new RuntimeException('Cache item was locked and maximum wait time exceeded.');
            }
        }

        return false;
    }

    /**
     * Checks if the cache item exists.
     *
     * You may override the use of an internal cache. This is cached
     * simply to prevent multiple consecutive hits on has() unless
     * absolutely required.
     *
     * @param bool $reset
     * @return mixed
     */
    public function exists($reset = false)
    {
        if (null === $this->exists || $reset) {
            $this->exists = $this->getStorage()->has($this->key);
        }

        return $this->exists;
    }

    /**
     * @return StorageInterface
     * @throws NoAttachedStorageException
     */
    public function getStorage()
    {
        if (null === $this->storage) {
            throw new NoAttachedStorageException();
        }
        return $this->storage;
    }

    /**
     * @param StorageInterface $storage
     */
    public function setStorage(StorageInterface $storage)
    {
        $this->storage = $storage;
    }

    /**
     * Has this item expired?
     *
     * @return bool
     */
    public function expired()
    {
        return !$this->exists() || ($this->getExpiration()->getTimestamp() < time());
    }

    /**
     * Lock the storage.
     *
     * @return bool
     * @throws NoAttachedStorageException
     */
    protected function lock()
    {
        if (null === $this->storage) {
            throw new NoAttachedStorageException();
        }

        if ($this->storage instanceof Support\LockableInterface) {
            return $this->storage->lock($this->key);
        }

        // Storage doesn't support locking - pretend we're locked so we can continue.
        return true;
    }

    /**
     * Set the cache data
     *
     * @param mixed $data
     * @return self
     */
    public function set($data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Persist data to store.
     *
     * @param null $ttl TTL for item
     * @return bool             Succesful save or not?
     */
    public function save($ttl = null)
    {
        if (null !== $ttl) {
            $this->options[Option::TTL] = $ttl;
        }

        // Ensure we've calculated an expiration
        $this->getExpiration();

        return $this->getStorage()->save(
            $this->key,
            $this,
            $ttl + $this->options[Option::WIGGLE]
        );
    }

    /**
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * @param int $ttl
     * @return $this
     * @throws InvalidArgumentException
     */
    public function setTtl($ttl)
    {
        if (!is_numeric($ttl)) {
            throw new InvalidArgumentException(sprintf(
                'TTL must be integer but an %s wqas passed.',
                gettype($ttl)
            ));
        }

        $this->options[Option::TTL] = $ttl;

        return $this;
    }

    /**
     * Sets the expiration time for this cache item.
     *
     * @param int|DateInterval $time
     *   The period of time from the present after which the item MUST be considered
     *   expired. An integer parameter is understood to be the time in seconds until
     *   expiration.
     *
     * @throws InvalidArgumentException
     * @return static
     *   The called object.
     */
    public function expiresAfter($time)
    {
        if (is_int($time)) {
            $time = new DateInterval('PT' . $time . 'S');
        }

        if (!$time instanceof DateInterval) {
            throw new InvalidArgumentException('expiresAfter() accepts a DateInterval or time in seconds.');
        }

        return $this->setExpiration(
            (new DateTime())->add($time)
        );
    }

    /**
     * Unlock the storage.
     *
     * @return $this
     * @throws NoAttachedStorageException
     */
    protected function unlock()
    {
        if (null === $this->storage) {
            throw new NoAttachedStorageException();
        }

        if ($this->storage instanceof Support\LockableInterface) {
            return $this->storage->unlock($this->key);
        }

        // Storage doesn't support locking - pretend we're unlocked so we can continue.
        return true;
    }
}
