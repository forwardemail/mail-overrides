<?php
/**
 * Forward Email Branding Plugin
 * Overrides login template with Forward Email branding and styling
 * Auto-configures CardDAV sync on first login
 */

class ForwardemailPlugin extends \RainLoop\Plugins\AbstractPlugin
{
	const
		NAME     = 'Forward Email',
		AUTHOR   = 'Forward Email LLC',
		URL      = 'https://forwardemail.net/',
		VERSION  = '1.1.0',
		RELEASE  = '2025-11-08',
		REQUIRED = '2.33.0',
		CATEGORY = 'General',
		LICENSE  = 'BUSL-1.1',
		DESCRIPTION = 'Forward Email custom branding and CardDAV auto-configuration';

	public function Init() : void
	{
		// Log that plugin is being initialized
		$oLogger = $this->Manager()->Actions()->Logger();
		if ($oLogger) {
			$oLogger->Write(
				'Forward Email Plugin: Initializing plugin version ' . self::VERSION,
				\LOG_INFO,
				'PLUGIN'
			);
		}

		// Add custom login template
		$this->addTemplate('templates/Views/User/Login.html');

		// Hook into login.success to auto-configure CardDAV
		$this->addHook('login.success', 'OnLoginSuccess');

		// Hook into json.action-pre-call to log test attempts
		$this->addHook('json.action-pre-call', 'OnJsonActionPreCall');

		if ($oLogger) {
			$oLogger->Write(
				'Forward Email Plugin: Hooked into login.success and json.action-pre-call',
				\LOG_INFO,
				'PLUGIN'
			);
		}
	}

	/**
	 * Configuration mapping for admin panel
	 */
	protected function configMapping() : array
	{
		return [
			\RainLoop\Plugins\Property::NewInstance('enable_carddav_auto_setup')
				->SetLabel('Enable CardDAV Auto-Setup')
				->SetType(\RainLoop\Enumerations\PluginPropertyType::BOOL)
				->SetDescription('Automatically configure CardDAV sync on first login')
				->SetDefaultValue(true),

			\RainLoop\Plugins\Property::NewInstance('carddav_url')
				->SetLabel('CardDAV URL Template')
				->SetType(\RainLoop\Enumerations\PluginPropertyType::STRING)
				->SetDescription('Use placeholders like {email}, {local}, {domain}, e.g. https://carddav.forwardemail.net/dav/{email}/addressbooks/default/')
				->SetDefaultValue('https://carddav.forwardemail.net/dav/{email}/addressbooks/default/'),

			\RainLoop\Plugins\Property::NewInstance('auto_enable_contacts_autosave')
				->SetLabel('Auto-Enable Contact Autosave')
				->SetType(\RainLoop\Enumerations\PluginPropertyType::BOOL)
				->SetDescription('Automatically enable "save recipients to contacts" feature')
				->SetDefaultValue(true)
		];
	}

	/**
	 * Called before JSON actions to log CardDAV test attempts
	 */
	public function OnJsonActionPreCall(string $sAction)
	{
		if ($sAction === 'TestContactsSyncData') {
			$oActions = $this->Manager()->Actions();
			$oLogger = $oActions->Logger();

			if ($oLogger) {
				$oLogger->Write(
					'Forward Email Plugin: TestContactsSyncData called',
					\LOG_INFO,
					'PLUGIN'
				);

				// Log what's stored
				try {
					$oAccount = $oActions->getAccountFromToken();
					$aStoredData = $this->getContactsSyncData($oAccount);

					if ($aStoredData) {
						$oLogger->Write(
							'Forward Email Plugin: Stored CardDAV data - User: ' . ($aStoredData['User'] ?? 'N/A') . ', URL: ' . ($aStoredData['Url'] ?? 'N/A') . ', Has Password: ' . (isset($aStoredData['Password']) ? 'YES (encrypted JSON)' : 'NO') . ', Has HMAC: ' . (isset($aStoredData['PasswordHMAC']) ? 'YES' : 'NO'),
							\LOG_INFO,
							'PLUGIN'
						);
					} else {
						$oLogger->Write(
							'Forward Email Plugin: No stored CardDAV data found',
							\LOG_WARNING,
							'PLUGIN'
						);
					}
				} catch (\Throwable $e) {
					$oLogger->Write(
						'Forward Email Plugin: Error retrieving stored data - ' . $e->getMessage(),
						\LOG_WARNING,
						'PLUGIN'
					);
				}
			}
		}
	}

