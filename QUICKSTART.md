# Quick Start Guide

Get up and running with mail-overrides in 5 minutes.

## One-Time Setup

```bash
# 1. Clone and initialize
git clone https://github.com/forwardemail/mail-overrides.git
cd mail-overrides
git submodule update --init --recursive

# 2. Build SnappyMail (compiles CSS/JS assets)
cd mail
npm install
npx gulp

cd ..

# 3. Build distribution (copies mail/ → dist/ + applies overrides)
chmod +x scripts/*.sh
./scripts/build.sh

# 4. Start development environment
docker-compose -f docker/docker-compose.yml up
```

Visit http://localhost:8080 🎉

## Development Workflow

```bash
# Make changes to plugins or themes
vim plugins/redis-ephemeral-sessions/index.php

# Rebuild dist/ with your changes
./scripts/build.sh

# Restart container to see changes
docker-compose -f docker/docker-compose.yml restart snappymail-dev
```

## Architecture

```
mail/         → Clean submodule (upstream SnappyMail)
  ↓ build
dist/         → Built SnappyMail + your overrides (gitignored)
  ↓ mount
Docker        → Serves from dist/
```

**Key principle:** Never edit `mail/` or `dist/` - they're build artifacts. Edit `plugins/` and `themes/` only.

## Useful Commands

```bash
# Clean build artifacts
./scripts/clean.sh

# Rebuild from scratch
./scripts/build.sh

# Stop Docker
docker-compose -f docker/docker-compose.yml down

# View logs
docker logs -f snappymail-local-dev
docker logs -f snappymail-redis-dev
```

## What's Included

- **SnappyMail** webmail client
- **Redis** for ephemeral session storage
- **3 plugins**:
  - forwardemail (branding)
  - redis-ephemeral-sessions (secure sessions)
  - client-ip-passthrough (IP forwarding)
- **Forward Email theme**
- **Pre-configured Forward Email servers**:
  - IMAP: imap.forwardemail.net:993 (TLS)
  - SMTP: smtp.forwardemail.net:465 (TLS)

## Next Steps

- See [TESTING.md](TESTING.md) for detailed testing guide
- See [README.md](README.md) for full documentation
- Configure plugins in admin panel at http://localhost:8080/?admin
