<?php
/**
 * Interface for Cache Hive object cache backends.
 *
 * This interface defines the required methods for any Cache Hive object cache backend implementation.
 *
 * @package Cache
 */

/**
 * Interface Cache_Hive_Backend_Interface
 *
 * Defines the contract for Cache Hive object cache backend implementations.
 */

interface Cache_Hive_Backend_Interface {
	/**
	 * Constructor for the backend interface.
	 *
	 * @param array $config Configuration array for the backend.
	 */
	public function __construct( $config );

	/**
	 * Retrieves an item from the cache.
	 *
	 * @param string $key   The key for the item.
	 * @param bool   &$found Pass-by-reference. Is set to true if the key was found in the cache, false otherwise.
	 * @return mixed The cached data, or false if not found or on error.
	 */
	public function get( $key, &$found );

	/**
	 * Retrieves multiple items from the cache.
	 *
	 * @param array $keys Array of keys to retrieve.
	 * @return array An associative array of found items. Returns an empty array on error.
	 */
	public function get_multiple( $keys );

	/**
	 * Stores an item in the cache.
	 *
	 * @param string $key   The key for the item.
	 * @param mixed  $value The data to store.
	 * @param int    $ttl   Time to live in seconds. 0 for no expiration.
	 * @return bool True on success, false on failure.
	 */
	public function set( $key, $value, $ttl );

	/**
	 * Adds an item to the cache, but only if the key does not already exist.
	 *
	 * @param string $key   The key for the item.
	 * @param mixed  $value The data to store.
	 * @param int    $ttl   Time to live in seconds. 0 for no expiration.
	 * @return bool True on success, false on failure.
	 */
	public function add( $key, $value, $ttl );

	/**
	 * Replaces an item in the cache, but only if the key already exists.
	 *
	 * @param string $key   The key for the item.
	 * @param mixed  $value The data to store.
	 * @param int    $ttl   Time to live in seconds. 0 for no expiration.
	 * @return bool True on success, false on failure.
	 */
	public function replace( $key, $value, $ttl );

	/**
	 * Deletes an item from the cache.
	 *
	 * @param string $key The key for the item.
	 * @return bool True on success, false on failure.
	 */
	public function delete( $key );

	/**
	 * Flushes the entire cache.
	 *
	 * @param bool $async Whether to perform the flush asynchronously.
	 * @return bool True on success, false on failure.
	 */
	public function flush( $async );

	/**
	 * Increments a numeric item's value.
	 *
	 * @param string $key    The key for the item.
	 * @param int    $offset The amount by which to increment.
	 * @return int|false The new value on success, false on failure.
	 */
	public function increment( $key, $offset );

	/**
	 * Decrements a numeric item's value.
	 *
	 * @param string $key    The key for the item.
	 * @param int    $offset The amount by which to decrement.
	 * @return int|false The new value on success, false on failure.
	 */
	public function decrement( $key, $offset );

	/**
	 * Closes the cache connection.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function close();

	/**
	 * Retrieves information and statistics about the cache backend.
	 *
	 * @return array An array of info and stats.
	 */
	public function get_info();

	/**
	 * Checks if the cache backend is connected.
	 *
	 * @return bool True if connected, false otherwise.
	 */
	public function is_connected();
}
