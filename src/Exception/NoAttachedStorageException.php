<?php

namespace Fozzy\Cache;

use Exception;
use Fozzy\Cache\Exception\CacheException;

/**
 * Class NoAttachedStorageException
 *
 * @package Fozzy\Cache
 */
class NoAttachedStorageException extends CacheException
{
    public function __construct($message = "No attached storage", $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
