<?php

/**
 * Redis Ephemeral Sessions Plugin for SnappyMail
 *
 * Provides secure, ephemeral session storage using Redis with client-side encryption.
 * - Credentials encrypted in browser with WebCrypto (AES-GCM)
 * - Encrypted blobs stored in Redis with automatic TTL expiry
 * - Ephemeral secrets stored only in browser sessionStorage
 * - Zero plaintext credentials on disk
 *
 * @category  Session
 * @package   RedisEphemeralSessions
 * @author    Forward Email <support@forwardemail.net>
 * @license   MIT
 */

class RedisEphemeralSessionsPlugin extends \RainLoop\Plugins\AbstractPlugin
{
	const
		NAME = 'Redis Ephemeral Sessions',
		VERSION = '1.0.1',
		RELEASE = '2025-11-11',
		REQUIRED = '2.36.0',
		CATEGORY = 'Session',
		DESCRIPTION = 'Secure ephemeral session storage using Redis with client-side encryption';

	/**
	 * Initialize plugin
	 */
	public function Init() : void
	{
		error_log('[RedisEphemeralSessions] Init() called');

		// Log to SnappyMail logger too
		if ($this->Manager() && $this->Manager()->Actions() && $this->Manager()->Actions()->Logger()) {
			$this->Manager()->Actions()->Logger()->Write(
				'RedisEphemeralSessions plugin Init() starting',
				\LOG_INFO,
				'REDIS-SESSION'
			);
		}

		// Register autoloader for plugin classes
		spl_autoload_register(function($sClassName) {
			if (strpos($sClassName, 'RedisEphemeralSessions\\') === 0) {
				$file = __DIR__ . DIRECTORY_SEPARATOR . str_replace('RedisEphemeralSessions\\', '', $sClassName) . '.php';
				if (is_file($file)) {
					require_once $file;
				}
			}
		});

		error_log('[RedisEphemeralSessions] Checking for Predis...');

		// Check if Predis is available
		if (!class_exists('Predis\Client')) {
			error_log('[RedisEphemeralSessions] Predis not found, attempting autoload');
			$this->attemptPredisAutoload();
		}

		if (!class_exists('Predis\Client')) {
			error_log('[RedisEphemeralSessions] Predis still not found after autoload attempt');
			if ($this->Manager()->Actions()->Logger()) {
				$this->Manager()->Actions()->Logger()->Write(
					'Redis Ephemeral Sessions: Predis library not found, plugin disabled',
					\LOG_ERR,
					'REDIS-SESSION'
				);
			}
			return;
		}

		error_log('[RedisEphemeralSessions] Predis found! Registering hooks...');

		// Add client-side JavaScript
		$this->addJs('assets/session.js');

		// Register hooks
		$this->addHook('login.success', 'OnLoginSuccess');
		$this->addHook('filter.app-data', 'FilterAppData');

		// Register JSON API endpoints
		// JavaScript sends "PluginRedisSessionCreate" which SnappyMail converts to "DoPluginRedisSessionCreate"
		$this->addJsonHook('RedisSessionCreate', 'DoRedisSessionCreate');
		$this->addJsonHook('RedisSessionGet', 'DoRedisSessionGet');
		$this->addJsonHook('RedisSessionDelete', 'DoRedisSessionDelete');
		$this->addJsonHook('RedisSessionRefresh', 'DoRedisSessionRefresh');
		$this->addJsonHook('RedisSessionStatus', 'DoRedisSessionStatus');
		$this->addJsonHook('RedisTestConnection', 'DoRedisTestConnection');

		error_log('[RedisEphemeralSessions] Init() complete, hooks registered');
	}

	/**
	 * Attempt to require the Composer autoloader so Predis becomes available.
	 */
	private function attemptPredisAutoload() : void
	{
		$paths = [
			APP_INDEX_ROOT_PATH . 'vendor/autoload.php',
			APP_VERSION_ROOT_PATH . 'vendor/autoload.php',
			dirname(APP_VERSION_ROOT_PATH) . '/vendor/autoload.php'
		];

		foreach ($paths as $path) {
			if (\is_readable($path)) {
				require_once $path;
				if (\class_exists('Predis\\Client')) {
					return;
				}
			}
		}
	}

