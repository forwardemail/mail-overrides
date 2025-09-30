<?php
/**
 * ForwardEmail SnappyMail Configuration
 * This file sets up the default configuration for ForwardEmail integration
 */

// Set default theme
if (class_exists('RainLoop\\Api')) {
    $oConfig = \RainLoop\Api::Config();
    if ($oConfig) {
        // Set ForwardEmail theme as default
        $oConfig->Set('webmail', 'theme', 'ForwardEmail');

        // Disable theme selection for users (optional)
        $oConfig->Set('webmail', 'allow_themes', false);

        // Set application title
        $oConfig->Set('webmail', 'title', 'ForwardEmail Webmail');

        // Set loading description
        $oConfig->Set('webmail', 'loading_description', 'ForwardEmail - Privacy-focused email hosting');

        // Save configuration
        $oConfig->Save();
    }
}

// For direct theme configuration, create application.ini content
// Use the same data path as defined in APP_DATA_FOLDER_PATH
$dataPath = defined('APP_DATA_FOLDER_PATH') ? APP_DATA_FOLDER_PATH : __DIR__ . '/data/';

// Set admin password from environment or use secure default
$adminPassword = $_ENV['SNAPPYMAIL_ADMIN_PASSWORD'] ?? 'admin';
$adminPasswordHash = password_hash($adminPassword, PASSWORD_DEFAULT);

$applicationIniContent = <<<INI
[webmail]
theme = "ForwardEmail"
title = "ForwardEmail Webmail"
loading_description = "ForwardEmail - Privacy-focused email hosting"
allow_themes = Off

[interface]
show_attachment_thumbnail = On

[cache]
; Disable all file-based caching for maximum privacy
enable = Off
server_uids = Off
system_data = Off
http = On

[contacts]
; Disable local contacts storage
enable = Off

[security]
admin_login = "admin"
admin_password = "$adminPasswordHash"
INI;

// Write to data directory if it exists and is writable
if (is_dir($dataPath) && is_writable($dataPath)) {
    file_put_contents($dataPath . 'application.ini', $applicationIniContent);
}
?>