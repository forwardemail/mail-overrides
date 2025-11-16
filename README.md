# Mail Overrides

Forward Email specific themes and plugins for SnappyMail webmail. This repository bridges the gap between the upstream SnappyMail clone ([forwardemail/mail](https://github.com/forwardemail/mail)) and Forward Email's custom branding needs.

## Architecture

```
┌─────────────────────────────────────────────────────────┐
│ forwardemail.net (main monorepo)                       │
│ https://github.com/forwardemail/forwardemail.net       │
│ - All Forward Email processes                          │
│ - Ansible deployment configurations                    │
│ - Shared security & cert management                    │
│                                                         │
│   └─> mail-overrides/ (git submodule)                  │
│       https://github.com/forwardemail/mail-overrides   │
│       - Forward Email specific themes & plugins        │
│                                                         │
│         └─> mail/ (git submodule)                      │
│             https://github.com/forwardemail/mail       │
│             - SnappyMail clone (stays in sync with upstream) │
│                                                         │
│               └─> upstream: the-djmaze/snappymail      │
└─────────────────────────────────────────────────────────┘
```

## Purpose

This repository contains **only** Forward Email customizations:
- `plugins/forwardemail/` - Custom login page with Forward Email branding
- `themes/ForwardEmail/` - Custom CSS styling and design
- `configs/` - Pre-configured application settings
- `scripts/` - Build automation to sync overrides into mail/

The [forwardemail/mail](https://github.com/forwardemail/mail) repo stays clean and in sync with upstream SnappyMail, while this repo adds Forward Email branding on top.

## Quick Start

### 1. Clone and Initialize

```bash
git clone https://github.com/forwardemail/mail-overrides.git
cd mail-overrides

# Initialize the mail submodule (forwardemail/mail)
git submodule update --init --recursive
```

### 2. Build SnappyMail (First Time Only)

Before you can use the overrides, SnappyMail needs to be built once:

```bash
cd mail
npm ci  # Use npm ci for locked dependencies (recommended)
npx gulp

cd ..
```

This compiles the CSS/JS assets. You only need to do this once (or when updating the mail submodule).

**Note:** We use `npm ci` instead of `npm install` to ensure reproducible builds using the locked dependencies in `package-lock.json`.

### 3. Build Distribution

```bash
# Make scripts executable
chmod +x scripts/*.sh

# Build: Copy mail/ → dist/ and apply overrides
./scripts/build.sh
```

This creates `dist/` with SnappyMail + Forward Email customizations.

### Runtime Data vs. Seed Files

SnappyMail copies the files in `dist/data/_data_/_default_/configs/` into its writable data directory the **first** time it runs. After that, those runtime copies are treated as mutable state (the admin UI rewrites `plugins.ini`, per-user settings live under `dist/data/_data_/_default_/storage/<domain>/<localPart>/settings/`, etc.). Editing the git versions later will not change an existing deployment.

Common tasks:

| Goal | Runtime file(s) | Notes |
|------|-----------------|-------|
| Change global defaults (refresh interval, autologout, contacts) | `dist/data/_data_/_default_/configs/application.ini` | Delete a user’s `storage/<domain>/<localPart>/settings/settings.ini` if you want them to pick up new defaults. |
| Enable/disable plugins | `dist/data/_data_/_default_/configs/plugins.ini` | Toggling via the SnappyMail admin UI edits this file. |
| Reset plugin-specific config | `dist/data/_data_/_default_/configs/plugin-*.json` | Copy-once: edit the runtime version or remove it before restarting. |

When in doubt, inspect the files under `dist/data/_data_/_default_/…` on the target host (or Docker volume). Treat the copies in git as templates for *new* environments.

### 4. Test Locally

**Option A: Docker (Recommended)**
```bash
docker-compose -f docker/docker-compose.yml up
```

Visit http://localhost:8080 to see Forward Email branding with Redis support.

**Option B: PHP Built-in Server**
```bash
cd dist
php -S localhost:8000
```

Visit http://localhost:8000 (Note: Redis won't be available with this method).

## Repository Structure

```
mail-overrides/
├── mail/                      # Submodule → forwardemail/mail (clean, for building)
│                              # (tracks upstream SnappyMail)
├── dist/                      # Build output (gitignored)
│                              # Contains final SnappyMail with overrides applied
├── plugins/
│   ├── forwardemail/         # Forward Email branding plugin
│   ├── client-ip-passthrough/  # Client IP forwarding plugin
│   └── redis-ephemeral-sessions/  # Redis session storage plugin
├── themes/
│   └── ForwardEmail/         # Forward Email theme
├── configs/
│   ├── application.ini       # Pre-configured with plugin enabled
│   └── include.php           # Custom PHP configuration
├── scripts/
│   ├── build.sh             # Build: mail/ → dist/ + apply overrides
│   ├── clean.sh             # Remove dist/ directory
│   └── update-snappymail.sh # Update mail submodule
└── docker/                   # Local development only
```

## Development Workflow

### Making Changes

**⚠️ Important**: Never edit files inside `mail/` or `dist/` - these are build artifacts

1. Edit files in `plugins/` or `themes/` directories (at root level)
2. Run `./scripts/build.sh` to rebuild `dist/` with your changes
3. Test locally with Docker
4. Commit and push

```bash
# Example: Update Redis plugin
vim plugins/redis-ephemeral-sessions/index.php

# Rebuild dist/ with your changes
./scripts/build.sh

# Test with Docker
docker-compose -f docker/docker-compose.yml restart

# Commit your changes (dist/ is gitignored)
git add plugins/redis-ephemeral-sessions/
git commit -m "Update Redis session plugin"
git push
```

### Updating SnappyMail

The `mail/` submodule (forwardemail/mail) tracks upstream SnappyMail. To update:

```bash
# Update the mail submodule to latest
cd mail
git pull origin master
cd ..

# Commit the submodule update
git add mail
git commit -m "Update mail submodule to latest SnappyMail"

# Rebuild and test
./scripts/build.sh
cd dist && php -S localhost:8000

# Push
git push
```

### Forward Email Plugin Behavior

The `plugins/forwardemail` package enforces several defaults at login:

- CardDAV auto-setup (URL configurable via `plugin-forwardemail.json`). If `contacts_sync` already exists, delete `dist/data/_data_/_default_/storage/<domain>/<localPart>/configs/contacts_sync` to regenerate it.
- `ContactsAutosave = true`, `AutoLogout = 0`, and `keyPassForget = 0` to keep sessions available until explicit logout.
- Safe retry logic if CardDAV credentials are missing.

To force a user to pick up new defaults, delete their `settings/settings.ini` (and `settings_local/settings.ini` if present) under `dist/data/_data_/_default_/storage/<domain>/<localPart>/` and have them log in again.

## Production Deployment

Production deployment is managed by Ansible from the main [forwardemail.net](https://github.com/forwardemail/forwardemail.net) monorepo.

### Integration with Main Monorepo

```bash
# In forwardemail.net monorepo
cd /path/to/forwardemail.net

# Add mail-overrides as submodule (if not already added)
git submodule add https://github.com/forwardemail/mail-overrides.git mail-overrides

# Initialize all submodules (including nested mail/ submodule)
git submodule update --init --recursive
```

### Deployment Flow

```
1. Make changes in mail-overrides
   → git push to forwardemail/mail-overrides

2. Update in forwardemail.net monorepo
   → cd forwardemail.net/mail-overrides && git pull
   → git add mail-overrides && git commit

3. Deploy with Ansible (from forwardemail.net)
   → ansible-playbook playbooks/deploy-snappymail.yml
   → Ansible runs build.sh and deploys dist/
```

The Ansible playbook in `forwardemail.net` will:
1. Run `mail-overrides/scripts/build.sh` to produce the `dist/` build output with customizations applied
2. Deploy `mail-overrides/dist/` to production servers
3. Configure web server, permissions, SSL (using shared configs)

## Docker (Local Development Only)

Docker is provided for local testing. Production uses Ansible from the main monorepo.

```bash
# Start
docker-compose -f docker/docker-compose.yml up

# Stop
docker-compose -f docker/docker-compose.yml down
```

## Scripts

### `scripts/build.sh`
Generates the deployable `dist/` artefacts while leaving the `mail/` submodule pristine:
- Copies the clean SnappyMail tree from `mail/` into `dist/`
- Overlays `plugins/forwardemail/` and `themes/ForwardEmail/` into `dist/snappymail/v/0.0.0/`
- Copies configuration files into `dist/data/_data_/_default_/`

**Run this after any changes to plugins or themes.**

### `scripts/deploy.sh`
Local development helper. Production deployment uses Ansible from forwardemail.net.

### `scripts/update-snappymail.sh`
Helper to update the `mail/` submodule to a specific SnappyMail version.

## Plugin & Theme

### ForwardEmail Plugin
- Located in `plugins/forwardemail/`
- Overrides login template with Forward Email branding
- Auto-enabled via `configs/application.ini`

### ForwardEmail Theme
- Located in `themes/ForwardEmail/`
- Custom CSS matching Forward Email brand
- Login page styles, responsive design

## Troubleshooting

- **Submodule missing** → run `git submodule update --init --recursive`.
- **New theme/plugin changes not showing** → run `./scripts/build.sh` and ensure you’re editing `plugins/` or `themes/`, not `mail/` or `dist/`.
- **Plugin stays enabled/disabled unexpectedly** → SnappyMail manages `dist/data/_data_/_default_/configs/plugins.ini`. Edit that runtime file (or use the admin UI) rather than the git copy.
- **Defaults (contacts, refresh interval, autologout) not updating** → delete `dist/data/_data_/_default_/storage/<domain>/<localPart>/settings/` (and `settings_local/`) so the account reloads from `application.ini`.
- **CardDAV sync errors** → confirm the PHP-FPM pool loads the curl extension *and* `curl_exec` is not listed in `php_admin_value[disable_functions]`. Verify the CardDAV server responds to `PROPFIND /.well-known/carddav` with 207/301 and that each `.vcf` URL is reachable.
- **Redis plugin still running** → disable it in the SnappyMail admin UI or edit the runtime `plugins.ini`, then clear `dist/data/_data_/_default_/cache`.

## Contributing

1. Fork this repository
2. Create feature branch: `git checkout -b feature/my-feature`
3. Make changes to `plugins/` or `themes/` (NOT in `mail/`)
4. Run `./scripts/build.sh` and test
5. Commit and create Pull Request

## License

- **Forward Email customizations** (plugins/themes): BUSL-1.1
- **SnappyMail** (via mail submodule): AGPL-3.0

## Related Repositories

- [forwardemail/mail](https://github.com/forwardemail/mail) - SnappyMail clone
- [forwardemail/forwardemail.net](https://github.com/forwardemail/forwardemail.net) - Main monorepo
- [the-djmaze/snappymail](https://github.com/the-djmaze/snappymail) - Upstream

## Support

- **Issues**: https://github.com/forwardemail/mail-overrides/issues
- **Forward Email**: https://forwardemail.net/en/faq
