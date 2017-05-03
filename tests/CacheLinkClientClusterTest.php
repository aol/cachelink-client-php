<?php

namespace Aol\CacheLink\Tests;

use Predis\Client;
use Predis\Connection\Aggregate\RedisCluster;

class CacheLinkClientClusterTest extends CacheLinkClientTest
{
    protected function getPort()
    {
        return 61112;
    }

    protected function createRedisClient()
	{
		return new \Predis\Client(
			['tcp://127.0.0.1:7001', 'tcp://127.0.0.1:7002', 'tcp://127.0.0.1:7003'],
			['cluster' => 'redis']
		);
	}

	protected function flushRedis(Client $redis_client)
	{
		$this->executeCommandOnAllClusterNodes($redis_client, function () use ($redis_client) {
			return $redis_client->createCommand('flushdb');
		});
	}

    protected function getAllRedisData(Client $redis_client)
    {
        $keys_responses = $this->executeCommandOnAllClusterNodes($redis_client, function () use ($redis_client) {
           return $redis_client->createCommand('keys', ['*']);
        });
        $result = [];
        foreach ($keys_responses as $response) {
            foreach ($response as $key) {
                $result[$key] = $redis_client->get($key);
            }
        }
        return $result;
    }

	private function executeCommandOnAllClusterNodes(Client $client, callable $command_creator)
	{
		$connection = $client->getConnection();
		if (!$connection instanceof RedisCluster) {
			throw new \Exception('Expected ' . RedisCluster::class . ' connection');
		}
		$slots = $connection->askSlotsMap();
		$hosts = [];
		foreach ($slots as $slot => $host) {
			$hosts[$host] = $slot;
		}
		$responses = [];
		foreach ($hosts as $host => $slot) {
			$command = $command_creator();
			$con = $connection->getConnectionBySlot($slot);
			$response = $con->executeCommand($command);
			$responses[$host] = $response;
		}
		return $responses;
	}
}