	/**
	 * Hook: Called after successful login
	 * Automatically configures CardDAV sync if not already set up
	 *
	 * @param \RainLoop\Model\Account $oAccount
	 */
	public function OnLoginSuccess(\RainLoop\Model\Account $oAccount) : void
	{
		$oActions = $this->Manager()->Actions();
		$oLogger = $oActions->Logger();

		// Log plugin execution start
		if ($oLogger) {
			$oLogger->Write(
				'Forward Email Plugin: OnLoginSuccess triggered for ' . $oAccount->Email(),
				\LOG_INFO,
				'PLUGIN'
			);
		}

		// Check if auto-setup is enabled
		$bCardDAVEnabled = $this->Config()->Get('plugin', 'enable_carddav_auto_setup', true);
		if ($oLogger) {
			$oLogger->Write(
				'Forward Email Plugin: CardDAV auto-setup enabled: ' . ($bCardDAVEnabled ? 'YES' : 'NO'),
				\LOG_INFO,
				'PLUGIN'
			);
		}
		if (!$bCardDAVEnabled) {
			return;
		}

		// Check if contacts sync is enabled globally
		$bSyncAllowed = $oActions->Config()->Get('contacts', 'allow_sync', false);
		if ($oLogger) {
			$oLogger->Write(
				'Forward Email Plugin: Global contacts sync allowed: ' . ($bSyncAllowed ? 'YES' : 'NO'),
				\LOG_INFO,
				'PLUGIN'
			);
		}
		if (!$bSyncAllowed) {
			return;
		}

		try {
			// Check if CardDAV is already configured
			$aExistingSync = $this->getContactsSyncData($oAccount);

			if ($oLogger) {
				$oLogger->Write(
					'Forward Email Plugin: Existing CardDAV config: ' . ($aExistingSync ? 'FOUND (URL: ' . ($aExistingSync['Url'] ?? 'none') . ')' : 'NOT FOUND'),
					\LOG_INFO,
					'PLUGIN'
				);
			}

			if (!$aExistingSync || empty($aExistingSync['Url'])) {
				// CardDAV not configured - auto-configure it now
				if ($oLogger) {
					$oLogger->Write(
						'Forward Email Plugin: Attempting to auto-configure CardDAV',
						\LOG_INFO,
						'PLUGIN'
					);
				}
				$this->autoConfigureCardDAV($oAccount);
			}

			// Enable contacts autosave if configured
			if ($this->Config()->Get('plugin', 'auto_enable_contacts_autosave', true)) {
				$this->ensureContactsAutosave($oAccount);
			}

			$this->ensureSecurityDefaults($oAccount);
			$this->ensureCheckMailInterval($oAccount);
			$this->ensureNotificationDefaults($oAccount);
		} catch (\Throwable $oException) {
			// Log error but don't fail login
			if ($oLogger) {
				$oLogger->Write(
					'Forward Email Plugin: Error - ' . $oException->getMessage() . ' in ' . $oException->getFile() . ':' . $oException->getLine(),
					\LOG_WARNING,
					'PLUGIN'
				);
			}
		}
	}

