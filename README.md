# ForwardEmail SnappyMail Integration

This repository contains a customized SnappyMail webmail client deployment configured for ForwardEmail's privacy-focused email hosting service.

## Overview

SnappyMail is a modern, lightweight web-based email client. This deployment has been customized with ForwardEmail branding, pre-configured IMAP/SMTP settings, and enhanced privacy features including zero persistent user data storage.

## Quick Start

### Prerequisites

- Docker and Docker Compose
- Port 8080 available on host machine

### Running the Application

```bash
# Start the container
docker-compose -f docker-compose-webmail.yml up -d

# Access the webmail interface
open http://localhost:8080

# Access the admin panel
open http://localhost:8080/?admin

# Get the auto-generated admin password
docker exec snappymail cat /tmp/snappymail-data/_data_/_default_/admin_password.txt
```

### Default Credentials

- **Admin Username**: `admin`
- **Admin Password**: Auto-generated on first startup (see command above)

## Key Differences from Default SnappyMail

### 1. Branding & Theme

| Setting | Default SnappyMail | ForwardEmail Configuration |
|---------|-------------------|---------------------------|
| **Application Title** | "SnappyMail Webmail" | "ForwardEmail Webmail" |
| **Loading Text** | "SnappyMail" | "ForwardEmail - Privacy-focused email hosting" |
| **Default Theme** | Default | ForwardEmail (NextcloudV25+ style) |
| **Theme Selection** | Enabled | Disabled (locked to ForwardEmail theme) |

### 2. Data Storage & Privacy

| Feature | Default SnappyMail | ForwardEmail Configuration |
|---------|-------------------|---------------------------|
| **Data Directory** | Persistent volume | tmpfs (RAM-only, 100MB limit) |
| **File-based Caching** | Enabled | Disabled |
| **UID Caching** | Enabled | Disabled |
| **System Data Cache** | Enabled | Disabled |
| **Local Contacts** | SQLite database | Disabled |
| **Data Persistence** | Survives restarts | Cleared on every restart |
| **Data Location** | `/var/www/html/data` | `/tmp/snappymail-data` (tmpfs) |

**Privacy Benefits:**
- ✅ No user data written to disk
- ✅ No email content cached locally
- ✅ All data cleared on container restart
- ✅ Configuration regenerated from environment/templates

### 3. IMAP/SMTP Configuration

**Pre-configured ForwardEmail Domain** (`forwardemail.net.json`):

| Service | Default | ForwardEmail Configuration |
|---------|---------|---------------------------|
| **IMAP Host** | localhost | imap.forwardemail.net |
| **IMAP Port** | 143 (STARTTLS) | 993 (SSL/TLS) |
| **IMAP Encryption** | STARTTLS | SSL/TLS (type=2) |
| **SMTP Host** | localhost | smtp.forwardemail.net |
| **SMTP Port** | 25 (Plain) | 465 (SSL/TLS) |
| **SMTP Encryption** | None | SSL/TLS (type=2) |
| **SMTP Auth** | Disabled | Enabled |
| **SSL Verification** | Disabled | Enabled |

Users with `@forwardemail.net` email addresses automatically use these settings.

### 4. Configuration Management

| Aspect | Default SnappyMail | ForwardEmail Configuration |
|--------|-------------------|---------------------------|
| **Config Location** | `data/_data_/_default_/configs/` | Same, but in tmpfs |
| **Config Persistence** | Saved between restarts | Regenerated on startup |
| **Admin Password** | Set via UI or environment | Auto-generated to tmpfs on first access |
| **Customization** | Manual via admin panel | Automated via entrypoint script |

### 5. Docker Setup

| Component | Default | ForwardEmail Configuration |
|-----------|---------|---------------------------|
| **Base Image** | php:8.2-apache | php:8.2-apache |
| **Data Volume** | Named volume `webmail-data` | tmpfs mount (no volume) |
| **Entrypoint** | `apache2-foreground` | Custom `docker-entrypoint.sh` |
| **Config Application** | Manual | Automatic on startup |
| **Volume Mounts** | Code + persistent data | Code only (read-write) |

## Architecture

### File Structure

