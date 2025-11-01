#!/bin/bash
#
# Build Script for Forward Email SnappyMail Deployment
# This script syncs customizations into the mail submodule
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(dirname "$SCRIPT_DIR")"

echo "========================================"
echo "Forward Email SnappyMail Build"
echo "========================================"
echo ""

cd "$ROOT_DIR"

# Check if mail submodule exists
if [ ! -d "mail/.git" ]; then
    echo "ERROR: mail submodule not initialized!"
    echo ""
    echo "Run: git submodule update --init --recursive"
    exit 1
fi

# Detect SnappyMail version directory
SNAPPYMAIL_VERSION_DIR=$(find mail/snappymail/v -maxdepth 1 -type d | grep -E 'v/[0-9]' | head -1)
if [ -z "$SNAPPYMAIL_VERSION_DIR" ]; then
    SNAPPYMAIL_VERSION_DIR="mail/snappymail/v/0.0.0"
fi

echo "SnappyMail version directory: $SNAPPYMAIL_VERSION_DIR"
echo ""

# Sync plugin
echo "→ Syncing ForwardEmail plugin..."
mkdir -p "$SNAPPYMAIL_VERSION_DIR/plugins"
rsync -av --delete plugins/forwardemail/ "$SNAPPYMAIL_VERSION_DIR/plugins/forwardemail/"
echo "  ✓ Plugin synced"

# Sync theme
echo "→ Syncing ForwardEmail theme..."
mkdir -p "$SNAPPYMAIL_VERSION_DIR/themes"
rsync -av --delete themes/ForwardEmail/ "$SNAPPYMAIL_VERSION_DIR/themes/ForwardEmail/"
echo "  ✓ Theme synced"

# Copy include.php if exists
if [ -f "configs/include.php" ]; then
    echo "→ Copying include.php configuration..."
    cp configs/include.php mail/include.php
    echo "  ✓ Configuration copied"
fi

# Prepare data directory structure
echo "→ Preparing data directory..."
mkdir -p mail/data/_data_/_default_/configs

# Copy application.ini if exists
if [ -f "configs/application.ini" ]; then
    echo "→ Copying application.ini..."
    cp configs/application.ini mail/data/_data_/_default_/configs/
    echo "  ✓ Application config copied"
fi

echo ""
echo "========================================"
echo "✓ Build Complete!"
echo "========================================"
echo ""
echo "Customizations synced to: ./mail/"
echo ""
echo "Next steps:"
echo "  • Test locally: cd mail && php -S localhost:8000"
echo "  • Build Docker: docker-compose -f docker/docker-compose.yml build"
echo "  • Deploy: rsync -av mail/ user@server:/var/www/snappymail/"
echo ""
