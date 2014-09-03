# cachelink PHP client

A PHP client for AOL [cachelink](https://github.com/aol/cachelink-service).

[![Build Status](https://travis-ci.org/aol/cachelink-client-php.svg?branch=master)](https://travis-ci.org/aol/cachelink-client-php)
[![Coverage Status](https://coveralls.io/repos/aol/cachelink-client-php/badge.png?branch=master)](https://coveralls.io/r/aol/cachelink-client-php?branch=master)
[![Latest Stable Version](https://poser.pugx.org/aol/cachelink-client-php/v/stable.png)](https://packagist.org/packages/aol/cachelink-client-php)
[![Latest Unstable Version](https://poser.pugx.org/aol/cachelink-client-php/v/unstable.png)](https://packagist.org/packages/aol/cachelink-client-php)
[![License](https://poser.pugx.org/aol/cachelink-client-php/license.png)](https://packagist.org/packages/aol/cachelink-client-php)

## Install

Add to `composer.json`:

```json
{
    "require": {
        "aol/cachelink-client-php": "~1.0"
    }
}
```

## Usage

```php
<?php

use Aol\CacheLink\CacheLinkClient;

// The base URL for where the cache service is hosted.
$cache_service_base_url = 'http://localhost:8899';

// The timeout in seconds for talking to the service (this is optional).
$timeout = 3;

// Create the client.
$cache = new CacheLinkClient($cache_service_base_url, $timeout);

// Add a Predis client for direct redis gets.
$cache->setupDirectRedis(new \Predis\Client(...));

// Set a value.
$cache->set('foo', 'bar', 3000);

// Get a value - outputs "bar".
echo $cache->get('foo');

// Clear "foo".
$cache->clear(['foo']);
```

## License

Copyright (c) 2013 AOL, Inc.

All rights reserved.

https://github.com/aol/cachelink-client-php/blob/master/LICENSE
