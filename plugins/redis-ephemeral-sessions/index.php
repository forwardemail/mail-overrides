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
		VERSION = '1.0.0',
		RELEASE = '2025-10-27',
		REQUIRED = '2.36.0',
		CATEGORY = 'Session',
		DESCRIPTION = 'Secure ephemeral session storage using Redis with client-side encryption';

	/**
	 * Initialize plugin
	 */
	public function Init() : void
	{
		// Register autoloader for plugin classes
		spl_autoload_register(function($sClassName) {
			if (strpos($sClassName, 'RedisEphemeralSessions\\') === 0) {
				$file = __DIR__ . DIRECTORY_SEPARATOR . str_replace('RedisEphemeralSessions\\', '', $sClassName) . '.php';
				if (is_file($file)) {
					require_once $file;
				}
			}
		});

		// Check if Predis is available
		if (!class_exists('Predis\Client')) {
			$this->attemptPredisAutoload();
		}

		if (!class_exists('Predis\Client')) {
			if ($this->Manager()->Actions()->Logger()) {
				$this->Manager()->Actions()->Logger()->Write(
					'Redis Ephemeral Sessions: Predis library not found, plugin disabled',
					\LOG_ERR,
					'REDIS-SESSION'
				);
			}
			return;
		}

		// Add client-side JavaScript
		$this->addJs('assets/session.js');

		// Register hooks
		$this->addHook('login.success', 'OnLoginSuccess');
		$this->addHook('filter.app-data', 'FilterAppData');

		// Register JSON API endpoints
		$this->addJsonHook('RedisSessionCreate', 'DoCreateSession');
		$this->addJsonHook('RedisSessionGet', 'DoGetSession');
		$this->addJsonHook('RedisSessionDelete', 'DoDeleteSession');
		$this->addJsonHook('RedisSessionRefresh', 'DoRefreshSession');
		$this->addJsonHook('RedisSessionStatus', 'DoSessionStatus');
		$this->addJsonHook('RedisTestConnection', 'DoTestConnection');
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
			'password' => $this->Config()->getDecrypted('plugin', 'password', ''),
			'ttl_seconds' => (int) $this->Config()->Get('plugin', 'ttl_seconds', 14400),
			'key_mask_secret' => $this->Config()->getDecrypted('plugin', 'key_mask_secret', '')
		];
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
	public function DoCreateSession() : array
	{
		try {
			$this->initRedisHelper();

			$alias = $this->jsonParam('alias', '');
			$ciphertext = $this->jsonParam('ciphertext', '');
			$iv = $this->jsonParam('iv', '');
			$salt = $this->jsonParam('salt', '');
			$meta = $this->jsonParam('meta', []);

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

			// Store in Redis
			$success = \RedisEphemeralSessions\RedisHelper::setSession($alias, $blob);

			if (!$success) {
				throw new \Exception('Failed to store session in Redis');
			}

			return $this->jsonResponse(__FUNCTION__, [
				'success' => true,
				'ttl' => (int) $this->Config()->Get('plugin', 'ttl_seconds', 14400)
			]);

		} catch (\Throwable $e) {
			$this->Manager()->Actions()->Logger()->Write(
				'Redis Session Create Error: ' . $e->getMessage(),
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
	public function DoGetSession() : array
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
	public function DoDeleteSession() : array
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
	public function DoRefreshSession() : array
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
	public function DoSessionStatus() : array
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
	public function DoTestConnection() : array
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
