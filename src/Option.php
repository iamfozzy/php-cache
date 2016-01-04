<?php

namespace Fozzy\Cache;

/**
 * Class Option
 *
 * Class contains options available to the Manager and Items.
 *
 * @package Fozzy\Cache
 */
class Option
{
    /**
     * Time to live - time in seconds until the cache item becomes invalid
     */
    const TTL = 'ttl';

    /**
     * This option adds seconds to the total TTL just before it's submitted to
     * the storage adapter. This is so that one process may regenerate cache
     * while other processes serve the expired content. This setting determins
     * if the amount of time this expired content stays valid.
     *
     * This is in SECONDS
     */
    const WIGGLE = 'wiggle';

    /**
     * How many times once a cache item is locked should it retry to fetch
     * before failing
     */
    const LOCK_RETRIES = 'lock_retries';

    /**
     * How long to sleep between lock retries
     *
     * This is in MILLISECONDS
     */
    const LOCK_SLEEP_TIMEOUT = 'lock_sleep_timeout';

    /**
     * How long should a lock last before timing out regardless
     *
     * This is in MILLISECONDS
     */
    const LOCK_TIMEOUT = 'lock_timeout';
}
