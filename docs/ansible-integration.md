# Ansible Integration Guide

This guide explains how to integrate `mail-overrides` with the main Forward Email monorepo's Ansible deployment.

## Adding as Submodule to Monorepo

```bash
cd /path/to/forwardemail/monorepo

# Add as submodule
git submodule add https://github.com/forwardemail/mail-overrides.git mail-overrides

# Initialize and update
git submodule update --init --recursive

# Commit
git add .gitmodules mail-overrides
git commit -m "Add mail-overrides as submodule"
```

## Directory Structure in Monorepo

```
forwardemail/
├── ansible/
│   ├── playbooks/
│   │   └── deploy-snappymail.yml
│   ├── roles/
│   │   └── snappymail/
│   │       ├── tasks/
│   │       │   └── main.yml
│   │       ├── templates/
│   │       └── vars/
│   └── inventory/
│       ├── staging
│       └── production
├── mail-overrides/          # Submodule
│   ├── mail/                   # Submodule
│   ├── plugins/
│   ├── themes/
│   └── scripts/
└── ...
```

## Ansible Playbook

Create `ansible/playbooks/deploy-snappymail.yml`:

```yaml
---
- name: Deploy Forward Email SnappyMail
  hosts: webmail_servers
  become: yes
  vars:
    repo_root: "{{ playbook_dir }}/../.."
    snappymail_source: "{{ repo_root }}/mail-overrides"
    snappymail_dest: "/var/www/snappymail"
    snappymail_user: "www-data"
    snappymail_group: "www-data"
  
  pre_tasks:
    - name: Build SnappyMail customizations locally
      command: ./scripts/build.sh
      args:
        chdir: "{{ snappymail_source }}"
      delegate_to: localhost
      run_once: true
      become: no

  roles:
    - snappymail

  post_tasks:
    - name: Verify deployment
      uri:
        url: "https://{{ ansible_host }}"
        validate_certs: yes
        status_code: 200
      delegate_to: localhost
```

## Ansible Role

**Note**: Forward Email uses Nginx (not Apache) for production deployments. The actual deployment playbook is in `forwardemail.net/ansible/playbooks/mail.yml`.

Below is a reference example for a role-based approach:

Create `ansible/roles/snappymail/tasks/main.yml`:

```yaml
---
- name: Install required packages
  apt:
    name:
      - php8.2-fpm
      - php8.2-cli
      - php8.2-intl
      - php8.2-opcache
      - php8.2-zip
      - php8.2-xml
      - php8.2-redis
      - nginx
    state: present
    update_cache: yes

- name: Create SnappyMail directory
  file:
    path: "{{ snappymail_dest }}"
    state: directory
    owner: "{{ snappymail_user }}"
    group: "{{ snappymail_group }}"
    mode: '0755'

- name: Sync SnappyMail files
  synchronize:
    src: "{{ snappymail_source }}/dist/"
    dest: "{{ snappymail_dest }}/"
    delete: yes
    rsync_opts:
      - "--exclude=.git"
    owner: no
    group: no
  notify: reload nginx

- name: Set ownership
  file:
    path: "{{ snappymail_dest }}"
    owner: "{{ snappymail_user }}"
    group: "{{ snappymail_group }}"
    recurse: yes

- name: Ensure data directory exists with correct permissions
  file:
    path: "{{ snappymail_dest }}/data"
    state: directory
    owner: "{{ snappymail_user }}"
    group: "{{ snappymail_group }}"
    mode: '0700'
    recurse: yes

- name: Create configs directory
  file:
    path: "{{ snappymail_dest }}/data/_data_/_default_/configs"
    state: directory
    owner: "{{ snappymail_user }}"
    group: "{{ snappymail_group }}"
    mode: '0755'

- name: Copy application.ini
  copy:
    src: "{{ snappymail_source }}/configs/application.ini"
    dest: "{{ snappymail_dest }}/data/_data_/_default_/configs/application.ini"
    owner: "{{ snappymail_user }}"
    group: "{{ snappymail_group }}"
    mode: '0644'
    force: no  # Don't overwrite if exists

- name: Copy include.php
  copy:
    src: "{{ snappymail_source }}/configs/include.php"
    dest: "{{ snappymail_dest }}/include.php"
    owner: "{{ snappymail_user }}"
    group: "{{ snappymail_group }}"
    mode: '0644'

- name: Configure Nginx site
  template:
    src: snappymail-site.conf.j2
    dest: /etc/nginx/sites-available/snappymail.conf
    mode: '0644'
    validate: 'nginx -t -c /etc/nginx/nginx.conf'
  notify: reload nginx

- name: Enable SnappyMail site
  file:
    src: /etc/nginx/sites-available/snappymail.conf
    dest: /etc/nginx/sites-enabled/snappymail.conf
    state: link
  notify: reload nginx

- name: Verify ForwardEmail plugin exists
  stat:
    path: "{{ snappymail_dest }}/snappymail/v/0.0.0/plugins/forwardemail"
  register: plugin_check
  failed_when: not plugin_check.stat.exists

- name: Verify ForwardEmail theme exists
  stat:
    path: "{{ snappymail_dest }}/snappymail/v/0.0.0/themes/ForwardEmail"
  register: theme_check
  failed_when: not theme_check.stat.exists
```

