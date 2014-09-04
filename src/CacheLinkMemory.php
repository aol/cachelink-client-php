<?php

namespace Aol\CacheLink;

class CacheLinkMemory implements CacheLinkInterface
{
	/** @var array Cached data. */
	protected $memory = [];

	/**
	 * @inheritdoc
	 */
	public function get($key, array $options = [])
	{
		if (isset($this->memory[$key])) {
			$val = $this->memory[$key];
			$now = time();
			if ($now <= $val['timeout']) {
				return $val['data'];
			} else {
				unset($this->memory[$key]);
			}
		}
		return null;
	}

	/**
	 * @inheritdoc
	 */
	public function getMany(array $keys, array $options = [])
	{
		$results = [];
		foreach ($keys as $key) {
			$results[] = $this->get($key);
		}
		return $results;
	}

	/**
	 * @inheritdoc
	 */
	public function set($key, $value, $millis, array $associations = [], array $options = [])
	{
		$this->memory[$key] = [
			'timeout' => time() + (int)($millis / 1000),
			'data'    => $value
		];
		return ['background' => true];
	}

	/**
	 * @inheritdoc
	 */
	public function clear(array $keys, $levels = self::CLEAR_LEVELS_ALL, array $options = [])
	{
		foreach ($keys as $key) {
			unset($this->memory[$key]);
		}
		return ['background' => true];
	}

	/**
	 * @inheritdoc
	 */
	public function clearLater(array $keys, array $options = [])
	{
		return ['background' => true];
	}

	/**
	 * @inheritdoc
	 */
	public function triggerClearNow(array $options = [])
	{
		return ['background' => true];
	}
}