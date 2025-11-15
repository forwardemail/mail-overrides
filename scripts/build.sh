#!/bin/bash
#
# Build Script for Forward Email SnappyMail
# Copies built SnappyMail from mail/ submodule to dist/ and applies overrides
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(dirname "$SCRIPT_DIR")"

echo "========================================"
echo "Forward Email SnappyMail Build"
echo "========================================"
echo ""

cd "$ROOT_DIR"

# Determine version to use
# 1. Check for APP_VERSION environment variable (matches SnappyMail convention)
# 2. Default to 0.0.0 (local Docker development)
echo "→ Environment check:"
echo "  APP_VERSION env var: ${APP_VERSION:-<not set>}"
echo "  Current user: $(whoami)"
echo "  Working directory: $(pwd)"

if [ -n "$APP_VERSION" ]; then
    VERSION="$APP_VERSION"
    echo "→ Using version from APP_VERSION env: $VERSION"
else
    VERSION="0.0.0"
    echo "→ Using default development version: $VERSION"
    echo "  ⚠ WARNING: APP_VERSION not set, defaulting to 0.0.0"
fi

# Check if mail submodule exists
if [ ! -e "mail/.git" ] && [ ! -d "mail/snappymail" ]; then
    echo "ERROR: mail submodule not initialized!"
    echo ""
    echo "Run: git submodule update --init --recursive"
    exit 1
fi

# Check if SnappyMail is built in mail/
if [ ! -f "mail/snappymail/v/0.0.0/static/css/boot.min.css" ]; then
    echo "ERROR: SnappyMail not built in mail/ submodule!"
    echo ""
    echo "SnappyMail needs to be built first. Run:"
    echo ""
    echo "  cd mail"
    echo "  npm ci  # Use npm ci for locked dependencies"
    echo "  npx gulp"
    echo "  cd .."
    echo ""
    echo "Then run this build script again."
    exit 1
fi

# Clean and create dist directory
echo "→ Preparing dist/ directory..."
rm -rf dist
mkdir -p dist
echo "  ✓ dist/ directory ready"

# Copy entire mail/ contents to dist/
echo "→ Copying SnappyMail from mail/ to dist/..."
rsync -a --exclude='.git' --exclude='node_modules' --exclude='.docker' mail/ dist/
echo "  ✓ SnappyMail copied to dist/"

# Patch index.php to use dynamic version and force production mode
echo "→ Patching index.php (version: $VERSION, production mode)..."
PHP_BIN="${PHP_BIN:-$(command -v php || true)}"
if [ -n "$PHP_BIN" ]; then
    "$PHP_BIN" -r "
\$version = '$VERSION';
\$content = file_get_contents('dist/index.php');

// Replace APP_VERSION
\$content = preg_replace(
    \"/define\\('APP_VERSION',\\s*'[^']*'\\);/\",
    \"define('APP_VERSION', '\$version');\",
    \$content
);

// Force production mode
\$search = \"define('APP_INDEX_ROOT_PATH', __DIR__ . DIRECTORY_SEPARATOR);\";
\$replace = \"define('APP_INDEX_ROOT_PATH', __DIR__ . DIRECTORY_SEPARATOR);\n}\n\n// Force production mode\nif (!defined('SNAPPYMAIL_DEV')) {\n\tdefine('SNAPPYMAIL_DEV', false);\";
\$content = str_replace(\$search, \$replace, \$content);

file_put_contents('dist/index.php', \$content);
"
    echo "  ✓ Version set to $VERSION, production mode forced"

    # Verify the patch was applied
    PATCHED_VERSION=$(grep "define('APP_VERSION'" dist/index.php | grep -oP "'[0-9]+\.[0-9]+\.[0-9]+'")
    echo "  ✓ Verified: dist/index.php APP_VERSION = $PATCHED_VERSION"
else
    echo "  ⚠ PHP CLI not found; skipping version/production mode patch."
    echo "    Install PHP or set PHP_BIN to enable versioning and production mode."
    echo "  This means APP_VERSION will remain as 0.0.0 in index.php!"
fi

