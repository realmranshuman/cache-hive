<?php
/**
 * Interface for Cache Hive object cache backends.
 *
 * @package Cache_Hive
 */

namespace Cache_Hive\Includes\Object_Cache;

/**
 * Defines the contract for Cache Hive object cache backend implementations.
 */
interface Cache_Hive_Backend_Interface {
	/**
	 * Constructor for the cache backend.
	 *
	 * @param array $config Configuration array for the backend.
	 */
	public function __construct( $config );

	/**
	 * Retrieves a value from the cache.
	 *
	 * @param string $key The key to retrieve.
	 * @param bool   $found Whether the key was found in the cache.
	 * @return mixed The cached value, or false if not found.
	 */
	public function get( $key, &$found );

	/**
	 * Retrieves multiple values from the cache.
	 *
	 * @param array $keys An array of keys to retrieve.
	 * @return array An associative array of cached values, keyed by the original keys.
	 */
	public function get_multiple( $keys );

	/**
	 * Stores a value in the cache.
	 *
	 * @param string $key The key to store the value under.
	 * @param mixed  $value The value to store.
	 * @param int    $ttl The time-to-live for the cache item in seconds.
	 * @return bool True on success, false on failure.
	 */
	public function set( $key, $value, $ttl );

	/**
	 * Adds a value to the cache only if the key does not already exist.
	 *
	 * @param string $key The key to store the value under.
	 * @param mixed  $value The value to store.
	 * @param int    $ttl The time-to-live for the cache item in seconds.
	 * @return bool True on success, false on failure.
	 */
	public function add( $key, $value, $ttl );

	/**
	 * Replaces a value in the cache only if the key already exists.
	 *
	 * @param string $key The key to store the value under.
	 * @param mixed  $value The value to store.
	 * @param int    $ttl The time-to-live for the cache item in seconds.
	 * @return bool True on success, false on failure.
	 */
	public function replace( $key, $value, $ttl );

	/**
	 * Deletes a value from the cache.
	 *
	 * @param string $key The key to delete.
	 * @return bool True on success, false on failure.
	 */
	public function delete( $key );

	/**
	 * Flushes the entire cache.
	 *
	 * @param bool $async Whether to flush asynchronously (if supported).
	 * @return bool True on success, false on failure.
	 */
	public function flush( $async );

	/**
	 * Increments a numeric item's value.
	 *
	 * @param string $key The key of the item to increment.
	 * @param int    $offset The amount by which to increment the item's value.
	 * @return int|false The new value on success, false on failure.
	 */
	public function increment( $key, $offset );

	/**
	 * Decrements a numeric item's value.
	 *
	 * @param string $key The key of the item to decrement.
	 * @param int    $offset The amount by which to decrement the item's value.
	 * @return int|false The new value on success, false on failure.
	 */
	public function decrement( $key, $offset );

	/**
	 * Closes the connection to the cache backend.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function close();

	/**
	 * Retrieves information about the cache backend.
	 *
	 * @return array An associative array containing information about the backend.
	 */
	public function get_info();

	/**
	 * Checks if the cache backend is connected.
	 *
	 * @return bool True if connected, false otherwise.
	 */
	public function is_connected();
}
