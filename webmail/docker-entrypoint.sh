#!/bin/bash
set -e

DATA_PATH="${APP_DATA_FOLDER_PATH:-/var/www/html/data/}"
PRIVATE_DATA="${DATA_PATH}_data_/_default_/"
CONFIG_DIR="${PRIVATE_DATA}configs/"
DOMAINS_DIR="${PRIVATE_DATA}domains/"

# Start Apache in background
apache2-foreground &
APACHE_PID=$!

# Function to apply ForwardEmail configuration
apply_config() {
    # Wait for SnappyMail setup to complete
    while [ ! -f "${PRIVATE_DATA}admin_password.txt" ] || [ ! -d "$CONFIG_DIR" ]; do
        sleep 2
    done

    echo "Applying ForwardEmail configuration..."

    # Wait for application.ini to be created
    while [ ! -f "${CONFIG_DIR}application.ini" ]; do
        sleep 1
    done

    sleep 1  # Extra wait for file to be fully written

    # Update application.ini with ForwardEmail branding and settings
    sed -i 's/^title = .*/title = "ForwardEmail Webmail"/' "${CONFIG_DIR}application.ini"
    sed -i 's/^loading_description = .*/loading_description = "ForwardEmail - Privacy-focused email hosting"/' "${CONFIG_DIR}application.ini"
    sed -i 's|^favicon_url = .*|favicon_url = "https://raw.githubusercontent.com/forwardemail/forwardemail.net/master/assets/img/logo-square.svg"|' "${CONFIG_DIR}application.ini"
    sed -i 's/^theme = .*/theme = "ForwardEmail"/' "${CONFIG_DIR}application.ini"
    sed -i 's/^allow_themes = .*/allow_themes = Off/' "${CONFIG_DIR}application.ini"

    # Add cache settings if not present
    if ! grep -q "\[cache\]" "${CONFIG_DIR}application.ini"; then
        cat >> "${CONFIG_DIR}application.ini" <<EOF

[cache]
enable = Off
server_uids = Off
system_data = Off
http = On
EOF
    fi

    # Update contacts to be disabled
    if grep -q "\[contacts\]" "${CONFIG_DIR}application.ini"; then
        sed -i '/\[contacts\]/,/^\[/ { /^enable = /c\enable = Off
}' "${CONFIG_DIR}application.ini"
    fi

    echo "Updated application.ini with ForwardEmail branding"

    # Remove default domain configs (gmail, hotmail, yahoo, etc.)
    echo "Removing default domain configurations..."
    find "${DOMAINS_DIR}" -type f \( -name "gmail.com.json" -o -name "hotmail.com.json" -o -name "yahoo.com.json" -o -name "outlook.com.json" -o -name "aol.com.json" \) -delete

    # Remove all .json domain configs except disabled file
    find "${DOMAINS_DIR}" -type f -name "*.json" ! -name "default.json" -delete
    echo "Removed default domain configurations"

    # Replace default.json with ForwardEmail settings (applies to ALL domains)
    if [ -f "/var/www/html/forwardemail.net.json" ]; then
        cp /var/www/html/forwardemail.net.json "${DOMAINS_DIR}default.json"
        chown www-data:www-data "${DOMAINS_DIR}default.json"
        chmod 600 "${DOMAINS_DIR}default.json"
        echo "Set default.json to ForwardEmail configuration (applies to all domains)"
    fi

    # Also create forwardemail.net.json for explicit domain matching
    if [ ! -f "${DOMAINS_DIR}forwardemail.net.json" ] && [ -f "/var/www/html/forwardemail.net.json" ]; then
        cp /var/www/html/forwardemail.net.json "${DOMAINS_DIR}forwardemail.net.json"
        chown www-data:www-data "${DOMAINS_DIR}forwardemail.net.json"
        chmod 600 "${DOMAINS_DIR}forwardemail.net.json"
        echo "Created ForwardEmail domain configuration"
    fi
}

# Run config application in background
apply_config &

# Wait for Apache process
wait $APACHE_PID