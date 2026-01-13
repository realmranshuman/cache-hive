<?php

/**
 * Cross-platform build script for Cache Hive.
 * Usage: php build-scripts.php <command>
 */

$command = $argv[1] ?? null;

if (!$command) {
    echo "Usage: php build-scripts.php [prepare-dirs|build]\n";
    exit(1);
}

switch ($command) {
    case 'prepare-dirs':
        prepareDirs();
        break;
    case 'build':
        build();
        break;
    default:
        echo "Unknown command: $command\n";
        exit(1);
}

function prepareDirs() {
    echo "Step: Preparing directories...\n";
    $dirs = [
        'lib/predis/predis/src',
        'lib/psr/log/src',
        'lib/psr/http-message/src',
        'lib/colinmollenhour/credis',
        'lib/matthiasmullie/minify/src',
        'lib/matthiasmullie/path-converter/src'
    ];

    foreach ($dirs as $dir) {
        if (!file_exists($dir)) {
            if (mkdir($dir, 0777, true)) {
                echo "Created: $dir\n";
            } else {
                echo "Failed to create: $dir\n";
                exit(1);
            }
        }
    }
}

function build() {
    // 1. Run Dev Build first
    echo "Step: Running Dev Build...\n";
    prepareDirs();
    
    echo "Step: Installing Composer Dependencies...\n";
    passthru('composer install', $return);
    if ($return !== 0) exit($return);

    echo "Step: Scoping PHP dependencies...\n";
    passthru('php-scoper add-prefix --output-dir=lib --force', $return);
    if ($return !== 0) exit($return);

    echo "Step: Refreshing autoloader...\n";
    passthru('composer dump-autoload --optimize', $return);
    if ($return !== 0) exit($return);

    echo "Step: Building JS assets...\n";
    // Check for npm vs npm.cmd on Windows
    $npm = (DIRECTORY_SEPARATOR === '\\') ? 'npm.cmd' : 'npm';
    passthru("$npm install && $npm run build", $return);
    if ($return !== 0) exit($return);

    // 2. Create Distribution
    echo "Step: Creating Distribution...\n";
    $dist = 'dist';
    $target = "$dist/cache-hive";

    if (file_exists($dist)) {
        removeDir($dist);
    }
    mkdir($target, 0777, true);

    // 3. Copy files with exclusions
    echo "Step: Copying project files...\n";
    copyProjectFiles('.', $target);

    // 4. Copy explicitly included artifacts (lib, build) that were ignored by project copy
    echo "Step: Copying build artifacts...\n";
    recursiveCopy('lib', "$target/lib");
    recursiveCopy('build', "$target/build");

    // 5. Setup production composer.json
    copy('composer.dist.json', "$target/composer.json");

    // 6. Production Autoload
    echo "Step: Generating production autoloader...\n";
    $cwd = getcwd();
    chdir($target);
    passthru('composer dump-autoload --no-dev --optimize', $return);
    chdir($cwd);
    if ($return !== 0) exit($return);

    // 7. Cleanup
    if (file_exists("$target/composer.json")) {
        unlink("$target/composer.json");
    }

    // 8. Copy extra JS
    copy('src/media-library.js', 'build/media-library.js');

    echo "\nSUCCESS! Production-ready plugin is in $target\n";
}

// Helpers

function removeDir($dir) {
    if (!file_exists($dir)) return;
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $fileinfo) {
        $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
        $todo($fileinfo->getRealPath());
    }
    rmdir($dir);
}

function recursiveCopy($src, $dst) {
    if (!file_exists($src)) return;
    if (!file_exists($dst)) mkdir($dst, 0777, true);
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $subPath = $iterator->getSubPathName();
        $targetPath = "$dst/$subPath";
        if ($item->isDir()) {
            if (!file_exists($targetPath)) mkdir($targetPath);
        } else {
            copy($item, $targetPath);
        }
    }
}

function copyProjectFiles($src, $dst) {
    $excludes = loadExclusions();
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $subPath = str_replace('\\', '/', $iterator->getSubPathName()); // Normalize for matching
        
        // Skip dot files/dirs at root if needed or check exclusions
        if (shouldExclude($subPath, $excludes)) {
            continue;
        }

        $targetPath = "$dst/" . $iterator->getSubPathName();
        if ($item->isDir()) {
            if (!file_exists($targetPath)) mkdir($targetPath);
        } else {
            copy($item, $targetPath);
        }
    }
}

function loadExclusions() {
    $lines = file_exists('.distignore') ? file('.distignore', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    $excludes = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        $excludes[] = $line;
    }
    // Always exclude dist to avoid recursion if not in ignore
    $excludes[] = 'dist/';
    return $excludes;
}

function shouldExclude($path, $patterns) {
    foreach ($patterns as $pattern) {
        // Simple emulation of rsync/gitignore matching
        $pattern = trim($pattern, '/');
        
        // If pattern matches the start of the path (directory ignore)
        if (strpos($path, $pattern) === 0) {
            // Check if it's an exact match or a subdirectory
            if (strlen($path) === strlen($pattern) || $path[strlen($pattern)] === '/') {
                return true;
            }
        }
        
        // Glob matching needed? For now simple prefix/exact match for this specific use case
        // The .distignore has simple entries like .git/, vendor/, etc.
        if ($path === $pattern) return true;
    }
    return false;
}
