# Architecture Overview

## Three-Layer Repository Structure

```
┌──────────────────────────────────────────────────────────────────┐
│ Layer 1: Upstream SnappyMail                                    │
│ https://github.com/the-djmaze/snappymail                        │
│ - Open source webmail project                                   │
│ - Upstream development                                          │
└──────────────────────────────────────────────────────────────────┘
                              ↓ (fork/track)
┌──────────────────────────────────────────────────────────────────┐
│ Layer 2: forwardemail/mail                                      │
│ https://github.com/forwardemail/mail                            │
│ - Clean SnappyMail clone                                        │
│ - Stays in sync with upstream                                   │
│ - NO Forward Email customizations                               │
│ - Can contribute patches back upstream                          │
└──────────────────────────────────────────────────────────────────┘
                              ↓ (git submodule)
┌──────────────────────────────────────────────────────────────────┐
│ Layer 3: forwardemail/mail-overrides (THIS REPO)                │
│ https://github.com/forwardemail/mail-overrides                  │
│ - Forward Email specific customizations                         │
│ - plugins/forwardemail/  (branding)                             │
│ - themes/ForwardEmail/   (styling)                              │
│ - configs/              (pre-configured settings)               │
│ - scripts/build.sh      (syncs overrides into mail/)            │
│ - Submodule points to: forwardemail/mail                        │
└──────────────────────────────────────────────────────────────────┘
                              ↓ (git submodule)
┌──────────────────────────────────────────────────────────────────┐
│ Layer 4: forwardemail/forwardemail.net (MAIN MONOREPO)          │
│ https://github.com/forwardemail/forwardemail.net                │
│ - All Forward Email processes & services                        │
│ - Ansible deployment configurations                             │
│ - Shared security, SSL certificates, monitoring                 │
│ - Submodule points to: forwardemail/mail-overrides              │
│                                                                  │
│   Deployment: ansible-playbook deploy-webmail.yml               │
│   ↓                                                              │
│   1. Run mail-overrides/scripts/build.sh                        │
│   2. Deploy mail-overrides/mail/ to servers                     │
│   3. Apply shared nginx configs, SSL certs                      │
└──────────────────────────────────────────────────────────────────┘
```

## Benefits of This Architecture

### 1. Clean Upstream Tracking
- `forwardemail/mail` stays clean
- Easy to sync with upstream SnappyMail
- Can contribute patches back to community
- No merge conflicts with Forward Email branding

### 2. Isolated Customizations
- `mail-overrides` contains ONLY Forward Email changes
- Easy to maintain and update
- Clear separation of concerns
- Build script applies overrides on demand

### 3. Centralized Deployment
- `forwardemail.net` monorepo manages all services
- Shared Ansible configurations
- Reuse SSL certs, security configs, monitoring
- Single source of truth for production

### 4. Version Control
- Each layer independently versioned
- Can pin specific versions at each level
- Easy rollback at any layer
- Clear dependency chain

## Repository Purposes

### forwardemail/mail
**Purpose**: Clean SnappyMail clone that tracks upstream

**Contains**:
- Exact copy of the-djmaze/snappymail
- No Forward Email customizations
- Regularly synced with upstream

**Updates**: When upstream releases new version

### forwardemail/mail-overrides
**Purpose**: Bridge between clean SnappyMail and Forward Email branding

**Contains**:
- Forward Email plugins
- Forward Email themes
- Build scripts to apply overrides
- Submodule to forwardemail/mail

**Updates**: When changing Forward Email branding

### forwardemail/forwardemail.net
**Purpose**: Main monorepo for all Forward Email services

**Contains**:
- All Forward Email processes
- Ansible deployment
- Shared infrastructure configs
- Submodule to mail-overrides

**Updates**: Continuous deployment

## Workflow Examples

### Updating SnappyMail Version

```bash
# 1. Update forwardemail/mail (sync with upstream)
cd /path/to/mail
git remote add upstream https://github.com/the-djmaze/snappymail.git
git fetch upstream
git merge upstream/master
git push origin master

# 2. Update mail submodule in mail-overrides
cd /path/to/mail-overrides
cd mail && git pull && cd ..
git add mail
git commit -m "Update SnappyMail to v2.38.0"
git push

# 3. Update mail-overrides in forwardemail.net
cd /path/to/forwardemail.net
cd mail-overrides && git pull && cd ..
git add mail-overrides
git commit -m "Update SnappyMail to v2.38.0"

# 4. Deploy
ansible-playbook ansible/playbooks/deploy-webmail.yml
```

### Updating Forward Email Branding

```bash
# 1. Update mail-overrides only
cd /path/to/mail-overrides
vim plugins/forwardemail/templates/Views/User/Login.html
./scripts/build.sh && cd mail && php -S localhost:8000  # Test
git commit -am "Update login page branding"
git push

# 2. Update in forwardemail.net
cd /path/to/forwardemail.net
cd mail-overrides && git pull && cd ..
git add mail-overrides
git commit -m "Update webmail branding"

# 3. Deploy
ansible-playbook ansible/playbooks/deploy-webmail.yml
```

## File Flow

```
Development:
plugins/forwardemail/           (source)
    ↓ (./scripts/build.sh)
mail/snappymail/.../plugins/    (built)
    ↓ (ansible deploy)
/var/www/webmail/.../plugins/   (production)

Production Access:
User → https://mail.forwardemail.net
    ↓ (nginx from forwardemail.net ansible)
/var/www/webmail/               (mail-overrides/mail/ deployed)
    ↓ (loads)
/var/www/webmail/.../plugins/forwardemail/  (Forward Email branding)
/var/www/webmail/.../themes/ForwardEmail/   (Forward Email theme)
```

## Key Principles

1. **forwardemail/mail**: Never contains Forward Email branding
2. **mail-overrides**: Never deployed directly (only its mail/ subdirectory)
3. **forwardemail.net**: Owns all production infrastructure
4. **Build before deploy**: Always run build.sh before deploying
5. **Test locally first**: Use PHP or Docker to test changes

This architecture ensures clean separation, easy updates, and maintainable deployments.
