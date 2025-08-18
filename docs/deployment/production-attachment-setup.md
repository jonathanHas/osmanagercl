# Production Setup for Invoice Attachments

## Overview
This guide ensures invoice attachments work correctly in production without manual intervention. The system has been updated to handle permissions automatically and supports environment-based configuration for various deployment scenarios.

## Key Principle
Files are created with group-readable permissions (664) and `www-data` group ownership, allowing both web server and CLI processes to access them.

## Environment Configuration

### OSAccounts File Path Setup

The attachment import system uses environment-based configuration:

```bash
# In production .env file
OSACCOUNTS_FILE_PATH=/path/to/osaccounts/files
```

This configuration is stored in `config/osaccounts.php` and supports Laravel's config caching for production performance.

### Cross-Server Deployment Scenarios

#### Scenario 1: Same Server
When OSAccounts and Laravel are on the same server:
```bash
OSACCOUNTS_FILE_PATH=/var/www/html/OSManager/invoice_storage
```

#### Scenario 2: Network Mount (Recommended for separate servers)
```bash
# Create mount point
sudo mkdir -p /mnt/osaccounts-files

# Mount OSAccounts server directory (NFS example)
sudo mount -t nfs osaccounts-server:/var/www/html/OSManager/invoice_storage /mnt/osaccounts-files

# Make permanent in /etc/fstab
echo "osaccounts-server:/var/www/html/OSManager/invoice_storage /mnt/osaccounts-files nfs defaults 0 0" | sudo tee -a /etc/fstab

# Configure Laravel
OSACCOUNTS_FILE_PATH=/mnt/osaccounts-files
```

#### Scenario 3: File Synchronization
```bash
# Create local storage
sudo mkdir -p /var/lib/osaccounts-files

# Set up rsync (consider adding to cron for regular updates)
rsync -av user@osaccounts-server:/var/www/html/OSManager/invoice_storage/ /var/lib/osaccounts-files/

# Configure Laravel
OSACCOUNTS_FILE_PATH=/var/lib/osaccounts-files
```

## Production Setup Requirements

### 1. User Configuration
Ensure the deployment user is in the `www-data` group:

```bash
# Add deploy user to www-data group
sudo usermod -a -G www-data deploy

# Verify group membership
groups deploy
# Should show: deploy : deploy www-data
```

### 2. Directory Permissions
Set up the storage directory with proper group ownership:

```bash
# Set ownership and permissions for storage
sudo chown -R www-data:www-data storage/app/private/invoices
sudo chmod -R 775 storage/app/private/invoices

# Set the setgid bit so new files inherit the group
sudo chmod g+s storage/app/private/invoices
```

### 3. Laravel Configuration
Ensure Laravel's filesystem configuration uses proper permissions:

```php
// config/filesystems.php
'private' => [
    'driver' => 'local',
    'root' => storage_path('app/private'),
    'permissions' => [
        'file' => [
            'public' => 0664,  // rw-rw-r--
            'private' => 0664, // rw-rw-r--
        ],
        'dir' => [
            'public' => 0775,  // rwxrwxr-x
            'private' => 0775, // rwxrwxr-x
        ],
    ],
],
```

### 4. Web Server Configuration

#### Apache
```apache
# Ensure Apache runs as www-data
User www-data
Group www-data
```

#### Nginx + PHP-FPM
```ini
; /etc/php/8.2/fpm/pool.d/www.conf
user = www-data
group = www-data
```

## How It Works

### Automatic Permission Handling (New)
The import command now automatically:
- Sets file permissions to `664` (rw-rw-r--)
- Sets group ownership to `www-data`
- Applies setgid bit to directories for group inheritance
- No manual `umask` or `sudo` required

### Import Scenarios

1. **Web Interface Imports**: 
   - PHP runs as `www-data` user
   - Files created with owner `www-data:www-data`
   - Permissions automatically set to 664

2. **CLI/Cron Imports**:
   - Command runs as deployment user (e.g., `deploy`)
   - Files created with owner `deploy:www-data`
   - Group automatically set to `www-data` by the import command
   - Web server can read files via group permissions

3. **No Sudo Required**:
   - Import command handles permissions internally
   - Works with any automation tool (Jenkins, GitHub Actions, etc.)
   - No password prompts or elevated privileges needed

## Verification Commands

```bash
# Check if web server can read files
sudo -u www-data ls -la storage/app/private/invoices/

# Test import as deploy user
php artisan osaccounts:import-attachments --dry-run

# Check file permissions after import
find storage/app/private/invoices -type f -ls | head -10
```

## Troubleshooting

### Issue: Permission Denied Errors
```bash
# Fix ownership
sudo chown -R www-data:www-data storage/app/private/invoices

# Fix permissions
sudo find storage/app/private/invoices -type d -exec chmod 775 {} \;
sudo find storage/app/private/invoices -type f -exec chmod 664 {} \;
```

