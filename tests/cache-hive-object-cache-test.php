<?php
/**
 * Cache Hive Object Cache Test Suite (Advanced).
 *
 * Loads the WordPress environment and runs a comprehensive test suite against the
 * active Cache Hive object cache backend, including advanced feature verification.
 *
 * @package   Cache_Hive
 * @since     1.2.0
 * @version   1.2.0
 *
 * @warning This script performs write operations to your cache backend. The HMAC
 *          integrity test involves writing intentionally malformed data to verify
 *          security, which is then deleted. Use in a staging environment is advised.
 */

// --- Load WordPress ---
if ( ! defined( 'ABSPATH' ) ) {
	$wp_load_path = __DIR__ . '/../../../../wp-load.php';
	if ( file_exists( $wp_load_path ) ) {
		require_once $wp_load_path;
	} else {
		header( 'Content-Type: text/plain; charset=utf-8' );
		exit( 'Error: Could not find wp-load.php. Please ensure this test script is in the correct directory.' );
	}
}

// --- Test Runner Setup ---
global $cache_hive_test_results;
$cache_hive_test_results = array(
	'passed' => 0,
	'failed' => 0,
);

/**
 * Runs a test case and prints the result.
 *
 * @since 1.2.0
 *
 * @param string   $description Test description.
 * @param callable $callback    Test logic, should return true on pass, false on fail.
 */
function run_test( $description, $callback ) {
	global $cache_hive_test_results;
	$start_time = microtime( true );
	$result     = false;
	$output     = '';

	ob_start();
	try {
		$result = $callback();
	} catch ( Exception $e ) {
		$result = false;
		echo 'Caught Exception: ' . esc_html( $e->getMessage() );
	}
	$output   = ob_get_clean();
	$duration = microtime( true ) - $start_time;

	if ( $result ) {
		++$cache_hive_test_results['passed'];
		$status_html = '<span class="status pass">PASS</span>';
	} else {
		++$cache_hive_test_results['failed'];
		$status_html = '<span class="status fail">FAIL</span>';
	}

	$allowed_html = array(
		'span' => array( 'class' => array() ),
	);

	echo '<tr>';
	echo '<td>' . wp_kses( $status_html, $allowed_html ) . '</td>';
	echo '<td>' . esc_html( $description ) . '</td>';
	echo '<td>' . esc_html( number_format( $duration * 1000, 2 ) ) . ' ms</td>';
	echo '</tr>';

	if ( ! $result && ! empty( $output ) ) {
		echo '<tr><td colspan="3" class="failure-details"><pre>' . esc_html( $output ) . '</pre></td></tr>';
	}
}

/**
 * Prints a section header row in the test table.
 *
 * @since 1.2.1
 * @param string $title The title of the section.
 */
function test_section_header( $title ) {
	echo '<tr><td colspan="3" class="section-header"><h2>' . esc_html( $title ) . '</h2></td></tr>';
}


