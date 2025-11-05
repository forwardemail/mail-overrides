#!/bin/bash

#
# Redis Ephemeral Sessions Plugin - Installation Script
#
# This script helps install and configure the plugin for SnappyMail
#

set -e

echo ""
echo "====================================================="
echo "Redis Ephemeral Sessions Plugin - Installer"
echo "====================================================="
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Helper functions
print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}!${NC} $1"
}

# Check if running as root
if [ "$EUID" -eq 0 ]; then
    print_error "Do not run this script as root"
    exit 1
fi

# Detect SnappyMail root directory
if [ -f "../../snappymail/v/0.0.0/index.php" ]; then
    SNAPPYMAIL_ROOT="$(cd ../.. && pwd)"
    print_success "Detected SnappyMail root: $SNAPPYMAIL_ROOT"
else
    print_error "Could not detect SnappyMail installation"
    echo "Please run this script from the plugin directory:"
    echo "  cd /path/to/snappymail/plugins/redis-ephemeral-sessions"
    echo "  ./install.sh"
    exit 1
fi

echo ""
echo "Step 1: Checking Prerequisites"
echo "---------------------------------------------------"

# Check PHP
if ! command -v php &> /dev/null; then
    print_error "PHP not found"
    exit 1
fi
PHP_VERSION=$(php -r 'echo PHP_VERSION;')
print_success "PHP version: $PHP_VERSION"

# Check Composer
if ! command -v composer &> /dev/null; then
    print_warning "Composer not found - you'll need to install Predis manually"
else
    print_success "Composer found"
fi

# Check Redis
if ! command -v redis-cli &> /dev/null; then
    print_warning "redis-cli not found - make sure Redis is installed"
else
    print_success "Redis CLI found"
fi

echo ""
echo "Step 2: Installing Dependencies"
echo "---------------------------------------------------"

if command -v composer &> /dev/null; then
    cd "$SNAPPYMAIL_ROOT"

    if [ ! -f "composer.json" ]; then
        print_warning "No composer.json found, creating minimal one..."
        cat > composer.json <<EOF
{
    "name": "snappymail/snappymail",
    "description": "SnappyMail Webmail",
    "require": {
        "php": ">=7.4"
    }
}
EOF
    fi

    echo "Installing Predis..."
    composer require predis/predis --no-interaction

    if [ $? -eq 0 ]; then
        print_success "Predis installed"
    else
        print_error "Failed to install Predis"
        exit 1
    fi
else
    print_warning "Skipping Predis installation (Composer not available)"
    echo "Install manually: composer require predis/predis"
fi

echo ""
echo "Step 3: Generating Configuration"
echo "---------------------------------------------------"

# Generate key mask secret
SECRET=$(openssl rand -base64 32 2>/dev/null || head -c 32 /dev/urandom | base64)

if [ -z "$SECRET" ]; then
    print_error "Failed to generate secret"
    exit 1
fi

print_success "Generated key mask secret"

# Save to temporary config file
CONFIG_FILE="$SNAPPYMAIL_ROOT/plugins/redis-ephemeral-sessions/.config.tmp"

cat > "$CONFIG_FILE" <<EOF
# Redis Ephemeral Sessions Configuration
# Generated: $(date)

Redis Host: 127.0.0.1
Redis Port: 6379
Use TLS: false
Redis Password: (leave empty if not set)
Session TTL: 14400 (4 hours)

Key Mask Secret:
$SECRET

IMPORTANT:
- Copy this secret to SnappyMail admin panel
- Do not commit this file to version control
- Delete this file after configuration
EOF

print_success "Configuration saved to: $CONFIG_FILE"

echo ""
echo "Step 4: Testing Redis Connection"
echo "---------------------------------------------------"

# Test Redis connection
REDIS_HOST="127.0.0.1"
REDIS_PORT="6379"

if command -v redis-cli &> /dev/null; then
    if redis-cli -h "$REDIS_HOST" -p "$REDIS_PORT" ping &> /dev/null; then
        print_success "Redis connection successful"
    else
        print_warning "Could not connect to Redis at $REDIS_HOST:$REDIS_PORT"
        echo "Make sure Redis is running:"
        echo "  sudo systemctl start redis"
        echo "  # or"
        echo "  redis-server"
    fi
else
    print_warning "Cannot test Redis connection (redis-cli not found)"
fi

echo ""
echo "Step 5: Setting Permissions"
echo "---------------------------------------------------"

# Set executable permissions
chmod +x "$SNAPPYMAIL_ROOT/plugins/redis-ephemeral-sessions/generate-secret.php"
chmod +x "$SNAPPYMAIL_ROOT/plugins/redis-ephemeral-sessions/install.sh"

print_success "Permissions set"

echo ""
echo "====================================================="
echo "Installation Complete!"
echo "====================================================="
echo ""
echo "Next Steps:"
echo ""
echo "1. Open SnappyMail Admin Panel"
echo "   URL: https://your-domain.com/?admin"
echo ""
echo "2. Navigate to: Plugins > Redis Ephemeral Sessions"
echo ""
echo "3. Configure the plugin with these values:"
echo "   - Redis Host: 127.0.0.1"
echo "   - Redis Port: 6379"
echo "   - Use TLS: false (true for production)"
echo "   - Session TTL: 14400 (4 hours)"
echo ""
echo "4. Copy the Key Mask Secret from:"
echo "   $CONFIG_FILE"
echo ""
echo "5. Enable the plugin and test login"
echo ""
echo "Documentation:"
echo "  - README: plugins/redis-ephemeral-sessions/README.md"
echo "  - Integration: plugins/redis-ephemeral-sessions/INTEGRATION.md"
echo ""
echo "Support:"
echo "  - Email: support@forwardemail.net"
echo "  - GitHub: https://github.com/forwardemail/snappymail-redis-sessions"
echo ""
echo "====================================================="
echo ""

# Offer to view config
read -p "View configuration file now? [y/N] " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    cat "$CONFIG_FILE"
fi

echo ""
print_success "Installation script completed successfully"
echo ""
