# Local Testing Guide

This guide walks you through testing the mail-overrides setup locally with Docker, including the Redis ephemeral sessions plugin.

## Prerequisites

- Docker and Docker Compose installed
- Git submodules initialized

## Quick Start

### 1. Initialize Submodules

```bash
git submodule update --init --recursive
```

### 2. Build SnappyMail (First Time Only)

SnappyMail must be compiled before use:

```bash
cd mail
npm install
npx gulp

cd ..
```

This builds the CSS/JS assets. Only needed once (or when updating the mail submodule).

### 3. Build Distribution

```bash
# Make scripts executable
chmod +x scripts/*.sh

# Build dist/ with SnappyMail + overrides
./scripts/build.sh
```

This copies `mail/` → `dist/` and applies your plugins/themes.

### 4. Start Docker Environment

```bash
# Build and start containers
docker-compose -f docker/docker-compose.yml up --build

# Or run in detached mode
docker-compose -f docker/docker-compose.yml up -d --build
```

This will start:
- **SnappyMail** on http://localhost:8080 (serving from `dist/`)
- **Redis** on localhost:6379

**Note:** Predis (PHP Redis client) is automatically installed on container startup.

### 5. Configure SnappyMail Admin

1. Visit http://localhost:8080/?admin
2. Default admin credentials are usually:
   - Username: `admin`
   - Password: `12345` (change immediately!)

3. If this is first setup, you'll need to create admin account

### 6. Enable and Configure Plugins

#### Enable ForwardEmail Plugin
1. Go to **Plugins** tab
2. Find **ForwardEmail** plugin
3. Click **Enable**

#### Configure Redis Ephemeral Sessions Plugin

1. Go to **Plugins** tab
2. Find **Redis Ephemeral Sessions** plugin
3. Click **Configure** and enter:

| Setting | Value | Notes |
|---------|-------|-------|
| Redis Host | `redis-dev` | Docker service name |
| Redis Port | `6379` | Default Redis port |
| Use TLS | `false` | Not needed for local dev |
| Redis Password | *(leave empty)* | Not set in local Redis |
| Session TTL | `14400` | 4 hours (default) |
| Key Mask Secret | *(see below)* | Generate with command below |

**Generate Key Mask Secret:**
```bash
openssl rand -base64 32
```
Copy the output and paste it into the "Key Mask Secret" field.

4. Click **Save** then **Enable**

#### Enable Client IP Passthrough Plugin (if needed)
1. Go to **Plugins** tab
2. Find **Client IP Passthrough** plugin
3. Click **Enable**

### 7. Test the Setup

#### Test SnappyMail is Running
Visit http://localhost:8080 - you should see the Forward Email branded login page.

#### Test Redis Connection

**Method 1: Via Redis CLI**
```bash
# Test Redis is running
docker exec -it snappymail-redis-dev redis-cli ping
# Expected output: PONG
```

**Method 2: Via SnappyMail Plugin API**
Open browser console on http://localhost:8080 and run:
```javascript
await fetch('?/Json/&q[]=/0/', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ Action: 'PluginRedisTestConnection' })
}).then(r => r.json()).then(console.log);
```

Expected output:
```json
{
  "Result": {
    "success": true,
    "message": "Redis connection successful"
  }
}
```

#### Test Email Login
1. Configure an email account (IMAP/SMTP settings)
2. Login with credentials
3. Check that session is stored in Redis:

```bash
# List Redis session keys
docker exec -it snappymail-redis-dev redis-cli KEYS "snappymail:v1:session:*"

# Count active sessions
docker exec -it snappymail-redis-dev redis-cli --scan --pattern "snappymail:v1:session:*" | wc -l
```

### 8. View Logs

**SnappyMail Logs:**
```bash
docker logs -f snappymail-local-dev
```

**Redis Logs:**
```bash
docker logs -f snappymail-redis-dev
```

**SnappyMail Application Logs:**
```bash
# View logs from inside container
docker exec -it snappymail-local-dev cat /var/www/html/data/logs/log-*.txt | grep "REDIS-SESSION"
```

## Development Workflow

### Making Changes to Plugins

1. Edit plugin files in `plugins/` directory (NOT in `mail/` or `dist/`)
2. Run build script to rebuild dist/:
   ```bash
   ./scripts/build.sh
   ```
