/**
 * Redis Ephemeral Sessions - Client-Side Encryption
 *
 * Provides WebCrypto-based encryption/decryption for session credentials.
 * - Generates ephemeral secrets per session
 * - Encrypts credentials with AES-GCM (256-bit)
 * - Uses PBKDF2 for key derivation
 * - Stores ephemeral secret only in sessionStorage
 *
 * @author Forward Email <support@forwardemail.net>
 * @license MIT
 */

(function () {
	'use strict';

	const STORAGE_KEY = 'snappymail_ephemeral_secret';
	const PBKDF2_ITERATIONS = 100000;

	/**
	 * Redis Ephemeral Session Manager
	 */
	class RedisEphemeralSession {
		constructor() {
			this.secret = null;
			this.initSecret();
		}

		getAppToken() {
			try {
				return (window.rl && window.rl.settings && window.rl.settings.app
					? window.rl.settings.app('token')
					: '') || '';
			} catch (error) {
				console.warn('[RedisEphemeralSession] Failed to read app token', error);
				return '';
			}
		}

		async jsonRequest(action, payload = {}) {
			const token = this.getAppToken();
			const response = await fetch('?/Json/&q[]=/0/', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-Client-Token': token
				},
				body: JSON.stringify({
					Action: `Plugin${action}`,
					XToken: token,
					...payload
				})
			});

			return response.json();
		}

		/**
		 * Initialize or retrieve ephemeral secret from sessionStorage
		 */
		initSecret() {
			// Try to get existing secret from sessionStorage
			this.secret = sessionStorage.getItem(STORAGE_KEY);

			// Generate new secret if none exists
			if (!this.secret) {
				this.secret = crypto.randomUUID();
				sessionStorage.setItem(STORAGE_KEY, this.secret);
				console.log('[RedisEphemeralSession] Generated new ephemeral secret');
			} else {
				console.log('[RedisEphemeralSession] Using existing ephemeral secret');
			}
		}

		/**
		 * Get the current ephemeral secret
		 *
		 * @returns {string} Ephemeral secret
		 */
		getSecret() {
			if (!this.secret) {
				this.initSecret();
			}
			return this.secret;
		}

		/**
		 * Clear ephemeral secret (on logout)
		 */
		clearSecret() {
			sessionStorage.removeItem(STORAGE_KEY);
			this.secret = null;
			console.log('[RedisEphemeralSession] Cleared ephemeral secret');
		}

		/**
		 * Derive encryption key from secret and salt using PBKDF2
		 *
		 * @param {string} secret - Ephemeral secret
		 * @param {Uint8Array} salt - Salt for key derivation
		 * @returns {Promise<CryptoKey>} Derived AES-GCM key
		 */
		async deriveKey(secret, salt) {
			const encoder = new TextEncoder();
			const keyMaterial = await crypto.subtle.importKey(
				'raw',
				encoder.encode(secret),
				'PBKDF2',
				false,
				['deriveKey']
			);

			return crypto.subtle.deriveKey(
				{
					name: 'PBKDF2',
					salt: salt,
					iterations: PBKDF2_ITERATIONS,
					hash: 'SHA-256'
				},
				keyMaterial,
				{ name: 'AES-GCM', length: 256 },
				false,
				['encrypt', 'decrypt']
			);
		}

		/**
		 * Encrypt payload with AES-GCM
		 *
		 * @param {string} secret - Ephemeral secret
		 * @param {Object} payload - Data to encrypt (will be JSON stringified)
		 * @returns {Promise<Object>} Encrypted data {ciphertext, iv, salt}
		 */
		async encryptPayload(secret, payload) {
			try {
				// Generate random salt and IV
				const salt = crypto.getRandomValues(new Uint8Array(16));
				const iv = crypto.getRandomValues(new Uint8Array(12));

				// Derive encryption key
				const key = await this.deriveKey(secret, salt);

				// Encode payload as JSON
				const encoder = new TextEncoder();
				const encoded = encoder.encode(JSON.stringify(payload));

				// Encrypt with AES-GCM
				const ciphertext = await crypto.subtle.encrypt(
					{ name: 'AES-GCM', iv: iv },
					key,
					encoded
				);

				// Convert to base64 for transport
				return {
					ciphertext: this.arrayBufferToBase64(ciphertext),
					iv: this.arrayBufferToBase64(iv),
					salt: this.arrayBufferToBase64(salt)
				};
			} catch (error) {
				console.error('[RedisEphemeralSession] Encryption error:', error);
				throw new Error('Failed to encrypt payload: ' + error.message);
			}
		}

		/**
		 * Decrypt payload with AES-GCM
		 *
		 * @param {string} secret - Ephemeral secret
		 * @param {string} ciphertext - Base64 encoded ciphertext
		 * @param {string} iv - Base64 encoded IV
		 * @param {string} salt - Base64 encoded salt
		 * @returns {Promise<Object>} Decrypted payload object
		 */
		async decryptPayload(secret, ciphertext, iv, salt) {
			try {
				// Convert from base64
				const ciphertextBuffer = this.base64ToArrayBuffer(ciphertext);
				const ivBuffer = this.base64ToArrayBuffer(iv);
				const saltBuffer = this.base64ToArrayBuffer(salt);

				// Derive decryption key
				const key = await this.deriveKey(secret, saltBuffer);

				// Decrypt with AES-GCM
				const decrypted = await crypto.subtle.decrypt(
					{ name: 'AES-GCM', iv: ivBuffer },
					key,
					ciphertextBuffer
				);

				// Decode JSON
				const decoder = new TextDecoder();
				const json = decoder.decode(decrypted);
				return JSON.parse(json);
			} catch (error) {
				console.error('[RedisEphemeralSession] Decryption error:', error);
				throw new Error('Failed to decrypt payload: ' + error.message);
			}
		}

		/**
		 * Store encrypted session in Redis via API
		 *
		 * @param {string} alias - User email/alias
		 * @param {string} password - User password
		 * @param {Object} meta - Optional metadata
		 * @returns {Promise<Object>} API response
		 */
		async storeSession(alias, password, meta = {}) {
			try {
				const secret = this.getSecret();

				// Prepare payload
				const payload = {
					alias: alias,
					password: password,
					meta: {
						userAgent: navigator.userAgent,
						timestamp: Date.now(),
						...meta
					}
				};

				// Encrypt payload
				const encrypted = await this.encryptPayload(secret, payload);

				// Send to server
				const result = await this.jsonRequest('RedisSessionCreate', {
					alias: alias,
					ciphertext: encrypted.ciphertext,
					iv: encrypted.iv,
					salt: encrypted.salt,
					meta: payload.meta
				});

				if (result && result.Result && result.Result.success) {
					console.log('[RedisEphemeralSession] Session stored successfully');
					return result.Result;
				} else {
					throw new Error(result.Result?.error || 'Failed to store session');
				}
			} catch (error) {
				console.error('[RedisEphemeralSession] Store session error:', error);
				throw error;
			}
		}

		/**
		 * Retrieve and decrypt session from Redis
		 *
		 * @param {string} alias - User email/alias
		 * @returns {Promise<Object>} Decrypted credentials
		 */
		async retrieveSession(alias) {
			try {
				const secret = this.getSecret();

				// Fetch from server
				const result = await this.jsonRequest('RedisSessionGet', {
					alias: alias
				});

				if (!result || !result.Result || !result.Result.success) {
					throw new Error(result.Result?.error || 'Session not found');
				}

				const session = result.Result.session;

				// Decrypt payload
				const decrypted = await this.decryptPayload(
					secret,
					session.ciphertext,
					session.iv,
					session.salt
				);

				console.log('[RedisEphemeralSession] Session retrieved and decrypted');
				return decrypted;
			} catch (error) {
				console.error('[RedisEphemeralSession] Retrieve session error:', error);
				throw error;
			}
		}

		/**
		 * Delete session from Redis
		 *
		 * @param {string} alias - User email/alias
		 * @returns {Promise<boolean>} Success status
		 */
		async deleteSession(alias) {
			try {
				const result = await this.jsonRequest('RedisSessionDelete', {
					alias: alias
				});

				if (result && result.Result && result.Result.success) {
					console.log('[RedisEphemeralSession] Session deleted');
					return true;
				}
				return false;
			} catch (error) {
				console.error('[RedisEphemeralSession] Delete session error:', error);
				return false;
			}
		}

		/**
		 * Refresh session TTL in Redis
		 *
		 * @param {string} alias - User email/alias
		 * @returns {Promise<boolean>} Success status
		 */
		async refreshSession(alias) {
			try {
				const result = await this.jsonRequest('RedisSessionRefresh', {
					alias: alias
				});

				if (result && result.Result && result.Result.success) {
					console.log('[RedisEphemeralSession] Session refreshed');
					return true;
				}
				return false;
			} catch (error) {
				console.error('[RedisEphemeralSession] Refresh session error:', error);
				return false;
			}
		}

		/**
		 * Get session status from Redis
		 *
		 * @param {string} alias - User email/alias
		 * @returns {Promise<Object>} Session status
		 */
		async getSessionStatus(alias) {
			try {
				const result = await this.jsonRequest('RedisSessionStatus', {
					alias: alias
				});
				return result.Result || {};
			} catch (error) {
				console.error('[RedisEphemeralSession] Get status error:', error);
				return { success: false, error: error.message };
			}
		}

		/**
		 * Convert ArrayBuffer to Base64
		 *
		 * @param {ArrayBuffer} buffer
		 * @returns {string} Base64 string
		 */
		arrayBufferToBase64(buffer) {
			const bytes = new Uint8Array(buffer);
			let binary = '';
			for (let i = 0; i < bytes.length; i++) {
				binary += String.fromCharCode(bytes[i]);
			}
			return btoa(binary);
		}

		/**
		 * Convert Base64 to ArrayBuffer
		 *
		 * @param {string} base64
		 * @returns {Uint8Array} Array buffer
		 */
		base64ToArrayBuffer(base64) {
			const binary = atob(base64);
			const bytes = new Uint8Array(binary.length);
			for (let i = 0; i < binary.length; i++) {
				bytes[i] = binary.charCodeAt(i);
			}
			return bytes;
		}
	}

	// Initialize global instance
	window.RedisEphemeralSession = new RedisEphemeralSession();

	// Export for usage
	if (typeof module !== 'undefined' && module.exports) {
		module.exports = RedisEphemeralSession;
	}

	console.log('[RedisEphemeralSession] Module loaded');

	// Track credentials captured during login flow
	let pendingCredentials = null;

	addEventListener('sm-user-login', event => {
		try {
			const data = event?.detail;
			if (!(data instanceof FormData)) {
				pendingCredentials = null;
				return;
			}

			const email = (data.get('Email') || '').trim();
			const password = data.get('Password') || '';

			if (email && password) {
				pendingCredentials = {
					email,
					password,
					signMe: data.get('signMe') === '1'
				};
			} else {
				pendingCredentials = null;
			}
		} catch (error) {
			console.error('[RedisEphemeralSession] Failed to capture login credentials:', error);
			pendingCredentials = null;
		}
	});

	addEventListener('sm-user-login-response', async event => {
		try {
			const detail = event?.detail || {};

			// Clear stored credentials if login failed
			if (detail.error) {
				pendingCredentials = null;
				return;
			}

			if (!pendingCredentials) {
				return;
			}

			const result = detail.data?.Result || {};
			const alias =
				(result.AuthEmail || result.Email || result.Account?.Email || '').trim() ||
				pendingCredentials.email;

			if (!alias) {
				pendingCredentials = null;
				return;
			}

			const meta = {
				signMe: pendingCredentials.signMe,
				ip: result.ClientIp || '',
				userAgent: navigator.userAgent
			};

			await window.RedisEphemeralSession.storeSession(alias, pendingCredentials.password, meta);
		} catch (error) {
			console.error('[RedisEphemeralSession] Failed to store Redis session:', error);
		} finally {
			pendingCredentials = null;
		}
	});

	addEventListener('beforeunload', () => {
		window.RedisEphemeralSession.clearSecret();
	});
})();
