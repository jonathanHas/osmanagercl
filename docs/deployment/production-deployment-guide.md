# 🚀 OS Manager - Production Deployment Guide

This guide covers the complete production deployment process for OS Manager, from server setup to going live.

---

## 📋 **Pre-Deployment Checklist**

### **Server Requirements**
- [ ] Ubuntu 20.04+ LTS or equivalent Linux distribution
- [ ] PHP 8.2+ with required extensions (curl, mbstring, xml, sqlite3, mysql, gd)
- [ ] MySQL 8.0+ or MariaDB 10.3+
- [ ] Web server (Apache 2.4+ or Nginx 1.18+)
- [ ] SSL certificate for HTTPS
- [ ] Domain name pointing to server
- [ ] Sufficient disk space (>10GB free, >50GB recommended)
- [ ] Minimum 2GB RAM (4GB+ recommended)

### **Access Requirements**
- [ ] SSH access to production server
- [ ] Sudo privileges for system configuration
- [ ] Database access and credentials
- [ ] Domain DNS management access
- [ ] SSL certificate files or Let's Encrypt access

---

## 🛠️ **Server Setup**

### **1. System Updates and Dependencies**
```bash
# Update system packages
sudo apt update && sudo apt upgrade -y

# Install essential packages
sudo apt install -y \
    curl \
    git \
    unzip \
    supervisor \
    software-properties-common \
    apt-transport-https \
    ca-certificates \
    gnupg \
    lsb-release
```

### **2. PHP Installation**
```bash
# Add PHP repository
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# Install PHP 8.2 and extensions
sudo apt install -y \
    php8.2 \
    php8.2-cli \
    php8.2-fpm \
    php8.2-mysql \
    php8.2-sqlite3 \
    php8.2-curl \
    php8.2-mbstring \
    php8.2-xml \
    php8.2-zip \
    php8.2-gd \
    php8.2-intl \
    php8.2-bcmath

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### **3. Database Setup**
```bash
# Install MySQL
sudo apt install -y mysql-server

# Secure MySQL installation
sudo mysql_secure_installation

# Create application database and user
sudo mysql -e "
CREATE DATABASE osmanager CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'osmanager'@'localhost' IDENTIFIED BY 'secure_password_here';
GRANT ALL PRIVILEGES ON osmanager.* TO 'osmanager'@'localhost';
FLUSH PRIVILEGES;
"
```

### **4. Web Server Setup**

#### **Option A: Apache**
```bash
# Install Apache
sudo apt install -y apache2

# Enable required modules
sudo a2enmod rewrite ssl headers

# Create virtual host configuration
sudo tee /etc/apache2/sites-available/osmanager.conf > /dev/null << 'EOF'
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /var/www/html/osmanager/public
    
    <Directory /var/www/html/osmanager/public>
        AllowOverride All
        Require all granted
    </Directory>
    
    # Redirect HTTP to HTTPS
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</VirtualHost>

<VirtualHost *:443>
    ServerName your-domain.com
    DocumentRoot /var/www/html/osmanager/public
    
    <Directory /var/www/html/osmanager/public>
        AllowOverride All
        Require all granted
    </Directory>
    
    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /path/to/your/certificate.crt
    SSLCertificateKeyFile /path/to/your/private.key
    SSLCertificateChainFile /path/to/your/ca_bundle.crt
    
    # Security headers
    Header always set Strict-Transport-Security "max-age=63072000; includeSubDomains; preload"
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
</VirtualHost>
EOF

# Enable site and disable default
sudo a2ensite osmanager
sudo a2dissite 000-default
sudo systemctl reload apache2
```

#### **Option B: Nginx**
```bash
# Install Nginx
sudo apt install -y nginx

