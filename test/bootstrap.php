<?php

/*
 * Set error reporting
 */
error_reporting(E_ALL | E_STRICT);

if (class_exists('\PHPUnit\Runner\Version', true)) {
    $phpUnitVersion = \PHPUnit\Runner\Version::id();
    if ('@package_version@' !== $phpUnitVersion && version_compare($phpUnitVersion, '6.1.0', '<')) {
        echo 'This version of PHPUnit (' . \PHPUnit\Runner\Version::id() . ') is not supported'
            . ' in the zend-db unit tests. Supported is version 4.0.0 or higher.'
            . ' See also the CONTRIBUTING.md file in the component root.' . PHP_EOL;
        exit(1);
    }
    unset($phpUnitVersion);
}

/*
 * Setup autoloading
 */
require __DIR__ . '/../vendor/autoload.php';