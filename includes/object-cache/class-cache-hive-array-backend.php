<?php
/**
 * Array fallback backend for Cache Hive object cache.
 *
 * @package Cache_Hive
 */

namespace Cache_Hive\Includes\Object_Cache;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Array-based cache backend implementation.
 */
class Cache_Hive_Array_Backend implements Cache_Hive_Backend_Interface {
	/**
	 * The internal array used for caching.
	 *
	 * @var array
	 */
	private $cache = array();

	/**
	 * Constructor for the array backend.
	 *
	 * @param array $config Configuration array for the backend.
	 */
	public function __construct( $config ) {}

	/**
	 * Retrieves a value from the cache.
	 *
	 * @param string $key The key to retrieve.
	 * @param bool   $found Whether the key was found in the cache.
	 * @return mixed The cached value, or false if not found.
	 */
	public function get( $key, &$found ) {
		$found = isset( $this->cache[ $key ] );
		return $found ? $this->cache[ $key ] : false; }

	/**
	 * Retrieves multiple values from the cache.
	 *
	 * @param array $keys An array of keys to retrieve.
	 * @return array An associative array of cached values, keyed by the original keys.
	 */
	public function get_multiple( $keys ) {
		$results = array();
		foreach ( $keys as $key ) {
			$found = false;
			$value = $this->get( $key, $found );
			if ( $found ) {
				$results[ $key ] = $value;
			}
		}
		return $results;
	}

	/**
	 * Stores a value in the cache.
	 *
	 * @param string $key The key to store the value under.
	 * @param mixed  $value The value to store.
	 * @param int    $ttl The time-to-live for the cache item in seconds.
	 * @return bool True on success, false on failure.
	 */
	public function set( $key, $value, $ttl ) {
		$this->cache[ $key ] = $value;
		return true; }

	/**
	 * Adds a value to the cache only if the key does not already exist.
	 *
	 * @param string $key The key to store the value under.
	 * @param mixed  $value The value to store.
	 * @param int    $ttl The time-to-live for the cache item in seconds.
	 * @return bool True on success, false on failure.
	 */
	public function add( $key, $value, $ttl ) {
		if ( isset( $this->cache[ $key ] ) ) {
			return false;
		} return $this->set( $key, $value, $ttl ); }

	/**
	 * Replaces a value in the cache only if the key already exists.
	 *
	 * @param string $key The key to store the value under.
	 * @param mixed  $value The value to store.
	 * @param int    $ttl The time-to-live for the cache item in seconds.
	 * @return bool True on success, false on failure.
	 */
	public function replace( $key, $value, $ttl ) {
		if ( ! isset( $this->cache[ $key ] ) ) {
			return false;
		} return $this->set( $key, $value, $ttl ); }

	/**
	 * Deletes a value from the cache.
	 *
	 * @param string $key The key to delete.
	 * @return bool True on success, false on failure.
	 */
	public function delete( $key ) {
		unset( $this->cache[ $key ] );
		return true; }

	/**
	 * Flushes the entire cache.
	 *
	 * @param bool $async Whether to flush asynchronously (if supported).
	 * @return bool True on success, false on failure.
	 */
	public function flush( $async ) {
		$this->cache = array();
		return true; }

	/**
	 * Increments a numeric item's value.
	 *
	 * @param string $key The key of the item to increment.
	 * @param int    $offset The amount by which to increment the item's value.
	 * @return int|false The new value on success, false on failure.
	 */
	public function increment( $key, $offset ) {
		if ( ! isset( $this->cache[ $key ] ) ) {
			$this->cache[ $key ] = 0;
		} $this->cache[ $key ] += $offset;
		return $this->cache[ $key ]; }

	/**
	 * Decrements a numeric item's value.
	 *
	 * @param string $key The key of the item to decrement.
	 * @param int    $offset The amount by which to decrement the item's value.
	 * @return int|false The new value on success, false on failure.
	 */
	public function decrement( $key, $offset ) {
		if ( ! isset( $this->cache[ $key ] ) ) {
			$this->cache[ $key ] = 0;
		} $this->cache[ $key ] -= $offset;
		return $this->cache[ $key ]; }

	/**
	 * Closes the connection to the cache backend.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function close() {
		return true; }

	/**
	 * Checks if the cache backend is connected.
	 *
	 * @return bool True if connected, false otherwise.
	 */
	public function is_connected() {
		return false; } // It's a fallback, so it's never "connected" to an external service.

	/**
	 * Retrieves information about the cache backend.
	 *
	 * @return array An associative array containing information about the backend.
	 */
	public function get_info() {
		return array(
			'status' => 'Not Connected',
			'client' => 'Array Cache (Fallback)',
		); }
}
