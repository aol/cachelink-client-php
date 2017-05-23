<?php

namespace Aol\CacheLink\Exceptions;

class CacheLinkUnserializeException extends CacheLinkRuntimeException
{
	/** @var string The cache key that failed. */
	private $cache_key;
	/** @var mixed The raw cache value that could not be unserialized. */
	private $cache_raw_value;

	/**
	 * @inheritdoc
	 *
	 * @param string $cache_key       The cache key that failed.
	 * @param mixed  $cache_raw_value The raw cache value that could not be unserialized.
	 */
	public function __construct(
		$message,
		$code,
		\Exception $previous,
		$cache_key,
		$cache_raw_value
	) {
		parent::__construct($message, $code, $previous);
		$this->cache_key = $cache_key;
		$this->cache_raw_value = $cache_raw_value;
	}

	/**
	 * @return string The cache key that failed.
	 */
	public function getCacheKey()
	{
		return $this->cache_key;
	}

	/**
	 * @return mixed The raw cache value that could not be unserialized.
	 */
	public function getCacheRawValue()
	{
		return $this->cache_raw_value;
	}
}
