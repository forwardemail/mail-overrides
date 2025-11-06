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

Create `ansible/roles/snappymail/tasks/main.yml`:

```yaml
---
- name: Install required packages
  apt:
    name:
      - php8.2
      - php8.2-intl
      - php8.2-opcache
      - php8.2-mysql
      - php8.2-zip
      - php8.2-xml
      - apache2
      - libapache2-mod-php8.2
    state: present
    update_cache: yes

- name: Enable Apache modules
  apache2_module:
    name: "{{ item }}"
    state: present
  loop:
    - rewrite
    - headers
  notify: restart apache

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
  notify: restart apache

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
    mode: '0755'

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

- name: Configure Apache vhost
  template:
    src: snappymail-vhost.conf.j2
    dest: /etc/apache2/sites-available/snappymail.conf
    mode: '0644'
  notify: restart apache

- name: Enable SnappyMail site
  command: a2ensite snappymail
  args:
    creates: /etc/apache2/sites-enabled/snappymail.conf
  notify: restart apache

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
- name: restart apache
  service:
    name: apache2
    state: restarted
```

## Apache VHost Template

Create `ansible/roles/snappymail/templates/snappymail-vhost.conf.j2`:

```apache
<VirtualHost *:80>
    ServerName {{ snappymail_domain }}
    DocumentRoot {{ snappymail_dest }}

    # Redirect to HTTPS
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}$1 [R=301,L]
</VirtualHost>

<VirtualHost *:443>
    ServerName {{ snappymail_domain }}
    DocumentRoot {{ snappymail_dest }}

    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/{{ snappymail_domain }}/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/{{ snappymail_domain }}/privkey.pem

    <Directory {{ snappymail_dest }}>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/snappymail-error.log
    CustomLog ${APACHE_LOG_DIR}/snappymail-access.log combined
</VirtualHost>
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