3. Restart Docker container:
   ```bash
   docker-compose -f docker/docker-compose.yml restart snappymail-dev
   ```
4. Refresh browser to see changes

**Note:** The `mail/` submodule stays clean - you never edit it directly. The `dist/` directory is rebuilt each time you run `build.sh`.

### Rebuilding Docker Containers

```bash
# Rebuild and restart
docker-compose -f docker/docker-compose.yml up --build

# Or rebuild specific service
docker-compose -f docker/docker-compose.yml build snappymail-dev
docker-compose -f docker/docker-compose.yml up snappymail-dev
```

### Clearing Redis Sessions

```bash
# Delete all sessions
docker exec -it snappymail-redis-dev redis-cli FLUSHDB

# Delete specific session pattern
docker exec -it snappymail-redis-dev redis-cli --scan --pattern "snappymail:v1:session:*" | xargs docker exec -i snappymail-redis-dev redis-cli DEL
```

### Clean Build Artifacts

```bash
# Remove dist/ directory
./scripts/clean.sh

# Rebuild from scratch
./scripts/build.sh
```

## Troubleshooting

### "SnappyMail not built"

The mail submodule needs to be built first:

```bash
cd mail
npm install
npx gulp

cd ..
./scripts/build.sh
```

### "Predis library not found"

Predis should be installed automatically on container startup. If you still see this error:

**Solution:**
```bash
# Check container logs to see if Predis installation failed
docker logs snappymail-local-dev

# Manually install Predis in the container
docker exec -it snappymail-local-dev composer install

# Or restart the container to trigger auto-install
docker-compose -f docker/docker-compose.yml restart snappymail-dev
```

### "Redis connection failed"

**Check Redis is running:**
```bash
docker ps | grep redis
docker exec -it snappymail-redis-dev redis-cli ping
```

**Check Redis host configuration:**
- In SnappyMail admin, verify Redis Host is set to `redis-dev` (not `127.0.0.1`)

### "Plugin not appearing in admin panel"

**Solution:**
```bash
# Rebuild dist/ with plugins
./scripts/build.sh

# Check plugins directory in dist/
ls -la dist/snappymail/v/0.0.0/plugins/

# Restart container
docker-compose -f docker/docker-compose.yml restart snappymail-dev
```

### "WebCrypto not available"

This happens when accessing SnappyMail over HTTP (not HTTPS).

**For local development:** Modern browsers allow WebCrypto on `localhost` even over HTTP, so this shouldn't be an issue.

**If still seeing error:** Try accessing via `https://localhost:8080` (you'll get a cert warning, that's OK for dev).

### Port Conflicts

If port 8080 or 6379 is already in use:

**Option 1: Stop conflicting services**
```bash
# Find what's using the port
lsof -i :8080
lsof -i :6379

# Kill the process or stop the service
```

**Option 2: Change ports in docker-compose.yml**
```yaml
ports:
  - "8081:80"  # Change from 8080
```

## Stopping the Environment

```bash
# Stop containers (preserves data)
docker-compose -f docker/docker-compose.yml stop

# Stop and remove containers (preserves volumes)
docker-compose -f docker/docker-compose.yml down

# Stop and remove everything including volumes (fresh start)
docker-compose -f docker/docker-compose.yml down -v
```

## Monitoring Redis in Real-Time

```bash
# Watch Redis commands in real-time
docker exec -it snappymail-redis-dev redis-cli MONITOR

# View Redis stats
docker exec -it snappymail-redis-dev redis-cli INFO stats

# View memory usage
docker exec -it snappymail-redis-dev redis-cli INFO memory
```

## Testing Checklist

Before deploying to production, verify:

- [ ] SnappyMail loads at http://localhost:8080
- [ ] Forward Email theme is applied (branded login page)
- [ ] All 3 plugins appear in admin panel
- [ ] Redis connection test succeeds
- [ ] Can login with email credentials
- [ ] Session is stored in Redis (check with `KEYS` command)
- [ ] Session persists across page refreshes
- [ ] Session expires after TTL (test with short TTL)
- [ ] Logout deletes session from Redis
- [ ] No errors in Docker logs
- [ ] No errors in SnappyMail application logs

## Next Steps

Once local testing is complete:
1. Commit changes to `mail-overrides` repository
2. Update `forwardemail.net` monorepo to pull latest `mail-overrides`
3. Deploy with Ansible from main monorepo
