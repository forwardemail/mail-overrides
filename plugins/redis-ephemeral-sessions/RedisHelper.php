<?php

namespace RedisEphemeralSessions;

/**
 * Redis Helper Class
 *
 * Manages Redis connection and session storage operations
 * Uses Predis client for Redis operations with automatic TTL management
 */
class RedisHelper
{
	/**
	 * @var \Predis\Client|null
	 */
	private static $client = null;

	/**
	 * @var array Plugin configuration
	 */
	private static $config = null;

	/**
	 * Initialize configuration
	 *
	 * @param array $config Plugin configuration array
	 */
	public static function setConfig(array $config) : void
	{
		self::$config = $config;
	}

	/**
	 * Get or create Redis connection
	 *
	 * @return \Predis\Client|null
	 * @throws \Exception if connection fails
	 */
	private static function connect() : ?\Predis\Client
	{
		if (self::$client !== null) {
			return self::$client;
		}

		if (self::$config === null) {
			throw new \Exception('Redis configuration not set');
		}

		try {
			$host = self::$config['host'] ?? '127.0.0.1';
			$port = (int) (self::$config['port'] ?? 6379);
			$useTls = (bool) (self::$config['use_tls'] ?? false);
			$password = self::$config['password'] ?? '';

			// Build connection parameters
			$params = [
				'scheme' => $useTls ? 'tls' : 'tcp',
				'host' => $host,
				'port' => $port
			];

			// Add password if provided
			if (!empty($password)) {
				$params['password'] = $password;
			}

			self::$client = new \Predis\Client($params);
			self::$client->connect();

			if (!self::$client->isConnected()) {
				self::$client = null;
				throw new \Exception('Redis connection failed');
			}

			return self::$client;
		} catch (\Throwable $e) {
			self::$client = null;
			throw new \Exception('Redis connection error: ' . $e->getMessage());
		}
	}

	/**
	 * Generate Redis key from alias using HMAC
	 *
	 * @param string $alias User email/alias
	 * @return string Redis key
	 */
	private static function makeKey(string $alias) : string
	{
		$secret = self::$config['key_mask_secret'] ?? '';

		if (empty($secret)) {
			throw new \Exception('key_mask_secret not configured');
		}

		// Decode base64 secret
		$decodedSecret = base64_decode($secret);
		if ($decodedSecret === false) {
			throw new \Exception('Invalid key_mask_secret format');
		}

		// Normalize alias to lowercase for consistent key generation
		$normalizedAlias = strtolower(trim($alias));

		// Generate HMAC-SHA256 hash
		$hash = hash_hmac('sha256', $normalizedAlias, $decodedSecret);

		return "snappymail:v1:session:{$hash}";
	}

	/**
	 * Store encrypted session data in Redis
	 *
	 * @param string $alias User email/alias
	 * @param array $blob Encrypted session data containing ciphertext, iv, salt, etc.
	 * @return bool Success status
	 */
	public static function setSession(string $alias, array $blob) : bool
	{
		try {
			$redis = self::connect();
			if ($redis === null) {
				return false;
			}

			$key = self::makeKey($alias);
			$ttl = (int) (self::$config['ttl_seconds'] ?? 14400);

			// Add server timestamp
			$blob['server_timestamp'] = time();

			// Store as JSON with TTL
			$result = $redis->setex($key, $ttl, json_encode($blob));

			return $result === true || $result == 'OK';
		} catch (\Throwable $e) {
			error_log('RedisHelper::setSession error: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Retrieve encrypted session data from Redis
	 *
	 * @param string $alias User email/alias
	 * @return array|null Session data or null if not found
	 */
	public static function getSession(string $alias) : ?array
	{
		try {
			$redis = self::connect();
			if ($redis === null) {
				return null;
			}

			$key = self::makeKey($alias);
			$data = $redis->get($key);

			if ($data === null || $data === false) {
				return null;
			}

			$decoded = json_decode($data, true);
			return is_array($decoded) ? $decoded : null;
		} catch (\Throwable $e) {
			error_log('RedisHelper::getSession error: ' . $e->getMessage());
			return null;
		}
	}

	/**
	 * Delete session data from Redis
	 *
	 * @param string $alias User email/alias
	 * @return bool Success status
	 */
	public static function deleteSession(string $alias) : bool
	{
		try {
			$redis = self::connect();
			if ($redis === null) {
				return false;
			}

			$key = self::makeKey($alias);
			$result = $redis->del($key);

			return $result > 0;
		} catch (\Throwable $e) {
			error_log('RedisHelper::deleteSession error: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Get remaining TTL for a session
	 *
	 * @param string $alias User email/alias
	 * @return int|null TTL in seconds or null if key doesn't exist
	 */
	public static function getSessionTTL(string $alias) : ?int
	{
		try {
			$redis = self::connect();
			if ($redis === null) {
				return null;
			}

			$key = self::makeKey($alias);
			$ttl = $redis->ttl($key);

			// -2 means key doesn't exist, -1 means no expiry set
			return ($ttl >= 0) ? $ttl : null;
		} catch (\Throwable $e) {
			error_log('RedisHelper::getSessionTTL error: ' . $e->getMessage());
			return null;
		}
	}

	/**
	 * Refresh/extend session TTL
	 *
	 * @param string $alias User email/alias
	 * @return bool Success status
	 */
	public static function refreshSession(string $alias) : bool
	{
		try {
			$redis = self::connect();
			if ($redis === null) {
				return false;
			}

			$key = self::makeKey($alias);
			$ttl = (int) (self::$config['ttl_seconds'] ?? 14400);

			$result = $redis->expire($key, $ttl);

			return $result === 1 || $result === true;
		} catch (\Throwable $e) {
			error_log('RedisHelper::refreshSession error: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Test Redis connection
	 *
	 * @return bool True if connection is successful
	 */
	public static function testConnection() : bool
	{
		try {
			$redis = self::connect();
			if ($redis === null) {
				return false;
			}

			$result = $redis->ping();
			return $result === 'PONG' || $result === true;
		} catch (\Throwable $e) {
			error_log('RedisHelper::testConnection error: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Close Redis connection
	 */
	public static function disconnect() : void
	{
		if (self::$client !== null) {
			try {
				self::$client->disconnect();
			} catch (\Throwable $e) {
				// Ignore disconnect errors
			}
			self::$client = null;
		}
	}
}
