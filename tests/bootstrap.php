<?php

// Because code coverage is messy
ini_set('memory_limit','1024M');

$loader = require __DIR__ . '/../vendor/autoload.php';
$loader->setPsr4("Aol\\CacheLink\\Tests\\", __DIR__);

\Aol\CacheLink\Tests\CacheLinkServer::getInstance()->start();

