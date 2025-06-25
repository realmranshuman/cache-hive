<?php
/**
 * Array fallback backend for Cache Hive object cache.
 *
 * @package Cache_Hive
 */

/**
 * This backend provides a non-persistent, in-memory cache for the current request.
 * It is used as a fallback when no external object cache is available.
 */
require_once __DIR__ . '/interface-backend.php';

/**
 * Array-based cache backend implementation.
 *
 * @package Cache_Hive
 */
class Cache_Hive_Array_Backend implements Cache_Hive_Backend_Interface {
	/**
	 * Holds the cache data.
	 *
	 * @var array
	 */
	private $cache = array();

	/**
	 * Constructor. The config is not used for this backend.
	 *
	 * @param array $config The backend configuration (unused).
	 */
	public function __construct( $config ) {}

	/**
	 * Retrieves an item from the cache.
	 *
	 * @param string    $key   The key of the item to retrieve.
	 * @param bool|null &$found Whether the key was found in the cache. Passed by reference.
	 * @return mixed The value of the item, or false on failure.
	 */
	public function get( $key, &$found ) {
		$found = isset( $this->cache[ $key ] );
		return $found ? $this->cache[ $key ] : false;
	}

	/**
	 * Retrieves multiple items from the cache.
	 *
	 * @param string[] $keys Array of keys to retrieve.
	 * @return array Array of found key-value pairs.
	 */
	public function get_multiple( $keys ) {
		$results = array();
		foreach ( $keys as $key ) {
			$found           = false;
			$results[ $key ] = $this->get( $key, $found );
		}
		return $results;
	}

	/**
	 * Stores an item in the cache.
	 *
	 * @param string $key   The key under which to store the value.
	 * @param mixed  $value The value to store.
	 * @param int    $ttl   Time to live (unused for this backend).
	 * @return bool Always returns true.
	 */
	public function set( $key, $value, $ttl ) {
		$this->cache[ $key ] = $value;
		return true;
	}

	/**
	 * Adds an item to the cache, but only if the key does not exist.
	 *
	 * @param string $key   The key under which to store the value.
	 * @param mixed  $value The value to store.
	 * @param int    $ttl   Time to live (unused for this backend).
	 * @return bool True on success, false if the key already exists.
	 */
	public function add( $key, $value, $ttl ) {
		if ( isset( $this->cache[ $key ] ) ) {
			return false;
		}
		return $this->set( $key, $value, $ttl );
	}

	/**
	 * Replaces an item in the cache, but only if the key already exists.
	 *
	 * @param string $key   The key of the item to replace.
	 * @param mixed  $value The new value.
	 * @param int    $ttl   Time to live (unused for this backend).
	 * @return bool True on success, false if the key does not exist.
	 */
	public function replace( $key, $value, $ttl ) {
		if ( ! isset( $this->cache[ $key ] ) ) {
			return false;
		}
		return $this->set( $key, $value, $ttl );
	}

	/**
	 * Deletes an item from the cache.
	 *
	 * @param string $key The key to delete.
	 * @return bool Always returns true.
	 */
	public function delete( $key ) {
		unset( $this->cache[ $key ] );
		return true;
	}

	/**
	 * Flushes the entire cache.
	 *
	 * @param bool $async Not used by this backend.
	 * @return bool Always returns true.
	 */
	public function flush( $async ) {
		$this->cache = array();
		return true;
	}

	/**
	 * Increments a numeric item's value.
	 *
	 * @param string $key    The key of the item to increment.
	 * @param int    $offset The amount by which to increment.
	 * @return int The new value.
	 */
	public function increment( $key, $offset ) {
		if ( ! isset( $this->cache[ $key ] ) ) {
			$this->cache[ $key ] = 0;
		}
		$this->cache[ $key ] += $offset;
		return $this->cache[ $key ];
	}

	/**
	 * Decrements a numeric item's value.
	 *
	 * @param string $key    The key of the item to decrement.
	 * @param int    $offset The amount by which to decrement.
	 * @return int The new value.
	 */
	public function decrement( $key, $offset ) {
		if ( ! isset( $this->cache[ $key ] ) ) {
			$this->cache[ $key ] = 0;
		}
		$this->cache[ $key ] -= $offset;
		return $this->cache[ $key ];
	}

	/**
	 * Closes the connection to the cache. (Does nothing for this backend).
	 *
	 * @return bool Always returns true.
	 */
	public function close() {
		return true;
	}

	/**
	 * Checks if the cache backend is connected.
	 *
	 * @return bool Always returns false as this is a fallback.
	 */
	public function is_connected() {
		return false;
	}

	/**
	 * Gets information about the cache backend.
	 *
	 * @return array An array of cache status information.
	 */
	public function get_info() {
		return array(
			'status' => 'Not Connected',
			'client' => 'Array Cache (Fallback)',
		);
	}
}
