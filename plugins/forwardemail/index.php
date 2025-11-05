<?php
/**
 * Forward Email Branding Plugin
 * Overrides login template with Forward Email branding and styling
 */

class ForwardemailPlugin extends \RainLoop\Plugins\AbstractPlugin
{
	const
		NAME     = 'Forward Email',
		AUTHOR   = 'Forward Email LLC',
		URL      = 'https://forwardemail.net/',
		VERSION  = '1.0.0',
		RELEASE  = '2025-11-01',
		REQUIRED = '2.33.0',
		CATEGORY = 'General',
		LICENSE  = 'BUSL-1.1',
		DESCRIPTION = 'Forward Email custom branding for login page';

	public function Init() : void
	{
		// Add custom login template
		$this->addTemplate('templates/Views/User/Login.html');
	}
}
