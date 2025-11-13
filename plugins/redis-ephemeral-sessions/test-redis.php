<?php
/**
 * Test script to verify Redis connection and Predis availability
 */

echo "=== Redis Connection Test ===\n\n";

// Check if running in SnappyMail context
if (!defined('APP_INDEX_ROOT_PATH')) {
    echo "Not in SnappyMail context, defining paths...\n";
    define('APP_INDEX_ROOT_PATH', '/var/www/html/');
    define('APP_VERSION_ROOT_PATH', '/var/www/html/snappymail/v/0.0.0/');
}

// Try to load Predis
$predisFound = false;
$paths = [
    APP_INDEX_ROOT_PATH . 'vendor/autoload.php',
    APP_VERSION_ROOT_PATH . 'vendor/autoload.php',
    dirname(APP_VERSION_ROOT_PATH) . '/vendor/autoload.php'
];

echo "Checking for Predis autoloader:\n";
foreach ($paths as $path) {
    echo "  - $path: ";
    if (is_readable($path)) {
        echo "EXISTS\n";
        require_once $path;
        if (class_exists('Predis\Client')) {
            echo "    ✓ Predis\\Client loaded!\n";
            $predisFound = true;
            break;
        }
    } else {
        echo "NOT FOUND\n";
    }
}

if (!$predisFound) {
    echo "\n✗ ERROR: Predis library not found!\n";
    exit(1);
}

// Load plugin config
$configPath = __DIR__ . '/../../configs/plugin-redis-ephemeral-sessions.json';
echo "\nLoading config from: $configPath\n";

if (!file_exists($configPath)) {
    echo "✗ ERROR: Config file not found!\n";
    exit(1);
}

$configData = json_decode(file_get_contents($configPath), true);
if (!$configData || !isset($configData['plugin'])) {
    echo "✗ ERROR: Invalid config format!\n";
    exit(1);
}

$config = $configData['plugin'];
echo "Config loaded:\n";
echo "  - Host: {$config['host']}\n";
echo "  - Port: {$config['port']}\n";
echo "  - Use TLS: " . ($config['use_tls'] ? 'Yes' : 'No') . "\n";
echo "  - TTL: {$config['ttl_seconds']}s\n";

// Test Redis connection
echo "\nTesting Redis connection...\n";

try {
    $redis = new \Predis\Client([
        'scheme' => $config['use_tls'] ? 'tls' : 'tcp',
        'host' => $config['host'],
        'port' => $config['port']
    ]);

    $redis->connect();

    if (!$redis->isConnected()) {
        echo "✗ ERROR: Redis not connected!\n";
        exit(1);
    }

    echo "✓ Connected to Redis\n";

    // Test PING
    $pong = $redis->ping();
    echo "✓ PING response: " . json_encode($pong) . "\n";

    // Test SET/GET
    $testKey = 'snappymail:test:' . time();
    $testValue = json_encode(['test' => 'data', 'timestamp' => time()]);

    $redis->setex($testKey, 60, $testValue);
    echo "✓ SET test key: $testKey\n";

    $retrieved = $redis->get($testKey);
    echo "✓ GET test key: " . ($retrieved === $testValue ? 'SUCCESS' : 'FAILED') . "\n";

    $redis->del($testKey);
    echo "✓ DEL test key\n";

    echo "\n=== ALL TESTS PASSED ===\n";

} catch (\Throwable $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
