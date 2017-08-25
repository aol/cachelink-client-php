<?php

namespace Aol\CacheLink;

use Aol\CacheLink\Exceptions\CacheLinkRuntimeException;
use Aol\CacheLink\Exceptions\CacheLinkThawException;

class CacheLinkItem
{
	const ITEM_IDENTIFIER = '[cachelink_php]';

	/** @var string The cache key. */
	protected $key;
	/** @var mixed The cached value. */
	protected $value;
	/** @var int The TTL in millis. */
	protected $millis;
	/** @var array Any associations. */
	protected $associations;
	/** @var array Metadata for the cached items. */
	protected $metadata;

	public function __construct($key, $value, $millis, array $associations = [], array $metadata = [])
	{
		$this->key          = $key;
		$this->value        = $value;
		$this->millis       = $millis;
		$this->associations = $associations;
		$this->metadata     = $metadata;
	}

	/**
	 * @return string The cache key.
	 */
	public function getKey()
	{
		return $this->key;
	}

	/**
	 * @return bool Whether this cache item is a hit.
	 */
	public function isHit()
	{
		return $this->value !== null;
	}

	/**
	 * @return bool Whether this cache item is a miss.
	 */
	public function isMiss()
	{
		return $this->value === null;
	}

	/**
	 * @return mixed|null The cached value.
	 */
	public function getValue()
	{
		return $this->value;
	}

	/**
	 * @return int The TTL in millis.
	 */
	public function getTtlMillis()
	{
		return $this->millis;
	}

	/**
	 * @return array The associations for this cached item.
	 */
	public function getAssociations()
	{
		return $this->associations;
	}

	/**
	 * @return array The metadata for this cached item.
	 */
	public function getMetadata()
	{
		return $this->metadata;
	}

	/**
	 * @return mixed The frozen representation of this item for cachelink.
	 */
	public function freeze()
	{
		return [self::ITEM_IDENTIFIER, $this->value, $this->millis, $this->associations, $this->metadata];
	}

	/**
	 * Thaw an item from cachelink.
	 *
	 * @param string     $key  The cached key.
	 * @param array|null $item The cached item or null.
	 *
	 * @return CacheLinkItem The item.
	 *
	 * @throws CacheLinkRuntimeException If the item could not be thawed.
	 */
	public static function thaw($key, $item)
	{
		if ($item === null) {
			return new self($key, null, null, [], []);
		}
		if (is_array($item) && count($item) === 5 && $item[0] === self::ITEM_IDENTIFIER) {
			return new self($key, $item[1], $item[2], $item[3], $item[4]);
		}
		throw new CacheLinkThawException(
			"CacheLink could not thaw key:($key) " . self::stringifyValueForErrorMessage($item)
		);
	}

	/**
	 * Return the string representation of a value in cache for debugging.
	 * The string representation is used in an exception message.
	 *
	 * @param mixed $value The value to stringify for use in an exception.
	 *
	 * @return string The stringified value.
	 */
	private static function stringifyValueForErrorMessage($value)
	{
		$val = json_encode($value);
		$max_val_length = 200;
		if (strlen($value) > $max_val_length) {
			$val = substr($value, 0, $max_val_length - 3) . '...';
		}
		$type = gettype($value);
		return "type:($type) value:($val)";
	}
}
