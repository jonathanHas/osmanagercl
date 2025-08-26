#!/bin/bash

# Fix ownership and permissions for invoice attachments
# This script should be run with sudo

echo "🔧 Fixing invoice attachment ownership and permissions..."

# Change ownership to www-data:www-data
echo "Setting ownership to www-data:www-data..."
chown -R www-data:www-data /var/www/html/osmanagercl/storage/app/private/invoices/

# Set proper permissions
echo "Setting directory permissions to 775..."
find /var/www/html/osmanagercl/storage/app/private/invoices/ -type d -exec chmod 775 {} \;

echo "Setting file permissions to 664..."
find /var/www/html/osmanagercl/storage/app/private/invoices/ -type f -exec chmod 664 {} \;

# Count the files fixed
DIRS=$(find /var/www/html/osmanagercl/storage/app/private/invoices/ -type d | wc -l)
FILES=$(find /var/www/html/osmanagercl/storage/app/private/invoices/ -type f | wc -l)

echo "✅ Fixed permissions for:"
echo "   - $DIRS directories"
echo "   - $FILES files"
echo ""
echo "All invoice attachments should now be accessible via the web interface."