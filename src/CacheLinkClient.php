<?php

namespace Aol\CacheLink;

use GuzzleHttp\Client;
use Predis\Response\Status;
use Psr\Http\Message\RequestInterface;

class CacheLinkClient implements CacheLinkInterface
{
	/** @var \GuzzleHttp\Client The Guzzle HTTP client for talking to the cachelink service. */
	private $client;
	/** @var int The timeout (in seconds) for requests to the cachelink service. */
	private $timeout;
	/** @var \Predis\Client The redis client for direct gets. */
	private $redis_client_read;
	/** @var \Predis\Client The redis client for direct sets. */
	private $redis_client_write;
	/** @var string The redis key prefix. */
	private $redis_prefix;
	/** @var string The redis data key prefix. */
	private $redis_prefix_data;
	/** @var string The redis in set key prefix. */
	private $redis_prefix_in;
	/** @var string The default charset.  */
	private $current_encoding;

	/**
	 * Create a new cachelink client.
	 *
	 * @param string $base_url The base URL for talking to the cachelink service.
	 * @param int    $timeout  The HTTP timeout in seconds for the cachelink service response (defaults to 5 seconds).
	 */
	public function __construct($base_url, $timeout = self::DEFAULT_TIMEOUT)
	{
		$this->current_encoding = mb_internal_encoding();
		$this->client           = new Client(['base_uri' => $base_url]);
		$this->timeout          = $timeout;
	}

	/**
	 * Setup a direct redis client. This will change the behavior of this client to connect
	 * to redis directly for `get` and `getMany` calls if the `redis_client_read` is provided and
	 * the `set` behavior if `redis_client_write` is provided.
	 *
	 * @param \Predis\Client $redis_client_read   The redis read client to use.
	 * @param \Predis\Client $redis_client_write  The redis write client to use.
	 * @param string         $key_prefix          The cachelink key prefix.
	 */
	public function setupDirectRedis(
		\Predis\Client $redis_client_read = null,
		\Predis\Client $redis_client_write = null,
		$key_prefix = ''
	) {
		$this->redis_client_read  = $redis_client_read;
		$this->redis_client_write = $redis_client_write;
		$this->redis_prefix       = $key_prefix;
		$this->redis_prefix_data  = $key_prefix . 'd:';
		$this->redis_prefix_in    = $key_prefix . 'i:';
	}

	/**
	 * Serialize the given data for storage. The data must be serialized to a UTF-8 string for transport.
	 *
	 * @param mixed $data The data to serialize.
	 *
	 * @return string The serialized, UTF-8 string.
	 */
	protected function serialize($data)
	{
		try {
			$string = serialize($data);
		} catch (\Exception $ex) {
			throw new \RuntimeException('CacheLink could not serialize data', 0, $ex);
		}
		if ($this->current_encoding !== 'UTF-8') {
			$string = iconv($this->current_encoding, 'UTF-8', $string);
		}
		return $string;
	}

	/**
	 * Unseralize the given string into an object. The string should be a UTF-8 string.
	 *
	 * @param string $string The serialized data string.
	 *
	 * @return mixed The unserialized data object.
	 */
	protected function unserialize($string)
	{
		if ($string === null) {
			return null;
		}
		if ($this->current_encoding !== 'UTF-8') {
			$string = iconv('UTF-8', $this->current_encoding, $string);
		}
		try {
			return unserialize($string);
		} catch (\Exception $ex) {
			throw new \RuntimeException('CacheLink could not unserialize data', 0, $ex);
		}
	}

	/**
	 * Perform a get directly from redis.
	 *
	 * @param string $key The key to get.
	 *
	 * @return mixed|null The value or null if there is none.
	 */
	protected function directGet($key)
	{
		// Get the data from cache.
		// If the result is not `null`, that means there was a hit.
		$serialized_value = $this->redis_client_read->get($this->redis_prefix_data . $key);
		$result = $this->unserialize($serialized_value);
		return $result;
	}

	/**
	 * Perform a multi-get directly from redis.
	 *
	 * @param string[] $keys The keys to get.
	 *
	 * @return array The array of values in the same order as the keys.
	 */
	protected function directGetMany(array $keys)
	{
		$keys_data = [];
		foreach ($keys as $key) {
			$keys_data[] = $this->redis_prefix_data . $key;
		}
		$results = [];
		$serialized_values = $this->redis_client_read->executeCommand(
			$this->redis_client_read->createCommand('mget', $keys_data)
		);
		foreach ($serialized_values as $serialized_value) {
			$item = $this->unserialize($serialized_value);
			$results[] = $item;
		}
		return $results;
	}

	/**
	 * Perform a set directly to redis.
	 *
	 * @param string $key    The key to set.
	 * @param mixed  $value  The value to set.
	 * @param int    $millis How long to keep the value in cache.
	 *
	 * @return mixed The result information of the set.
	 */
	protected function directSet($key, $value, $millis)
	{
		$serialized_value = $this->serialize($value);
		$key_data         = $this->redis_prefix_data . $key;
		$key_in           = $this->redis_prefix_in   . $key;
		$responses        = $this->redis_client_write->pipeline()
			->set($key_data, $serialized_value, 'px', $millis)
			->del($key_in)
			->execute();
		$success = ($responses[0] instanceof Status && $responses[0]->getPayload() === 'OK');
		return [
			'cacheSet'        => $success ? 'OK' : false,
			'clearAssocIn'    => 0,
			'success'         => $success,
			'broadcastResult' => null,
		];
	}

	/**
	 * Perform a get from the cachelink service.
	 *
	 * @param string $key The key to get.
	 *
	 * @return mixed|null The value or null if there is none.
	 */
	protected function serviceGet($key)
	{
		$request = $this->requestGet($key);
		$raw     = $this->makeRequest($request, true);
		$result  = $this->unserialize($raw);
		return $result;
	}