	/**
	 * Check if plugin is supported
	 *
	 * @return string Empty if supported, error message otherwise
	 */
	public function Supported() : string
	{
		if (!class_exists('Predis\Client')) {
			return 'Predis library not found. Install via: composer require predis/predis';
		}

		if (!extension_loaded('json')) {
			return 'JSON extension required';
		}

		return '';
	}

	/**
	 * Configuration mapping for admin panel
	 *
	 * @return array Configuration properties
	 */
	protected function configMapping() : array
	{
		return [
			\RainLoop\Plugins\Property::NewInstance('host')
				->SetLabel('Redis Host')
				->SetDescription('Hostname or IP address of the Redis server')
				->SetDefaultValue('127.0.0.1'),

			\RainLoop\Plugins\Property::NewInstance('port')
				->SetLabel('Redis Port')
				->SetType(\RainLoop\Enumerations\PluginPropertyType::INT)
				->SetDescription('Port number of the Redis server')
				->SetDefaultValue(6379),

			\RainLoop\Plugins\Property::NewInstance('use_tls')
				->SetLabel('Use TLS')
				->SetType(\RainLoop\Enumerations\PluginPropertyType::BOOL)
				->SetDescription('Enable TLS/SSL encryption for Redis connection')
				->SetDefaultValue(false),

			\RainLoop\Plugins\Property::NewInstance('password')
				->SetLabel('Redis Password')
				->SetType(\RainLoop\Enumerations\PluginPropertyType::PASSWORD)
				->SetDescription('Redis authentication password (optional)')
				->SetDefaultValue('')
				->SetEncrypted(true),

			\RainLoop\Plugins\Property::NewInstance('ttl_seconds')
				->SetLabel('Session TTL (seconds)')
				->SetType(\RainLoop\Enumerations\PluginPropertyType::INT)
				->SetDescription('Time-to-live for session data in Redis (default: 14400 = 4 hours)')
				->SetDefaultValue(14400),

			\RainLoop\Plugins\Property::NewInstance('key_mask_secret')
				->SetLabel('Key Mask Secret')
				->SetType(\RainLoop\Enumerations\PluginPropertyType::STRING_TEXT)
				->SetDescription('Base64-encoded 32-byte secret for Redis key HMAC (generate with: openssl rand -base64 32)')
				->SetDefaultValue('')
				->SetEncrypted(true)
		];
	}

	/**
	 * Get plugin configuration as array
	 *
	 * @return array Configuration values
	 */
	private function getConfig() : array
	{
		return [
			'host' => $this->Config()->Get('plugin', 'host', '127.0.0.1'),
			'port' => (int) $this->Config()->Get('plugin', 'port', 6379),
			'use_tls' => (bool) $this->Config()->Get('plugin', 'use_tls', false),
			'password' => $this->getSecretValue('password'),
			'ttl_seconds' => (int) $this->Config()->Get('plugin', 'ttl_seconds', 14400),
			'key_mask_secret' => $this->getSecretValue('key_mask_secret')
		];
	}

	/**
	 * Retrieve encrypted config values with fallback for plain strings
	 */
	private function getSecretValue(string $name) : string
	{
		$decrypted = $this->Config()->getDecrypted('plugin', $name, '');
		if (!empty($decrypted)) {
			return $decrypted;
		}

		$raw = $this->Config()->Get('plugin', $name, '');
		$trimmed = \ltrim((string) $raw);

		// Allow plain secrets when seeding config files (non-JSON value)
		if (!empty($trimmed) && \strlen($trimmed) && \substr($trimmed, 0, 1) !== '[') {
			return $trimmed;
		}

		return '';
	}

	/**
	 * Initialize RedisHelper with current config
	 */
	private function initRedisHelper() : void
	{
		\RedisEphemeralSessions\RedisHelper::setConfig($this->getConfig());
	}