	/**
	 * Auto-configure CardDAV for the user
	 *
	 * @param \RainLoop\Model\Account $oAccount
	 * @throws \Exception
	 */
	private function autoConfigureCardDAV(\RainLoop\Model\Account $oAccount) : void
	{
		$oActions = $this->Manager()->Actions();
		$oLogger = $oActions->Logger();

		// Get the user's email and password from the account
		// Note: ImapPass() returns a string (calls getValue() on the SensitiveString internally)
		$sEmail = $oAccount->Email();
		$sPassword = $oAccount->ImapPass();

		if ($oLogger) {
			$oLogger->Write(
				'Forward Email Plugin: Configuring CardDAV for ' . $sEmail,
				\LOG_INFO,
				'PLUGIN'
			);
		}

		if (empty($sPassword)) {
			throw new \Exception('Cannot access user password for CardDAV setup - password is empty');
		}

		// Get CardDAV URL from config
		$sCardDAVUrl = $this->buildCardDavUrl($sEmail);

		if ($oLogger) {
			$oLogger->Write(
				'Forward Email Plugin: Using CardDAV URL: ' . $sCardDAVUrl,
				\LOG_INFO,
				'PLUGIN'
			);
		}

		// Prepare CardDAV sync data
		$aData = [
			'Mode' => 1, // Read/write mode
			'User' => $sEmail,
			'Password' => $sPassword,
			'Url' => $sCardDAVUrl
		];

		if ($oLogger) {
			$oLogger->Write(
				'Forward Email Plugin: CardDAV data prepared - User: ' . $sEmail . ', URL: ' . $sCardDAVUrl . ', Password length: ' . \strlen($sPassword),
				\LOG_INFO,
				'PLUGIN'
			);
		}

		// Save CardDAV configuration
		$bResult = $this->setContactsSyncData($oAccount, $aData);

		if ($oLogger) {
			$oLogger->Write(
				'Forward Email Plugin: CardDAV configuration ' . ($bResult ? 'SUCCEEDED' : 'FAILED') . ' for ' . $sEmail,
				$bResult ? \LOG_INFO : \LOG_WARNING,
				'PLUGIN'
			);
		}
	}

	/**
	 * Get CardDAV sync data for an account
	 *
	 * @param \RainLoop\Model\Account $oAccount
	 * @return array|null
	 */
	private function getContactsSyncData(\RainLoop\Model\Account $oAccount) : ?array
	{
		$oActions = $this->Manager()->Actions();
		$sData = $oActions->StorageProvider()->Get(
			$oAccount,
			\RainLoop\Providers\Storage\Enumerations\StorageType::CONFIG,
			'contacts_sync'
		);

		if (!empty($sData)) {
			$aData = \json_decode($sData, true);
			return $aData ?: null;
		}

		return null;
	}

	/**
	 * Set CardDAV sync data for an account
	 *
	 * @param \RainLoop\Model\Account $oAccount
	 * @param array $aData
	 * @return bool
	 */
	private function setContactsSyncData(\RainLoop\Model\Account $oAccount, array $aData) : bool
	{
		$oActions = $this->Manager()->Actions();
		$oMainAccount = $oActions->getMainAccountFromToken();
		$oLogger = $oActions->Logger();

		if ($oLogger) {
			$oLogger->Write(
				'Forward Email Plugin: setContactsSyncData - User: ' . ($aData['User'] ?? 'N/A') . ', URL: ' . ($aData['Url'] ?? 'N/A') . ', Password length (plain): ' . (isset($aData['Password']) ? \strlen($aData['Password']) : 0) . ', CryptKey length: ' . \strlen($oMainAccount->CryptKey()),
				\LOG_INFO,
				'PLUGIN'
			);
		}

		// Encrypt password
		if (!empty($aData['Password'])) {
			$sPlainPassword = $aData['Password'];
			$aData['Password'] = \SnappyMail\Crypt::EncryptToJSON($aData['Password'], $oMainAccount->CryptKey());
			$aData['PasswordHMAC'] = \hash_hmac('sha1', $aData['Password'], $oMainAccount->CryptKey());

			if ($oLogger) {
				$oLogger->Write(
					'Forward Email Plugin: Password encrypted - HMAC: ' . \substr($aData['PasswordHMAC'], 0, 10) . '..., Encrypted length: ' . \strlen($aData['Password']),
					\LOG_INFO,
					'PLUGIN'
				);
			}
		}

		$bResult = $oActions->StorageProvider()->Put(
			$oAccount,
			\RainLoop\Providers\Storage\Enumerations\StorageType::CONFIG,
			'contacts_sync',
			\json_encode($aData)
		);

		if ($oLogger) {
			$oLogger->Write(
				'Forward Email Plugin: Storage PUT ' . ($bResult ? 'SUCCEEDED' : 'FAILED'),
				$bResult ? \LOG_INFO : \LOG_WARNING,
				'PLUGIN'
			);
		}

		return $bResult;
	}

