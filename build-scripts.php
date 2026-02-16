<?php
/**
 * Cross-platform build script for Cache Hive.
 *
 * Usage: php build-scripts.php <command>
 *
 * @package Cache_Hive
 */

$command = $argv[1] ?? null;

if ( ! $command ) {
	echo "Usage: php build-scripts.php [prepare-dirs|build]\n";
	exit( 1 );
}

switch ( $command ) {
	case 'prepare-dirs':
		prepare_dirs();
		break;
	case 'build':
		build();
		break;
	default:
		echo "Unknown command: $command\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit( 1 );
}

/**
 * Prepares the necessary directories for the build.
 *
 * @return void
 */
function prepare_dirs() {
	echo "Step: Preparing directories...\n";
	$dirs = array(
		'lib/predis/predis/src',
		'lib/psr/log/src',
		'lib/psr/http-message/src',
		'lib/colinmollenhour/credis',
		'lib/matthiasmullie/minify/src',
		'lib/matthiasmullie/path-converter/src',
	);

	foreach ( $dirs as $dir ) {
		if ( ! file_exists( $dir ) ) {
			if ( mkdir( $dir, 0777, true ) ) {
				echo "Created: $dir\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} else {
				echo "Failed to create: $dir\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				exit( 1 );
			}
		}
	}
}

/**
 * Executes the full build process.
 *
 * @return void
 */
function build() {
	// 1. Run Dev Build first.
	echo "Step: Running Dev Build...\n";
	prepare_dirs();

	echo "Step: Installing Composer Dependencies...\n";
	passthru( 'composer install', $return );
	if ( 0 !== $return ) {
		exit( $return ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	echo "Step: Scoping PHP dependencies...\n";
	passthru( 'php-scoper add-prefix --output-dir=lib --force', $return );
	if ( 0 !== $return ) {
		exit( $return ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	echo "Step: Refreshing autoloader...\n";
	passthru( 'composer dump-autoload --optimize', $return );
	if ( 0 !== $return ) {
		exit( $return ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	echo "Step: Building JS assets...\n";
	// Check for npm vs npm.cmd on Windows.
	$npm = ( '\\' === DIRECTORY_SEPARATOR ) ? 'npm.cmd' : 'npm';
	passthru( "$npm install && $npm run build", $return ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_passthru
	if ( 0 !== $return ) {
		exit( $return ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	// 2. Create Distribution.
	echo "Step: Creating Distribution...\n";
	$dist   = 'dist';
	$target = "$dist/cache-hive";

	if ( file_exists( $dist ) ) {
		remove_directory( $dist );
	}
	mkdir( $target, 0777, true );

	// 3. Copy files with exclusions.
	echo "Step: Copying project files...\n";
	copy_project_files( '.', $target );

	// 4. Copy explicitly included artifacts (lib, build) that were ignored by project copy.
	echo "Step: Copying build artifacts...\n";
	recursive_copy( 'lib', "$target/lib" );
	recursive_copy( 'build', "$target/build" );

	// 5. Setup production composer.json.
	copy( 'composer.dist.json', "$target/composer.json" );

	// 6. Production Autoload.
	echo "Step: Generating production autoloader...\n";
	$cwd = getcwd();
	chdir( $target );
	passthru( 'composer dump-autoload --no-dev --optimize', $return );
	chdir( $cwd );
	if ( 0 !== $return ) {
		exit( $return ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	// 7. Cleanup.
	if ( file_exists( "$target/composer.json" ) ) {
		unlink( "$target/composer.json" );
	}

	// 8. Copy extra JS.
	copy( 'src/media-library.js', 'build/media-library.js' );

	echo "\nSUCCESS! Production-ready plugin is in $target\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

// Helpers.

/**
 * Recursively removes a directory.
 *
 * @param string $dir The directory to remove.
 * @return void
 */
function remove_directory( $dir ) {
	if ( ! file_exists( $dir ) ) {
		return;
	}
	$files = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ( $files as $fileinfo ) {
		$todo = ( $fileinfo->isDir() ? 'rmdir' : 'unlink' );
		$todo( $fileinfo->getRealPath() );
	}
	rmdir( $dir );
}

/**
 * Recursively copies a directory.
 *
 * @param string $src Source directory.
 * @param string $dst Destination directory.
 * @return void
 */
function recursive_copy( $src, $dst ) {
	if ( ! file_exists( $src ) ) {
		return;
	}
	if ( ! file_exists( $dst ) ) {
		mkdir( $dst, 0777, true );
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $src, RecursiveDirectoryIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::SELF_FIRST
	);

	foreach ( $iterator as $item ) {
		$sub_path    = $iterator->getSubPathName();
		$target_path = "$dst/$sub_path";
		if ( $item->isDir() ) {
			if ( ! file_exists( $target_path ) ) {
				mkdir( $target_path );
			}
		} else {
			copy( $item, $target_path );
		}
	}
}

/**
 * Copies project files to the destination, respecting exclusions.
 *
 * @param string $src Source directory.
 * @param string $dst Destination directory.
 * @return void
 */
function copy_project_files( $src, $dst ) {
	$excludes = load_exclusions();

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $src, RecursiveDirectoryIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::SELF_FIRST
	);

	foreach ( $iterator as $item ) {
		$sub_path = str_replace( '\\', '/', $iterator->getSubPathName() ); // Normalize for matching.

		// Skip dot files/dirs at root if needed or check exclusions.
		if ( should_exclude( $sub_path, $excludes ) ) {
			continue;
		}

		$target_path = "$dst/" . $iterator->getSubPathName();
		if ( $item->isDir() ) {
			if ( ! file_exists( $target_path ) ) {
				mkdir( $target_path );
			}
		} else {
			copy( $item, $target_path );
		}
	}
}

/**
 * Loads exclusions from .distignore.
 *
 * @return array Array of exclusion patterns.
 */
function load_exclusions() {
	$lines    = file_exists( '.distignore' ) ? file( '.distignore', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) : array();
	$excludes = array();
	foreach ( $lines as $line ) {
		$line = trim( $line );
		if ( '' === $line || 0 === strpos( $line, '#' ) ) {
			continue;
		}
		$excludes[] = $line;
	}
	// Always exclude dist to avoid recursion if not in ignore.
	$excludes[] = 'dist/';
	return $excludes;
}

/**
 * Checks if a path should be excluded based on patterns.
 *
 * @param string $path     The path to check.
 * @param array  $patterns The list of exclusion patterns.
 * @return bool True if excluded, false otherwise.
 */
function should_exclude( $path, $patterns ) {
	foreach ( $patterns as $pattern ) {
		// Simple emulation of rsync/gitignore matching.
		$pattern = trim( $pattern, '/' );

		// If pattern matches the start of the path (directory ignore).
		if ( 0 === strpos( $path, $pattern ) ) {
			// Check if it's an exact match or a subdirectory.
			if ( strlen( $path ) === strlen( $pattern ) || '/' === $path[ strlen( $pattern ) ] ) {
				return true;
			}
		}

		// Glob matching needed? For now simple prefix/exact match for this specific use case.
		// The .distignore has simple entries like .git/, vendor/, etc.
		if ( $path === $pattern ) {
			return true;
		}
	}
	return false;
}
