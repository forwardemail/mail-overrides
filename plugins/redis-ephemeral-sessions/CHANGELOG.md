# Changelog

All notable changes to the Redis Ephemeral Sessions plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.1] - 2025-11-11

### Changed
- Bumped plugin version so SnappyMail cache invalidates and new JS bundle (with `Plugin` JSON actions) loads reliably.
- Documented the updated test command for the JSON action prefix.

## [1.0.0] - 2025-10-27

### Added
- Initial release of Redis Ephemeral Sessions plugin
- Client-side AES-GCM 256-bit encryption using WebCrypto API
- PBKDF2 key derivation with 100,000 iterations
- Redis storage with automatic TTL expiry
- HMAC-SHA256 Redis key masking for security
- Ephemeral secret management via sessionStorage
- JSON API endpoints for session CRUD operations
- Comprehensive configuration via SnappyMail admin panel
- Support for Redis TLS connections
- Support for Redis AUTH password authentication
- Session refresh/extend functionality
- Session status and TTL checking
- Automatic cleanup on logout
- Secret generation utility script
- Comprehensive documentation and examples
- Security best practices guide
- Troubleshooting guide

### Security
- Zero plaintext password persistence on disk
- All credentials encrypted client-side before transmission
- HMAC-protected Redis keys prevent enumeration attacks
- Ephemeral secrets automatically cleared on tab close
- Configurable session TTL for automatic expiry
- Support for Redis TLS encryption
- Support for Redis password authentication

## [Unreleased]

### Planned
- Redis Cluster support for horizontal scaling
- Redis Sentinel support for high availability
- Session sharing across multiple SnappyMail instances
- WebAuthn integration for hardware-backed encryption keys
- KMS envelope encryption for additional security layer
- Session analytics and monitoring dashboard
- Automatic key rotation mechanism
- TOTP-based session re-authentication
- Rate limiting for API endpoints
- Session conflict resolution for multi-device scenarios

---

[1.0.1]: https://github.com/forwardemail/snappymail-redis-sessions/releases/tag/v1.0.1
[1.0.0]: https://github.com/forwardemail/snappymail-redis-sessions/releases/tag/v1.0.0
