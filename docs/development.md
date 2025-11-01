# Development Guide

## Setup Development Environment

```bash
# Clone and initialize
git clone https://github.com/forwardemail/hosted-snappymail.git
cd hosted-snappymail
git submodule update --init --recursive

# Build
./scripts/build.sh

# Run local server
cd mail && php -S localhost:8000
```

## Making Changes

### Editing Plugin

```bash
# Edit plugin files
vim plugins/forwardemail/templates/Views/User/Login.html

# Rebuild and test
./scripts/build.sh
cd mail && php -S localhost:8000
```

### Editing Theme

```bash
# Edit theme files
vim themes/ForwardEmail/styles.css

# Rebuild and test
./scripts/build.sh
cd mail && php -S localhost:8000
```

## Testing

### Local Testing
1. Run `./scripts/build.sh`
2. Start PHP dev server: `cd mail && php -S localhost:8000`
3. Visit http://localhost:8000
4. Test login page appearance and functionality

### Docker Testing
```bash
./scripts/build.sh
docker-compose -f docker/docker-compose.yml up
# Visit http://localhost:8080
```

## Updating SnappyMail

```bash
# Check available versions
./scripts/update-snappymail.sh

# Update to specific version
./scripts/update-snappymail.sh v2.38.0

# Rebuild and test
./scripts/build.sh
```
