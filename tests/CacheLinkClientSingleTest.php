<?php

namespace Aol\CacheLink\Tests;

use Predis\Client;

class CacheLinkClientSingleTest extends CacheLinkClientTest
{
    protected function getPort()
    {
        return 61111;
    }

    protected function createRedisClient()
    {
        return new \Predis\Client;
    }

    protected function flushRedis(Client $redis_client)
    {
        return $redis_client->flushdb();
    }

    protected function getAllRedisData(Client $redis_client)
    {
        $keys = $redis_client->keys('*');
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $redis_client->get($key);
        }
        return $result;
    }
}
