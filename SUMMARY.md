# Hosted-SnappyMail Setup Summary

## What Was Created

A complete `hosted-snappymail` repository configured for:
- **Local Development**: Docker and PHP built-in server
- **Production Deployment**: Ansible integration with main monorepo
- **Version Control**: Git submodule approach for clean separation

## Repository Structure

```
hosted-snappymail/
├── plugins/forwardemail/     # ✓ Custom plugin with branded login
├── themes/ForwardEmail/       # ✓ Custom theme with styles
├── configs/                   # ✓ Pre-configured settings
├── scripts/                   # ✓ Build and deployment automation
├── docker/                    # ✓ Local development only
└── docs/                      # ✓ Comprehensive documentation
```

## Key Design Decisions

### 1. Submodule Approach (Option 4)
- `hosted-snappymail` is the primary deployment repo
- Contains `mail/` as Git submodule to SnappyMail upstream
- Customizations live at root level, not in submodule
- Clean separation of concerns

### 2. Docker for Local Dev Only
- Simplified Docker setup focused on development
- Production uses Ansible from main monorepo
- No production-specific Docker configurations

### 3. Ansible Integration
- Comprehensive Ansible playbook examples provided
- `hosted-snappymail` will be submodule in main monorepo
- Automated deployment to staging and production

## Workflow Summary

### Development
```bash
# Edit customizations
vim plugins/forwardemail/templates/Views/User/Login.html

# Build and test
./scripts/build.sh
cd mail && php -S localhost:8000

# Commit
git commit -am "Update login page"
git push
```

### Production Deployment
```bash
# From main monorepo
cd forwardemail/hosted-snappymail
git pull
cd ..
git add hosted-snappymail
git commit -m "Update hosted-snappymail"

# Deploy with Ansible
ansible-playbook ansible/playbooks/deploy-snappymail.yml
```

## Next Steps

1. **Initialize mail submodule**:
   ```bash
   cd /Users/shaunwarman/Development/Source/empire/hosted-snappymail
   git submodule add https://github.com/the-djmaze/snappymail.git mail
   chmod +x scripts/*.sh
   ./scripts/build.sh
   ```

2. **Test locally**:
   ```bash
   cd mail && php -S localhost:8000
   # Visit http://localhost:8000
   ```

3. **Push to GitHub**:
   ```bash
   git add .
   git commit -m "Initial hosted-snappymail setup"
   git remote add origin https://github.com/forwardemail/hosted-snappymail.git
   git push -u origin main
   ```

4. **Integrate with monorepo**:
   ```bash
   cd /path/to/forwardemail/monorepo
   git submodule add https://github.com/forwardemail/hosted-snappymail.git hosted-snappymail
   ```

5. **Set up Ansible**:
   - Follow `docs/ansible-integration.md`
   - Create playbooks in monorepo
   - Configure inventory for staging/production

## Files Created

### Configuration
- `configs/application.ini` - Plugin enabled, theme set
- `configs/include.php` - Custom PHP settings
- `.gitignore` - Excludes build artifacts
- `.gitmodules` - Submodule configuration

### Scripts
- `scripts/build.sh` - Sync customizations to mail/
- `scripts/deploy.sh` - Local deployment helper
- `scripts/update-snappymail.sh` - Version updater

### Docker (Local Dev)
- `docker/Dockerfile` - Development container
- `docker/docker-compose.yml` - Local dev environment
- `docker/.env.example` - Environment variables

### Documentation
- `README.md` - Main documentation
- `SETUP.md` - Initial setup guide
- `docs/deployment.md` - Deployment guide
- `docs/development.md` - Development workflow
- `docs/ansible-integration.md` - Ansible integration guide

### Customizations
- `plugins/forwardemail/` - Complete plugin with branded login
- `themes/ForwardEmail/` - Complete theme with styles

## Benefits of This Approach

✅ **Clean Separation**: Customizations separate from upstream  
✅ **Easy Updates**: Update SnappyMail via submodule  
✅ **Version Control**: Track both SnappyMail and customizations  
✅ **Local Dev**: Quick iteration with Docker or PHP  
✅ **Production Ready**: Ansible integration for deployment  
✅ **Maintainable**: Clear structure and documentation  

## Important Notes

- Docker is **LOCAL DEVELOPMENT ONLY**
- Production deployment uses **ANSIBLE from main monorepo**
- Never edit files in `mail/` submodule directly
- Always run `./scripts/build.sh` after changes
- Plugin and theme auto-enabled via `application.ini`