```
hosted-snappymail/
├── docker-compose-webmail.yml    # Docker Compose configuration
├── README.md                      # This file
└── webmail/
    ├── Dockerfile                 # Container build instructions
    ├── docker-entrypoint.sh       # Startup script (applies ForwardEmail config)
    ├── _include.php               # ForwardEmail integration config (template)
    ├── include.php                # Active integration config
    ├── forwardemail-config.php    # Theme and branding settings (legacy)
    ├── forwardemail.net.json      # IMAP/SMTP defaults for ForwardEmail
    ├── index.php                  # Application entry point
    ├── CONFIG.md                  # Detailed configuration reference
    ├── CONTRIBUTING.md            # Codebase overview for contributors
    └── snappymail/                # SnappyMail v2.38.2 core
        └── v/2.38.2/
            ├── app/               # PHP backend
            ├── static/            # Frontend assets (JS, CSS)
            └── themes/
                └── ForwardEmail/  # Custom ForwardEmail theme
```

### Startup Flow

1. **Container Start**: Docker starts with custom entrypoint
2. **Apache Launch**: Apache server starts in background
3. **Config Monitor**: Entrypoint waits for SnappyMail setup to complete
4. **Auto-Configuration**:
   - Detects when `admin_password.txt` is created
   - Updates `application.ini` with ForwardEmail branding
   - Copies `forwardemail.net.json` domain configuration
5. **Ready**: Webmail accessible with ForwardEmail branding

### Data Flow

```
User Request → Apache → index.php → include.php (_include.php)
                                        ↓
                        Sets APP_DATA_FOLDER_PATH=/tmp/snappymail-data/
                                        ↓
                        SnappyMail Core (v/2.38.2/include.php)
                                        ↓
                        Reads config from tmpfs
                                        ↓
                        Returns response to user
```

## Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `APP_DATA_FOLDER_PATH` | `/tmp/snappymail-data/` | Location of runtime data (in tmpfs) |

## Configuration Files

### `_include.php` / `include.php`

**Purpose**: ForwardEmail-specific integration configuration loaded before SnappyMail initialization.

**Key Settings**:
```php
// API Integration
define('FORWARDEMAIL_API_URL', $_ENV['FORWARDEMAIL_API_URL'] ?? 'http://localhost:3000');

// Data storage in tmpfs
define('APP_DATA_FOLDER_PATH', $_ENV['APP_DATA_FOLDER_PATH'] ?? __DIR__ . '/data/');

// Disable local storage
define('APP_USE_APCU_CACHE', false);
define('DISABLE_LOCAL_STORAGE', true);
```

### `docker-entrypoint.sh`

**Purpose**: Automatic configuration application on container startup.

**Operations**:
1. Starts Apache in background
2. Monitors for SnappyMail initialization
3. Updates `application.ini` with ForwardEmail branding
4. Installs ForwardEmail domain configuration
5. Waits for Apache process

### `forwardemail.net.json`

**Purpose**: Pre-configured IMAP/SMTP settings for ForwardEmail domains.

**Settings**:
- IMAP: `imap.forwardemail.net:993` (SSL/TLS)
- SMTP: `smtp.forwardemail.net:465` (SSL/TLS)
- SSL verification enabled
- SASL auth methods: PLAIN, LOGIN

## Performance Considerations

### Caching Disabled

Since file-based caching is disabled for privacy:

**Slower**:
- No server-side caching of message lists or UIDs
- Every request requires API calls to ForwardEmail backend
- No persistent sessions across container restarts

**Faster**:
- RAM-based tmpfs is extremely fast (no disk I/O)
- Browser HTTP caching still works for static assets
- No cache invalidation overhead

### Resource Usage

- **RAM**: ~100MB for tmpfs data directory + normal Apache/PHP memory
- **CPU**: Slightly higher due to no caching (more API calls)
- **Disk**: Minimal (only code, no data)

### Recommended For

✅ Privacy-focused deployments where data must not touch disk
✅ Ephemeral/stateless container architectures
✅ API-backed systems where persistence lives in backend
✅ Compliance requirements for zero local data retention

❌ High-traffic production without API backend
❌ Offline usage (requires ForwardEmail API)
❌ Slow network connections to IMAP/SMTP servers

## Maintenance

### Viewing Logs

```bash
# Container logs
docker logs snappymail

# Follow logs in real-time
docker logs -f snappymail

# Check for configuration application
docker logs snappymail | grep -E "Applying|Updated|Created"
```

### Accessing Container

```bash
# Shell access
docker exec -it snappymail bash

# Check tmpfs contents
docker exec snappymail ls -la /tmp/snappymail-data/

# View current configuration
docker exec snappymail cat /tmp/snappymail-data/_data_/_default_/configs/application.ini
```

