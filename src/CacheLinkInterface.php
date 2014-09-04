<?php

namespace Aol\CacheLink;

interface CacheLinkInterface
{
	/** Clears all association levels. */
	const CLEAR_LEVELS_ALL  = 'all';
	/** Clears no associations. */
	const CLEAR_LEVELS_NONE = 'none';
	/** The default timeout (in seconds) when talking to the cachelink service. */
	const DEFAULT_TIMEOUT   = 5;

	/**
	 * Get the value for the given key. This will attempt to use redis directly if
	 * `setupDirectRedis` was previously called.
	 *
	 * @param string $key     The key to get.
	 * @param array  $options A set of options for the get.
	 * <code>
	 * [
	 *   'from_service' => true|false - whether to force the use of the service to perform the get
	 * ]
	 * </code>
	 *
	 * @return mixed|null The value or null if there is none.
	 */
	function get($key, array $options = []);

	/**
	 * Get the values for the given keys. This will attempt to use redis directly if
	 * `setupDirectRedis` was previously called.
	 *
	 * @param string[] $keys The keys to get.
	 * @param array  $options A set of options for the get.
	 * <code>
	 * [
	 *    'from_service' => true|false (default false) -
	 *                      whether to force the use of the service to perform the multi-get
	 * ]
	 * </code>
	 *
	 * @return array The array of values in the same order as the keys.
	 */
	function getMany(array $keys, array $options = []);

	/**
	 * Set the given key to the given value by contacting the cachelink service.
	 *
	 * @param string $key          The key for the set.
	 * @param string $value        The value for the set.
	 * @param int    $millis       TTL in millis.
	 * @param array  $associations The keys to associate (optional).
	 * @param array  $options      Options for the set.
	 * <code>
	 * [
	 *    'broadcast' => true|false (default false) - whether to broadcast the set to all data centers.
	 *  , 'wait'      => true|false (default false) - whether to wait for the set to complete.
	 * ]
	 * </code>
	 *
	 * @return mixed The result information of the set.
	 */
	function set($key, $value, $millis, array $associations = [], array $options = []);

	/**
	 * Immediately clear the given keys and optionally their associations.
	 *
	 * @param string[] $keys    The keys to clear.
	 * @param string   $levels  The number of association levels to clear (defaults to "all").
	 * @param array    $options Options for the clear.
	 * <code>
	 * [
	 *    'broadcast' => true|false (default true)  - whether to broadcast the clear to all data centers.
	 *  , 'wait'      => true|false (default false) - whether to wait for the clear to complete.
	 * ]
	 * </code>
	 *
	 * @return mixed The result information of the clear.
	 */
	function clear(array $keys, $levels = self::CLEAR_LEVELS_ALL, array $options = []);

	/**
	 * Clear the given keys at a later time.
	 *
	 * @param string[] $keys    The keys to clear later.
	 * @param array    $options Options for the clear.
	 * <code>
	 * [
	 *    'wait' => true|false (default false) - whether to wait for the clear later to be in place.
	 * ]
	 * </code>
	 *
	 * @return mixed The result information of the clear later.
	 */
	function clearLater(array $keys, array $options = []);

	/**
	 * Clear all keys previously added via `clearLater` now.
	 *
	 * @param array $options Options for the clear.
	 * <code>
	 * [
	 *    'wait' => true|false (default false) - whether to wait for all keys to be cleared.
	 * ]
	 * </code>
	 *
	 * @return mixed The result information of the clear now trigger.
	 */
	function triggerClearNow(array $options = []);
}