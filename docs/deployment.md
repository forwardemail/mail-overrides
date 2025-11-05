# Deployment Guide

## Architecture Overview

```
forwardemail.net (main monorepo)
├── ansible/                   # Shared deployment configs
│   ├── playbooks/
│   │   └── deploy-webmail.yml
│   ├── roles/
│   │   └── webmail/
│   └── inventory/
│       ├── staging
│       └── production
└── mail-overrides/            # This repo (submodule)
    ├── mail/                  # forwardemail/mail (nested submodule)
    ├── plugins/forwardemail/  # FE customizations
    └── themes/ForwardEmail/   # FE customizations
```

## Local Development

Docker is provided for local development only.

### PHP Built-in Server (Fastest)

```bash
cd mail-overrides
./scripts/build.sh
cd dist && php -S localhost:8000
```

### Docker

```bash
cd mail-overrides
./scripts/build.sh
docker-compose -f docker/docker-compose.yml up
```

Visit http://localhost:8080

## Production Deployment

All production deployments are handled by Ansible from the `forwardemail.net` monorepo.

### Prerequisites

1. `mail-overrides` is added as submodule in `forwardemail.net`
2. Ansible playbooks configured in `forwardemail.net/ansible/`
3. SSH access configured to production servers

### Deployment Flow

#### 1. Update mail-overrides Repository

```bash
cd /path/to/mail-overrides

# Make changes
vim plugins/forwardemail/templates/Views/User/Login.html

# Test locally
./scripts/build.sh
cd dist && php -S localhost:8000

# Commit and push
git add plugins/
git commit -m "Update login branding"
git push origin master
```

#### 2. Update forwardemail.net Monorepo

```bash
cd /path/to/forwardemail.net

# Update mail-overrides submodule
cd mail-overrides
git pull origin master
cd ..

# Commit submodule update
git add mail-overrides
git commit -m "Update mail-overrides with new branding"
git push
```

#### 3. Deploy with Ansible

```bash
cd /path/to/forwardemail.net

# Deploy to staging
ansible-playbook ansible/playbooks/deploy-webmail.yml \
  -i ansible/inventory/staging \
  --limit webmail_servers

# Deploy to production
ansible-playbook ansible/playbooks/deploy-webmail.yml \
  -i ansible/inventory/production \
  --limit webmail_servers
```

## Ansible Integration

The Ansible playbook should:

1. **Build** - Run `mail-overrides/scripts/build.sh` locally
2. **Sync** - Deploy `mail-overrides/dist/` to servers
3. **Configure** - Use shared SSL certs, nginx configs from monorepo
4. **Permissions** - Set correct ownership and permissions
5. **Verify** - Check deployment succeeded

### Example Playbook Tasks

```yaml
- name: Build webmail with Forward Email customizations
  command: ./scripts/build.sh
  args:
    chdir: "{{ playbook_dir }}/../../mail-overrides"
  delegate_to: localhost
  run_once: true

- name: Sync webmail to server
  synchronize:
    src: "{{ playbook_dir }}/../../mail-overrides/dist/"
    dest: /var/www/webmail/
    delete: yes

- name: Apply shared nginx configuration
  template:
    src: templates/webmail-nginx.conf.j2
    dest: /etc/nginx/sites-available/webmail.conf
  notify: reload nginx

- name: Apply shared SSL certificates
  copy:
    src: "{{ ssl_cert_path }}"
    dest: /etc/ssl/certs/
  notify: reload nginx
```

## Shared Resources from Monorepo

The `forwardemail.net` monorepo provides:

- **SSL Certificates** - Let's Encrypt certs managed centrally
- **Nginx Configs** - Shared reverse proxy configurations
- **Security Settings** - Firewall rules, fail2ban
- **Monitoring** - Prometheus, alerts
- **Backup Scripts** - Automated backups

## Environment-Specific Deployment

### Staging

```bash
ansible-playbook ansible/playbooks/deploy-webmail.yml \
  -i ansible/inventory/staging \
  -e "webmail_domain=mail-staging.forwardemail.net"
```

### Production

```bash
ansible-playbook ansible/playbooks/deploy-webmail.yml \
  -i ansible/inventory/production \
  -e "webmail_domain=mail.forwardemail.net"
```

## Rollback

To rollback:

```bash
cd /path/to/forwardemail.net/mail-overrides

# Revert to previous commit
git checkout <previous-commit-hash>
cd ..

# Update and deploy
git add mail-overrides
git commit -m "Rollback mail-overrides"
ansible-playbook ansible/playbooks/deploy-webmail.yml -i ansible/inventory/production
```

## Verification

After deployment:

```bash
# Check service
curl -I https://mail.forwardemail.net

# SSH to server and verify
ssh webmail-server
ls -la /var/www/webmail/snappymail/v/0.0.0/plugins/forwardemail/
ls -la /var/www/webmail/snappymail/v/0.0.0/themes/ForwardEmail/

# Check nginx
sudo nginx -t
sudo systemctl status nginx

# Check permissions
ls -la /var/www/webmail/data/
```

## Troubleshooting

### Build fails in Ansible

```bash
# Run build manually
cd /path/to/forwardemail.net/mail-overrides
./scripts/build.sh

# Check for errors
ls -la dist/snappymail/v/0.0.0/plugins/
```

### Sync fails

```bash
# Check rsync works
rsync -avz --dry-run mail-overrides/dist/ server:/var/www/webmail/
```

### Customizations don't appear

```bash
# SSH to server
ssh webmail-server

# Verify files exist
ls -la /var/www/webmail/snappymail/v/0.0.0/plugins/forwardemail/
ls -la /var/www/webmail/snappymail/v/0.0.0/themes/ForwardEmail/

# Check config
cat /var/www/webmail/data/_data_/_default_/configs/application.ini | grep forwardemail
```

### Permission issues

```bash
# SSH to server
sudo chown -R www-data:www-data /var/www/webmail/
sudo chmod -R 755 /var/www/webmail/
sudo chmod -R 755 /var/www/webmail/data/
```