### Rebuilding

```bash
# Rebuild image
docker-compose -f docker-compose-webmail.yml build

# Rebuild and restart (fresh tmpfs)
docker-compose -f docker-compose-webmail.yml down
docker-compose -f docker-compose-webmail.yml up -d
```

### Updating SnappyMail Version

1. Update `APP_VERSION` in `webmail/_include.php`
2. Add new version directory to `webmail/snappymail/v/X.Y.Z/`
3. Ensure ForwardEmail theme exists in new version
4. Test tmpfs compatibility
5. Rebuild container

## Troubleshooting

### Admin Password Not Working

```bash
# Get current auto-generated password
docker exec snappymail cat /tmp/snappymail-data/_data_/_default_/admin_password.txt
```

### Branding Not Applied

```bash
# Check if configuration was applied
docker logs snappymail | grep "Applying ForwardEmail"

# Manually verify config
docker exec snappymail cat /tmp/snappymail-data/_data_/_default_/configs/application.ini | grep theme
```

### Data Directory Empty

The tmpfs is cleared on every restart by design. If you need to inspect data:

```bash
# Check if tmpfs is mounted
docker exec snappymail mount | grep tmpfs

# Check permissions
docker exec snappymail ls -la /tmp/snappymail-data/
```

### ForwardEmail Domain Config Missing

```bash
# Check if domain config exists
docker exec snappymail ls -la /tmp/snappymail-data/_data_/_default_/domains/

# Manually copy if needed (requires restart)
docker exec snappymail cp /var/www/html/forwardemail.net.json /tmp/snappymail-data/_data_/_default_/domains/
```

## Security Considerations

### Strengths

- ✅ No persistent user data on disk
- ✅ Data cleared on restart (RAM-only)
- ✅ SSL/TLS enforced for IMAP/SMTP
- ✅ HSTS header enabled
- ✅ Modern PHP 8.2 with security updates
- ✅ Auto-generated admin passwords (not in repository)

### Limitations

- ⚠️ Admin password stored in plain text in tmpfs (by SnappyMail design)
- ⚠️ No persistent audit logs (cleared on restart)
- ⚠️ Requires secure backend (ForwardEmail API)
- ⚠️ Volume mount exposes source code to container

### Recommendations

1. Use strong admin passwords (change after first login)
2. Run behind HTTPS reverse proxy (nginx, Caddy, Traefik)
3. Restrict network access to container
4. Monitor container logs for failed auth attempts
5. Keep SnappyMail and base image updated

## Development

### Local Testing

```bash
# Build and run
docker-compose -f docker-compose-webmail.yml up --build

# Access admin panel
open http://localhost:8080/?admin

# Make changes to webmail/ directory (auto-reflected via volume mount)
```

### Customization

To modify ForwardEmail branding:

1. **Theme**: Edit `webmail/snappymail/v/2.38.2/themes/ForwardEmail/`
2. **Logo**: Replace `webmail/snappymail/v/2.38.2/themes/ForwardEmail/images/logo.svg`
3. **Settings**: Modify `webmail/docker-entrypoint.sh` config application
4. **Domain Config**: Edit `webmail/forwardemail.net.json`

## Documentation

- [CONFIG.md](webmail/CONFIG.md) - Detailed configuration reference
- [CONTRIBUTING.md](webmail/CONTRIBUTING.md) - Codebase overview for contributors
- [SnappyMail Wiki](https://github.com/the-djmaze/snappymail/wiki) - Upstream documentation

## License

SnappyMail is released under **GNU AGPL v3**.
See: http://www.gnu.org/licenses/agpl-3.0.html

Copyright (c) 2020-2024 SnappyMail
Copyright (c) 2013-2022 RainLoop

## Support

For issues specific to this ForwardEmail integration:
- Review [CONFIG.md](webmail/CONFIG.md) for configuration details
- Check Docker logs: `docker logs snappymail`
- Inspect tmpfs: `docker exec snappymail ls -la /tmp/snappymail-data/`

For SnappyMail core issues:
- [SnappyMail GitHub Issues](https://github.com/the-djmaze/snappymail/issues)
- [SnappyMail Wiki](https://github.com/the-djmaze/snappymail/wiki)

## Version

- **SnappyMail**: v2.38.2
- **PHP**: 8.2
- **Apache**: 2.4.65
- **Configuration**: ForwardEmail Integration v1.0