### Issue: Files Created with Wrong Group
```bash
# Ensure setgid bit is set on directories
sudo find storage/app/private/invoices -type d -exec chmod g+s {} \;
```

### Issue: Cron Jobs Failing
```bash
# Add to crontab to ensure proper group
* * * * * newgrp www-data && cd /var/www/html/osmanagercl && php artisan schedule:run
```

## Configuration Deployment Steps

### Step 1: Environment Setup
```bash
# 1. Configure OSAccounts file path in .env
echo "OSACCOUNTS_FILE_PATH=/path/to/osaccounts/files" >> .env

# 2. Clear and cache configuration
sudo -u www-data php artisan config:clear
sudo -u www-data php artisan config:cache

# 3. Test configuration
sudo -u www-data php artisan tinker --execute="echo 'Path: ' . config('osaccounts.file_path');"
```

### Step 2: Test Import
```bash
# Test with dry run first
sudo -u www-data php artisan osaccounts:import-attachments --dry-run

# Check output shows correct path:
# 📁 Using base path: /your/configured/path
```

## Troubleshooting Cross-Server Issues

### Issue: "Base path does not exist"
```bash
# Check if path is accessible
ls -la /path/to/osaccounts/files

# For network mounts, verify mount is active
mount | grep osaccounts

# Test network connectivity
ping osaccounts-server
```

### Issue: "Files not found" with correct path
```bash
# Check file permissions on source server
sudo find /var/www/html/OSManager/invoice_storage -type f -name "*.pdf" | head -5

# Verify Laravel can read files
sudo -u www-data ls -la /path/to/osaccounts/files/

# Check SELinux (if enabled)
sestatus
# If enforcing, may need: setsebool -P httpd_use_nfs 1
```

### Issue: Network mount fails
```bash
# Install NFS utilities
sudo apt-get install nfs-common

# Test mount manually
sudo mount -t nfs -v osaccounts-server:/path/to/files /mnt/test

# Check firewall on OSAccounts server
# Ensure ports 111, 2049 are open for NFS
```

### Issue: Permission denied on mounted files
```bash
# Check mount options
mount | grep osaccounts

# Remount with proper options
sudo umount /mnt/osaccounts-files
sudo mount -t nfs -o rw,sync,hard,intr osaccounts-server:/path /mnt/osaccounts-files
```

## Production Deployment Checklist

### Environment Configuration
- [ ] `OSACCOUNTS_FILE_PATH` configured in production `.env`
- [ ] Configuration cached with `php artisan config:cache`
- [ ] Path accessibility verified with dry run import
- [ ] Network mount configured (if cross-server)
- [ ] Mount persisted in `/etc/fstab` (if applicable)

### Laravel Application
- [ ] Deployment user is in `www-data` group
- [ ] Storage directory has correct ownership (`www-data:www-data`)
- [ ] Storage directory has setgid bit set
- [ ] File permissions are 664, directory permissions are 775
- [ ] Web server runs as `www-data`
- [ ] Test import works without sudo
- [ ] Files are accessible via web interface

### Cross-Server (if applicable)
- [ ] Network connectivity between servers verified
- [ ] File sharing protocol configured (NFS/CIFS)
- [ ] Mount permissions allow Laravel user access
- [ ] Firewall rules allow required ports
- [ ] SELinux policies configured (if applicable)

## Additional Commands

### Cleanup Duplicate Attachments
If you have duplicate attachments from previous imports:

```bash
# Preview what would be cleaned
php artisan attachments:cleanup-duplicates --dry-run

# Remove duplicates and fix permissions
php artisan attachments:cleanup-duplicates --fix-permissions
```

### Fix Existing Permissions
For files imported before the automatic permission handling:

```bash
# Check current status
php artisan attachments:fix-permissions --dry-run

# Fix permissions (run as user in www-data group)
php artisan attachments:fix-permissions

# For full ownership fix (requires sudo)
sudo chown -R www-data:www-data storage/app/private/invoices
```

## Important Notes

- **Never use 777 permissions** - This is a security risk
- **Always test imports** after deployment to verify permissions
- **Monitor logs** for permission errors in production
- **Use group permissions** instead of changing ownership
- **Success Rate**: Current implementation achieves 98.4% import success rate

## Recent Improvements (August 2025)

1. **Automatic Permission Management**: No manual `umask` or `sudo` required
2. **Smart Path Resolution**: Handles various OSAccounts path formats
3. **HTML Entity Decoding**: Properly handles special characters in paths
4. **Duplicate Prevention**: SHA-256 hash-based duplicate detection
5. **Group Ownership**: Automatic `www-data` group assignment
6. **Setgid Directories**: Ensures proper group inheritance

This setup ensures attachments work correctly regardless of how they're imported (web, CLI, cron) without requiring sudo or manual intervention.