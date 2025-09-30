<?php

// ForwardEmail SnappyMail Integration Configuration

header('Strict-Transport-Security: max-age=31536000');

/**
 * ForwardEmail Backend API URL
 */
define('FORWARDEMAIL_API_URL', $_ENV['FORWARDEMAIL_API_URL'] ?? 'http://localhost:3000');

/**
 * Disable local data storage - use ForwardEmail backend
 */
define('APP_USE_APCU_CACHE', false);

/**
 * Custom 'data' folder path for temporary files only
 * Can be overridden by environment variable APP_DATA_FOLDER_PATH
 */
if (!defined('APP_DATA_FOLDER_PATH')) {
    define('APP_DATA_FOLDER_PATH', $_ENV['APP_DATA_FOLDER_PATH'] ?? __DIR__ . '/data/');
}

/**
 * Configuration name for multi-tenant setup
 */
define('APP_CONFIGURATION_NAME', 'forwardemail.ini');

/**
 * Disable local storage providers - use API
 */
define('DISABLE_LOCAL_STORAGE', true);

/**
 * ForwardEmail integration mode
 */
define('FORWARDEMAIL_MODE', true);

/**
 * Set default theme to ForwardEmail
 */
if (!defined('APP_DEFAULT_THEME')) {
    define('APP_DEFAULT_THEME', 'ForwardEmail');
}

/**
 * Load ForwardEmail configuration
 */
if (file_exists(__DIR__ . '/forwardemail-config.php')) {
    include_once __DIR__ . '/forwardemail-config.php';
}