	/**
	 * Hook: Successful login event
	 *
	 * @param \RainLoop\Model\MainAccount $oAccount User account
	 */
	public function OnLoginSuccess(\RainLoop\Model\MainAccount $oAccount) : void
	{
		// Log successful login (session creation happens client-side)
		$this->Manager()->Actions()->Logger()->Write(
			'Redis Ephemeral Session: Login success for ' . $oAccount->Email(),
			\LOG_INFO,
			'REDIS-SESSION'
		);
	}

	/**
	 * Hook: Filter application data sent to client
	 *
	 * @param bool $bAdmin Is admin context
	 * @param array $aAppData Application data array
	 */
	public function FilterAppData(bool $bAdmin, array &$aAppData) : void
	{
		if (!$bAdmin && is_array($aAppData)) {
			// Add plugin configuration to client
			$aAppData['RedisEphemeralSessions'] = [
				'enabled' => true,
				'ttl' => (int) $this->Config()->Get('plugin', 'ttl_seconds', 14400)
			];
		}
	}

	/**
	 * JSON API: Create encrypted session in Redis
	 *
	 * @return array JSON response
	 */
	public function DoRedisSessionCreate() : array
	{
		$this->Manager()->Actions()->Logger()->Write(
			'DoCreateSession: Method called',
			\LOG_INFO,
			'REDIS-SESSION'
		);

		try {
			$this->Manager()->Actions()->Logger()->Write(
				'DoCreateSession: Initializing Redis helper',
				\LOG_INFO,
				'REDIS-SESSION'
			);
			$this->initRedisHelper();

			$alias = $this->jsonParam('alias', '');
			$ciphertext = $this->jsonParam('ciphertext', '');
			$iv = $this->jsonParam('iv', '');
			$salt = $this->jsonParam('salt', '');
			$meta = $this->jsonParam('meta', []);

			$this->Manager()->Actions()->Logger()->Write(
				'DoCreateSession: Parameters - alias=' . $alias . ', ciphertext_len=' . strlen($ciphertext),
				\LOG_INFO,
				'REDIS-SESSION'
			);

			// Validate required parameters
			if (empty($alias) || empty($ciphertext) || empty($iv) || empty($salt)) {
				throw new \Exception('Missing required parameters');
			}

			// Prepare session blob
			$blob = [
				'ciphertext' => $ciphertext,
				'iv' => $iv,
				'salt' => $salt,
				'meta' => is_array($meta) ? $meta : [],
				'timestamp' => time()
			];

			$this->Manager()->Actions()->Logger()->Write(
				'DoCreateSession: Calling RedisHelper::setSession',
				\LOG_INFO,
				'REDIS-SESSION'
			);

			// Store in Redis
			$success = \RedisEphemeralSessions\RedisHelper::setSession($alias, $blob);

			$this->Manager()->Actions()->Logger()->Write(
				'DoCreateSession: RedisHelper::setSession returned ' . ($success ? 'true' : 'false'),
				\LOG_INFO,
				'REDIS-SESSION'
			);

			if (!$success) {
				throw new \Exception('Failed to store session in Redis');
			}

			return $this->jsonResponse(__FUNCTION__, [
				'success' => true,
				'ttl' => (int) $this->Config()->Get('plugin', 'ttl_seconds', 14400)
			]);

		} catch (\Throwable $e) {
			$this->Manager()->Actions()->Logger()->Write(
				'Redis Session Create Error: ' . $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine(),
				\LOG_ERR,
				'REDIS-SESSION'
			);

			return $this->jsonResponse(__FUNCTION__, [
				'success' => false,
				'error' => $e->getMessage()
			]);
		}
	}

