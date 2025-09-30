# ForwardEmail SnappyMail Configuration

This document outlines the ForwardEmail-specific configuration changes that differ from SnappyMail defaults.

## Overview

This deployment is customized for ForwardEmail's privacy-focused email hosting service, with significant modifications to ensure zero persistent user data storage and full API integration.

## Configuration Files

### `_include.php`

**Purpose:** Version bootstrapper and entry point

**ForwardEmail Changes:**
- Defines `APP_VERSION` as `2.38.2`
- Loads SnappyMail core from versioned directory
- No custom modifications (uses SnappyMail defaults)

### `forwardemail-config.php`

**Purpose:** ForwardEmail-specific integration configuration loaded before SnappyMail initialization

**ForwardEmail Changes:**

```php
// API Integration
define('FORWARDEMAIL_API_URL', $_ENV['FORWARDEMAIL_API_URL'] ?? 'http://localhost:3000');
define('FORWARDEMAIL_MODE', true);

// Disable local storage
define('APP_USE_APCU_CACHE', false);
define('DISABLE_LOCAL_STORAGE', true);
define('APP_DATA_FOLDER_PATH', __DIR__ . '/data/');
define('APP_CONFIGURATION_NAME', 'forwardemail.ini');
```

**Application Settings Written to `application.ini`:**

```ini
[webmail]
theme = "ForwardEmail"                    # Custom ForwardEmail theme
title = "ForwardEmail Webmail"            # vs. "SnappyMail"
loading_description = "ForwardEmail - Privacy-focused email hosting"
favicon_url = "https://raw.githubusercontent.com/forwardemail/forwardemail.net/master/assets/img/logo-square.svg"
allow_themes = Off                        # vs. On (prevent theme changes)

[interface]
show_attachment_thumbnail = On            # Default

[cache]
enable = Off                              # vs. On (disable file caching)
server_uids = Off                         # vs. On (no UID caching)
system_data = Off                         # vs. On (no system data cache)
http = On                                 # Default (browser cache only)

[contacts]
enable = Off                              # vs. On (no local contacts)
```

## Docker Configuration

### `docker-compose-webmail.yml`

**ForwardEmail Changes:**

```yaml
tmpfs:
  # Mount data directory in memory for privacy (no persistent user data)
  - /var/www/html/data:mode=1777,size=100m
```

**Removed:**
```yaml
volumes:
  webmail-data:    # No persistent volume for data directory
```

**SnappyMail Default:** Uses persistent volume for `data/` directory

**ForwardEmail:** Uses tmpfs (RAM) - all data cleared on restart

### `Dockerfile`

**No ForwardEmail-specific changes** - uses standard SnappyMail PHP 8.2-Apache setup

## Key Differences from SnappyMail Defaults

| Setting | SnappyMail Default | ForwardEmail Configuration |
|---------|-------------------|---------------------------|
| **Theme** | Default | ForwardEmail (custom) |
| **Theme Selection** | Allowed | Disabled |
| **File Cache** | Enabled | Disabled |
| **UID Cache** | Enabled | Disabled |
| **System Data Cache** | Enabled | Disabled |
| **Local Contacts** | SQLite enabled | Disabled |
| **Data Storage** | Persistent volume | tmpfs (RAM, 100MB) |
| **API Backend** | IMAP/SMTP direct | ForwardEmail API |
| **Local Storage** | Enabled | Disabled |
| **APCu Cache** | Optional | Disabled |
| **Domain Configs** | Gmail, Hotmail, Yahoo, etc. | All removed - ForwardEmail only |
| **Default IMAP/SMTP** | Localhost | ForwardEmail servers (all domains) |
| **Favicon** | SnappyMail logo | ForwardEmail logo |

## Domain Configuration

### All Domains Use ForwardEmail Settings

**Key Change**: Unlike default SnappyMail which has pre-configured settings for Gmail, Hotmail, Yahoo, Outlook, and other providers, this deployment **forces all domains** to use ForwardEmail IMAP/SMTP settings.

