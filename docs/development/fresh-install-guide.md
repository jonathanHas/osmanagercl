# OSManager CL - Fresh Install Guide

Complete instructions for setting up the OSManager CL Laravel application as a development
machine on a fresh Ubuntu or Kubuntu install.

> **Read this first — the app needs three databases, on two engines.**
>
> | Database | Engine | Where | Holds |
> |---|---|---|---|
> | `osmanagercl` | MariaDB 10.11 (native) | `127.0.0.1:3306` | The Laravel app — invoices, VAT returns, deliveries, aggregates |
> | `unicenta2016` | MySQL 5.7 (**Docker**) | `127.0.0.1:3307` | uniCenta POS snapshot — products, tickets, sales |
> | `OSAccounts` | MySQL 5.7 (**Docker**) | `127.0.0.1:3307` | Legacy accounts — supplier invoices, attachments |
>
> The two POS-side databases live together in a **Docker `mysql:5.7.33` container**, not in your
> system MySQL. Getting this wrong is the single most common way this install fails — see
> [section 5](#5-pos--osaccounts-database-container).

## Prerequisites

- Ubuntu / Kubuntu 22.04 or 24.04 LTS (reference dev machine: Ubuntu 24.04.4; Kubuntu shares the
  same base, so every command below applies unchanged)
- `sudo` access
- Git configured with SSH access to GitHub
- **On the shop LAN (`192.168.69.0/24`)** if you intend to import real data — the production
  server and the POS box are only reachable from there

---

## 1. System Packages

```bash
sudo apt update && sudo apt upgrade -y

# Apache
sudo apt install -y apache2

# PHP 8.3 and required extensions
sudo apt install -y php8.3 php8.3-cli php8.3-common php8.3-curl php8.3-gd \
    php8.3-mbstring php8.3-mysql php8.3-xml php8.3-zip php8.3-opcache \
    php8.3-readline php8.3-sqlite3 libapache2-mod-php8.3

# If php8.3 is not available in default repos (Ubuntu 22.04), add the PPA first:
# sudo add-apt-repository ppa:ondrej/php -y && sudo apt update

# MariaDB (for the Laravel application database)
sudo apt install -y mariadb-server

# Docker (for the uniCenta / OSAccounts MySQL 5.7 container)
sudo apt install -y docker.io
sudo usermod -aG docker $USER

# Node.js 22.x (via NodeSource)
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs

# Python 3 (for invoice parser scripts)
sudo apt install -y python3 python3-venv python3-pip

# System dependencies for invoice parser (OCR, PDF processing)
sudo apt install -y tesseract-ocr poppler-utils libreoffice

# CUPS client (Zebra label printing shells out to lp/lpstat)
sudo apt install -y cups-client

# Composer (PHP package manager)
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm composer-setup.php
```

> **Log out and back in** after `usermod -aG docker` — group membership is only applied to new
> login sessions. Verify with `docker ps` (it must work without `sudo`).

**Why MariaDB and not `mysql-server`?** Either works — Laravel's `mysql` driver talks to both,
and production runs MariaDB 10.11. `mariadb-server` simply matches the reference setup.

---

## 2. Configure Apache

### Enable required modules

```bash
sudo a2enmod rewrite
sudo a2enmod php8.3
```

`mod_rewrite` is mandatory — `public/.htaccess` is stock Laravel and needs `AllowOverride All`.

### Create the virtual host

```bash
sudo tee /etc/apache2/sites-available/osmanagercl.conf > /dev/null <<'EOF'
<VirtualHost *:80>
    ServerName osmanagercl.local
    DocumentRoot /var/www/html/osmanagercl/public

    <Directory /var/www/html/osmanagercl/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/osmanagercl-error.log
    CustomLog ${APACHE_LOG_DIR}/osmanagercl-access.log combined
</VirtualHost>
EOF
```

### Enable the site

```bash
sudo a2ensite osmanagercl.conf
sudo systemctl restart apache2
```

### Add local hostname

```bash
echo "127.0.0.1 osmanagercl.local" | sudo tee -a /etc/hosts
```

---

## 3. Application Database (MariaDB, port 3306)

```bash
sudo mysql
```

Inside the shell:

```sql
CREATE DATABASE osmanagercl CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'osmanager'@'localhost' IDENTIFIED BY 'YOUR_PASSWORD_HERE';
GRANT ALL PRIVILEGES ON osmanagercl.* TO 'osmanager'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

> Production names this database `osmanager`. On dev it is `osmanagercl`. The rename matters when
> importing a production dump — see [section 9a](#9a-application-database-from-production).

---

## 4. Clone the Repository

```bash
cd /var/www/html
git clone git@github.com:jonathanHas/osmanagercl.git
cd osmanagercl
```

> **Clone to exactly `/var/www/html/osmanagercl`.** Several `.env` values are absolute paths into
> this directory (`PYTHON_PARSER_DIR`, `PYTHON_VENV_PATH`, `INVOICE_PARSER_SCRIPT`). A different
> location works, but you must edit all of them.

### Set directory permissions

```bash
# Set ownership to your user and www-data group
sudo chown -R $USER:www-data /var/www/html/osmanagercl

# Storage and cache must be writable by Apache
sudo chmod -R 775 storage bootstrap/cache
```

---

## 5. POS / OSAccounts Database Container

uniCenta's schema is legacy and does not load cleanly on MySQL 8 or MariaDB, so the POS snapshot
runs in a pinned **MySQL 5.7.33** container. This is also why `config/database.php` sets
`'strict' => false` on both the `pos` and `osaccounts` connections.

The container serves **both** `unicenta2016` and `OSAccounts` on host port **3307**.

### ⚠️ 3306 vs 3307 — read this

Your machine now has two database servers. `127.0.0.1:3306` is MariaDB (the Laravel app);
`127.0.0.1:3307` is the container (POS + OSAccounts).

**`mysql`/`mysqldump` ignore `-P 3307` unless you also pass `-h 127.0.0.1`.** Without an explicit
host the client uses the local Unix socket, silently connecting to MariaDB instead. Every command
in this guide passes both, and so should yours.

Mixing these up is not hypothetical — it has caused real production bugs where F&V price sync
wrote to the wrong uniCenta instance. See
[Backend System Issues](../troubleshooting/backend-system-issues.md) and
[Known Issues](./known-issues.md).

### Create the container

```bash
# Config: allow connections from outside the container
cat > ~/mysql57_custom.cnf <<'EOF'
[mysqld]
bind-address = 0.0.0.0
EOF

# Persistent data directory (grows to ~2 GB once uniCenta is restored)
mkdir -p ~/mysql57_data

docker run -d \
  --name mysql57 \
  --restart unless-stopped \
  -p 3307:3306 \
  -e MYSQL_ROOT_PASSWORD='YOUR_ROOT_PASSWORD' \
  -v ~/mysql57_data:/var/lib/mysql \
  -v ~/mysql57_custom.cnf:/etc/mysql/conf.d/custom.cnf:ro \
  mysql:5.7.33
```

Give it ~30 seconds to initialise on first run, then check:

```bash
docker ps --filter name=mysql57
mysql -h 127.0.0.1 -P 3307 -u root -p -e "SELECT VERSION();"   # expect 5.7.33
```

### Create the databases and users

```bash
mysql -h 127.0.0.1 -P 3307 -u root -p <<'EOF'
CREATE DATABASE IF NOT EXISTS unicenta2016 CHARACTER SET utf8mb4;
CREATE DATABASE IF NOT EXISTS OSAccounts CHARACTER SET utf8mb4;

CREATE USER 'unicenta_user'@'%' IDENTIFIED BY 'YOUR_POS_PASSWORD';
GRANT SELECT, INSERT, UPDATE, DELETE ON unicenta2016.* TO 'unicenta_user'@'%';

CREATE USER 'osaccounts_user'@'%' IDENTIFIED BY 'YOUR_OSA_PASSWORD';
GRANT SELECT ON OSAccounts.* TO 'osaccounts_user'@'%';

FLUSH PRIVILEGES;
EOF
```

> **The POS user needs write access, not just `SELECT`.** The app writes back to uniCenta —
> F&V price sync updates `PRODUCTS.PRICESELL`, and product edits write `PRICEBUY`. A read-only
> POS user will make those features fail. `OSAccounts` is import-only, so `SELECT` is enough.

---

## 6. Install PHP Dependencies

```bash
cd /var/www/html/osmanagercl
composer install
```

---

## 7. Configure Environment

```bash
cp .env.example .env
php artisan key:generate
```

`.env.example` ships with SQLite defaults and a commented-out POS block — it does **not** reflect
a working setup. Edit `.env` to match this shape:

```env
APP_NAME="OS Manager"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://osmanagercl.local

# Laravel application database (MariaDB, port 3306)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=osmanagercl
DB_USERNAME=osmanager
DB_PASSWORD=YOUR_PASSWORD_HERE

# uniCenta POS snapshot (Docker container, port 3307)
POS_DB_HOST=127.0.0.1
POS_DB_PORT=3307
POS_DB_DATABASE=unicenta2016
POS_DB_USERNAME=unicenta_user
POS_DB_PASSWORD=YOUR_POS_PASSWORD

# OSAccounts legacy database (same container, port 3307)
INV_DB_HOST=127.0.0.1
INV_DB_PORT=3307
INV_DB_DATABASE=OSAccounts
INV_DB_USERNAME=osaccounts_user
INV_DB_PASSWORD=YOUR_OSA_PASSWORD

# Invoice attachment files — a SIBLING directory, outside this repo
OSACCOUNTS_FILE_PATH=/var/www/html/OSManager/invoice_storage

# Python invoice parser — ABSOLUTE paths, must match your clone location
PYTHON_EXECUTABLE=/usr/bin/python3
PYTHON_PARSER_DIR=/var/www/html/osmanagercl/scripts/invoice-parser
PYTHON_VENV_PATH=/var/www/html/osmanagercl/scripts/invoice-parser/venv
INVOICE_PARSER_SCRIPT=/var/www/html/osmanagercl/scripts/invoice-parser/invoice_parser_laravel.py

QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database
```

Redis is configured in `config/database.php` but not required — cache, queue and sessions all use
the database driver.

### Optional keys

Supply these by hand only if you need the feature:

| Keys | Feature |
|---|---|
| `GEMINI_API_KEY`, `MISTRAL_API_KEY` | AI invoice parsing, bank reconciliation |
| `UDEA_USERNAME`, `UDEA_PASSWORD` | Udea supplier price scraping |
| `MAIL_*` | Supplier sales emails, notifications |
| `ZEBRA_PRINTER_HOST`, `ZEBRA_PRINTER_PORT`, `ZEBRA_PRINTER_NAME`, `ZEBRA_PRINTER_TIMEOUT` | Label printing (LAN printer). The CUPS spool lives on `ZEBRA_PRINTER_HOST`, not on this machine — check it via the Printer Queue card on `/labels/zebra` or `lpstat -h {host}:631 -o` |

---

## 8. Schema

```bash
php artisan migrate
```

If you are importing production data, you can skip straight to section 9 — the dump carries the
schema with it, and you will run `migrate` afterwards to catch up any newer migrations.

---

## 9. Load the Data

A migrated-but-empty install runs, but almost every screen is blank: no products, no sales, no
invoices. Choose one of the two paths below.

> **This copies real business data onto your machine.** Treat the dumps and the resulting
> databases as live commercial records — full-disk encryption, no cloud sync, delete stale dumps.

### Where the data lives

| Data | Source host | Notes |
|---|---|---|
| `osmanager` (Laravel app) | `jon@lilThink2` (192.168.69.18), MariaDB 10.11 | ~12 MB gzipped |
| `unicenta2016` (POS) | `shop` (192.168.69.4), MySQL 5.7.33 | **~1.9 GB** raw, grows 50–60 MB/month — always gzip |
| `OSAccounts` | `shop` (192.168.69.4), MySQL 5.7.33 | ~1.3 MB |
| `storage/app` | `jon@lilThink2:/var/www/html/osmanager` | ~235 MB |
| `invoice_storage` | `jon@lilThink2:/var/www/html/OSManager` | ~127 MB |

### 9a. Application database from production

Production credentials live only in `lilThink2:/var/www/html/osmanager/.env` (there is no
committed production env file). Read them, then dump:

```bash
# On the production server
ssh jon@lilThink2
cd /var/www/html/osmanager
grep -E '^DB_(DATABASE|USERNAME|PASSWORD|HOST)=' .env      # note the values

mysqldump -h 127.0.0.1 -u osmanager -p osmanager | gzip > ~/osmanager_prod.sql.gz
exit

# Back on the dev machine
scp jon@lilThink2:~/osmanager_prod.sql.gz /tmp/
gunzip -c /tmp/osmanager_prod.sql.gz | mysql -u osmanager -p osmanagercl
```

> **Dump without `--databases`.** The production database is called `osmanager`; yours is
> `osmanagercl`. With `--databases`, the dump embeds `CREATE DATABASE` / `USE osmanager` and the
> import recreates the production name locally — your `osmanagercl` stays empty and the app
> appears to have loaded nothing.

Then bring the schema up to date and clear the inherited runtime state:

```bash
php artisan migrate                 # your branch may be ahead of production

php artisan tinker --execute="
  collect(['sessions','cache','cache_locks','jobs','failed_jobs'])
    ->each(fn(\$t) => DB::table(\$t)->truncate());
"
```

> **`APP_KEY`**: encrypted columns and sessions are keyed to it. If anything in the dump is
> encrypted and you want it readable, copy production's `APP_KEY` into your `.env` instead of
> using the one `key:generate` made. Otherwise keep your own key and accept that encrypted values
> will not decrypt.

Your local login credentials come from the dump, so use a production account to sign in.

### 9b. POS and OSAccounts snapshots

These come from **`shop` (192.168.69.4)**, not from `lilThink2`.

⚠️ **`shop` is the live till database.** Dump it read-only and outside trading hours — a 1.9 GB
dump puts real load on the machine the shop is selling through. If you only need *a* dataset
rather than a current one, clone from an existing dev machine's container instead (same commands,
`-h 127.0.0.1 -P 3307`).

```bash
# Dump from the POS box (gzip is not optional at this size)
ssh jon@shop "mysqldump -u <pos_user> -p unicenta2016 | gzip" > /tmp/unicenta2016.sql.gz
ssh jon@shop "mysqldump -u <pos_user> -p OSAccounts   | gzip" > /tmp/OSAccounts.sql.gz

# Restore into the container — note -h 127.0.0.1 AND -P 3307 on every line
gunzip -c /tmp/unicenta2016.sql.gz | mysql -h 127.0.0.1 -P 3307 -u root -p \
    --init-command="SET FOREIGN_KEY_CHECKS=0" unicenta2016

gunzip -c /tmp/OSAccounts.sql.gz | mysql -h 127.0.0.1 -P 3307 -u root -p \
    --init-command="SET FOREIGN_KEY_CHECKS=0" OSAccounts
```

> **`FOREIGN_KEY_CHECKS=0` is required.** uniCenta has cross-database constraints that make an
> ordered restore impossible.
>
> The `restore_unicenta.sh` script in the repo root shows this pattern but **is not a safe
> template**: it omits `-h 127.0.0.1`, so its `-P 3307` is ignored and the restore lands in
> MariaDB. It also carries a hardcoded password. Use the commands above instead.

The uniCenta restore takes several minutes. Verify:

```bash
mysql -h 127.0.0.1 -P 3307 -u root -p -e \
  "SELECT COUNT(*) FROM unicenta2016.PRODUCTS; SELECT COUNT(*) FROM OSAccounts.suppliers;"
```

#### Refreshing later

The POS snapshot goes stale as the shop trades. To refresh, re-run the dump and restore commands
above — the restore overwrites in place, no need to drop the database first. The app database
(9a) can be refreshed the same way, but re-run `php artisan migrate` and the truncate step after
each import.

### 9c. Files

Database rows reference files that live on disk. Without these, invoice attachments and uploads
404:

```bash
rsync -avz jon@lilThink2:/var/www/html/osmanager/storage/app/ \
    /var/www/html/osmanagercl/storage/app/

sudo mkdir -p /var/www/html/OSManager
sudo chown $USER:www-data /var/www/html/OSManager
rsync -avz jon@lilThink2:/var/www/html/OSManager/invoice_storage/ \
    /var/www/html/OSManager/invoice_storage/
```

`invoice_storage` sits **outside this repo**, in a sibling application directory. The path is set
by `OSACCOUNTS_FILE_PATH`; if the directory is missing, attachment import fails.

### Alternative: fresh install with no production data

If you have no LAN access, seed a minimal working install instead:

```bash
php artisan migrate
php artisan db:seed --class=RolesAndPermissionsSeeder   # MUST run first
php artisan db:seed --class=AdminUserSeeder
php artisan db:seed --class=LabelTemplateSeeder
```

> **Run these explicitly, in this order.** `DatabaseSeeder` calls `AdminUserSeeder` and
> `LabelTemplateSeeder` but **never calls `RolesAndPermissionsSeeder` at all**. Since
> `AdminUserSeeder` looks up the `admin` role and falls back to `'role_id' => null` when it is
> missing, a bare `php artisan db:seed` silently produces an admin account with no role — and
> therefore no permissions.

POS-dependent screens will stay empty without a `unicenta2016` snapshot.

---

## 10. Frontend Assets

```bash
npm install
npm run build      # or: npm run dev  (Vite dev server with hot reload)
```

> **Stale `public/hot`:** this file is written by `npm run dev` and tells Blade to load assets
> from the Vite dev server. If it survives after you stop Vite, every page loads broken assets.
> Delete it (`rm -f public/hot`) or re-run `npm run dev`.

---

## 11. Invoice Parser (Optional)

The Python parser handles supplier invoices (PDF, Excel, images).

```bash
cd /var/www/html/osmanagercl/scripts/invoice-parser
./setup.sh
```

Or manually:

```bash
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt
deactivate
```

Requires `tesseract-ocr`, `poppler-utils` and `libreoffice` from section 1.

---

## 12. Storage Link

```bash
php artisan storage:link
```

Creates `public/storage` → `storage/app/public`. It is gitignored, so it never arrives with a
clone and must be created on every new machine.

---

## 13. Queue and Scheduler

**Queue** — `QUEUE_CONNECTION=database`, so jobs need a worker. In development `composer run dev`
starts one for you. To run one standalone:

```bash
php artisan queue:work
```

**Scheduler** — `routes/console.php` schedules two jobs: `sales:import-daily --today` at 20:00 and
`suppliers:send-daily-sales` at 20:15. Neither runs without a cron entry:

```bash
crontab -e
# add:
* * * * * cd /var/www/html/osmanagercl && php artisan schedule:run >> /dev/null 2>&1
```

Most dev machines do not want these — they email suppliers and hit the POS. Leave the cron entry
out unless you are specifically testing scheduled work.

> The supervisor `.conf` files in the repo root (`osmanager-queue-worker.conf`,
> `osmanager-dedicated-workers.conf`) are **production artefacts** — they reference
> `/var/www/html/osmanager`, not `osmanagercl`. Do not install them on a dev box unedited.

---

## 14. HTTPS (Optional)

Browsers only expose the camera API over HTTPS (or `localhost`). Without it, the **barcode
scanner** and **camera invoice capture** will not work on a phone or tablet pointed at your dev
machine.

```bash
sudo a2enmod ssl

sudo openssl req -x509 -nodes -days 825 -newkey rsa:2048 \
    -keyout /etc/ssl/private/osmanagercl.key \
    -out /etc/ssl/certs/osmanagercl.crt \
    -subj "/CN=osmanagercl.local"

sudo tee /etc/apache2/sites-available/osmanagercl-ssl.conf > /dev/null <<'EOF'
<VirtualHost *:443>
    ServerName osmanagercl.local
    DocumentRoot /var/www/html/osmanagercl/public

    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/osmanagercl.crt
    SSLCertificateKeyFile /etc/ssl/private/osmanagercl.key

    <Directory /var/www/html/osmanagercl/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
EOF

sudo a2ensite osmanagercl-ssl.conf
sudo systemctl restart apache2
```

Self-signed certificates produce a browser warning you must accept once per device. Set
`APP_URL=https://osmanagercl.local` if you switch over.

---

## 15. Development Tooling

Some of the working setup lives outside the repository and will not arrive with a clone.

**Notification sound** — `CLAUDE.md` asks the assistant to play a sound after each response:

```bash
sudo apt install -y mpg123
mkdir -p ~/Music
scp <other-machine>:~/Music/notification.mp3 ~/Music/
```

**Claude Code permissions** — `.claude/settings.local.json` is gitignored, so its allowlist does
not travel with the clone. Copy it from your existing machine or rebuild it as you go:

```bash
scp <other-machine>:/var/www/html/osmanagercl/.claude/settings.local.json \
    /var/www/html/osmanagercl/.claude/
```

`.claude/agents/*.md` **are** committed and arrive with the clone.

**Assistant memory** — project notes live at
`~/.claude/projects/-var-www-html-osmanagercl/memory/` and are also outside the repo. Copy the
directory across if you want the accumulated context.

---

## 16. Verify the Install

```bash
php artisan optimize:clear
php artisan test
```

> The test suite has **pre-existing failures** unrelated to your setup. Compare against a run on a
> known-good machine rather than expecting a clean pass.

Check all three database connections:

```bash
php artisan tinker --execute="
  echo 'app:  ' . DB::connection()->getDatabaseName() . PHP_EOL;
  echo 'pos:  ' . DB::connection('pos')->table('PRODUCTS')->count() . ' products' . PHP_EOL;
  echo 'osa:  ' . DB::connection('osaccounts')->getDatabaseName() . PHP_EOL;
"
```

Then browse to `http://osmanagercl.local` and confirm:

1. The login page loads with styling (proves Vite assets built)
2. You can log in
3. A product page shows price and stock (proves the `pos` connection)
4. An invoice with an attachment opens (proves `osaccounts` + `OSACCOUNTS_FILE_PATH`)

### Start all dev services at once

```bash
composer run dev
```

Runs `php artisan serve`, `queue:listen`, `php artisan pail` (logs) and `npm run dev` together via
`concurrently`.

---

## Troubleshooting

### Apache shows 403 Forbidden

```bash
sudo chmod -R 775 storage bootstrap/cache
sudo chown -R $USER:www-data storage bootstrap/cache
```

### POS connection refused, or POS data looks wrong

```bash
docker ps --filter name=mysql57        # running?
docker start mysql57                   # if not
docker logs --tail 50 mysql57          # why not

# Confirm you are hitting the container, not MariaDB
mysql -h 127.0.0.1 -P 3307 -u root -p -e "SELECT VERSION();"   # must say 5.7.33
```

If `VERSION()` reports 10.11 (MariaDB), you dropped `-h 127.0.0.1` and reached the wrong server.

### Assets are broken / "Vite manifest not found"

```bash
rm -f public/hot
npm run build
```

### Logged in but everything is forbidden

The admin user has no role — `AdminUserSeeder` ran before `RolesAndPermissionsSeeder`. Re-run them
in the correct order (section 9), or set the user's `role_id` by hand.

### "Class not found" errors

```bash
composer dump-autoload
php artisan optimize:clear
```

### Storage not accessible

```bash
php artisan storage:link
```

### PHP extensions missing

```bash
php -m | grep -E "pdo_mysql|mbstring|gd|zip"
sudo apt install -y php8.3-<name>
sudo systemctl restart apache2
```

### Database connection refused (app database)

```bash
sudo systemctl status mariadb
mysql -u osmanager -p -e "SELECT 1"
```

---

## Quick Reference — All Commands in Order

```bash
# 1. System packages
sudo apt update && sudo apt upgrade -y
sudo apt install -y apache2 php8.3 php8.3-cli php8.3-common php8.3-curl \
    php8.3-gd php8.3-mbstring php8.3-mysql php8.3-xml php8.3-zip \
    php8.3-opcache php8.3-readline php8.3-sqlite3 libapache2-mod-php8.3 \
    mariadb-server docker.io python3 python3-venv python3-pip \
    tesseract-ocr poppler-utils libreoffice cups-client mpg123
sudo usermod -aG docker $USER          # then log out and back in
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm composer-setup.php

# 2. Apache
sudo a2enmod rewrite php8.3
# (create osmanagercl.conf as shown in section 2)
sudo a2ensite osmanagercl.conf && sudo systemctl restart apache2
echo "127.0.0.1 osmanagercl.local" | sudo tee -a /etc/hosts

# 3. App database (MariaDB :3306)
sudo mysql -e "CREATE DATABASE osmanagercl CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'osmanager'@'localhost' IDENTIFIED BY 'YOUR_PASSWORD_HERE';"
sudo mysql -e "GRANT ALL PRIVILEGES ON osmanagercl.* TO 'osmanager'@'localhost'; FLUSH PRIVILEGES;"

# 4. Clone
cd /var/www/html && git clone git@github.com:jonathanHas/osmanagercl.git && cd osmanagercl
sudo chown -R $USER:www-data . && sudo chmod -R 775 storage bootstrap/cache

# 5. POS container (MySQL 5.7 :3307)
printf '[mysqld]\nbind-address = 0.0.0.0\n' > ~/mysql57_custom.cnf
mkdir -p ~/mysql57_data
docker run -d --name mysql57 --restart unless-stopped -p 3307:3306 \
  -e MYSQL_ROOT_PASSWORD='YOUR_ROOT_PASSWORD' \
  -v ~/mysql57_data:/var/lib/mysql \
  -v ~/mysql57_custom.cnf:/etc/mysql/conf.d/custom.cnf:ro mysql:5.7.33
# (create databases + users as shown in section 5)

# 6-7. Dependencies and environment
composer install
cp .env.example .env && php artisan key:generate
# Edit .env per section 7 — DB_*, POS_DB_* (3307), INV_DB_* (3307), PYTHON_* paths

# 8-9. Schema and data
php artisan migrate
# then EITHER import production data (section 9) OR:
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan db:seed --class=AdminUserSeeder

# 10-12. Frontend, parser, storage
npm install && npm run build
cd scripts/invoice-parser && ./setup.sh && cd ../..
php artisan storage:link

# 16. Verify
php artisan test
composer run dev
```

---

## Related Documentation

- [Quick Start Guide](./quick-start-guide.md) — day-to-day development commands
- [System Requirements](./system-requirements.md) — version matrix
- [Known Issues](./known-issues.md) — previously resolved problems
- [POS Integration](../features/pos-integration.md) — how the `pos` connection is used
- [Production Deployment Guide](../deployment/production-deployment-guide.md) — deploying to `lilThink2`
