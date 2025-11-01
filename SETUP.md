# Setup Guide

## Initial Setup

### 1. Clone mail-overrides

```bash
git clone https://github.com/forwardemail/mail-overrides.git
cd mail-overrides
```

### 2. Initialize mail submodule

```bash
# Initialize the forwardemail/mail submodule
# (which contains the SnappyMail clone)
git submodule update --init --recursive
```

### 3. Build customizations

```bash
chmod +x scripts/*.sh
./scripts/build.sh
```

This syncs Forward Email plugins/themes into the `mail/` directory.

### 4. Test locally

```bash
cd mail
php -S localhost:8000
# Visit http://localhost:8000
```

You should see the Forward Email branded login page.

## Integration with forwardemail.net Monorepo

### Add mail-overrides to Main Monorepo

```bash
cd /path/to/forwardemail.net

# Add as submodule
git submodule add https://github.com/forwardemail/mail-overrides.git mail-overrides

# Initialize all nested submodules
git submodule update --init --recursive

# Commit
git add .gitmodules mail-overrides
git commit -m "Add mail-overrides submodule"
```

### Directory Structure in forwardemail.net

```
forwardemail.net/
├── ansible/
│   └── playbooks/
│       └── deploy-webmail.yml
├── mail-overrides/              # This repo (submodule)
│   ├── mail/                    # forwardemail/mail (nested submodule)
│   ├── plugins/
│   ├── themes/
│   └── scripts/
└── ...
```

### Update Ansible Playbook

Edit `forwardemail.net/ansible/playbooks/deploy-webmail.yml`:

```yaml
---
- name: Deploy SnappyMail Webmail
  hosts: webmail_servers
  become: yes
  vars:
    mail_overrides_path: "{{ playbook_dir }}/../../mail-overrides"
    webmail_dest: "/var/www/webmail"
  
  tasks:
    - name: Build mail with Forward Email customizations
      command: ./scripts/build.sh
      args:
        chdir: "{{ mail_overrides_path }}"
      delegate_to: localhost
      run_once: true

    - name: Deploy webmail
      synchronize:
        src: "{{ mail_overrides_path }}/mail/"
        dest: "{{ webmail_dest }}"
        delete: yes
        rsync_opts:
          - "--exclude=data/*"

    - name: Set permissions
      file:
        path: "{{ webmail_dest }}"
        owner: www-data
        group: www-data
        recurse: yes
```

## Development Workflow

### Making Changes

```bash
cd /path/to/mail-overrides

# Edit customizations
vim plugins/forwardemail/templates/Views/User/Login.html

# Build and test
./scripts/build.sh
cd mail && php -S localhost:8000

# Commit
git add plugins/
git commit -m "Update login branding"
git push
```

### Deploy to Production

```bash
cd /path/to/forwardemail.net

# Update mail-overrides submodule
cd mail-overrides && git pull && cd ..
git add mail-overrides
git commit -m "Update mail-overrides"

# Deploy with Ansible
ansible-playbook ansible/playbooks/deploy-webmail.yml
```

## Verify Installation

After deployment:

1. Visit your webmail domain
2. Confirm Forward Email branding on login page
3. Access admin: `https://mail.forwardemail.net/?admin`
4. Check Extensions → Plugins → "Forward Email" is enabled
5. Check Settings → General → Theme is "ForwardEmail"

## Troubleshooting

### Submodules not initialized
```bash
git submodule update --init --recursive
```

### Build doesn't sync files
```bash
# Check mail submodule is initialized
ls -la mail/snappymail/

# Rebuild
./scripts/build.sh

# Verify
ls -la mail/snappymail/v/0.0.0/plugins/forwardemail/
```

### forwardemail.net can't find mail-overrides
```bash
cd /path/to/forwardemail.net
git submodule update --init --recursive
```