	/**
	 * Ensure contacts autosave is enabled for the account
	 *
	 * @param \RainLoop\Model\Account $oAccount
	 */
	private function ensureContactsAutosave(\RainLoop\Model\Account $oAccount) : void
	{
		try {
			$oActions = $this->Manager()->Actions();
			$oSettings = $oActions->SettingsProvider()->Load($oAccount);

			if ($oSettings) {
				$bCurrentValue = $oSettings->GetConf('ContactsAutosave', false);

				if (!$bCurrentValue) {
					$oSettings->SetConf('ContactsAutosave', true);
					$oSettings->save();
				}
			}
		} catch (\Throwable $oException) {
			// Silently fail - this is not critical
		}
	}

	/**
	 * Ensure security-related defaults (auto logout & key passphrase cache)
	 * Always enforces these values to override SnappyMail hardcoded defaults
	 */
	private function ensureSecurityDefaults(\RainLoop\Model\Account $oAccount) : void
	{
		try {
			$oActions = $this->Manager()->Actions();
			$oSettings = $oActions->SettingsProvider()->Load($oAccount);
			if (!$oSettings) {
				return;
			}

			$bChanged = false;

			// Get default values from application.ini
			$oConfig = $oActions->Config();
			$iDefaultAutoLogout = (int) $oConfig->Get('defaults', 'autologout', 0);
			$iDefaultKeyPassForget = (int) $oConfig->Get('defaults', 'key_pass_forget', 0);

			$oLogger = $oActions->Logger();

			// Check and update AutoLogout
			// NOTE: GetConf will return stored value or null if not set, so we check explicitly
			$currentAutoLogout = $oSettings->GetConf('AutoLogout', null);
			if ($currentAutoLogout === null || (int)$currentAutoLogout !== $iDefaultAutoLogout) {
				$oSettings->SetConf('AutoLogout', $iDefaultAutoLogout);
				$bChanged = true;
				if ($oLogger) {
					$oLogger->Write(
						'Forward Email Plugin: Set AutoLogout from ' . ($currentAutoLogout ?? 'unset') . ' to ' . $iDefaultAutoLogout,
						\LOG_INFO,
						'PLUGIN'
					);
				}
			}

			// Check and update keyPassForget
			// This is especially important because SnappyMail hardcodes this to 15 in Actions.php:611
			$currentKeyPassForget = $oSettings->GetConf('keyPassForget', null);
			if ($currentKeyPassForget === null || (int)$currentKeyPassForget !== $iDefaultKeyPassForget) {
				$oSettings->SetConf('keyPassForget', $iDefaultKeyPassForget);
				$bChanged = true;
				if ($oLogger) {
					$oLogger->Write(
						'Forward Email Plugin: Set keyPassForget from ' . ($currentKeyPassForget ?? 'unset') . ' to ' . $iDefaultKeyPassForget,
						\LOG_INFO,
						'PLUGIN'
					);
				}
			}

			if ($bChanged) {
				$bSaved = $oSettings->save();
				if ($oLogger) {
					$oLogger->Write(
						'Forward Email Plugin: Security defaults save ' . ($bSaved ? 'SUCCEEDED' : 'FAILED'),
						$bSaved ? \LOG_INFO : \LOG_WARNING,
						'PLUGIN'
					);
				}
			}
		} catch (\Throwable $oException) {
			// Do not interrupt login flow
			if ($oActions && $oActions->Logger()) {
				$oActions->Logger()->Write(
					'Forward Email Plugin: ensureSecurityDefaults error - ' . $oException->getMessage(),
					\LOG_WARNING,
					'PLUGIN'
				);
			}
		}
	}

