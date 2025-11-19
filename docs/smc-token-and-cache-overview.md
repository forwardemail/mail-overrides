# smctoken and `smc*` Cookie Overview

## smctoken Lifecycle
- `mail/dev/boot.js:29` seeds `smctoken` from the existing cookie, falls back to `localStorage`, and, if both are missing, generates a 16-byte random value so the token survives across sessions even when cookies are cleared but local storage persists.
- That same block writes the token back into both storages (`localStorage.setItem`) and issues a non-expiring, SameSite=Strict cookie (`doc.cookie = 'smctoken='…`) so browsers send it on subsequent requests while keeping it first-party (`mail/dev/boot.js:29`).
- Server-side cryptography relies on this value: when no explicit key is provided, `SnappyMail\Crypt::Passphrase` derives the encryption secret from `smctoken + APP_VERSION`, hashing it with `APP_SALT`; if the cookie vanished the server re-issues a fresh one but will no longer decrypt legacy secure cookies (`mail/snappymail/v/0.0.0/app/libraries/snappymail/crypt.php:44`, `mail/snappymail/v/0.0.0/app/libraries/snappymail/crypt.php:53`).
- Because `smctoken` drives `Cookies::setSecure`, losing it invalidates `smaccount` / `smadditional`; persisting the same value in `localStorage` avoids unnecessary logouts after browser restarts (`mail/snappymail/v/0.0.0/app/libraries/RainLoop/Actions/UserAuth.php:331`).
- OAuth plugins note they can fold `localStorage.getItem('smctoken')` into their state to bind third-party flows to the same browser identity (`mail/plugins/login-gmail/LoginOAuth2.js:18`, `mail/plugins/login-o365/LoginOAuth2.js:26`).

## Authentication Cookies
- `smaccount` stores the encrypted main account payload (email, logins, secrets) via `Cookies::setSecure`, which encrypts with the `smctoken`-derived key and flags the cookie HttpOnly by default (`mail/snappymail/v/0.0.0/app/libraries/RainLoop/Actions.php:34`, `mail/snappymail/v/0.0.0/app/libraries/RainLoop/Actions/UserAuth.php:331`, `mail/snappymail/v/0.0.0/app/libraries/snappymail/Cookies.php:116`). Large values are transparently chunked into `smaccount~1`, `~2`, etc. (`mail/snappymail/v/0.0.0/app/libraries/snappymail/Cookies.php:129`).
- `smadditional` mirrors that workflow for an optional secondary account, again encrypted with the same device-specific key (`mail/snappymail/v/0.0.0/app/libraries/RainLoop/Actions.php:40`, `mail/snappymail/v/0.0.0/app/libraries/RainLoop/Actions/UserAuth.php:337`).
- `smremember` is the 30-day “remember me” cookie: it embeds the address, a UUID, and encrypted credentials, and is cross-checked against server-side storage before allowing silent login (`mail/snappymail/v/0.0.0/app/libraries/RainLoop/Actions.php:28`, `mail/snappymail/v/0.0.0/app/libraries/RainLoop/Actions/UserAuth.php:368`).
- `smadmin` carries the admin session token, encoded with `Utils::EncodeKeyValuesQ` and guarded by the regular session token; cache invalidation clears both client cookie and backend entry on logout (`mail/snappymail/v/0.0.0/app/libraries/RainLoop/ActionsAdmin.php:380`, `mail/snappymail/v/0.0.0/app/libraries/RainLoop/Actions/Admin.php:32`).

## Session & CSRF Cookies
- `smsession` is the short-lived session cookie. When missing, `Utils::GetSessionToken` generates a new random token, stores the raw value in the cookie, and returns a hashed variant that the server uses for lookups; losing it triggers full re-authentication (`mail/snappymail/v/0.0.0/app/libraries/RainLoop/Utils.php:55`).
- `smtoken` is the 30-day connection token. Unauthenticated users get a random value, while logged-in users receive deterministic hashes of their account ID; it backs CSRF protection, the `System.token` exposed to the UI, and audit logging (`mail/snappymail/v/0.0.0/app/libraries/RainLoop/Utils.php:12`, `mail/snappymail/v/0.0.0/app/libraries/RainLoop/Utils.php:69`).
- Every JSON/API call must echo that token as `X-SM-Token` (or `XToken` in POST bodies); mismatches produce an immediate 401 and are written to the logs (`mail/snappymail/v/0.0.0/app/libraries/RainLoop/ServiceActions.php:97`, `mail/snappymail/v/0.0.0/app/libraries/RainLoop/Actions.php:565`).

## Auxiliary Cookies
- `smmailtoauth` is set when a `mailto:` handler redirects into SnappyMail. It stores encoded payload (including a timestamp) with a key derived from the session token so only the originating session can recover it (`mail/snappymail/v/0.0.0/app/libraries/RainLoop/Actions.php:20`, `mail/snappymail/v/0.0.0/app/libraries/RainLoop/ServiceActions.php:515`, `mail/snappymail/v/0.0.0/app/libraries/RainLoop/Utils.php:39`).
- During logout, SnappyMail explicitly clears `smaccount`, `smadditional`, and the remember-me cookie, but leaves `smctoken` alone so a new login can reuse the same encryption key (`mail/snappymail/v/0.0.0/app/libraries/RainLoop/Actions/UserAuth.php:436`).

