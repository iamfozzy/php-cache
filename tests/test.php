<?php

class Awesome
{
    public $name = 'Stuart';
}

use Fozzy\Cache\Pool;
use Fozzy\Cache\Storage\File;

// Autoloader
spl_autoload_register(function ($class) {
    $file     = str_replace('Fozzy\\Cache', '', $class) . '.php';
    $file     = ltrim(str_replace('\\', DIRECTORY_SEPARATOR, $file), '\\');
    $filename = realpath(dirname(__FILE__) . '/../src/' . $file);
    if (file_exists($filename)) {
        include $filename;
        return true;
    }
});

$fileStorage = new File('/var/tmp');
$pool        = new Pool($fileStorage);

// Fetch item from pool
$item = $pool->getItem('test', 10);
//$item->setExpiration(new DateTime('-10 minutes'))->save();

var_dump($item->getExpiration());
var_dump(time());
var_dump($item->expired());

// Did we hit the cache this time? If not - regenerate it
if (!$item->isHit()) {

    $awesome = new Awesome();

    sleep(10);

    // Set and save
    $item->set($awesome)->save();
}

// Return the item
var_dump($item->get()); exit;
