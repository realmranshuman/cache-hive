<?php
/**
 * PHP-Scoper configuration file for Cache Hive.
 *
 * @package Cache_Hive
 */

use Isolated\Symfony\Component\Finder\Finder;

return array(
	// The prefix that will be added to all namespaces and global classes.
	// This is the answer to "how do I find the new namespace?". It's what you define here.
	'prefix'  => 'Cache_Hive\\Vendor',

	// Tell Scoper which files to scope. We will target ONLY the Predis, Credis,
	// and their required PSR dependency directories within the `vendor` folder.
	'finders' => array(
		Finder::create()
			->files()
			->in( 'vendor' )
			// Scope Predis and its PSR dependency.
			->path( 'predis/predis/src' )
			->path( 'psr/http-message/src' )
			->path( 'psr/container/src' )
			// Scope Credis.
			->path( 'colinmollenhour/credis' ),

		// We also need to include the top-level composer files for autoloading.
		Finder::create()
			->files()
			->in( 'vendor' )
			->depth( '== 0' ) // important: only files in vendor/, not subdirectories.
			->name( '/autoload_.*\.php/' ),

		Finder::create()
			->files()
			->in( 'vendor/composer' )
			->name( '/.*\.php/' ),

	),

	// By default, Scoper whitelists (exposes) all global functions, constants,
	// and classes. This is generally safe and what we want, so we don't need
	// to add a `patchers` or `expose-` array for this use case.
);
