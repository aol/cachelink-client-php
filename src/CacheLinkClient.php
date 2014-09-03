<?php

namespace Aol\CacheLink;

use GuzzleHttp\Client;
use GuzzleHttp\Message\RequestInterface;

class CacheLinkClient
{
	const CLEAR_LEVELS_ALL  = 'all';
	const CLEAR_LEVELS_NONE = 'none';
	const DEFAULT_TIMEOUT   = 5;

	/** @var \GuzzleHttp\Client */
	private $client;
	private $timeout;


	private $redis_client;
	private $redis_prefix;
	private $redis_prefix_data;


	public function __construct($base_url, $timeout = self::DEFAULT_TIMEOUT)
	{
		$this->client  = new Client(['base_url' => $base_url]);
		$this->timeout = $timeout;
	}

	public function setupDirectRedis(\Predis\Client $redis_client, $key_prefix = '')
	{
		$this->redis_client      = $redis_client;
		$this->redis_prefix      = $key_prefix;
		$this->redis_prefix_data = $key_prefix . 'd:';
	}

	protected function directGet($key)
	{
		// Get the data from cache.
		// If the result is not `null`, that means there was a hit.
		$serialized_value = $this->redis_client->get($this->redis_prefix_data . $key);
		if ($serialized_value === null) {
			$result = null;
		} else {
			$result = unserialize($serialized_value);
			if ($result === false) {
				$result = null;
			}
		}
		return $result;
	}

	protected function directGetMany(array $keys)
	{
		$keys_data = [];
		foreach ($keys as $key) {
			$keys_data[] = $this->redis_prefix_data . $key;
		}
		$results = [];
		$serialized_values = $this->redis_client->executeCommand(
			$this->redis_client->createCommand('mget', $keys_data)
		);
		foreach ($serialized_values as $serialized_value) {
			if ($serialized_value === null) {
				$item = null;
			} else {
				$item = unserialize($serialized_value);
				if ($item === false) {
					$item = null;
				}
			}
			$results[] = $item;
		}
		return $results;
	}

	protected function serviceGet($key)
	{
		$request = $this->requestGet($key);
		$raw     = $this->makeRequest($request, true);
		$result  = null;
		if ($raw !== null) {
			$result = unserialize($raw);
			if ($result === false) {
				$result = null;
			}
		}
		return $result;
	}

	protected function serviceGetMany(array $keys)
	{
		$index_by_key  = [];
		foreach ($keys as $i => $key) {
			$index_by_key[$key] = $i;
		}
		$request         = $this->requestGetMany($keys);
		$raw_by_key      = $this->makeRequest($request, true);
		$result_by_index = array_fill(0, count($keys), null);
		if (!empty($raw_by_key) && is_array($raw_by_key)) {
			foreach ($raw_by_key as $key => $raw) {
				if ($raw !== null && isset($index_by_key[$key])) {
					$val = unserialize($raw);
					if ($val !== false) {
						$result_by_index[$index_by_key[$key]] = $val;
					}
				}
			}
		}
		return $result_by_index;
	}


	public function get($key, array $options = [])
	{
		$from_service = isset($options['from_service']) && $options['from_service'] === true;
		if ($this->redis_client && !$from_service) {
			return $this->directGet($key);
		} else {
			return $this->serviceGet($key);
		}
	}

	public function getMany(array $keys, array $options = [])
	{
		$from_service = isset($options['from_service']) && $options['from_service'] === true;
		if ($this->redis_client && !$from_service) {
			return $this->directGetMany($keys);
		} else {
			return $this->serviceGetMany($keys);
		}
	}

	public function set($key, $value, $millis, array $associations = [], array $options = [])
	{
		$broadcast = isset($options['broadcast']) && $options['broadcast'] === true;
		$wait      = isset($options['wait']) && $options['wait'] === true;
		$request   = $this->requestSet($key, $value, $millis, $associations, $broadcast);
		return $this->makeRequest($request, $wait);
	}

	public function clear(array $keys, $levels = self::CLEAR_LEVELS_ALL, array $options = [])
	{
		$broadcast = !isset($options['broadcast']) || $options['broadcast'] !== false;
		$wait      = isset($options['wait']) && $options['wait'] === true;
		$request   = $this->requestClear($keys, $levels, $broadcast);
		return $this->makeRequest($request, $wait);
	}

	public function clearLater(array $keys, array $options = [])
	{
		$wait    = isset($options['wait']) && $options['wait'] === true;
		$request = $this->requestClearLater($keys);
		return $this->makeRequest($request, $wait);
	}

	public function triggerClearNow(array $options = [])
	{
		$wait    = isset($options['wait']) && $options['wait'] === true;
		$request = $this->requestTriggerClearNow();
		return $this->makeRequest($request, $wait);
	}

	private function makeRequest(RequestInterface $request, $wait)
	{
		if (!$wait) {
			$query = $request->getQuery();
			$query['background'] = true;
			$request->setQuery($query);
		}

		$response = $this->client->send($request);
		$status   = (int)$response->getStatusCode();
		if (200 !== $status) {
			throw new CacheLinkServerException($response->getBody(), $status);
		}
		return $response->json();
	}

	private function requestGet($key)
	{
		return $this->client->createRequest('GET', '/' . urlencode($key), [
			'timeout' => $this->timeout
		]);
	}

	private function requestGetMany(array $keys)
	{
		return $this->client->createRequest('GET', '/', [
			'timeout' => $this->timeout,
			'query'   => ['k' => $keys]
		]);
	}

	private function requestSet($key, $value, $millis, array $associations = [], $all_data_centers = false)
	{
		$serialized_value = serialize($value);
		return $this->client->createRequest('PUT', '/', [
			'timeout' => $this->timeout,
			'json'    => [
				'key'          => $key,
				'data'         => $serialized_value,
				'millis'       => $millis,
				'associations' => $associations,
				'broadcast'    => !!$all_data_centers
			]
		]);
	}

	private function requestClear(array $keys, $levels = self::CLEAR_LEVELS_ALL, $broadcast = true)
	{
		return $this->client->createRequest('DELETE', '/', [
			'timeout' => $this->timeout,
			'json'    => [
				'key'    => $keys,
				'levels' => $levels,
				'local'  => !$broadcast
			]
		]);
	}

	private function requestClearLater(array $keys)
	{
		return $this->client->createRequest('PUT', '/clear-later', [
			'timeout' => $this->timeout,
			'json'    => ['key' => $keys]
		]);
	}

	private function requestTriggerClearNow()
	{
		return $this->client->createRequest('GET', '/clear-now', [
			'timeout' => $this->timeout
		]);
	}
}