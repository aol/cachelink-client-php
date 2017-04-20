<?php

// Because code coverage is messy
ini_set('memory_limit','1024M');

$loader = require __DIR__ . '/../vendor/autoload.php';
$loader->setPsr4("Aol\\CacheLink\\Tests\\", __DIR__);

// Setup a custom error handler to throw exceptions.
// This will allow us to catch exceptions for all PHP errors.
set_error_handler(function ($errno, $errstr, $errfile, $errline ) {
    throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
});
