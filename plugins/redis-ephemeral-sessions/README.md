# Redis Ephemeral Sessions Plugin for SnappyMail

Secure, ephemeral session storage using Redis with client-side encryption.

## Overview

This plugin provides enterprise-grade security for SnappyMail by:

- **Client-side encryption** of credentials using WebCrypto (AES-GCM 256-bit)
- **Redis-backed storage** with automatic TTL expiry
- **Zero plaintext persistence** - credentials never touch disk in plain form
- **Ephemeral secrets** stored only in browser sessionStorage
- **HMAC-protected Redis keys** to prevent enumeration attacks

Perfect for privacy-focused deployments where zero-trust architecture is critical.

## Security Architecture

**Encryption Flow:**
1. Browser generates ephemeral secret `S` (crypto.randomUUID())
2. WebCrypto encrypts `{alias, password}` with AES-GCM using PBKDF2-derived key
3. Encrypted blob `{ciphertext, iv, salt}` sent to SnappyMail plugin
4. Plugin stores blob in Redis with `HMAC(alias, key_mask_secret)` as key
5. Redis expires key automatically after TTL (default: 4 hours)

**Decryption Flow:**
1. Frontend requests encrypted blob from Redis via plugin
2. Browser decrypts using ephemeral secret `S` from sessionStorage
3. Credentials used for IMAP connection (never stored)
4. Secret `S` cleared on tab close or logout

## Quick Start

### Prerequisites

- SnappyMail 2.36.0+
- Redis 5.0+
- PHP 7.4+ with JSON extension
- Predis library (PHP Redis client)

### Installation (5 minutes)

```bash
# 1. Install Predis in SnappyMail root directory
cd /path/to/snappymail
composer require predis/predis

# 2. Copy plugin to plugins directory
cp -r redis-ephemeral-sessions plugins/

# 3. Generate secret key
openssl rand -base64 32
# Save this output - you'll need it for configuration!
```

### Configuration

1. Log into SnappyMail **admin panel**
2. Navigate to **Plugins** → **Redis Ephemeral Sessions** → **Configure**
3. Enter settings:

| Setting | Example | Description |
|---------|---------|-------------|
| Redis Host | `127.0.0.1` | Redis server hostname/IP |
| Redis Port | `6379` | Redis server port |
| Use TLS | `false` | Enable for production |
| Redis Password | *(optional)* | AUTH password if enabled |
| Session TTL | `14400` | Expiration time (4 hours) |
| Key Mask Secret | *(from step 3)* | Base64 secret |

4. Click **Save** and **Enable** the plugin

### Test Connection

```bash
# Test Redis connection
redis-cli -h 127.0.0.1 -p 6379 ping
# Expected: PONG

# Test plugin API (in browser console)
await fetch('?/Json/&q[]=/0/', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ Action: 'PluginRedisTestConnection' })
}).then(r => r.json()).then(console.log);
// Expected: {Result: {success: true, message: "Redis connection successful"}}
```

## Usage

### Automatic Integration

The plugin automatically integrates with SnappyMail's authentication flow. No code changes required!

### Manual API Usage (Advanced)

```javascript
// Store encrypted session
await window.RedisEphemeralSession.storeSession(
  'user@example.com',
  'password123',
  { customMeta: 'optional metadata' }
);

// Retrieve and decrypt session
const credentials = await window.RedisEphemeralSession.retrieveSession('user@example.com');
console.log(credentials.alias, credentials.password);

// Delete session (logout)
await window.RedisEphemeralSession.deleteSession('user@example.com');

// Refresh session TTL
await window.RedisEphemeralSession.refreshSession('user@example.com');

// Get session status
const status = await window.RedisEphemeralSession.getSessionStatus('user@example.com');
console.log('TTL remaining:', status.ttl_remaining);
```

### Session Monitoring Example

```javascript
// Check session periodically and warn before expiry
async function monitorSession(email) {
  setInterval(async () => {
    const status = await window.RedisEphemeralSession.getSessionStatus(email);

    if (!status.exists) {
      console.warn('Session expired');
      window.location.href = '/login';
      return;
    }

    // Warn if < 5 minutes remaining
    if (status.ttl_remaining < 300) {
      if (confirm('Session expiring soon. Extend?')) {
        await window.RedisEphemeralSession.refreshSession(email);
      }
    }
  }, 60000); // Check every minute
}
```

## Configuration Reference

### Development (Local)
```ini
host = 127.0.0.1
port = 6379
use_tls = false
password = (empty)
ttl_seconds = 14400  # 4 hours
```

### Production (Secure)
```ini
host = redis.prod.example.com
port = 6380
use_tls = true
password = <strong-password>
ttl_seconds = 3600   # 1 hour
```

### Redis Sentinel (High Availability)

For Redis Sentinel support, modify `RedisHelper.php` connection parameters:

