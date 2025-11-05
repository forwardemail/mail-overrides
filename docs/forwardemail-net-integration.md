# Integration with forwardemail.net Monorepo

This guide explains how to integrate `mail-overrides` into the main `forwardemail.net` monorepo.

## Repository Structure

```
forwardemail.net/                  # Main monorepo
├── ansible/
│   ├── playbooks/
│   │   ├── deploy-webmail.yml    # Deploy SnappyMail
│   │   ├── deploy-api.yml        # Deploy API servers
│   │   └── ...                    # Other services
│   ├── roles/
│   │   ├── webmail/              # Webmail-specific tasks
│   │   ├── common/               # Shared security, SSL
│   │   └── ...
│   ├── inventory/
│   │   ├── production
│   │   └── staging
│   └── group_vars/
│       ├── all.yml               # Shared variables
│       └── webmail_servers.yml   # Webmail-specific vars
├── mail-overrides/               # Submodule (this repo)
│   ├── mail/                     # Nested submodule (forwardemail/mail)
│   ├── plugins/forwardemail/
│   ├── themes/ForwardEmail/
│   └── scripts/build.sh
└── ...                           # Other FE services
```

## Step 1: Add mail-overrides as Submodule

```bash
cd /path/to/forwardemail.net

# Add mail-overrides submodule
git submodule add https://github.com/forwardemail/mail-overrides.git mail-overrides

# Initialize all nested submodules
git submodule update --init --recursive

# Commit
git add .gitmodules mail-overrides
git commit -m "Add mail-overrides submodule for webmail customizations"
git push
```

## Step 2: Create Ansible Playbook

Create `ansible/playbooks/deploy-webmail.yml`:

```yaml
---
- name: Deploy SnappyMail Webmail with Forward Email Branding
  hosts: webmail_servers
  become: yes
  
  vars:
    # Paths
    repo_root: "{{ playbook_dir }}/../.."
    mail_overrides: "{{ repo_root }}/mail-overrides"
    webmail_dest: "/var/www/webmail"
    
    # Config
    webmail_user: "www-data"
    webmail_group: "www-data"
    webmail_domain: "{{ lookup('env', 'WEBMAIL_DOMAIN') | default('mail.forwardemail.net') }}"
    
  pre_tasks:
    - name: Ensure mail-overrides submodule is initialized
      command: git submodule update --init --recursive
      args:
        chdir: "{{ repo_root }}"
      delegate_to: localhost
      run_once: true
      become: no

    - name: Build webmail with Forward Email customizations
      command: ./scripts/build.sh
      args:
        chdir: "{{ mail_overrides }}"
      delegate_to: localhost
      run_once: true
      become: no
      register: build_result

    - name: Show build output
      debug:
        var: build_result.stdout_lines
      when: build_result is defined

  roles:
    - common              # Shared security, firewall, fail2ban
    - webmail             # Webmail-specific configuration

  post_tasks:
    - name: Verify deployment
      uri:
        url: "https://{{ webmail_domain }}"
        validate_certs: yes
        status_code: 200
      delegate_to: localhost
      retries: 3
      delay: 5
```

## Step 3: Create Webmail Role

Create `ansible/roles/webmail/tasks/main.yml`:

```yaml
---
- name: Install PHP and extensions
  apt:
    name:
      - php8.2
      - php8.2-fpm
      - php8.2-intl
      - php8.2-opcache
      - php8.2-mysql
      - php8.2-zip
      - php8.2-xml
      - php8.2-curl
    state: present
    update_cache: yes

- name: Create webmail directory
  file:
    path: "{{ webmail_dest }}"
    state: directory
    owner: "{{ webmail_user }}"
    group: "{{ webmail_group }}"
    mode: '0755'

- name: Sync webmail files from mail-overrides/dist
  synchronize:
    src: "{{ mail_overrides }}/dist/"
    dest: "{{ webmail_dest }}/"
    delete: yes
    rsync_opts:
      - "--exclude=.git"
      - "--exclude=.gitmodules"
    owner: no
    group: no

- name: Set ownership
  file:
    path: "{{ webmail_dest }}"
    owner: "{{ webmail_user }}"
    group: "{{ webmail_group }}"
    recurse: yes

- name: Ensure data directory exists
  file:
    path: "{{ webmail_dest }}/data/_data_/_default_/configs"
    state: directory
    owner: "{{ webmail_user }}"
    group: "{{ webmail_group }}"
    mode: '0755'

- name: Copy application.ini
  copy:
    src: "{{ mail_overrides }}/configs/application.ini"
    dest: "{{ webmail_dest }}/data/_data_/_default_/configs/application.ini"
    owner: "{{ webmail_user }}"
    group: "{{ webmail_group }}"
    mode: '0644'
    force: no

- name: Configure nginx for webmail
  template:
    src: webmail-nginx.conf.j2
    dest: /etc/nginx/sites-available/webmail.conf
    mode: '0644'
  notify: reload nginx

- name: Enable webmail site
  file:
    src: /etc/nginx/sites-available/webmail.conf
    dest: /etc/nginx/sites-enabled/webmail.conf
    state: link
  notify: reload nginx

- name: Verify Forward Email customizations
  stat:
    path: "{{ webmail_dest }}/snappymail/v/0.0.0/plugins/forwardemail"
  register: fe_plugin
  failed_when: not fe_plugin.stat.exists

- name: Verify Forward Email theme
  stat:
    path: "{{ webmail_dest }}/snappymail/v/0.0.0/themes/ForwardEmail"
  register: fe_theme
  failed_when: not fe_theme.stat.exists
```

