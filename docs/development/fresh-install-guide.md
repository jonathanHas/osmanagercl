# OSManager CL - Fresh Install Guide

Complete instructions for setting up the OSManager CL Laravel application on a new Ubuntu machine.

## Prerequisites

- Ubuntu 22.04 or 24.04 LTS
- `sudo` access
- Git configured with SSH access to GitHub

---

## 1. System Packages

```bash
sudo apt update && sudo apt upgrade -y

# Apache
sudo apt install -y apache2

# PHP 8.3 and required extensions
sudo apt install -y php8.3 php8.3-cli php8.3-common php8.3-curl php8.3-gd \
    php8.3-mbstring php8.3-mysql php8.3-xml php8.3-zip php8.3-opcache \
    php8.3-readline libapache2-mod-php8.3

# If php8.3 is not available in default repos (Ubuntu 22.04), add the PPA first:
# sudo add-apt-repository ppa:ondrej/php -y && sudo apt update

# MySQL
sudo apt install -y mysql-server

# Node.js 22.x (via NodeSource)
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs

# Python 3 (for invoice parser scripts)
sudo apt install -y python3 python3-venv python3-pip

# System dependencies for invoice parser (OCR, PDF processing)
sudo apt install -y tesseract-ocr poppler-utils libreoffice

# Composer (PHP package manager)
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm composer-setup.php
```

---

## 2. Configure Apache

### Enable required modules

```bash
sudo a2enmod rewrite
sudo a2enmod php8.3
```

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

## 3. Configure MySQL

```bash
sudo mysql
```

Inside the MySQL shell:

```sql
CREATE DATABASE osmanagercl CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'osmanager'@'localhost' IDENTIFIED BY 'YOUR_PASSWORD_HERE';
GRANT ALL PRIVILEGES ON osmanagercl.* TO 'osmanager'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### (Optional) POS Database Connection

If connecting to the uniCenta POS database:

```sql
-- Create a read-only user for the POS database
CREATE USER 'pos_readonly'@'localhost' IDENTIFIED BY 'YOUR_POS_PASSWORD';
GRANT SELECT ON unicenta.* TO 'pos_readonly'@'localhost';
FLUSH PRIVILEGES;
```

---

## 4. Clone the Repository

```bash
cd /var/www/html
git clone git@github.com:jonathanHas/osmanagercl.git
cd osmanagercl
```

### Set directory permissions

```bash
# Set ownership to your user and www-data group
sudo chown -R $USER:www-data /var/www/html/osmanagercl

# Storage and cache must be writable by Apache
sudo chmod -R 775 storage bootstrap/cache
```

---

## 5. Install PHP Dependencies

```bash
cd /var/www/html/osmanagercl
composer install
```

---

## 6. Configure Environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` with your settings:

```bash
nano .env
```

Key values to set:

```env
APP_NAME="OS Manager"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://osmanagercl.local

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=osmanagercl
DB_USERNAME=osmanager
DB_PASSWORD=YOUR_PASSWORD_HERE

# POS Database (optional - uncomment if connecting to uniCenta)
# POS_DB_HOST=127.0.0.1
# POS_DB_PORT=3306
# POS_DB_DATABASE=unicenta
# POS_DB_USERNAME=pos_readonly
# POS_DB_PASSWORD=YOUR_POS_PASSWORD

# OSAccounts path (optional - for invoice attachment import)
# OSACCOUNTS_FILE_PATH=/var/www/html/OSManager/invoice_storage
```

---

## 7. Database Setup

```bash
php artisan migrate
```

### (Optional) Seed with initial data

```bash
php artisan db:seed
```

---

## 8. Install Frontend Dependencies

```bash
npm install
```

### Build assets for production

```bash
npm run build
```

### Or run dev server (during development)

```bash
npm run dev
```

---

## 9. Set Up Invoice Parser (Optional)

The Python-based invoice parser is used for parsing supplier invoices (PDF, Excel, etc.).

```bash
cd /var/www/html/osmanagercl/scripts/invoice-parser

# Create virtual environment
python3 -m venv venv

# Activate it
source venv/bin/activate

# Install Python dependencies
pip install -r requirements.txt

# Deactivate when done
deactivate
```

---

## 10. Storage Link

Laravel needs a symlink from `public/storage` to `storage/app/public`:

```bash
php artisan storage:link
```

---

## 11. Cache & Optimize (Production)

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 12. Verify Installation

### Check the site loads

Open `http://osmanagercl.local` in your browser.

### Run tests

```bash
php artisan test
```

### Start all dev services at once

```bash
composer run dev
```

This starts (via `concurrently`):
- Laravel dev server (`php artisan serve`)
- Queue worker (`php artisan queue:listen`)
- Log viewer (`php artisan pail`)
- Vite dev server (`npm run dev`)

---

## Troubleshooting

### Apache shows 403 Forbidden

```bash
# Check permissions
sudo chmod -R 775 storage bootstrap/cache
sudo chown -R $USER:www-data storage bootstrap/cache
```

### "Class not found" errors

```bash
composer dump-autoload
php artisan optimize:clear
```

### Vite manifest not found

```bash
npm run build
```

### Storage not accessible

```bash
php artisan storage:link
```

### PHP extensions missing

```bash
# Check what's installed
php -m

# Install missing extension (example: sqlite)
sudo apt install php8.3-sqlite3
sudo systemctl restart apache2
```

### MySQL connection refused

```bash
# Check MySQL is running
sudo systemctl status mysql

# Verify credentials
mysql -u osmanager -p -e "SELECT 1"
```

---

## Quick Reference - All Commands in Order

```bash
# 1. System packages
sudo apt update && sudo apt upgrade -y
sudo apt install -y apache2 php8.3 php8.3-cli php8.3-common php8.3-curl \
    php8.3-gd php8.3-mbstring php8.3-mysql php8.3-xml php8.3-zip \
    php8.3-opcache php8.3-readline libapache2-mod-php8.3 \
    mysql-server python3 python3-venv python3-pip \
    tesseract-ocr poppler-utils libreoffice
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm composer-setup.php

# 2. Apache
sudo a2enmod rewrite php8.3
# (create osmanagercl.conf as shown above)
sudo a2ensite osmanagercl.conf
sudo systemctl restart apache2
echo "127.0.0.1 osmanagercl.local" | sudo tee -a /etc/hosts

# 3. MySQL
sudo mysql -e "CREATE DATABASE osmanagercl CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'osmanager'@'localhost' IDENTIFIED BY 'YOUR_PASSWORD_HERE';"
sudo mysql -e "GRANT ALL PRIVILEGES ON osmanagercl.* TO 'osmanager'@'localhost'; FLUSH PRIVILEGES;"

# 4. Clone & permissions
cd /var/www/html
git clone git@github.com:jonathanHas/osmanagercl.git
cd osmanagercl
sudo chown -R $USER:www-data .
sudo chmod -R 775 storage bootstrap/cache

# 5. PHP deps
composer install

# 6. Environment
cp .env.example .env
php artisan key:generate
# Edit .env with your DB credentials

# 7. Database
php artisan migrate

# 8. Frontend
npm install
npm run build

# 9. Invoice parser (optional)
cd scripts/invoice-parser && python3 -m venv venv && source venv/bin/activate && pip install -r requirements.txt && deactivate && cd ../..

# 10. Storage link
php artisan storage:link

# 11. Verify
php artisan test
```