# Create server configuration
sudo tee /etc/nginx/sites-available/osmanager > /dev/null << 'EOF'
server {
    listen 80;
    server_name your-domain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name your-domain.com;
    root /var/www/html/osmanager/public;
    index index.php;

    # SSL Configuration
    ssl_certificate /path/to/your/certificate.crt;
    ssl_certificate_key /path/to/your/private.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512:ECDHE-RSA-AES256-GCM-SHA384:DHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;

    # Security headers
    add_header Strict-Transport-Security "max-age=63072000; includeSubDomains; preload";
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options DENY;
    add_header X-XSS-Protection "1; mode=block";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF

# Enable site
sudo ln -s /etc/nginx/sites-available/osmanager /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

---

## 🚀 **Application Deployment**

### **1. Directory Setup**
```bash
# Create application directory
sudo mkdir -p /var/www/html
cd /var/www/html

# Set proper ownership
sudo chown -R www-data:www-data /var/www/html
```

### **2. Deploy Application Code**
```bash
# Clone repository (replace with your repository URL)
sudo -u www-data git clone https://github.com/your-username/osmanager.git
cd osmanager

# Install dependencies
sudo -u www-data composer install --no-dev --optimize-autoloader

# Set proper permissions
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### **3. Environment Configuration**
```bash
# Copy environment file
sudo -u www-data cp .env.example .env

# Generate application key
sudo -u www-data php artisan key:generate

# Edit environment configuration
sudo -u www-data nano .env
```

**Critical Environment Variables:**
```env
APP_NAME="OS Manager"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=osmanager
DB_USERNAME=osmanager
DB_PASSWORD=your_secure_password

# Cache and Sessions
CACHE_DRIVER=file
SESSION_DRIVER=file
QUEUE_CONNECTION=database

# Mail Configuration (for notifications)
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-email
MAIL_PASSWORD=your-email-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@your-domain.com
MAIL_FROM_NAME="${APP_NAME}"
```

### **4. Database Setup**
```bash
# Run migrations
sudo -u www-data php artisan migrate --force

# Seed initial data (roles, permissions, etc.)
sudo -u www-data php artisan db:seed --force

# Create admin user
sudo -u www-data php artisan db:seed --class=AdminUserSeeder --force
```

### **5. Application Optimization**
```bash
# Optimize application for production
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
sudo -u www-data php artisan event:cache

# Create storage symlink
sudo -u www-data php artisan storage:link

# Install and build frontend assets (if applicable)
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt-get install -y nodejs
sudo -u www-data npm install
sudo -u www-data npm run build
```

---

## ⚙️ **Service Configuration**

### **1. Queue Workers (for Background Jobs)**
```bash
# Copy supervisor configuration
sudo cp deployment-configs/queue-worker.conf /etc/supervisor/conf.d/osmanager-queue-worker.conf

# Update supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start osmanager-queue-worker:*
```

### **2. Scheduled Tasks (Cron Jobs)**
```bash
# Add Laravel scheduler to crontab
echo "* * * * * cd /var/www/html/osmanager && php artisan schedule:run >> /dev/null 2>&1" | sudo -u www-data crontab -
```

### **3. Log Rotation**
```bash
# Configure log rotation
sudo tee /etc/logrotate.d/osmanager > /dev/null << 'EOF'
/var/www/html/osmanager/storage/logs/*.log {
    daily
    missingok
    rotate 52
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
    postrotate
        php artisan config:cache > /dev/null 2>&1 || true
    endscript
}
EOF
```

---

## 🔒 **Security Configuration**

### **1. Firewall Setup**
```bash
# Configure UFW firewall
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow ssh
sudo ufw allow 'Apache Full'  # or 'Nginx Full'
sudo ufw --force enable
```

### **2. SSL Certificate (Let's Encrypt)**
```bash
# Install Certbot
sudo apt install -y certbot python3-certbot-apache  # for Apache
# OR
sudo apt install -y certbot python3-certbot-nginx   # for Nginx

# Obtain certificate
sudo certbot --apache -d your-domain.com  # for Apache
# OR
sudo certbot --nginx -d your-domain.com   # for Nginx

# Auto-renewal
sudo systemctl enable certbot.timer
```

### **3. File Permissions Security**
```bash
# Set secure permissions
sudo find /var/www/html/osmanager -type f -exec chmod 644 {} \;
sudo find /var/www/html/osmanager -type d -exec chmod 755 {} \;
sudo chmod -R 775 storage bootstrap/cache
sudo chmod 600 .env
```

---

## 📊 **Monitoring and Maintenance**

### **1. Log Monitoring**
```bash
# Monitor application logs
sudo tail -f /var/www/html/osmanager/storage/logs/laravel.log

# Monitor web server logs
sudo tail -f /var/log/apache2/error.log  # Apache
sudo tail -f /var/log/nginx/error.log    # Nginx

# Monitor system logs
sudo tail -f /var/log/syslog
```

### **2. Performance Monitoring**
```bash
# Install htop for system monitoring
sudo apt install -y htop

# Monitor disk usage
df -h

# Monitor MySQL performance
sudo mysqladmin status
sudo mysqladmin processlist
```

### **3. Backup Configuration**
```bash
# Create backup script
sudo tee /usr/local/bin/osmanager-backup.sh > /dev/null << 'EOF'
#!/bin/bash
BACKUP_DIR="/var/backups/osmanager"
DATE=$(date +%Y%m%d_%H%M%S)

# Create backup directory
mkdir -p $BACKUP_DIR

# Database backup
mysqldump -u osmanager -p'your_password' osmanager | gzip > $BACKUP_DIR/database_$DATE.sql.gz

# Application files backup (excluding vendor and node_modules)
tar --exclude='vendor' --exclude='node_modules' --exclude='storage/logs/*' \
    -czf $BACKUP_DIR/application_$DATE.tar.gz /var/www/html/osmanager

# Keep only last 7 days of backups
find $BACKUP_DIR -name "*.gz" -mtime +7 -delete

echo "Backup completed: $DATE"
EOF

sudo chmod +x /usr/local/bin/osmanager-backup.sh

# Add to daily cron
echo "0 2 * * * /usr/local/bin/osmanager-backup.sh >> /var/log/osmanager-backup.log 2>&1" | sudo crontab -
```

---

## 🧪 **Testing and Verification**

### **1. Application Health Check**
```bash
# Test application response
curl -I https://your-domain.com

# Test database connection
cd /var/www/html/osmanager
sudo -u www-data php artisan tinker --execute="DB::connection()->getPdo(); echo 'Database OK';"

# Test queue workers
sudo supervisorctl status osmanager-queue-worker:*

# Check Laravel status
sudo -u www-data php artisan about
```

### **2. Security Verification**
```bash
# Check SSL configuration
curl -I https://your-domain.com
openssl s_client -connect your-domain.com:443 -servername your-domain.com

# Verify file permissions
ls -la /var/www/html/osmanager/.env
ls -la /var/www/html/osmanager/storage

# Check for security headers
curl -I https://your-domain.com | grep -E "(Strict-Transport-Security|X-Content-Type-Options|X-Frame-Options)"
```

---

## 🔄 **Deployment Updates**

### **1. Application Updates**
```bash
# Create deployment script
sudo tee /usr/local/bin/osmanager-deploy.sh > /dev/null << 'EOF'
#!/bin/bash
cd /var/www/html/osmanager

# Backup before update
/usr/local/bin/osmanager-backup.sh

# Pull latest code
sudo -u www-data git pull origin main

# Update dependencies
sudo -u www-data composer install --no-dev --optimize-autoloader

# Run migrations
sudo -u www-data php artisan migrate --force

# Clear and rebuild caches
sudo -u www-data php artisan config:clear
sudo -u www-data php artisan cache:clear
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache

# Restart queue workers
sudo supervisorctl restart osmanager-queue-worker:*

# Build frontend assets if needed
sudo -u www-data npm install
sudo -u www-data npm run build

echo "Deployment completed: $(date)"
EOF

sudo chmod +x /usr/local/bin/osmanager-deploy.sh
```

### **2. Zero-Downtime Deployment (Advanced)**
```bash
# Use deployment tools like Envoy or Deployer for zero-downtime deployments
# This involves symbolic links and multiple release directories
```

---

## 🚨 **Troubleshooting**

### **Common Issues**

#### **Permission Errors**
```bash
# Fix storage permissions
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

#### **Database Connection Issues**
```bash
# Test database connection
mysql -u osmanager -p osmanager

# Check MySQL status
sudo systemctl status mysql
```

#### **Queue Jobs Not Processing**
```bash
# Check supervisor status
sudo supervisorctl status

# Restart queue workers
sudo supervisorctl restart osmanager-queue-worker:*

# Check queue logs
sudo tail -f /var/www/html/osmanager/storage/logs/laravel.log
```

#### **SSL Certificate Issues**
```bash
# Renew certificate
sudo certbot renew

# Test certificate
sudo certbot certificates
```

### **Performance Issues**
```bash
# Check server resources
htop
df -h
free -h

# Check MySQL performance
sudo mysql -e "SHOW PROCESSLIST;"

# Optimize Laravel
sudo -u www-data php artisan optimize
```

---

## 📚 **Additional Resources**

- **[Laravel Deployment Documentation](https://laravel.com/docs/deployment)**
- **[Nginx Configuration](https://nginx.org/en/docs/)**
- **[Apache Configuration](https://httpd.apache.org/docs/)**
- **[Let's Encrypt Documentation](https://letsencrypt.org/docs/)**
- **[MySQL Performance Tuning](https://dev.mysql.com/doc/refman/8.0/en/optimization.html)**

---

## 🎯 **Feature-Specific Deployment**

For specialized features that require additional setup:

- **[Invoice Parsing System](./invoice-parsing-deployment-guide.md)** - Queue workers, Python parsers, OCR setup
- **[POS Integration](../features/pos-integration.md)** - Database connections, real-time sync
- **[Sales Analytics](../features/sales-data-import-plan.md)** - Performance optimization, data import

---

*This guide covers the general production deployment process. For feature-specific deployment requirements, refer to the individual feature documentation.*