Create `ansible/roles/snappymail/handlers/main.yml`:

```yaml
---
- name: reload nginx
  service:
    name: nginx
    state: reloaded
```

## Nginx Site Configuration Template

The actual production template is at `forwardemail.net/ansible/playbooks/templates/snappymail/snappymail-site.conf.j2`.

Create `ansible/roles/snappymail/templates/snappymail-site.conf.j2`:

```nginx
# SnappyMail Nginx Configuration

upstream php-fpm {
    server unix:/run/php/php8.2-fpm.sock;
    keepalive 32;
}

{% if lookup('env', 'MAIL_SSL_ENABLED') | default('false') == 'true' %}
# Redirect HTTP to HTTPS
server {
    listen 80;
    listen [::]:80;
    server_name {{ snappymail_domain }};
    return 301 https://$server_name$request_uri;
}
{% endif %}

server {
{% if lookup('env', 'MAIL_SSL_ENABLED') | default('false') == 'true' %}
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    ssl_certificate /etc/letsencrypt/live/{{ snappymail_domain }}/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/{{ snappymail_domain }}/privkey.pem;
{% else %}
    listen 80;
    listen [::]:80;
{% endif %}

    server_name {{ snappymail_domain }};
    root {{ snappymail_dest }}/dist;
    index index.php;
    charset utf-8;

    # Security headers
    add_header X-Frame-Options "DENY" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Logging
    access_log /var/log/nginx/snappymail-access.log;
    error_log /var/log/nginx/snappymail-error.log warn;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        # Security: Don't execute PHP in writable directories
        location ~ ^/(data|cache)/ {
            deny all;
            return 404;
        }

        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass php-fpm;
        fastcgi_index index.php;
    }

    # Deny access to data directory
    location ^~ /data/ {
        deny all;
        return 404;
    }

    # Deny access to hidden files
    location ~ /\. {
        deny all;
        return 404;
    }
}
```

## Inventory Files

`ansible/inventory/production`:

```ini
[webmail_servers]
mail01.forwardemail.net ansible_user=deploy

[webmail_servers:vars]
snappymail_domain=mail.forwardemail.net
env=production
```

`ansible/inventory/staging`:

```ini
[webmail_servers]
mail-staging.forwardemail.net ansible_user=deploy

[webmail_servers:vars]
snappymail_domain=mail-staging.forwardemail.net
env=staging
```

## Running Deployments

```bash
cd /path/to/forwardemail/monorepo

# Deploy to staging
ansible-playbook \
  -i ansible/inventory/staging \
  ansible/playbooks/deploy-snappymail.yml

# Deploy to production
ansible-playbook \
  -i ansible/inventory/production \
  ansible/playbooks/deploy-snappymail.yml

# Deploy with verbose output
ansible-playbook \
  -i ansible/inventory/production \
  ansible/playbooks/deploy-snappymail.yml \
  -vvv

# Dry run (check mode)
ansible-playbook \
  -i ansible/inventory/production \
  ansible/playbooks/deploy-snappymail.yml \
  --check
```

## Updating SnappyMail Version

```bash
# Update mail-overrides submodule
cd mail-overrides

# Update SnappyMail version
./scripts/update-snappymail.sh v2.38.0

# Go back to monorepo root
cd ..

# Commit the submodule update
git add mail-overrides
git commit -m "Update SnappyMail to v2.38.0"

# Deploy
ansible-playbook -i ansible/inventory/staging ansible/playbooks/deploy-snappymail.yml
```

## CI/CD Integration

Add to `.github/workflows/deploy-snappymail.yml`:

```yaml
name: Deploy SnappyMail

on:
  push:
    branches: [main]
    paths:
      - 'mail-overrides/**'

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
        with:
          submodules: recursive

      - name: Setup Ansible
        run: |
          pip install ansible
          
      - name: Deploy to staging
        run: |
          ansible-playbook \
            -i ansible/inventory/staging \
            ansible/playbooks/deploy-snappymail.yml
        env:
          ANSIBLE_HOST_KEY_CHECKING: false
```

## Monitoring

Add checks to ensure deployment succeeded:

```yaml
- name: Check SnappyMail is responding
  uri:
    url: "https://{{ snappymail_domain }}"
    status_code: 200
    validate_certs: yes
  retries: 3
  delay: 5

- name: Verify plugin is loaded
  command: >
    grep -q 'forwardemail'
    {{ snappymail_dest }}/data/_data_/_default_/configs/application.ini
  changed_when: false
```
