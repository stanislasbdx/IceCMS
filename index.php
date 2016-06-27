<?php 

if (PHP_VERSION_ID < 50306) {
    die('Ice requires PHP 5.3.6 or above to run');
}

require_once(__DIR__ . '/vendor/autoload.php');

$ice = new Ice(
    __DIR__,
    'config/',
    'plugins/',
    'themes/'
);

echo $ice->run();