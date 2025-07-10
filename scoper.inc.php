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

	// Tell Scoper which files to scope. We will target ONLY the Predis, Credis, Minify
	// and their required PSR dependency directories within the `vendor` folder.
	'finders' => array(
		// Find and scope all the production dependencies.
		Finder::create()
			->files()
			->in('vendor')
			->path([
				'predis/predis',
				'colinmollenhour/credis',
				'psr/log',
				'psr/http-message',
				'matthiasmullie/minify',
				'matthiasmullie/path-converter',
			]),

		// Find and scope the Composer autoloader files.
		Finder::create()
			->files()
			->in('vendor/composer')
			->name('/.*\.php/'),
	),
);