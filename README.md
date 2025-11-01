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

### 2. Build

```bash
# Make scripts executable
chmod +x scripts/*.sh

# Sync Forward Email customizations into mail/
./scripts/build.sh
```

### 3. Test Locally

**Option A: PHP Built-in Server**
```bash
cd mail
php -S localhost:8000
```

**Option B: Docker**
```bash
docker-compose -f docker/docker-compose.yml up
```

Visit http://localhost:8000 (or :8080 for Docker) to see Forward Email branding.

## Repository Structure

```
mail-overrides/
├── mail/                      # Submodule → forwardemail/mail
│                              # (which tracks upstream snappymail)
├── plugins/
│   └── forwardemail/         # Forward Email plugin
├── themes/
│   └── ForwardEmail/         # Forward Email theme
├── configs/
│   ├── application.ini       # Pre-configured with plugin enabled
│   └── include.php           # Custom PHP configuration
├── scripts/
│   ├── build.sh             # Sync overrides → mail/
│   └── update-snappymail.sh # Update mail submodule
└── docker/                   # Local development only
```

## Development Workflow

### Making Changes

**⚠️ Important**: Never edit files inside `mail/` submodule - they will be overwritten by `build.sh`

1. Edit files in `plugins/` or `themes/` directories (at root level)
2. Run `./scripts/build.sh` to sync into `mail/`
3. Test locally
4. Commit and push

```bash
# Example: Update login page
vim plugins/forwardemail/templates/Views/User/Login.html

# Rebuild
./scripts/build.sh

# Test
cd mail && php -S localhost:8000

# Commit
git add plugins/forwardemail/
git commit -m "Update login page branding"
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
cd mail && php -S localhost:8000

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
   → Ansible runs build.sh and deploys mail/
```

The Ansible playbook in `forwardemail.net` will:
1. Run `mail-overrides/scripts/build.sh` to sync customizations
2. Deploy `mail-overrides/mail/` to production servers
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
Syncs Forward Email customizations into the `mail/` submodule:
- Copies `plugins/forwardemail/` → `mail/snappymail/v/0.0.0/plugins/`
- Copies `themes/ForwardEmail/` → `mail/snappymail/v/0.0.0/themes/`
- Copies config files

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