	/**
	 * Perform a multi-get from the cachelink service.
	 *
	 * @param string[] $keys The keys to get.
	 *
	 * @return array The array of values in the same order as the keys.
	 */
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
					$val = $this->unserialize($raw);
					$result_by_index[$index_by_key[$key]] = $val;
				}
			}
		}
		return $result_by_index;
	}

	/**
	 * Perform a set using the cachelink service.
	 *
	 * @param string $key          The key to set.
	 * @param mixed  $value        The value to set.
	 * @param int    $millis       How long to cache the value.
	 * @param array  $associations The associations for the set.
	 * @param bool   $broadcast    Whether to broadcast the set to all data centers.
	 * @param bool   $wait         Whether to wait for the result from the service.
	 *
	 * @return mixed The result information of the set.
	 *
	 * @throws CacheLinkServerException
	 */
	protected function serviceSet($key, $value, $millis, $associations, $broadcast, $wait)
	{
		$serialized_value = $this->serialize($value);
		$request = $this->requestSet($key, $serialized_value, $millis, $associations, $broadcast);
		return $this->makeRequest($request, $wait);
	}

	/**
	 * @inheritdoc
	 */
	public function get($key, array $options = [])
	{
		$from_service = isset($options['from_service']) && $options['from_service'] === true;
		if ($this->redis_client_read && !$from_service) {
			return $this->directGet($key);
		} else {
			return $this->serviceGet($key);
		}
	}

	/**
	 * @inheritdoc
	 */
	public function getMany(array $keys, array $options = [])
	{
		$from_service = isset($options['from_service']) && $options['from_service'] === true;
		if ($this->redis_client_read && !$from_service) {
			return $this->directGetMany($keys);
		} else {
			return $this->serviceGetMany($keys);
		}
	}

	/**
	 * @inheritdoc
	 */
	public function set($key, $value, $millis, array $associations = [], array $options = [])
	{
		$from_service = isset($options['from_service']) && $options['from_service'] === true;
		$broadcast    = isset($options['broadcast']) && $options['broadcast'] === true;
		$wait         = isset($options['wait']) && $options['wait'] === true;
		if ($this->redis_client_write && !$from_service && empty($associations) && !$broadcast) {
			return $this->directSet($key, $value, $millis);
		} else {
			return $this->serviceSet($key, $value, $millis, $associations, $broadcast, $wait);
		}
	}

	/**
	 * @inheritdoc
	 */
	public function clear(array $keys, $levels = self::CLEAR_LEVELS_ALL, array $options = [])
	{
		$broadcast = !isset($options['broadcast']) || $options['broadcast'] !== false;
		$wait      = isset($options['wait']) && $options['wait'] === true;
		$request   = $this->requestClear($keys, $levels, $broadcast);
		return $this->makeRequest($request, $wait);
	}

	/**
	 * @inheritdoc
	 */
	public function clearLater(array $keys, array $options = [])
	{
		$wait    = isset($options['wait']) && $options['wait'] === true;
		$request = $this->requestClearLater($keys);
		return $this->makeRequest($request, $wait);
	}

	/**
	 * @inheritdoc
	 */
	public function triggerClearNow(array $options = [])
	{
		$wait    = isset($options['wait']) && $options['wait'] === true;
		$request = $this->requestTriggerClearNow();
		return $this->makeRequest($request, $wait);
	}

	/**
	 * Make a request to the cachelink service and return the result.
	 *
	 * @param RequestInterface $request The request to make.
	 * @param bool             $wait    True to wait for the result or false to process the request in the background.
	 *
	 * @return mixed The result from the cachelink service.
	 *
	 * @throws CacheLinkServerException If the cachelink service returned an error.
	 */
	private function makeRequest(array $request, $wait)
	{
		list ($method, $uri, $options) = $request;
		if (!$wait) {
			$options['query']['background'] = true;
		}

		try {
			/** @var \Psr\Http\Message\ResponseInterface $response */
			$response = $this->client->request($method, $uri, $options);
			$contents = $response->getBody()->getContents();
			return json_decode($contents, true);
		} catch (\GuzzleHttp\Exception\ServerException $ex) {
			throw new CacheLinkServerException($ex->getMessage(), $ex->getCode(), $ex);
		}
	}

	private function requestGet($key)
	{
		return ['GET', '/' . urlencode($key), [
			'timeout' => $this->timeout
		]];
	}

	private function requestGetMany(array $keys)
	{
		return ['GET', '/', [
			'timeout' => $this->timeout,
			'query'   => ['k' => $keys]
		]];
	}

	private function requestSet($key, $value, $millis, array $associations = [], $all_data_centers = false)
	{
		return ['PUT', '/', [
			'timeout' => $this->timeout,
			'json'    => [
				'key'          => $key,
				'data'         => $value,
				'millis'       => $millis,
				'associations' => $associations,
				'broadcast'    => !!$all_data_centers
			]
		]];
	}

	private function requestClear(array $keys, $levels = self::CLEAR_LEVELS_ALL, $broadcast = true)
	{
		return ['DELETE', '/', [
			'timeout' => $this->timeout,
			'json'    => [
				'key'    => $keys,
				'levels' => $levels,
				'local'  => !$broadcast
			]
		]];
	}

	private function requestClearLater(array $keys)
	{
		return ['PUT', '/clear-later', [
			'timeout' => $this->timeout,
			'json'    => ['key' => $keys]
		]];
	}

	private function requestTriggerClearNow()
	{
		return ['GET', '/clear-now', [
			'timeout' => $this->timeout
		]];
	}
}