```php
$params = [
    'tcp://sentinel1:26379',
    'tcp://sentinel2:26379',
    'tcp://sentinel3:26379'
];
$options = ['replication' => 'sentinel', 'service' => 'mymaster'];
self::$client = new \Predis\Client($params, $options);
```

## Security Best Practices

### Deployment Checklist

- [ ] Use HTTPS for all SnappyMail traffic (required for WebCrypto)
- [ ] Enable Redis AUTH with a strong password
- [ ] Enable Redis TLS for production
- [ ] Firewall Redis to allow only SnappyMail server access
- [ ] Rotate `key_mask_secret` periodically (e.g., every 90 days)
- [ ] Set appropriate TTL based on security requirements
- [ ] Use Redis ACLs (Redis 6+) to restrict plugin access

### Redis Hardening

```bash
# redis.conf
bind 127.0.0.1 ::1
requirepass your-strong-redis-password

# Disable dangerous commands
rename-command FLUSHDB ""
rename-command FLUSHALL ""
rename-command CONFIG ""

# Enable TLS (Redis 6+)
tls-port 6380
tls-cert-file /path/to/redis.crt
tls-key-file /path/to/redis.key

# Set maxmemory policy
maxmemory 256mb
maxmemory-policy allkeys-lru
```

### Network Security

```bash
# Firewall: Allow only SnappyMail server to access Redis
iptables -A INPUT -p tcp -s <snappymail-server-ip> --dport 6379 -j ACCEPT
iptables -A INPUT -p tcp --dport 6379 -j DROP
```

## Troubleshooting

### "Predis library not found"
```bash
cd /path/to/snappymail
composer require predis/predis
```

### "Redis connection failed"
```bash
# Check Redis is running
systemctl status redis

# Test connection
redis-cli -h 127.0.0.1 -p 6379 ping

# Verify credentials
redis-cli -h host -p port -a password PING
```

### "key_mask_secret not configured"
Generate secret and add to plugin configuration:
```bash
openssl rand -base64 32
```

### "WebCrypto not available"
**Cause:** SnappyMail not served over HTTPS
**Solution:** Enable HTTPS (WebCrypto requires secure context)

### "Session not found or expired"
```bash
# Check Redis TTL
redis-cli TTL "snappymail:v1:session:*"

# Verify ephemeral secret exists
# In browser console:
console.log(sessionStorage.getItem('snappymail_ephemeral_secret'))
```

## Monitoring

### Redis Commands

```bash
# List session keys
redis-cli KEYS "snappymail:v1:session:*"

# Count active sessions
redis-cli --scan --pattern "snappymail:v1:session:*" | wc -l

# Check specific session TTL
redis-cli TTL "snappymail:v1:session:abc123..."

# Monitor Redis in real-time
redis-cli --stat

# Get memory usage
redis-cli INFO memory
```

### SnappyMail Logs

```bash
tail -f /path/to/snappymail/data/logs/log-*.txt | grep "REDIS-SESSION"
```

## Migration & Maintenance

### Rotating key_mask_secret

**Warning:** This invalidates ALL active sessions!

```bash
# Generate new secret
NEW_SECRET=$(openssl rand -base64 32)

# Update plugin configuration in SnappyMail admin panel
# Users will need to re-login

# Cleanup old Redis keys (optional)
redis-cli --scan --pattern "snappymail:v1:session:*" | xargs redis-cli DEL
```

### Upgrading from Standard Sessions

1. Backup existing SnappyMail data
2. Install and configure plugin
3. Enable plugin (existing sessions remain functional)
4. Users re-login to migrate to Redis sessions
5. Old sessions expire naturally

No data loss occurs - the plugin adds an additional storage layer.

## Performance

### Memory Sizing

```
Average session size: ~500 bytes (encrypted)
1,000 users: ~500 KB
10,000 users: ~5 MB
100,000 users: ~50 MB
```

### Latency

| Operation | Time |
|-----------|------|
| Encryption (client) | ~5-10ms |
| Key derivation (PBKDF2) | ~50-100ms |
| Redis SET/GET | ~1-5ms (local) |
| Full store cycle | ~60-120ms |

## File Structure

```
redis-ephemeral-sessions/
├── index.php              # Main plugin class (hooks, JSON APIs)
├── RedisHelper.php        # Redis connection and storage logic
├── assets/
│   └── session.js         # Client-side encryption/decryption
├── generate-secret.php    # CLI tool for secret generation
├── README.md              # This file
└── CHANGELOG.md           # Version history
```

## License

MIT License - see LICENSE file for details.

## Credits

Developed by [Forward Email](https://forwardemail.net) for privacy-focused email infrastructure.

Built with:
- [SnappyMail](https://snappymail.eu) - Modern webmail client
- [Predis](https://github.com/predis/predis) - PHP Redis client
- [WebCrypto API](https://www.w3.org/TR/WebCryptoAPI/) - Browser cryptography

## Support

- **Issues**: [GitHub Issues](https://github.com/the-djmaze/snappymail/issues)
- **Email**: support@forwardemail.net
- **Documentation**: This README