# Set up versioned directory structure
# If VERSION is not 0.0.0, we need to copy from v/0.0.0 to v/$VERSION
if [ "$VERSION" != "0.0.0" ]; then
    echo "→ Creating versioned directory structure for v/$VERSION..."

    if [ ! -d "dist/snappymail/v/0.0.0" ]; then
        echo "ERROR: Source directory dist/snappymail/v/0.0.0 not found!"
        exit 1
    fi

    # Copy 0.0.0 to the versioned directory
    mkdir -p "dist/snappymail/v/$VERSION"
    rsync -a dist/snappymail/v/0.0.0/ "dist/snappymail/v/$VERSION/"

    # Remove the 0.0.0 directory to keep only the versioned one
    rm -rf dist/snappymail/v/0.0.0

    echo "  ✓ Versioned directory created: dist/snappymail/v/$VERSION"
fi

SNAPPYMAIL_VERSION_DIR="dist/snappymail/v/$VERSION"
echo "→ Using SnappyMail version directory: $SNAPPYMAIL_VERSION_DIR"
echo ""

# Apply overrides: Sync plugins
echo "→ Applying plugin overrides..."
mkdir -p "$SNAPPYMAIL_VERSION_DIR/plugins"
mkdir -p dist/data/_data_/_default_/plugins
for plugin_dir in plugins/*/; do
    if [ -d "$plugin_dir" ]; then
        plugin_name=$(basename "$plugin_dir")
        echo "  → Copying $plugin_name plugin..."
        rsync -a "$plugin_dir" "$SNAPPYMAIL_VERSION_DIR/plugins/$plugin_name/"
        rsync -a "$plugin_dir" "dist/data/_data_/_default_/plugins/$plugin_name/"
    fi
done
echo "  ✓ All plugins applied"

# Apply overrides: Sync theme
echo "→ Applying theme overrides..."
mkdir -p "$SNAPPYMAIL_VERSION_DIR/themes"
rsync -a themes/ForwardEmail/ "$SNAPPYMAIL_VERSION_DIR/themes/ForwardEmail/"
echo "  ✓ Theme applied"

# Apply overrides: Copy include.php if exists
if [ -f "configs/include.php" ]; then
    echo "→ Applying include.php configuration..."
    cp configs/include.php dist/include.php
    echo "  ✓ Configuration applied"
fi

# Prepare data directory structure
echo "→ Preparing data directory..."
mkdir -p dist/data/_data_/_default_/configs
mkdir -p dist/data/_data_/_default_/domains

# Apply overrides: Copy application.ini if exists
if [ -f "configs/application.ini" ]; then
    echo "→ Applying application.ini..."
    cp configs/application.ini dist/data/_data_/_default_/configs/
    echo "  ✓ Application config applied"
fi

# Apply overrides: Copy plugins.ini if exists
if [ -f "configs/plugins.ini" ]; then
    echo "→ Applying plugins.ini..."
    cp configs/plugins.ini dist/data/_data_/_default_/configs/
    echo "  ✓ Plugin config applied"
fi

# Apply overrides: Copy plugin JSON configuration files
if compgen -G "configs/plugin-*.json" > /dev/null; then
    echo "→ Applying plugin JSON configs..."
    cp configs/plugin-*.json dist/data/_data_/_default_/configs/
    echo "  ✓ Plugin JSON configs applied"
fi

# Apply overrides: Copy domain configurations
if [ -d "configs/domains" ]; then
    echo "→ Applying domain configurations..."
    rsync -a configs/domains/ dist/data/_data_/_default_/domains/
    echo "  ✓ Domain configs applied"
fi

echo ""
echo "========================================"
echo "✓ Build Complete!"
echo "========================================"
echo ""
echo "Built SnappyMail with overrides in: ./dist/"
echo ""
echo "Next steps:"
echo "  • Test with Docker: docker-compose -f docker/docker-compose.yml up --build"
echo "  • Test with PHP: cd dist && php -S localhost:8000"
echo "  • Deploy: rsync -av dist/ user@server:/var/www/snappymail/"
echo ""
