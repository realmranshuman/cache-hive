<?php
/**
 * CacheHive Advanced Cache Drop-in
 *
 * This file is executed by WordPress before the main bootstrap process.
 * Its purpose is to check for a static cache file and serve it directly,
 * or to stop if an exclusion rule is met. It must be extremely fast.
 *
 * @version 1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// === STAGE 1: CHECK AGAINST COMPILED EXCLUSION RULES ===
// This is the fastest possible way to check for dynamic exclusions.
$ch_exclusion_file = WP_CONTENT_DIR . '/cache/cache-hive/config/exclusions-local.php';

if ( is_readable( $ch_exclusion_file ) ) {
	// The included file returns true if the request should be excluded, false otherwise.
	$is_excluded = include $ch_exclusion_file;
	if ( $is_excluded ) {
		// This request is excluded by a rule, so we stop processing and let WordPress load normally.
		return;
	}
}


// === STAGE 2: PREPARE FOR CACHE SERVING ===
// If we've reached this point, the request is potentially cacheable.

// A fast helper function for mobile detection.
function cachehive_is_mobile_request_early() {
	if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
		return false;
	}
	// This regex is a standard pattern for detecting a wide range of mobile devices.
	$mobile_pattern = '/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|rim)|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i';
	return (bool) preg_match( $mobile_pattern, $_SERVER['HTTP_USER_AGENT'] );
}

// --- Path Construction ---
if ( strpos( $_SERVER['REQUEST_URI'], '..' ) !== false ) {
	// Prevent directory traversal attacks.
	return;
}

// Use strtok to safely get the host and URI path without port numbers or query strings.
$ch_host     = defined( 'CACHEHIVE_HOST' ) ? CACHEHIVE_HOST : strtok( $_SERVER['HTTP_HOST'], ':' );
$ch_uri_path = strtok( $_SERVER['REQUEST_URI'], '?' );

// Determine the base path for cache files.
$ch_base_path = WP_CONTENT_DIR . '/cache/cache-hive/' . $ch_host;
$handler_type = 'desktop';

// Check if we need to look in the mobile cache directory.
if ( is_dir( $ch_base_path . '/mobile' ) && cachehive_is_mobile_request_early() ) {
	$ch_base_path .= '/mobile';
	$handler_type  = 'mobile';
}

// Sanitize the URI path again as a final precaution.
$ch_uri_path  = preg_replace( '/[ \'"\?&<>\(\)]/', '', $ch_uri_path );
$ch_full_path = $ch_base_path . $ch_uri_path;

// Append 'index.html' for directory requests.
if ( '/' === substr( $ch_uri_path, -1 ) ) {
	$ch_file_to_check = $ch_full_path . 'index.html';
} else {
	$ch_file_to_check = $ch_full_path;
}


// === STAGE 3: SERVE THE CACHED FILE ===
// Serve the file if it exists and is readable by the web server user.
if ( is_readable( $ch_file_to_check ) ) {
	// Send headers to identify the cache source and content type.
	header( 'X-Cache-Handler: CacheHive (advanced-cache.php ' . $handler_type . ')' );
	header( 'Content-Type: text/html; charset=UTF-8' );

	// Serve the file and stop PHP execution.
	readfile( $ch_file_to_check );
	exit;
}

// If no rules matched and no cache file was found, WordPress will continue its normal execution.
