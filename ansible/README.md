# Ansible deployment for the `wlsb` Bedrock site

Production-grade provisioning and zero-downtime deployment for this
[Roots Bedrock](https://roots.io/bedrock/) WordPress application on a single
Ubuntu 24.04 LTS VPS.

The setup is split in two:

| Playbook | What it does | How often you run it |
| --- | --- | --- |
| **`provision.yml`** | Turns a bare VPS into a hardened web server: security, PHP-FPM 8.3, MySQL 8.4, Nginx, Composer, WP-CLI, TLS. | Once per server, then whenever infra config changes. |
| **`deploy.yml`** | Ships application code as an atomic, rollback-able release. | Every release. |
| **`rollback.yml`** | Repoints `current` to the previous release. | When a release goes bad. |
| **`site.yml`** | `provision.yml` + `deploy.yml`. | First-time full bring-up. |

## Architecture

```
                        ┌──────────────────────── VPS (Ubuntu 24.04) ───────────────────────┐
   Git remote  ──clone──►  /srv/www/wlsb/                                                     │
   (deploy key)         │    releases/20260701T142530/   ← composer install --no-dev          │
                        │    releases/20260701T151002/  ┐                                     │
                        │    current ──────────────────►┘ (atomic symlink)                    │
                        │    shared/.env                  ← secrets, never in Git             │
                        │    shared/web/app/uploads/      ← persists across releases          │
                        │                                                                      │
   Browser ─https──► Nginx ──fastcgi──► PHP-FPM 8.3 (pool: wlsb, user: deploy) ──► MySQL 8.4  │
                        │    UFW + fail2ban + hardened SSH + unattended security upgrades      │
                        └──────────────────────────────────────────────────────────────────┘
```

- **Atomic releases.** Each deploy builds a fresh, timestamped release directory;
  going live is a single `rename(2)` of the `current` symlink, so visitors never
  see a partially-built site. Rollback is just flipping the symlink back.
- **Shared state.** `.env` and `web/app/uploads` live in `shared/` and are
  symlinked into every release, so config and media survive deploys.
- **Least privilege.** The app runs as the unprivileged `deploy` user. Deploys
  connect as `deploy` and never need root except one narrowly-scoped `sudo` rule
  to reload PHP-FPM.

## Directory layout

```
ansible/
├── ansible.cfg               # inventory default, SSH tuning, output formatting
├── requirements.yml          # Galaxy collections
├── site.yml / provision.yml / deploy.yml / rollback.yml
├── inventory/
│   ├── production/
│   │   ├── hosts.yml         # server connection details
│   │   └── group_vars/all/
│   │       ├── main.yml      # all non-secret config (edit this)
│   │       └── vault.yml     # encrypted secrets (you create this)
│   └── staging/…             # same shape, staging overrides
└── roles/
    ├── common          # base packages, timezone, swap, unattended-upgrades
    ├── deploy_user     # unprivileged app user + admin SSH keys + scoped sudo
    ├── ssh_hardening   # sshd drop-in (key-only auth, no root login)
    ├── firewall        # UFW default-deny + rate-limited SSH
    ├── fail2ban        # SSH brute-force protection
    ├── php             # PHP-FPM 8.3, extensions, OPcache, per-site pool
    ├── mysql           # MySQL 8.4 LTS, hardened, app DB + user
    ├── composer        # Composer + WP-CLI
    ├── wordpress       # Bedrock dir layout, shared .env, deploy key, WP-Cron
    ├── nginx           # vhost, TLS bootstrap, security headers
    ├── letsencrypt     # certbot cert issuance + auto-renew
    └── deploy          # atomic Git release + rollback
```

## Prerequisites

**Control machine (your laptop / CI runner):**

- Ansible ≥ 2.16 (`pipx install ansible` or `pip install ansible`)
- The Galaxy collections in `requirements.yml`

**Target VPS:**

- Fresh Ubuntu **24.04 LTS**
- SSH access as `root` (or a sudo user) using a key
- DNS A/AAAA records for your domain pointing at the server **before** you run
  provisioning with `letsencrypt_enabled: true` (the HTTP-01 challenge needs it)

## Quick start

```bash
cd ansible

# 1. Install collection dependencies.
ansible-galaxy collection install -r requirements.yml -p ./collections

# 2. Point the inventory at your server.
$EDITOR inventory/production/hosts.yml       # set ansible_host (+ ansible_user)

# 3. Create and encrypt your secrets.
cp inventory/production/group_vars/all/vault.yml.example \
   inventory/production/group_vars/all/vault.yml
$EDITOR inventory/production/group_vars/all/vault.yml    # fill in every CHANGE_ME
ansible-vault encrypt inventory/production/group_vars/all/vault.yml

# 4. Set your real configuration (domain, repo, sizing…).
$EDITOR inventory/production/group_vars/all/main.yml

# 5. Provision the server (creates the deploy user, hardens SSH, installs the stack).
ansible-playbook provision.yml -i inventory/production --ask-vault-pass

# 6. Ship the code.
ansible-playbook deploy.yml -i inventory/production --ask-vault-pass
```

Then finish WordPress setup once (creates the DB tables / admin user):

```bash
ssh deploy@your-server
cd /srv/www/wlsb/current
wp core install --url=https://example.com --title="WLSB" \
  --admin_user=admin --admin_email=you@example.com --prompt=admin_password
```

## Secrets (Ansible Vault)

Everything sensitive lives in `inventory/<env>/group_vars/all/vault.yml`,
encrypted at rest. `main.yml` references those `vault_*` values.

Generate strong values:

```bash
openssl rand -base64 24        # DB / MySQL root passwords
curl https://roots.io/salts.html   # the 8 WordPress salts
```

Manage the vault:

```bash
ansible-vault edit    inventory/production/group_vars/all/vault.yml
ansible-vault rekey   inventory/production/group_vars/all/vault.yml
```

To avoid typing the password each run, put it in a git-ignored file and set
`vault_password_file` in `ansible.cfg` (or use `--vault-password-file`).

**Required in the vault:** `vault_mysql_root_password`, `vault_db_password`,
`vault_wp_salts` (all 8), `vault_admin_ssh_keys` (≥ 1 public key). Add
`vault_deploy_private_key` when cloning a private repo.

## Deploying & rolling back

```bash
# Deploy the configured branch (main by default).
ansible-playbook deploy.yml -i inventory/production --ask-vault-pass

# Deploy a specific branch/tag.
ansible-playbook deploy.yml -i inventory/production -e "deploy_branch=release/2.0" --ask-vault-pass

# Roll back one release.
ansible-playbook rollback.yml -i inventory/production --ask-vault-pass

# Staging uses the same commands with a different inventory.
ansible-playbook deploy.yml -i inventory/staging --ask-vault-pass
```

Old releases are pruned automatically, keeping the newest `deploy_keep_releases`
(5 in production, 3 in staging).

## Useful tags

`provision.yml` is fully tagged so you can converge just one concern:

```bash
# Only re-apply the Nginx + TLS config.
ansible-playbook provision.yml -i inventory/production --tags nginx,letsencrypt --ask-vault-pass

# Only security hardening.
ansible-playbook provision.yml -i inventory/production --tags security --ask-vault-pass

# Everything except MySQL.
ansible-playbook provision.yml -i inventory/production --skip-tags mysql --ask-vault-pass
```

Available tags include: `common`, `security`, `ssh`, `firewall`, `fail2ban`,
`php`, `mysql`, `composer`, `wordpress`, `nginx`, `letsencrypt`, `deploy`.

## Key variables (`group_vars/all/main.yml`)

| Variable | Purpose | Default |
| --- | --- | --- |
| `site_domain` / `site_aliases` | Primary domain + extra `server_name`s | `example.com` |
| `deploy_repo` / `deploy_branch` | Git source of the app | — / `main` |
| `deploy_keep_releases` | Releases retained for rollback | `5` |
| `php_version` | PHP series (matches `composer.json`) | `8.3` |
| `php_fpm_pm_max_children` | FPM worker cap — tune to RAM | `12` |
| `mysql_version` | MySQL LTS series | `8.4` |
| `mysql_innodb_buffer_pool_mb` | InnoDB cache size | `256` |
| `letsencrypt_enabled` / `_email` | Issue a real TLS cert | `true` / — |
| `letsencrypt_staging` | Use LE staging (test certs) | `false` |
| `swap_file_size_mb` | Swap file size (0 = none) | `2048` |
| `ssh_permit_root_login` | Allow root SSH after hardening | `false` |

## Security posture

- **SSH:** key-only auth, root login disabled, `MaxAuthTries` limited, idle
  sessions timed out. Hardening refuses to run unless an admin key is present
  (no accidental lockout).
- **Firewall:** UFW default-deny inbound; only SSH/80/443 open; SSH rate-limited.
- **fail2ban:** bans SSH brute-forcers (reads journald).
- **Automatic updates:** unattended security upgrades enabled.
- **MySQL:** binds to `127.0.0.1` only, anonymous users/test DB removed, remote
  root disabled, app user scoped to its own database.
- **PHP:** `expose_php` off, per-site FPM pool as the `deploy` user,
  `security.limit_extensions=.php`, production OPcache.
- **Nginx:** `server_tokens off`, HSTS + security headers on HTTPS, modern TLS
  (Mozilla intermediate), dotfiles denied. Bedrock keeps `.env`/`config/`
  outside the docroot, so secrets are never web-reachable.
- **TLS:** Let's Encrypt with automatic renewal + Nginx reload hook.
- **Secrets:** all sensitive values in an encrypted Vault; `no_log` on tasks
  that handle them.

## Idempotency & re-runs

Every role is idempotent — re-running `provision.yml` converges configuration
without side effects, and a no-op run reports zero changes. Do a dry run first
with `--check --diff` when you want to preview changes.

## First-run notes & gotchas

- **DNS must resolve before TLS.** If the domain doesn't point at the server yet,
  set `letsencrypt_enabled: false`, provision, fix DNS, then re-run with it `true`
  (or use `--tags letsencrypt`). Use `letsencrypt_staging: true` while testing to
  avoid hitting Let's Encrypt rate limits.
- **Don't lock yourself out.** Put at least one working public key in
  `vault_admin_ssh_keys` before the first run — password auth gets disabled.
- **After the first provision** you can switch `ansible_user` in the inventory
  from `root` to a personal sudo user; `deploy.yml` always connects as `deploy`.
- **Host key verification** is on. On first connect, either accept the key
  interactively or pre-seed it: `ssh-keyscan <ip> >> ~/.ssh/known_hosts`.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| `certbot` fails | DNS points at the server? Port 80 open? Try `letsencrypt_staging: true`. |
| MySQL auth errors | `python3-pymysql` is installed by `common`; `/root/.my.cnf` holds root creds. |
| 502 Bad Gateway | `systemctl status php8.3-fpm`; confirm the socket `/run/php/wlsb-fpm.sock`. |
| Deploy can't clone | Deploy key added to the repo? `sudo -u deploy ssh -T git@github.com`. |
| Uploads not writable | `shared/web/app/uploads` is `0775` owned by `deploy`. |
| Changes not visible | Reload FPM (deploy does this) — OPcache uses `validate_timestamps=0`. |
