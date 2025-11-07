<?php
/**
 * Forward Email SnappyMail Custom Configuration
 * This file is loaded before SnappyMail initializes
 */

// Set default HOME environment variable if not set
// This prevents PHP warnings in GPG operations
if (!isset($_ENV['HOME']) && !getenv('HOME')) {
	$_ENV['HOME'] = '/tmp';
	putenv('HOME=/tmp');
}

// Uncomment to enable multiple domain installation
// define('MULTIDOMAIN', 1);

// Uncomment to disable APCU cache
// define('APP_USE_APCU_CACHE', false);

// Custom data folder path (useful for Docker deployments)
// define('APP_DATA_FOLDER_PATH', getenv('SNAPPYMAIL_DATA_PATH') ?: __DIR__ . '/data/');

// Additional configuration file name (for multi-tenant setups)
// define('APP_CONFIGURATION_NAME', $_SERVER['HTTP_HOST'] . '.ini');

// Update plugins on upgrade
// define('SNAPPYMAIL_UPDATE_PLUGINS', 1);
