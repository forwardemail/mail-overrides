#!/bin/bash
set -e

# Entrypoint script for SnappyMail development container
# Automatically installs Predis if not present

echo "========================================"
echo "SnappyMail Development Container"
echo "========================================"

# Prepare data directory (mounted volume)
echo "→ Preparing data directory..."
mkdir -p /var/www/html/data
echo "  ✓ Data directory exists"

# Seed data directory with build output defaults when needed
SEED_DIR="/seed-data"
TARGET_DATA="/var/www/html/data"
TARGET_CONFIG="${TARGET_DATA}/_data_/_default_/configs/application.ini"
SEED_CONFIG="${SEED_DIR}/_data_/_default_/configs/application.ini"
TARGET_PLUGINS="${TARGET_DATA}/_data_/_default_/configs/plugins.ini"
SEED_PLUGINS="${SEED_DIR}/_data_/_default_/configs/plugins.ini"

if [ -d "${SEED_DIR}/_data_" ]; then
    if [ ! -d "${TARGET_DATA}/_data_" ]; then
        echo "→ Seeding SnappyMail data directory from build artifacts..."
        cp -a "${SEED_DIR}/." "${TARGET_DATA}/"
        echo "  ✓ Data directory seeded"
    fi

    if [ -f "${SEED_CONFIG}" ]; then
        echo "→ Refreshing application.ini with Forward Email defaults..."
        mkdir -p "$(dirname "${TARGET_CONFIG}")"
        cp "${SEED_CONFIG}" "${TARGET_CONFIG}"
        echo "  ✓ application.ini refreshed"
    fi

    if [ -f "${SEED_PLUGINS}" ]; then
        echo "→ Refreshing plugins.ini with Forward Email defaults..."
        mkdir -p "$(dirname "${TARGET_PLUGINS}")"
        cp "${SEED_PLUGINS}" "${TARGET_PLUGINS}"
        echo "  ✓ plugins.ini refreshed"
    fi

    for PLUGIN_JSON in "${SEED_DIR}"/_data_/_default_/configs/plugin-*.json; do
        [ -e "$PLUGIN_JSON" ] || continue
        PLUGIN_BASENAME=$(basename "$PLUGIN_JSON")
        TARGET_PLUGIN_JSON="${TARGET_DATA}/_data_/_default_/configs/${PLUGIN_BASENAME}"
        echo "→ Refreshing ${PLUGIN_BASENAME} configuration..."
        mkdir -p "$(dirname "${TARGET_PLUGIN_JSON}")"
        cp "${PLUGIN_JSON}" "${TARGET_PLUGIN_JSON}"
        echo "  ✓ ${PLUGIN_BASENAME} refreshed"
    done

    # Sync plugin directories into data storage (SnappyMail loads plugins from here)
    if [ -d "${SEED_DIR}/_data_/_default_/plugins" ]; then
        echo "→ Syncing plugin directories into data storage..."
        rm -rf "${TARGET_DATA}/_data_/_default_/plugins"
        mkdir -p "${TARGET_DATA}/_data_/_default_/plugins"
        cp -a "${SEED_DIR}/_data_/_default_/plugins/." "${TARGET_DATA}/_data_/_default_/plugins/"
        echo "  ✓ Plugins synced"
    fi

    # Ensure wildcard domain defaults point to Forward Email services
    SEED_DOMAIN="${SEED_DIR}/_data_/_default_/domains/default.json"
    TARGET_DOMAIN_DIR="${TARGET_DATA}/_data_/_default_/domains"
    TARGET_DOMAIN="${TARGET_DOMAIN_DIR}/default.json"
    if [ -f "${SEED_DOMAIN}" ]; then
        echo "→ Refreshing default domain configuration..."
        mkdir -p "${TARGET_DOMAIN_DIR}"
        cp "${SEED_DOMAIN}" "${TARGET_DOMAIN}"
        echo "  ✓ Domain defaults refreshed"
    fi
else
    echo "→ Seed directory not found; skipping data seeding."
fi

# Reapply secure permissions after seeding
echo "→ Applying secure permissions to data directory..."
chown -R www-data:www-data "${TARGET_DATA}"
find "${TARGET_DATA}" -type d -exec chmod 700 {} +
find "${TARGET_DATA}" -type f -exec chmod 600 {} +
echo "  ✓ Permissions updated"

# Set up /dev/shm cache directory
echo "→ Setting up cache directory in /dev/shm..."
mkdir -p /dev/shm/snappymail-cache
chown -R www-data:www-data /dev/shm/snappymail-cache
chmod -R 755 /dev/shm/snappymail-cache
echo "  ✓ Cache directory ready"

# Check if Predis is installed
if [ ! -d "vendor/predis" ]; then
    echo "→ Predis not found, installing..."

    # Check if composer.json exists
    if [ ! -f "composer.json" ]; then
        echo "  Creating composer.json..."
        cat > composer.json << 'EOF'
{
    "require": {
        "predis/predis": "^2.0"
    }
}
EOF
    fi

    # Install Predis
    composer install --no-dev --optimize-autoloader
    echo "  ✓ Predis installed"
else
    echo "→ Predis already installed"
fi

echo "========================================"
echo "Starting Apache..."
echo "========================================"

# Execute the original Apache command
exec apache2-foreground
