<?php
/**
 * Login Pre-Population Plugin
 * Automatically pre-fills login username from URL parameter
 *
 * Usage: https://mail.forwardemail.net?login=user@domain.com
 */

class LoginPrepopulatePlugin extends \RainLoop\Plugins\AbstractPlugin
{
	const
		NAME     = 'Login Pre-Populate',
		AUTHOR   = 'Forward Email LLC',
		URL      = 'https://forwardemail.net/',
		VERSION  = '1.0.0',
		RELEASE  = '2025-11-06',
		REQUIRED = '2.33.0',
		CATEGORY = 'Login',
		LICENSE  = 'BUSL-1.1',
		DESCRIPTION = 'Pre-populates login username from URL parameter';

	public function Init() : void
	{
		// Hook into the login screen to inject JavaScript
		$this->addHook('main.fabrica', 'MainFabrica');
		$this->addJs('js/login-prepopulate.js');
	}

	/**
	 * @param string $sName
	 * @param mixed $oDriver
	 */
	public function MainFabrica(string $sName, &$oDriver)
	{
		// No backend changes needed - all handled in JS
	}
}