## Cache Configuration
- Forward Email overrides keep caching enabled and point the cache path to `/dev/shm/snappymail-cache/` for tmpfs-backed writes, while also defining both `index` and `fast_cache_index` for easy namespace invalidation and enabling HTTP plus server UID caching (`configs/application.ini:135`, `configs/application.ini:142`, `configs/application.ini:148`).
- SnappyMail builds a `MailSo\Cache\CacheClient` per account (or a shared default) and injects either a plugin-provided driver or the configured file path; updating `fast_cache_index` forces the client to roll to a new namespace (`mail/snappymail/v/0.0.0/app/libraries/RainLoop/Actions.php:473`).
- Template bootstrapping, language packs, and compiled CSS/JS use that cache when `cache.system_data` stays On; the HTTP layer combines `cache.enable`, `cache.http`, and `cache.index` to decide whether to emit ETag/Expires headers (`mail/snappymail/v/0.0.0/app/libraries/RainLoop/Service.php:186`, `mail/snappymail/v/0.0.0/app/libraries/RainLoop/ServiceActions.php:396`, `mail/snappymail/v/0.0.0/app/libraries/RainLoop/Actions.php:885`).
- Message-list operations wire the same cache client into the IMAP layer when `cache.server_uids` is enabled, improving search/threading performance while remaining configurable per deployment (`mail/snappymail/v/0.0.0/app/libraries/RainLoop/Actions/Messages.php:88`).

### Cache Index Settings

The `[cache]` section in `application.ini` uses two index keys to manage cache invalidation:

- **`index`** - Main application cache key. Controls caching of:
  - Compiled HTML templates
  - Language/translation files
  - Configuration data
  - Plugin metadata
  - Static asset references

- **`fast_cache_index`** - Fast-access cache key. Controls caching of:
  - Session data
  - User preferences
  - IMAP folder lists
  - Message UID mappings for search/threading

Changing these values (e.g., `v1` → `v2`) forces SnappyMail to treat all existing cached data as stale and rebuild from source files.

### Clearing the Cache

After deploying changes to templates, CSS, JS, or configuration, you may need to clear the cache for changes to take effect:

```bash
# 1. Clear SnappyMail's file cache
rm -rf /dev/shm/snappymail-cache/*

# 2. Restart PHP-FPM to clear OPcache
sudo systemctl restart php8.2-fpm
```

Alternatively, force a complete cache rebuild by bumping the index values in `/var/www/snappymail/dist/data/_data_/_default_/configs/application.ini`:

```ini
[cache]
index = "v2"           ; was "v1"
fast_cache_index = "v2" ; was "v1"
```

### Browser Cache

The `http_expires` setting (default: 3600 seconds) controls browser-level caching. Users may need to:
- Hard refresh (Cmd+Shift+R / Ctrl+Shift+R)
- Clear browser cache
- Use incognito/private window to bypass cached assets

## User Data Storage

SnappyMail stores user data in multiple locations. Understanding these is important to avoid accidental data loss.

### Browser localStorage (Client-side)

Stored in the user's browser and will be lost if they clear site data:

**PGP Keys:**
- `openpgp-public-keys` - JSON array of armored public keys
- `openpgp-private-keys` - JSON array of armored private keys

**Authentication:**
- `smctoken` - Device encryption token used to derive encryption keys for cookies

**Client Settings:**
- `rlcsc` - Client-side storage index containing UI preferences

### Server-side Storage

**Application cache** (safe to clear after deployments):
- `/dev/shm/snappymail-cache/` - Compiled templates, language files, asset references

**User data** (do NOT clear - contains persistent user settings):
- `/var/www/snappymail/dist/data/_data_/_default_/storage/` - Per-user settings, contacts, filters

### Important Distinction

| Location | Contents | Safe to Clear? |
|----------|----------|----------------|
| `/dev/shm/snappymail-cache/` | Application cache | Yes - rebuilds automatically |
| Browser localStorage | PGP keys, smctoken | No - loses user PGP keys |
| Server `/data/` directory | User settings, contacts | No - loses user data |

The ansible deployment clears `/dev/shm/snappymail-cache/` which is safe and won't affect user PGP keys or settings stored in browser localStorage or server-side storage.

## Additional Notes
- All cookies except `smctoken` are HttpOnly and respect the configured SameSite/secure flags inherited through `SnappyMail\Cookies::set`; `smctoken` is intentionally script-visible to make the encryption key recoverable on the client (`mail/snappymail/v/0.0.0/app/libraries/snappymail/crypt.php:53`, `mail/snappymail/v/0.0.0/app/libraries/snappymail/Cookies.php:102`).
- Changing `APP_VERSION` automatically hardens `smctoken`-derived secrets because the passphrase concatenates the version string, forcing re-encryption after upgrades (`mail/snappymail/v/0.0.0/app/libraries/snappymail/crypt.php:56`).
