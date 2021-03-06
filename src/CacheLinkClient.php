<?php

namespace Aol\CacheLink;

use Aol\CacheLink\Exceptions\CacheLinkEncoderException;
use Aol\CacheLink\Exceptions\CacheLinkRuntimeException;
use Aol\CacheLink\Exceptions\CacheLinkServerException;
use Aol\CacheLink\Exceptions\CacheLinkUnserializeException;
use GuzzleHttp\Client;
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
	/** @var callable The unserialize exception handler. */
	private $unserialize_exception_handler;
	/** @var CacheLinkEncoderInterface The encoder to use (default to standard). */
	private $encoder;
	/** @var CacheLinkEncoderInterface The decoder to use (default to the encoder). */
	private $decoder;

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
	 * Get the encoder.
	 *
	 * @return CacheLinkEncoderInterface The encoder.
	 */
	public function getEncoder()
	{
		if (!$this->encoder) {
			$this->encoder = new CacheLinkEncoderStandard();
		}
		return $this->encoder;
	}

	/**
	 * Set the encoder.
	 *
	 * @param CacheLinkEncoderInterface $encoder The encoder to use.
	 */
	public function setEncoder(CacheLinkEncoderInterface $encoder)
	{
		$this->encoder = $encoder;
	}

	/**
	 * Get the decoder.
	 *
	 * @return CacheLinkEncoderInterface The decoder.
	 */
	public function getDecoder()
	{
		if (!$this->decoder) {
			return $this->getEncoder();
		}
		return $this->decoder;
	}

	/**
	 * Set the decoder.
	 *
	 * @param CacheLinkEncoderInterface $decoder The decoder to use.
	 */
	public function setDecoder(CacheLinkEncoderInterface $decoder)
	{
		$this->decoder = $decoder;
	}

	/**
	 * Set an exception handler for unserialize failures.
	 *
	 * @param callable|null $unserialize_exception_handler The exception handler for unserialize failures.
	 */
	public function setUnserializeExceptionHandler(callable $unserialize_exception_handler = null)
	{
		$this->unserialize_exception_handler = $unserialize_exception_handler;
	}

	/**
	 * Serialize the given data for storage. The data must be serialized to a UTF-8 string for transport.
	 *
	 * @param mixed $data The data to serialize.
	 *
	 * @return string The serialized, UTF-8 string.
	 */
	private function serialize($data)
	{
		$string = $this->getEncoder()->encode($data);
		if ($this->current_encoding !== 'UTF-8') {
			$string = iconv($this->current_encoding, 'UTF-8', $string);
		}
		return $string;
	}

	/**
	 * Unserialize the given string into an object. The string should be a UTF-8 string.
	 *
	 * @param string $string The serialized data string.
	 *
	 * @return mixed The unserialized data object.
	 */
	private function unserialize($string)
	{
		if ($string === null) {
			return null;
		}
		if ($this->current_encoding !== 'UTF-8') {
			$string = iconv('UTF-8', $this->current_encoding, $string);
		}
		return $this->getDecoder()->decode($string);
	}

	/**
	 * Unserialize then thaw the the given key/value pair.
	 *
	 * @param string      $key              The key being thawed.
	 * @param string|null $serialized_value The serialized value or null if none was found (miss).
	 *
	 * @return CacheLinkItem The thawed item.
	 *
	 * @throws \Exception If there was a problem unserializing or thawing the given data.
	 */
	protected function thawSerialized($key, $serialized_value)
	{
		try {
			$val = $this->unserialize($serialized_value);
			return CacheLinkItem::thaw($key, $val);
		} catch (\Exception $ex) {
			$exception = new CacheLinkUnserializeException(
				__METHOD__ . ': CacheLink could not thaw data: ' . $ex->getMessage(),
				0,
				$ex,
				$key,
				$serialized_value
			);
			$unserialize_exception_handler = $this->unserialize_exception_handler;
			if ($unserialize_exception_handler) {
				$unserialize_exception_handler($exception);
				return new CacheLinkItem($key, null, null, [], []);
			} else {
				throw $exception;
			}
		}
	}

	/**
	 * Perform a get directly from redis.
	 *
	 * @param string $key The key to get.
	 *
	 * @return CacheLinkItem The value or null if there is none.
	 */
	protected function directGet($key)
	{
		// Get the data from cache.
		$serialized_value = $this->redis_client_read->get($this->redis_prefix_data . $key);
		$item             = $this->thawSerialized($key, $serialized_value);
		return $item;
	}

	/**
	 * Perform a multi-get directly from redis.
	 *
	 * @param string[] $keys The keys to get.
	 *
	 * @return CacheLinkItem[] The array of values in the same order as the keys.
	 */
	protected function directGetMany(array $keys)
	{
		// If the read connection is using redis cluster,
		// use normal GET commands as MGET is not supported.
		if ($this->isRedisCluster($this->redis_client_read)) {
			return array_map([$this, 'directGet'], $keys);
		}

		$keys_data = [];
		foreach ($keys as $key) {
			$keys_data[] = $this->redis_prefix_data . $key;
		}
		$results = [];
		$serialized_values = $this->redis_client_read->executeCommand(
			$this->redis_client_read->createCommand('mget', $keys_data)
		);
		foreach ($serialized_values as $index => $serialized_value) {
			$item      = $this->thawSerialized($keys[$index], $serialized_value);
			$results[] = $item;
		}
		return $results;
	}

	/**
	 * Perform a get from the cachelink service.
	 *
	 * @param string $key The key to get.
	 *
	 * @return CacheLinkItem The value or null if there is none.
	 */
	protected function serviceGet($key)
	{
		$request = $this->requestGet($key);
		$raw     = $this->makeRequest($request, true);
		$item    = $this->thawSerialized($key, $raw);
		return $item;
	}

	/**
	 * Perform a multi-get from the cachelink service.
	 *
	 * @param string[] $keys The keys to get.
	 *
	 * @return CacheLinkItem[] The array of values in the same order as the keys.
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
					$item                    = $this->thawSerialized($key, $raw);
					$index                   = $index_by_key[$key];
					$result_by_index[$index] = $item;
				}
			}
		}
		$result = [];
		foreach ($result_by_index as $index => $item) {
			if ($item === null) {
				$result[] = new CacheLinkItem($keys[$index], null, null, [], []);
			} else {
				$result[] = $item;
			}
		}
		return $result;
	}

	/**
	 * Perform a set directly to redis.
	 *
	 * @param string $key      The key to set.
	 * @param mixed  $value    The value to set.
	 * @param int    $millis   How long to keep the value in cache.
	 * @param array  $metadata The metadata for the set.
	 *
	 * @return mixed The result information of the set.
	 */
	protected function directSet($key, $value, $millis, $metadata = [])
	{
		$item             = new CacheLinkItem($key, $value, $millis, [], $metadata);
		$frozen           = $item->freeze();
		$serialized_value = $this->serialize($frozen);
		$key_data         = $this->redis_prefix_data . $key;
		$key_in           = $this->redis_prefix_in   . $key;
		$client           = $this->redis_client_write;

		$commands = [
			$client->createCommand('set', [$key_data, $serialized_value, 'px', $millis]),
			$client->createCommand('del', [$key_in]),
		];

		// If the write connection is using redis cluster,
		// do not use pipelining, as the keys may be on two different nodes.
		if ($this->isRedisCluster($client)) {
			$responses = [];
			foreach ($commands as $command) {
				$responses[] = $client->executeCommand($command);
			}
		} else {
			$pipeline = $client->pipeline();
			foreach ($commands as $command) {
				$pipeline->executeCommand($command);
			}
			$responses = $pipeline->execute();
		}

		$success = ($responses[0] instanceof \Predis\Response\Status && $responses[0]->getPayload() === 'OK');
		return [
			'cacheSet'        => $success ? 'OK' : false,
			'clearAssocIn'    => 0,
			'success'         => $success,
			'broadcastResult' => null,
			'directSet'       => true,
		];
	}

	/**
	 * Perform a set using the cachelink service.
	 *
	 * @param string $key          The key to set.
	 * @param mixed  $value        The value to set.
	 * @param int    $millis       How long to cache the value.
	 * @param array  $associations The associations for the set.
	 * @param array  $metadata     The metadata for the set.
	 * @param bool   $broadcast    Whether to broadcast the set to all data centers.
	 * @param bool   $wait         Whether to wait for the result from the service.
	 *
	 * @return mixed The result information of the set.
	 *
	 * @throws CacheLinkServerException
	 */
	protected function serviceSet($key, $value, $millis, $associations, $metadata, $broadcast, $wait)
	{
		$item             = new CacheLinkItem($key, $value, $millis, $associations, $metadata);
		$frozen           = $item->freeze();
		$serialized_value = $this->serialize($frozen);
		$request          = $this->requestSet($key, $serialized_value, $millis, $associations, $broadcast);

		return $this->makeRequest($request, $wait);
	}

	/**
	 * @inheritdoc
	 */
	public function getSimple($key, array $options = [])
	{
		return $this->get($key, $options)->getValue();
	}

	/**
	 * @inheritdoc
	 */
	public function getManySimple(array $keys, array $options = [])
	{
		$items = $this->getMany($keys, $options);
		$values = [];
		foreach ($items as $item) {
			$values[] = $item->getValue();
		}
		return $values;
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
	public function set($key, $value, $millis, array $associations = [], array $options = [], array $metadata = [])
	{
		$from_service = isset($options['from_service']) && $options['from_service'] === true;
		$broadcast    = isset($options['broadcast']) && $options['broadcast'] === true;
		$wait         = isset($options['wait']) && $options['wait'] === true;
		if ($this->redis_client_write && !$from_service && empty($associations) && !$broadcast) {
			return $this->directSet($key, $value, $millis, $metadata);
		} else {
			return $this->serviceSet($key, $value, $millis, $associations, $metadata, $broadcast, $wait);
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

	private function isRedisCluster(\Predis\Client $client)
	{
		$connection = $client->getConnection();
		return $connection instanceof \Predis\Connection\Aggregate\RedisCluster;
	}
}