	/**
	 * JSON API: Get encrypted session from Redis
	 *
	 * @return array JSON response
	 */
	public function DoRedisSessionGet() : array
	{
		try {
			$this->initRedisHelper();

			$alias = $this->jsonParam('alias', '');

			if (empty($alias)) {
				throw new \Exception('Missing alias parameter');
			}

			$session = \RedisEphemeralSessions\RedisHelper::getSession($alias);

			if ($session === null) {
				return $this->jsonResponse(__FUNCTION__, [
					'success' => false,
					'error' => 'Session not found or expired'
				]);
			}

			return $this->jsonResponse(__FUNCTION__, [
				'success' => true,
				'session' => $session
			]);

		} catch (\Throwable $e) {
			$this->Manager()->Actions()->Logger()->Write(
				'Redis Session Get Error: ' . $e->getMessage(),
				\LOG_ERR,
				'REDIS-SESSION'
			);

			return $this->jsonResponse(__FUNCTION__, [
				'success' => false,
				'error' => $e->getMessage()
			]);
		}
	}

	/**
	 * JSON API: Delete session from Redis
	 *
	 * @return array JSON response
	 */
	public function DoRedisSessionDelete() : array
	{
		try {
			$this->initRedisHelper();

			$alias = $this->jsonParam('alias', '');

			if (empty($alias)) {
				throw new \Exception('Missing alias parameter');
			}

			$success = \RedisEphemeralSessions\RedisHelper::deleteSession($alias);

			return $this->jsonResponse(__FUNCTION__, [
				'success' => $success
			]);

		} catch (\Throwable $e) {
			$this->Manager()->Actions()->Logger()->Write(
				'Redis Session Delete Error: ' . $e->getMessage(),
				\LOG_ERR,
				'REDIS-SESSION'
			);

			return $this->jsonResponse(__FUNCTION__, [
				'success' => false,
				'error' => $e->getMessage()
			]);
		}
	}

	/**
	 * JSON API: Refresh session TTL in Redis
	 *
	 * @return array JSON response
	 */
	public function DoRedisSessionRefresh() : array
	{
		try {
			$this->initRedisHelper();

			$alias = $this->jsonParam('alias', '');

			if (empty($alias)) {
				throw new \Exception('Missing alias parameter');
			}

			$success = \RedisEphemeralSessions\RedisHelper::refreshSession($alias);

			return $this->jsonResponse(__FUNCTION__, [
				'success' => $success,
				'ttl' => (int) $this->Config()->Get('plugin', 'ttl_seconds', 14400)
			]);

		} catch (\Throwable $e) {
			return $this->jsonResponse(__FUNCTION__, [
				'success' => false,
				'error' => $e->getMessage()
			]);
		}
	}

	/**
	 * JSON API: Get session status and TTL
	 *
	 * @return array JSON response
	 */
	public function DoRedisSessionStatus() : array
	{
		try {
			$this->initRedisHelper();

			$alias = $this->jsonParam('alias', '');

			if (empty($alias)) {
				throw new \Exception('Missing alias parameter');
			}

			$ttl = \RedisEphemeralSessions\RedisHelper::getSessionTTL($alias);

			if ($ttl === null) {
				return $this->jsonResponse(__FUNCTION__, [
					'success' => false,
					'exists' => false,
					'message' => 'Session not found or expired'
				]);
			}

			return $this->jsonResponse(__FUNCTION__, [
				'success' => true,
				'exists' => true,
				'ttl_remaining' => $ttl
			]);

		} catch (\Throwable $e) {
			return $this->jsonResponse(__FUNCTION__, [
				'success' => false,
				'error' => $e->getMessage()
			]);
		}
	}

	/**
	 * JSON API: Test Redis connection
	 *
	 * @return array JSON response
	 */
	public function DoRedisTestConnection() : array
	{
		try {
			$this->initRedisHelper();

			$success = \RedisEphemeralSessions\RedisHelper::testConnection();

			return $this->jsonResponse(__FUNCTION__, [
				'success' => $success,
				'message' => $success ? 'Redis connection successful' : 'Redis connection failed'
			]);

		} catch (\Throwable $e) {
			return $this->jsonResponse(__FUNCTION__, [
				'success' => false,
				'error' => $e->getMessage()
			]);
		}
	}
}
