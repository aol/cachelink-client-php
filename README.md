# cachelink PHP client

A PHP client for AOL [cachelink](https://github.com/aol/cachelink-service).

[![Build Status](https://travis-ci.org/aol/cachelink-client-php.svg?branch=master)](https://travis-ci.org/aol/cachelink-client-php)
[![Coverage Status](https://coveralls.io/repos/github/aol/cachelink-client-php/badge.svg?branch=master)](https://coveralls.io/github/aol/cachelink-client-php?branch=master)
[![Latest Stable Version](https://poser.pugx.org/aol/cachelink-client-php/v/stable.png)](https://packagist.org/packages/aol/cachelink-client-php)
[![Latest Unstable Version](https://poser.pugx.org/aol/cachelink-client-php/v/unstable.png)](https://packagist.org/packages/aol/cachelink-client-php)
[![License](https://poser.pugx.org/aol/cachelink-client-php/license.png)](https://packagist.org/packages/aol/cachelink-client-php)

## Install

```
composer require aol/cachelink-client-php
```

## Usage

```php
<?php

use Aol\CacheLink\CacheLinkClient;

// The base URL for where the cache service is hosted.
$cache_service_base_url = 'http://localhost:8899';

// The timeout in seconds for talking to the service (this is optional).
$timeout = 3;

// Whether to set detailed data in cachelink for retrieval later (TTL, associations, metadata, etc.).
$set_detailed = true;

// Create the client.
$cache = new CacheLinkClient($cache_service_base_url, $timeout, $set_detailed);

// Add a Predis client for direct redis gets.
$cache->setupDirectRedis(new \Predis\Client(...));

// Set a value.
$cache->set('foo', 'bar', 3000);

// Get a value - outputs "bar".
echo $cache->get('foo')->getValue();

// Clear "foo".
$cache->clear(['foo']);
```

### Get Many

```php
$cache = new CacheLinkClient(...);

$items = $cache->getMany(['foo', 'bar', 'baz']);

foreach ($items as $item) {
	$item->getKey();          // The cache key for the item.
	$item->isHit();           // Whether the item is a cache hit.
	$item->isMiss();          // Whether the item is a cache miss.
	$item->getValue();        // The value from cache, null if there was none.
	$item->getTtlMillis();    // The item's original TTL in millis or null if none.
	$item->getMetadata();     // The item's original metadata or an empty array if none.
	$item->getAssociations(); // The item's original associations or an empty array if none.
}
```

### Simple Gets

```php
$cache = new CacheLinkClient(...);

// Get the value for the "foo" key from cache or null if a miss.
$value = $cache->getSimple('foo');

// Get the values for the "foo", "bar", and "baz" keys from cache or nulls if misses.
$values = $cache->getManySimple(['foo', 'bar', 'baz']);
```

## License

Copyright © 2013 AOL, Inc.

All rights reserved.

[MIT](LICENSE)