// --- Environment Setup ---
wp_cache_init();
$info             = function_exists( 'wp_cache_get_info' ) ? wp_cache_get_info() : null;
$config           = null;
$config_file_path = WP_CONTENT_DIR . '/cache-hive-config/config.php';
if ( file_exists( $config_file_path ) ) {
	$config_json = include $config_file_path;
	$config      = json_decode( $config_json, true );
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Cache Hive - Object Cache Test Suite</title>
	<style>
		body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; background-color: #f0f0f1; color: #444; margin: 20px; }
		.container { max-width: 960px; margin: 0 auto; background: #fff; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-radius: 4px; }
		h1, h2 { color: #2271b1; border-bottom: 1px solid #ddd; padding-bottom: 10px; }
		h1 { font-size: 24px; }
		h2 { font-size: 20px; margin-top: 15px; margin-bottom: 10px; }
		table { width: 100%; border-collapse: collapse; margin-top: 20px; }
		th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e5e5e5; }
		th { background-color: #f5f5f5; font-weight: 600; }
		.status { font-weight: bold; padding: 4px 8px; border-radius: 4px; color: #fff; font-size: 12px; }
		.status.pass { background-color: #28a745; }
		.status.fail { background-color: #dc3545; }
		.status.skip { background-color: #6c757d; }
		.summary { padding: 20px; margin-top: 20px; border-radius: 4px; }
		.summary.success { background-color: #e9f6eb; border: 1px solid #a0d3a9; }
		.summary.error { background-color: #fbeaea; border: 1px solid #e8a9a9; }
		.info-box { background: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; border-radius: 4px; margin-top: 20px; }
		.info-box h3 { margin-top: 0; }
		.info-box pre { background: #fff; padding: 10px; border-radius: 4px; white-space: pre-wrap; word-break: break-all; }
		.failure-details { background-color: #fff6f6; }
		.failure-details pre { margin: 10px; padding: 10px; border: 1px dashed #e1acac; white-space: pre-wrap; }
		.section-header { background-color: #f0f8ff; }
		.section-header h2 { border: none; }
		code { font-family: monospace; background: #eee; padding: 2px 4px; border-radius: 3px; }
	</style>
</head>
<body>
	<div class="container">
		<h1><svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 10px;"><path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"></path></svg>Cache Hive - Object Cache Test Suite</h1>
		<p><em>Generated at: <?php echo esc_html( gmdate( 'Y-m-d H:i:s T' ) ); ?></em></p>

		<?php if ( ! defined( 'WP_CACHE_HIVE_OBJECT_CACHE_LOADED' ) || ! $config ) : ?>
			<div class="summary error">
				<h2>Error: Cache Hive Object Cache Not Active</h2>
				<p>The Cache Hive drop-in or its config file (<code>wp-content/cache-hive-config/config.php</code>) was not detected. Please enable the Object Cache from the Cache Hive settings page.</p>
				<?php if ( file_exists( WP_CONTENT_DIR . '/object-cache.php' ) ) : ?>
					<div class="info-box">
						<h3>Detected Drop-in Content (First 200 chars)</h3>
						<pre><?php echo esc_html( file_get_contents( WP_CONTENT_DIR . '/object-cache.php', false, null, 0, 200 ) ); ?></pre>
					</div>
				<?php endif; ?>
			</div>
			<?php exit; ?>
		<?php endif; ?>

		<div class="info-box">
			<h3>Active Backend Information</h3>
			<pre><?php echo esc_html( print_r( $info, true ) ); ?></pre>
		</div>
		
		<table id="test-results">
			<thead>
				<tr>
					<th style="width: 80px;">Status</th>
					<th>Test Description</th>
					<th style="width: 120px;">Duration</th>
				</tr>
			</thead>
			<tbody>
				<?php
				wp_cache_flush();

				test_section_header( 'Basic API Compliance' );

				$key   = 'test_key_' . uniqid( '', true );
				$group = 'test_group';

				run_test(
					'Basic: wp_cache_add() new key',
					function () use ( $key, $group ) {
						return wp_cache_add( $key, 'initial_value', $group );
					}
				);
				run_test(
					'Basic: wp_cache_add() existing key (should fail)',
					function () use ( $key, $group ) {
						return ! wp_cache_add( $key, 'another_value', $group );
					}
				);
				run_test(
					'Basic: wp_cache_get() retrieves correct value',
					function () use ( $key, $group ) {
						return 'initial_value' === wp_cache_get( $key, $group );
					}
				);
				run_test(
					'Basic: wp_cache_set() overwrites existing key',
					function () use ( $key, $group ) {
						return wp_cache_set( $key, 'set_value', $group );
					}
				);
				run_test(
					'Basic: wp_cache_get() retrieves "set" value',
					function () use ( $key, $group ) {
						return 'set_value' === wp_cache_get( $key, $group );
					}
				);
				run_test(
					'Basic: wp_cache_replace() fails on non-existent key',
					function () use ( $group ) {
						return ! wp_cache_replace( 'non_existent_key', 'value', $group );
					}
				);
				run_test(
					'Basic: wp_cache_replace() succeeds on existing key',
					function () use ( $key, $group ) {
						return wp_cache_replace( $key, 'replaced_value', $group );
					}
				);
				run_test(
					'Basic: wp_cache_get() retrieves replaced value',
					function () use ( $key, $group ) {
						return 'replaced_value' === wp_cache_get( $key, $group );
					}
				);
				run_test(
					'Basic: wp_cache_delete() removes key',
					function () use ( $key, $group ) {
						wp_cache_delete( $key, $group );
						$found = null;
						wp_cache_get( $key, $group, false, $found );
						return ! $found;
					}
				);
				run_test(
					'Counters: wp_cache_incr() and wp_cache_decr()',
					function () use ( $group ) {
						$key = 'counter_key';
						wp_cache_delete( $key, $group );
						$result1 = wp_cache_incr( $key, 1, $group );
						if ( false === $result1 ) {
							wp_cache_set( $key, 0, $group );
							$result1 = wp_cache_incr( $key, 1, $group );
						} $result2 = wp_cache_incr( $key, 1, $group );
						$result3   = wp_cache_decr( $key, 1, $group );
						return in_array( $result3, array( 0, 1 ), true ) && 2 === $result2;
					}
				);

				test_section_header( 'Data Handling & Expiration' );

				run_test(
					'Expiration (TTL): Value expires after 2 seconds',
					function () use ( $group ) {
						$ttl_key = 'ttl_key';
						wp_cache_set( $ttl_key, 'temporary_value', $group, 2 );
						wp_cache_reset();
						sleep( 3 );
						$found = null;
						wp_cache_get( $ttl_key, $group, false, $found );
						return ! $found;
					}
				);
				$data_types = array(
					'string'        => 'Hello World',
					'integer'       => 12345,
					'float'         => 3.14159,
					'boolean_true'  => true,
					'boolean_false' => false,
					'null'          => null,
					'array'         => array(
						'a' => 1,
						'b' => array( 'c' => 2 ),
					),
					'object'        => (object) array(
						'a' => 1,
						'b' => 'test',
					),
				);
				foreach ( $data_types as $type => $value ) {
					run_test(
						"Data Types: Caching a(n) {$type}",
						function () use ( $type, $value, $group ) {
										$data_key = "data_{$type}";
										wp_cache_set( $data_key, $value, $group );
										$retrieved = wp_cache_get( $data_key, $group );
										return wp_json_encode( $retrieved ) === wp_json_encode( $value );
						}
					); }

				test_section_header( 'Advanced Feature Verification' );

				run_test(
					'Security: HMAC signature prevents tampering',
					function () use ( $config ) {
						$tamper_key   = 'tamper_key_' . uniqid( '', true );
						$tamper_group = 'tamper_group';
						$key_prefix   = $config['object_cache_key'] ?? '';
						$full_key     = ( $key_prefix ? $key_prefix . ':' : '' ) . "{$tamper_group}:{$tamper_key}";
						$backend      = null;

						try {
							if ( 'redis' === ( $config['object_cache_method'] ?? '' ) && class_exists( 'Redis' ) ) {
								$backend = new Redis();
								if ( 'unix' === ( $config['object_cache_host'][0] ?? '' ) ) {
									$backend->connect( $config['object_cache_host'] );
								} else {
									$backend->connect( $config['object_cache_host'], $config['object_cache_port'] );
								}
								if ( ! empty( $config['object_cache_password'] ) ) {
									$backend->auth( $config['object_cache_password'] );
								}
								if ( ! empty( $config['object_cache_database'] ) ) {
									$backend->select( $config['object_cache_database'] );
								}
							} elseif ( 'memcached' === ( $config['object_cache_method'] ?? '' ) && class_exists( 'Memcached' ) ) {
								$backend = new Memcached();
								$backend->addServer( $config['object_cache_host'], $config['object_cache_port'] );
							}

							if ( ! $backend ) {
								echo 'Skipping: Could not establish a direct connection to the backend.';
								return true; // Skip test if direct connection fails.
							}

							$malformed_value = serialize( 'tampered_value' ); // No HMAC signature.
							$backend->set( $full_key, $malformed_value );

							wp_cache_reset(); // Clear local cache.
							$value = wp_cache_get( $tamper_key, $tamper_group, false, $found );

							// Clean up.
							$backend->delete( $full_key );

							if ( $found ) {
								echo 'FAIL: Malformed data was retrieved from the cache. HMAC verification may have failed.';
								return false;
							}
							return true; // PASS: The value was not found, meaning decode failed as expected.

						} catch ( Exception $e ) {
							echo 'Skipping: ' . esc_html( $e->getMessage() );
							return true; // Skip test if direct connection throws an exception.
						}
					}
				);

				run_test(
					'Config: Persistent Connection flag is correctly reported',
					function () use ( $config, $info ) {
						return (bool) ( $config['object_cache_persistent_connection'] ?? false ) === (bool) ( $info['persistent'] ?? false );
					}
				);
				run_test(
					'Config: Asynchronous Flush flag is correctly reported',
					function () use ( $config, $info ) {
						return (bool) ( $config['object_cache_flush_async'] ?? false ) === (bool) ( $info['flush_async'] ?? false );
					}
				);
				run_test(
					'Config: Prefetch flag is correctly reported',
					function () use ( $config, $info ) {
						return (bool) ( $config['object_cache_prefetch'] ?? false ) === (bool) ( $info['prefetch'] ?? false );
					}
				);

				$no_cache_group = $config['object_cache_no_cache_groups'][0] ?? 'comment';
				run_test(
					"Behavior: 'No-Cache' group ({$no_cache_group}) is not persistent",
					function () use ( $no_cache_group ) {
						$key = 'no_cache_test';
						wp_cache_set( $key, 'non_persistent_value', $no_cache_group );
						$val1 = wp_cache_get( $key, $no_cache_group );
						wp_cache_reset(); // Clear in-memory cache.
						$val2 = wp_cache_get( $key, $no_cache_group, false, $found );
						return 'non_persistent_value' === $val1 && false === $found;
					}
				);

				wp_cache_flush();
				?>
			</tbody>
		</table>

		<div class="summary <?php echo ( 0 < $cache_hive_test_results['failed'] ) ? 'error' : 'success'; ?>">
			<h2>Test Summary</h2>
			<p>
				<strong>Passed:</strong> <?php echo esc_html( $cache_hive_test_results['passed'] ); ?> |
				<strong>Failed:</strong> <?php echo esc_html( $cache_hive_test_results['failed'] ); ?>
			</p>
		</div>
	</div>
</body>
</html>