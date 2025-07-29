#!/bin/bash

#########################################################
# Laravel Storage Permissions Fix Script
# 
# This script fixes common permission issues with Laravel
# storage directories. Run after deployment or when
# experiencing permission errors.
#
# Usage: ./fix-storage-permissions.sh
#########################################################

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Get the directory where the script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$SCRIPT_DIR"

echo -e "${GREEN}Starting Laravel storage permissions fix...${NC}"

# Check if we're in a Laravel project
if [ ! -f "artisan" ]; then
    echo -e "${RED}Error: This doesn't appear to be a Laravel project root.${NC}"
    echo "Please run this script from your Laravel project directory."
    exit 1
fi

# Detect web server user
WEB_USER="www-data"
if id "apache" &>/dev/null; then
    WEB_USER="apache"
elif id "nginx" &>/dev/null; then
    WEB_USER="nginx"
fi

echo -e "${YELLOW}Using web server user: ${WEB_USER}${NC}"

# Create required directories if they don't exist
echo "Creating required storage directories..."
mkdir -p storage/app/private/temp
mkdir -p storage/app/public
mkdir -p storage/framework/{sessions,views,cache,testing}
mkdir -p storage/logs
mkdir -p bootstrap/cache

# Fix ownership
echo "Setting ownership to ${WEB_USER}:${WEB_USER}..."
sudo chown -R ${WEB_USER}:${WEB_USER} storage bootstrap/cache

# Fix directory permissions
echo "Setting directory permissions to 775..."
sudo find storage -type d -exec chmod 775 {} \;
sudo find bootstrap/cache -type d -exec chmod 775 {} \;

# Fix file permissions
echo "Setting file permissions to 664..."
sudo find storage -type f -exec chmod 664 {} \;
sudo find bootstrap/cache -type f -exec chmod 664 {} \;

# Ensure log file exists and has correct permissions
if [ ! -f "storage/logs/laravel.log" ]; then
    echo "Creating laravel.log file..."
    sudo touch storage/logs/laravel.log
    sudo chown ${WEB_USER}:${WEB_USER} storage/logs/laravel.log
    sudo chmod 664 storage/logs/laravel.log
fi

# Clear Laravel caches
echo "Clearing Laravel caches..."
sudo -u ${WEB_USER} php artisan cache:clear 2>/dev/null || echo "Cache clear skipped"
sudo -u ${WEB_USER} php artisan config:clear 2>/dev/null || echo "Config clear skipped"
sudo -u ${WEB_USER} php artisan view:clear 2>/dev/null || echo "View clear skipped"

# Check if SELinux is enabled (for RedHat/CentOS)
if command -v getenforce &> /dev/null && [ "$(getenforce)" != "Disabled" ]; then
    echo "Setting SELinux context..."
    sudo chcon -R -t httpd_sys_rw_content_t storage bootstrap/cache
fi

echo -e "${GREEN}✓ Storage permissions fixed successfully!${NC}"
echo ""
echo "Summary of changes:"
echo "- Created missing storage directories"
echo "- Set ownership to ${WEB_USER}:${WEB_USER}"
echo "- Set directory permissions to 775"
echo "- Set file permissions to 664"
echo "- Cleared Laravel caches"

# Verify permissions
echo ""
echo "Verifying permissions:"
ls -la storage/ | head -5
echo "..."
ls -la storage/logs/laravel.log 2>/dev/null || echo "Log file not found"