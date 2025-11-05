#!/usr/bin/env php
<?php

/**
 * Generate Key Mask Secret for Redis Ephemeral Sessions Plugin
 *
 * This script generates a secure random 32-byte secret encoded in base64
 * for use as the key_mask_secret configuration value.
 *
 * Usage:
 *   php generate-secret.php
 *
 * Or use OpenSSL directly:
 *   openssl rand -base64 32
 */

echo "\n";
echo "====================================================\n";
echo "Redis Ephemeral Sessions - Secret Generator\n";
echo "====================================================\n";
echo "\n";

try {
	// Generate 32 random bytes
	$randomBytes = random_bytes(32);

	// Encode as base64
	$secret = base64_encode($randomBytes);

	echo "Generated Key Mask Secret:\n";
	echo "\n";
	echo $secret . "\n";
	echo "\n";
	echo "====================================================\n";
	echo "\n";
	echo "Instructions:\n";
	echo "1. Copy the secret above\n";
	echo "2. Open SnappyMail admin panel\n";
	echo "3. Navigate to Plugins > Redis Ephemeral Sessions\n";
	echo "4. Paste the secret into 'Key Mask Secret' field\n";
	echo "5. Configure other Redis settings (host, port, etc.)\n";
	echo "6. Save and enable the plugin\n";
	echo "\n";
	echo "IMPORTANT:\n";
	echo "- Keep this secret secure and private\n";
	echo "- Do not commit it to version control\n";
	echo "- Changing this secret will invalidate all existing sessions\n";
	echo "\n";

} catch (Exception $e) {
	echo "Error generating secret: " . $e->getMessage() . "\n";
	exit(1);
}
