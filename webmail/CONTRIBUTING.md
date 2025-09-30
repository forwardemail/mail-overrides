# SnappyMail Codebase Overview

This document provides a high-level overview of the SnappyMail webmail folder structure for future contributors.

## Project Structure

### Root Level (`webmail/`)

- `index.php` - Main entry point for the application
- `_include.php` - Version bootstrapper (v2.38.2) that loads the core SnappyMail library
- `forwardemail-config.php` - ForwardEmail-specific integration configuration
- `Dockerfile` - PHP 8.2-Apache container setup with required extensions
- `data/` - Runtime data storage (user configs, cache, etc.)

### Core Application (`webmail/snappymail/v/2.38.2/`)

**Directory Layout:**

- `app/` - Core application code
  - `libraries/` - Third-party and core PHP libraries
    - `MailSo/` - Mail protocol implementation (IMAP, SMTP)
    - `RainLoop/` - Legacy RainLoop framework code
    - `snappymail/` - Core SnappyMail utilities (crypto, GPG, JWT, QR codes, etc.)
    - `Sabre/` - XML/DAV libraries
    - `OAuth2/` - OAuth authentication
  - `domains/` - Domain-specific configurations
  - `localization/` - i18n language files (~40 languages)
  - `templates/` - Server-side HTML templates

- `static/` - Frontend assets
  - `js/` - JavaScript application code (admin, app, sieve)
  - `css/` - Stylesheets
  - `images/`, `sounds/` - Media assets
  - Icons and PWA manifest

- `themes/` - UI themes (~25 themes including custom ForwardEmail theme)

## Key Features & Architecture

### Modern PHP Stack

- PHP 8.2+ with Apache
- No database required (file-based storage)
- Extensions: intl, zip, gd, dom, xml, curl, mbstring, PDO

### ForwardEmail Integration

- Custom configuration in `forwardemail-config.php`
- API integration via `FORWARDEMAIL_API_URL` environment variable
- Custom theme at `themes/ForwardEmail/`
- Disables local storage in favor of API backend

### Frontend (ES2020+)

- ~95% smaller than original RainLoop
- No jQuery, replaced with native JavaScript
- Modified Knockout.js 3.5.1 for templating
- Squire HTML editor (replaces CKEditor)
- Service worker for notifications
- Rollup bundler (not webpack)

### Security Features

- Modern PGP support (OpenPGP.js v5, GnuPG, Mailvelope)
- ECDSA/EDDSA key support
- Sodium/OpenSSL encryption
- JWT authentication
- Privacy-focused (no trackers, social integrations removed)

## Development Notes

### Removed from RainLoop

- POP3 support
- jQuery, Modernizr, Moment.js
- Old browser support (IE, Edge Legacy)
- Social integrations (Gravatar, Facebook, etc.)
- Premium/license code

### Browser Support

- Chrome 80+, Firefox 78+, Safari 13.1+, Edge 80+, Opera 67+

## Entry Flow

1. `index.php` → `_include.php` → `snappymail/v/2.38.2/include.php`
2. ForwardEmail config loaded before SnappyMail initialization
3. Application uses file-based data storage in `data/` directory

## Privacy & Data Storage Configuration

This setup is configured for maximum privacy with **no persistent user data** stored on disk:

### Data Directory (`data/`)

The `data/` directory structure:
- `_data_/_default_/` - Configuration and runtime data
  - `configs/` - Application configuration files
  - `cache/` - Temporary cache (disabled in production)
  - `storage/` - User data storage (unused with API backend)
  - `plugins/` - Plugin storage
  - `domains/` - Domain configurations
- System files: `SALT.php`, `INSTALLED`, `VERSION`

### Privacy Configuration

**File-based caching is disabled** via `forwardemail-config.php`:
```ini
[cache]
enable = Off          # No file-based caching
server_uids = Off     # Don't cache message UIDs
system_data = Off     # Don't cache system data
http = On            # Browser cache only (no filesystem)

[contacts]
enable = Off         # Disable local contacts storage
```

**Temporary filesystem (tmpfs)** - The entire `data/` directory is mounted in RAM:
- Configured in `docker-compose-webmail.yml` with `tmpfs`
- 100MB RAM limit
- All data is lost on container restart (by design)
- No persistent user data touches the disk

### Security Benefits

1. **No data persistence** - User data never written to disk
2. **No cache files** - Email content not cached locally
3. **Memory-only storage** - Configuration regenerated on startup
4. **API-driven** - All persistent data lives in ForwardEmail backend
5. **Admin credentials** - Not stored in repository (generated at runtime)

### Performance Considerations

- Browser-level HTTP caching still enabled for static assets
- API calls may be slightly slower without local caching
- RAM-based storage is extremely fast for temporary files
- Suitable for privacy-focused deployments where performance trade-off is acceptable

## Summary

This is a modern, security-focused webmail client built on a stripped-down RainLoop fork, optimized for performance and privacy with ForwardEmail backend integration. The configuration prioritizes **zero persistent user data** on the filesystem while maintaining acceptable performance through RAM-based temporary storage and browser caching.