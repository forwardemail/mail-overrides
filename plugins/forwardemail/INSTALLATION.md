# Forward Email Plugin - Installation Guide

This plugin overrides the default SnappyMail login page with Forward Email branding.

## Method 1: Enable via Admin Panel (Manual)

1. Access the SnappyMail admin interface: `/?admin`
2. Go to **Extensions** → **Plugins**
3. Find **Forward Email** in the list
4. Click the **Enable** button
5. The plugin is now active

## Method 2: Enable by Default via Configuration File

### Option A: Direct application.ini Configuration

The plugin configuration is stored in: `data/_data_/_default_/configs/application.ini`

To enable the ForwardEmail plugin by default, edit this file and add/modify:

```ini
[plugins]
enable = On
enabled_list = "forwardemail"
```

If you need multiple plugins enabled:
```ini
[plugins]
enable = On
enabled_list = "forwardemail,other-plugin,another-plugin"
```

### Option B: Using include.php for Deployment Automation

For automated deployments or Docker containers, you can programmatically enable the plugin.

1. Create or edit `include.php` in the root directory (next to `index.php`):

```php
<?php

/**
 * Forward Email Custom Configuration
 * This file is loaded before SnappyMail initializes
 */

// Set custom data path (optional - for Docker/containerized deployments)
// define('APP_DATA_FOLDER_PATH', '/var/snappymail-data/');

// After SnappyMail initializes, enable the plugin programmatically
// This approach requires modifying the Config after it loads
```

2. **Better approach**: Use a post-installation script that modifies `application.ini`:

```bash
#!/bin/bash
# scripts/enable-forwardemail-plugin.sh

CONFIG_FILE="data/_data_/_default_/configs/application.ini"

# Wait for SnappyMail to create the config directory
while [ ! -f "$CONFIG_FILE" ]; do
  echo "Waiting for SnappyMail to initialize..."
  sleep 2
done

# Enable plugins if not already enabled
if ! grep -q "^enable = On" "$CONFIG_FILE"; then
  sed -i.bak '/^\[plugins\]/,/^\[/ s/^enable = .*/enable = On/' "$CONFIG_FILE"
fi

# Add forwardemail to enabled_list if not present
if ! grep -q "forwardemail" "$CONFIG_FILE"; then
  sed -i.bak '/^\[plugins\]/,/^\[/ s/^enabled_list = "\(.*\)"/enabled_list = "forwardemail,\1"/' "$CONFIG_FILE"
fi

echo "Forward Email plugin enabled!"
```

### Option C: Environment Variable Override (Advanced)

SnappyMail supports environment variable substitution in configuration files.

1. Edit `include.php`:
```php
<?php
// Enable environment variable replacement in config
define('APP_CONFIGURATION_NAME', getenv('SNAPPYMAIL_CONFIG_NAME') ?: 'application.ini');
```

2. Edit `application.ini` to use environment variables:
```ini
[labs]
replace_env_in_configuration = "SNAPPYMAIL_PLUGINS_ENABLED"

[plugins]
enable = On
enabled_list = "{SNAPPYMAIL_PLUGINS_ENABLED}"
```

3. Set the environment variable:
```bash
export SNAPPYMAIL_PLUGINS_ENABLED="forwardemail"
```

## Method 3: Docker/Container Deployment

For Docker deployments, mount a pre-configured `application.ini`:

```dockerfile
# Dockerfile
FROM your-base-image

# Copy pre-configured application.ini with plugin enabled
COPY ./configs/application.ini /var/www/html/data/_data_/_default_/configs/

# Or use initialization script
COPY ./scripts/init-snappymail.sh /docker-entrypoint.d/
RUN chmod +x /docker-entrypoint.d/init-snappymail.sh
```

Or use a startup script:

```bash
#!/bin/bash
# docker-entrypoint.d/init-snappymail.sh

CONFIG_DIR="/var/www/html/data/_data_/_default_/configs"
mkdir -p "$CONFIG_DIR"

# Create application.ini with ForwardEmail plugin enabled
cat > "$CONFIG_DIR/application.ini" <<EOF
[plugins]
enable = On
enabled_list = "forwardemail"
EOF

# Start PHP-FPM or Apache
exec "$@"
```

## Verification

To verify the plugin is enabled:

1. **Via Admin Panel**: Check Extensions → Plugins shows "Forward Email" as enabled
2. **Via File**: Check `data/_data_/_default_/configs/application.ini` contains:
   ```ini
   enabled_list = "forwardemail"
   ```
3. **Via Login Page**: Visit the login page and confirm Forward Email branding appears

## Troubleshooting

### Plugin doesn't appear in admin panel
- Ensure the plugin directory exists: `snappymail/v/0.0.0/plugins/forwardemail/`
- Check file permissions (directories: 755, files: 644)
- Verify `index.php` exists in the plugin directory

### Login page doesn't change
- Confirm plugin is enabled in admin panel
- Check browser cache (hard refresh: Ctrl+Shift+R or Cmd+Shift+R)
- Verify ForwardEmail theme is active
- Check browser console for JavaScript errors

### Configuration file doesn't exist
- SnappyMail creates config files on first run
- Access `/?admin` to initialize the application
- Check `data/_data_/_default_/configs/` directory exists

## Integration with ForwardEmail Theme

This plugin works together with the ForwardEmail theme:
- **Theme** (`themes/ForwardEmail/`): Provides CSS styling
- **Plugin** (`plugins/forwardemail/`): Provides HTML template override

Both should be active for complete branding.