	/**
	 * Ensure check mail interval is set to default_refresh_interval from config
	 * This overrides SnappyMail's hardcoded default of 15 minutes
	 */
	private function ensureCheckMailInterval(\RainLoop\Model\Account $oAccount) : void
	{
		try {
			$oActions = $this->Manager()->Actions();
			$oConfig = $oActions->Config();

			// Get the default refresh interval from application.ini
			// This is in webmail.default_refresh_interval (in minutes)
			$iDefaultInterval = (int) $oConfig->Get('webmail', 'default_refresh_interval', 1);
			$iMinInterval = (int) $oConfig->Get('webmail', 'min_refresh_interval', 1);

			// Ensure we respect the minimum
			$iDesiredInterval = \max($iDefaultInterval, $iMinInterval);

			// Load the account-specific settings (not global settings)
			$oSettings = $oActions->SettingsProvider(true)->Load($oAccount);
			if (!$oSettings) {
				return;
			}

			$oLogger = $oActions->Logger();

			// Check current value
			$iCurrentInterval = (int) $oSettings->GetConf('CheckMailInterval', null);

			// Only update if not set or different from desired
			if ($iCurrentInterval === null || $iCurrentInterval !== $iDesiredInterval) {
				$oSettings->SetConf('CheckMailInterval', $iDesiredInterval);
				$bSaved = $oSettings->save();

				if ($oLogger) {
					$oLogger->Write(
						'Forward Email Plugin: Set CheckMailInterval from ' . ($iCurrentInterval ?? 'unset') . ' to ' . $iDesiredInterval . ' minutes (' . ($bSaved ? 'SUCCEEDED' : 'FAILED') . ')',
						$bSaved ? \LOG_INFO : \LOG_WARNING,
						'PLUGIN'
					);
				}
			}
		} catch (\Throwable $oException) {
			// Do not interrupt login flow
			if ($oActions && $oActions->Logger()) {
				$oActions->Logger()->Write(
					'Forward Email Plugin: ensureCheckMailInterval error - ' . $oException->getMessage(),
					\LOG_WARNING,
					'PLUGIN'
				);
			}
		}
	}

	/**
	 * Ensure the SnappyMail SoundNotification toggle starts disabled so users opt-in
	 */
	private function ensureNotificationDefaults(\RainLoop\Model\Account $oAccount) : void
	{
		try {
			$oActions = $this->Manager()->Actions();
			$oSettings = $oActions->SettingsProvider()->Load($oAccount);
			if (!$oSettings) {
				return;
			}

			$mCurrentSound = $oSettings->GetConf('SoundNotification', null);
			if ($mCurrentSound === null) {
				$oSettings->SetConf('SoundNotification', false);
				$bSaved = $oSettings->save();

				$oLogger = $oActions->Logger();
				if ($oLogger) {
					$oLogger->Write(
						'Forward Email Plugin: Initialized SoundNotification to Off for ' . $oAccount->Email() . ' (' . ($bSaved ? 'saved' : 'failed to save') . ')',
						$bSaved ? \LOG_INFO : \LOG_WARNING,
						'PLUGIN'
					);
				}
			}
		} catch (\Throwable $oException) {
			$oActions = $this->Manager()->Actions();
			if ($oActions && $oActions->Logger()) {
				$oActions->Logger()->Write(
					'Forward Email Plugin: ensureNotificationDefaults error - ' . $oException->getMessage(),
					\LOG_WARNING,
					'PLUGIN'
				);
			}
		}
	}

	/**
	 * Build the CardDAV URL using the configured template.
	 */
	private function buildCardDavUrl(string $sEmail) : string
	{
		$sTemplate = $this->Config()->Get('plugin', 'carddav_url', 'https://carddav.forwardemail.net/dav/{email}/addressbooks/default/');
		$sEmailLower = \trim($sEmail);
		$aParts = \explode('@', $sEmailLower, 2);
		$sLocal = $aParts[0] ?? $sEmailLower;
		$sDomain = $aParts[1] ?? '';

		return \strtr($sTemplate, [
			'{email}' => $sEmailLower,
			'{local}' => $sLocal,
			'{domain}' => $sDomain,
		]);
	}
}
