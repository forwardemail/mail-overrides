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
		// Add custom login template
		$this->addTemplate('templates/Views/User/Login.html');

		// Hook into login.success to auto-configure CardDAV
		$this->addHook('login.success', 'OnLoginSuccess');
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
				->SetLabel('CardDAV URL')
				->SetType(\RainLoop\Enumerations\PluginPropertyType::STRING)
				->SetDescription('CardDAV server URL')
				->SetDefaultValue('https://carddav.forwardemail.net'),

			\RainLoop\Plugins\Property::NewInstance('auto_enable_contacts_autosave')
				->SetLabel('Auto-Enable Contact Autosave')
				->SetType(\RainLoop\Enumerations\PluginPropertyType::BOOL)
				->SetDescription('Automatically enable "save recipients to contacts" feature')
				->SetDefaultValue(true)
		];
	}

	/**
	 * Hook: Called after successful login
	 * Automatically configures CardDAV sync if not already set up
	 *
	 * @param \RainLoop\Model\Account $oAccount
	 */
	public function OnLoginSuccess(\RainLoop\Model\Account $oAccount) : void
	{
		// Check if auto-setup is enabled
		if (!$this->Config()->Get('plugin', 'enable_carddav_auto_setup', true)) {
			return;
		}

		// Check if contacts sync is enabled globally
		$oActions = $this->Manager()->Actions();
		if (!$oActions->Config()->Get('contacts', 'allow_sync', false)) {
			return;
		}

		try {
			// Check if CardDAV is already configured
			$aExistingSync = $this->getContactsSyncData($oAccount);

			if (!$aExistingSync || empty($aExistingSync['Url'])) {
				// CardDAV not configured - auto-configure it now
				$this->autoConfigureCardDAV($oAccount);
			}

			// Enable contacts autosave if configured
			if ($this->Config()->Get('plugin', 'auto_enable_contacts_autosave', true)) {
				$this->ensureContactsAutosave($oAccount);
			}
		} catch (\Throwable $oException) {
			// Log error but don't fail login
			if ($oActions->Logger()) {
				$oActions->Logger()->Write(
					'Forward Email Plugin: Failed to auto-configure CardDAV: ' . $oException->getMessage(),
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

		// Get the user's email and password from the account
		$sEmail = $oAccount->Email();
		$sPassword = $oAccount->ImapPass();

		if (!$sPassword || !($sPassword instanceof \SnappyMail\SensitiveString)) {
			throw new \Exception('Cannot access user password for CardDAV setup');
		}

		// Get CardDAV URL from config
		$sCardDAVUrl = $this->Config()->Get('plugin', 'carddav_url', 'https://carddav.forwardemail.net');

		// Prepare CardDAV sync data
		$aData = [
			'Mode' => 1, // Read/write mode
			'User' => $sEmail,
			'Password' => $sPassword->getValue(),
			'Url' => $sCardDAVUrl
		];

		// Save CardDAV configuration
		$bResult = $this->setContactsSyncData($oAccount, $aData);

		if ($bResult && $oActions->Logger()) {
			$oActions->Logger()->Write(
				'Forward Email Plugin: CardDAV auto-configured for ' . $sEmail,
				\LOG_INFO,
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

		// Encrypt password
		if (!empty($aData['Password'])) {
			$aData['Password'] = \SnappyMail\Crypt::EncryptToJSON($aData['Password'], $oMainAccount->CryptKey());
			$aData['PasswordHMAC'] = \hash_hmac('sha1', $aData['Password'], $oMainAccount->CryptKey());
		}

		return $oActions->StorageProvider()->Put(
			$oAccount,
			\RainLoop\Providers\Storage\Enumerations\StorageType::CONFIG,
			'contacts_sync',
			\json_encode($aData)
		);
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
}
