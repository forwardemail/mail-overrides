/**
 * Login Pre-Population JavaScript
 * Pre-fills the login username field from URL parameter
 *
 * Usage: https://mail.forwardemail.net?login=user@domain.com
 */

(function () {
	'use strict';

	// Get URL parameters
	function getUrlParameter(name) {
		name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
		var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
		var results = regex.exec(location.search);
		return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
	}

	// Wait for DOM to be ready
	function waitForLoginForm() {
		// Try multiple selectors to find the email input field
		var loginInput = document.getElementById('fe-email') ||
		                 document.getElementById('login_user') ||
		                 document.querySelector('input[name="Email"]') ||
		                 document.querySelector('input[name="email"]');

		if (loginInput) {
			var loginParam = getUrlParameter('login');

			if (loginParam) {
				// Validate email format to prevent XSS
				var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
				if (emailRegex.test(loginParam)) {
					loginInput.value = loginParam;

					// Trigger Knockout.js binding updates if present
					if (typeof ko !== 'undefined' && ko.dataFor) {
						var context = ko.dataFor(loginInput);
						if (context && context.email) {
							context.email(loginParam);
						}
					}

					loginInput.focus();

					// Try to trigger any change events for non-Knockout scenarios
					if (typeof Event === 'function') {
						loginInput.dispatchEvent(new Event('input', { bubbles: true }));
						loginInput.dispatchEvent(new Event('change', { bubbles: true }));
					}
				}
			}
		} else {
			// Retry after a short delay if login form not ready
			setTimeout(waitForLoginForm, 100);
		}
	}

	// Initialize when DOM is ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', waitForLoginForm);
	} else {
		waitForLoginForm();
	}
})();