**How it works:**
1. All default domain configs (gmail.com.json, hotmail.com.json, etc.) are **deleted** on startup
2. `default.json` is **replaced** with ForwardEmail settings
3. Any email domain (including @gmail.com, @yahoo.com, etc.) will use:
   - **IMAP**: imap.forwardemail.net:993 (SSL/TLS)
   - **SMTP**: smtp.forwardemail.net:465 (SSL/TLS)

**Applies to:**
- ✅ @forwardemail.net (explicit config)
- ✅ @gmail.com (uses default.json → ForwardEmail)
- ✅ @yahoo.com (uses default.json → ForwardEmail)
- ✅ @outlook.com (uses default.json → ForwardEmail)
- ✅ @anyotherdomain.com (uses default.json → ForwardEmail)

**Implementation:** See `docker-entrypoint.sh` lines 55-77

## Environment Variables

### Required

- `FORWARDEMAIL_API_URL` - ForwardEmail backend API endpoint (default: `http://localhost:3000`)

### Optional SnappyMail Variables (Not Used)

These standard SnappyMail environment variables are **overridden** by ForwardEmail configuration:

- `APP_DATA_FOLDER_PATH` - Set via `forwardemail-config.php`
- `APP_CONFIGURATION_NAME` - Set to `forwardemail.ini`
- Theme settings - Forced to ForwardEmail theme

## Privacy & Security Enhancements

### What's Disabled

1. **File-based caching** - No email content, UIDs, or metadata cached to disk
2. **Local contacts storage** - No SQLite database with contact information
3. **Persistent data volume** - Data directory cleared on every container restart
4. **APCu caching** - No in-memory cache that could persist between requests
5. **Local storage providers** - All data operations go through API

### What's Enabled

1. **Browser HTTP caching** - Static assets (CSS, JS) cached in browser
2. **tmpfs data directory** - Fast RAM-based temporary storage (100MB limit)
3. **ForwardEmail API** - All persistent operations handled by backend
4. **Security headers** - HSTS enforced via `forwardemail-config.php`

## Performance Implications

### Trade-offs

**Slower:**
- No server-side caching of message lists, UIDs, or metadata
- Every request requires API calls to ForwardEmail backend
- No persistent sessions across container restarts

**Faster:**
- RAM-based temporary storage (tmpfs) extremely fast
- Browser caching reduces static asset load times
- No disk I/O for cache operations

### Recommended Use Case

This configuration is optimized for:
- **Privacy-focused deployments** where user data must not touch disk
- **Ephemeral containers** where persistence isn't required
- **API-backed architectures** where all data lives in backend
- **Compliance requirements** for zero local data retention

## Theme Customization

### ForwardEmail Theme Location

`snappymail/v/2.38.2/themes/ForwardEmail/`

**Custom files:**
- `styles.css` - ForwardEmail branding
- `images/logo.svg` - ForwardEmail logo
- `images/logo.png` - Logo bitmap fallback
- `images/background.jpg` - Login background
- `manifest.json` - PWA configuration

**Forced Settings:**
- Theme cannot be changed by users (`allow_themes = Off`)
- Branding matches ForwardEmail visual identity

## Maintenance Notes

### Configuration Reset

Since `data/` is mounted on tmpfs, configuration is **regenerated on every container start** from `forwardemail-config.php`. This means:

1. Manual changes via admin panel are **lost on restart**
2. All configuration must be in `forwardemail-config.php` or environment variables
3. Admin password must be set via API or environment (not stored locally)

### Upgrading SnappyMail

To upgrade SnappyMail version:

1. Update `APP_VERSION` in `_include.php`
2. Add new version directory to `snappymail/v/X.Y.Z/`
3. Ensure ForwardEmail theme exists in new version
4. Test tmpfs compatibility with new version

### Debugging

**Enable logging temporarily** (add to `forwardemail-config.php`):

```ini
[logs]
enable = On
path = ""           # Uses tmpfs data directory
level = 7           # Debug level
hide_passwords = On
```

Note: Logs are **lost on restart** due to tmpfs.

## References

- SnappyMail Documentation: https://github.com/the-djmaze/snappymail/wiki
- SnappyMail Configuration: https://github.com/the-djmaze/snappymail/wiki/Configuration
- ForwardEmail API: Internal documentation