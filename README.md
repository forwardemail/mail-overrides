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
npm install
npx gulp

cd ..
```

This compiles the CSS/JS assets. You only need to do this once (or when updating the mail submodule).

### 3. Build Distribution

```bash
# Make scripts executable
chmod +x scripts/*.sh

# Build: Copy mail/ → dist/ and apply overrides
./scripts/build.sh
```

This creates `dist/` with SnappyMail + Forward Email customizations.

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

### Submodule not initialized
```bash
git submodule update --init --recursive
```

### Customizations not appearing
```bash
./scripts/build.sh
ls -la mail/snappymail/v/0.0.0/plugins/forwardemail/
```

### Update mail submodule
```bash
cd mail && git pull && cd ..
git add mail && git commit -m "Update mail submodule"
```

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
