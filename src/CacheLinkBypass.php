<?php

namespace Aol\CacheLink;

class CacheLinkBypass implements CacheLinkInterface
{
	/**
	 * @inheritdoc
	 */
	public function getSimple($key, array $options = [])
	{
		return null;
	}

	/**
	 * @inheritdoc
	 */
	public function getManySimple(array $keys, array $options = [])
	{
		return array_fill(0, count($keys), null);
	}

	/**
	 * @inheritdoc
	 */
	public function get($key, array $options = [])
	{
		return new CacheLinkItem($key, null, null, [], []);
	}

	/**
	 * @inheritdoc
	 */
	public function getMany(array $keys, array $options = [])
	{
		$result = [];
		foreach ($keys as $key) {
			$result[] = new CacheLinkItem($key, null, null, [], []);
		}
		return $result;
	}

	/**
	 * @inheritdoc
	 */
	public function set($key, $value, $millis, array $associations = [], array $options = [], array $metadata = [])
	{
		return ['background' => true];
	}

	/**
	 * @inheritdoc
	 */
	public function clear(array $keys, $levels = self::CLEAR_LEVELS_ALL, array $options = [])
	{
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