Create `ansible/roles/webmail/handlers/main.yml`:

```yaml
---
- name: reload nginx
  service:
    name: nginx
    state: reloaded

- name: restart php-fpm
  service:
    name: php8.2-fpm
    state: restarted
```

## Step 4: Nginx Configuration Template

Create `ansible/roles/webmail/templates/webmail-nginx.conf.j2`:

```nginx
# Webmail - SnappyMail with Forward Email Branding

server {
    listen 80;
    server_name {{ webmail_domain }};

    # Redirect to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name {{ webmail_domain }};

    # SSL Configuration (using shared certs from common role)
    ssl_certificate {{ ssl_cert_path }}/{{ webmail_domain }}/fullchain.pem;
    ssl_certificate_key {{ ssl_cert_path }}/{{ webmail_domain }}/privkey.pem;
    ssl_trusted_certificate {{ ssl_cert_path }}/{{ webmail_domain }}/chain.pem;

    # SSL Settings (from common role)
    include /etc/nginx/snippets/ssl-params.conf;

    root {{ webmail_dest }};
    index index.php;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;

    # Logging
    access_log /var/log/nginx/webmail-access.log;
    error_log /var/log/nginx/webmail-error.log;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }

    location ~* ^/data/ {
        deny all;
    }
}
```

## Step 5: Inventory Configuration

Edit `ansible/inventory/production`:

```ini
[webmail_servers]
webmail01.forwardemail.net ansible_user=deploy
webmail02.forwardemail.net ansible_user=deploy

[webmail_servers:vars]
webmail_domain=mail.forwardemail.net
env=production
```

Edit `ansible/inventory/staging`:

```ini
[webmail_servers]
webmail-staging.forwardemail.net ansible_user=deploy

[webmail_servers:vars]
webmail_domain=mail-staging.forwardemail.net
env=staging
```

## Step 6: Group Variables

Create `ansible/group_vars/webmail_servers.yml`:

```yaml
---
# Webmail Server Configuration

# Paths
webmail_dest: /var/www/webmail
webmail_user: www-data
webmail_group: www-data

# PHP Settings
php_memory_limit: 256M
php_upload_max_filesize: 25M
php_post_max_size: 25M

# SSL (shared from common role)
ssl_cert_path: /etc/letsencrypt/live

# Backup
backup_enabled: true
backup_schedule: "0 2 * * *"  # 2 AM daily
backup_retention_days: 30
```

## Step 7: CI/CD Integration

Add to `.github/workflows/deploy-webmail.yml`:

```yaml
name: Deploy Webmail

on:
  push:
    branches: [main]
    paths:
      - 'mail-overrides/**'
      - 'ansible/playbooks/deploy-webmail.yml'
      - 'ansible/roles/webmail/**'

jobs:
  deploy-staging:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
        with:
          submodules: recursive

      - name: Setup Ansible
        run: pip install ansible

      - name: Deploy to staging
        env:
          ANSIBLE_HOST_KEY_CHECKING: False
        run: |
          ansible-playbook \
            ansible/playbooks/deploy-webmail.yml \
            -i ansible/inventory/staging

  deploy-production:
    needs: deploy-staging
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main'
    environment: production
    steps:
      - uses: actions/checkout@v3
        with:
          submodules: recursive

      - name: Setup Ansible
        run: pip install ansible

      - name: Deploy to production
        env:
          ANSIBLE_HOST_KEY_CHECKING: False
        run: |
          ansible-playbook \
            ansible/playbooks/deploy-webmail.yml \
            -i ansible/inventory/production
```

## Usage

### Update Webmail Branding

```bash
# 1. Make changes in mail-overrides repo
cd /path/to/mail-overrides
vim plugins/forwardemail/templates/Views/User/Login.html
git commit -am "Update login branding"
git push

# 2. Update in forwardemail.net
cd /path/to/forwardemail.net
cd mail-overrides && git pull && cd ..
git add mail-overrides
git commit -m "Update webmail branding"

# 3. Deploy
ansible-playbook ansible/playbooks/deploy-webmail.yml -i ansible/inventory/production
```

### Update SnappyMail Version

```bash
# 1. Update in forwardemail/mail repo first (separate process)

# 2. Update mail submodule in mail-overrides
cd /path/to/mail-overrides
cd mail && git pull && cd ..
git add mail
git commit -m "Update SnappyMail to v2.38.0"
git push

# 3. Update in forwardemail.net
cd /path/to/forwardemail.net
cd mail-overrides && git pull && cd ..
git add mail-overrides
git commit -m "Update SnappyMail"

# 4. Deploy
ansible-playbook ansible/playbooks/deploy-webmail.yml -i ansible/inventory/staging
# Test, then deploy to production
```

## Shared Resources

The `common` role provides:

- SSL certificate management (Let's Encrypt)
- Nginx base configuration
- Firewall rules (ufw)
- Fail2ban protection
- Security hardening
- Monitoring (Prometheus node exporter)
- Backup scripts

These are shared across all Forward Email services.
