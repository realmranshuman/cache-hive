<?php
// scoper.inc.php

declare(strict_types=1);

use Isolated\Symfony\Component\Finder\Finder;

return [
    // The prefix that will be used for all namespaces.
    'prefix' => 'Cache_Hive\\Vendor',

    // Specifies where Scoper can find the files to scope.
    // This finder configuration is crucial to ensure it picks up the right files.
    'finders' => [
        Finder::create()
            ->files()
            ->in('vendor')
            ->notPath('composer/installed.json') // Don't include this file
            ->name('*.php'),

        Finder::create()->files()->in('vendor')->depth('== 0')->name('autoload.php'),
    ],

    // Whitelist specific namespaces that should NOT be prefixed.
    // This is the definitive fix for the fatal error.
    'exclude-namespaces' => [
        // The double backslash is crucial for the regex to be valid.
        '#^Composer\\\\Autoload#',
        '#^Symfony\\\\Polyfill#',
    ],
    
    // By default, Scoper is smart enough not to prefix global PHP functions and classes.
    'expose-global-constants' => true,
    'expose-global-classes'   => true,
    'expose-global-functions' => true,
];