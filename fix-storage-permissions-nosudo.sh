#!/bin/bash

# Fix storage permissions for invoice upload system (no sudo version)

echo "Fixing storage permissions for invoice upload..."

# Create necessary directories
mkdir -p /var/www/html/osmanagercl/storage/app/private/temp/invoices

# Set permissions using current user
chmod -R 775 /var/www/html/osmanagercl/storage/app/private 2>/dev/null || true

# Fix any existing batch directories with wrong permissions
find /var/www/html/osmanagercl/storage/app/private/temp/invoices -type d -name "BATCH-*" -exec chmod 775 {} \; 2>/dev/null || true
find /var/www/html/osmanagercl/storage/app/private/temp/invoices -type f -name "*.pdf" -exec chmod 664 {} \; 2>/dev/null || true

# Ensure directories are accessible
chmod 775 /var/www/html/osmanagercl/storage/app/private/temp 2>/dev/null || true
chmod 775 /var/www/html/osmanagercl/storage/app/private/temp/invoices 2>/dev/null || true

echo "Permissions fixed. Testing write access..."

# Test write access from PHP
php -r "
\$testFile = '/var/www/html/osmanagercl/storage/app/private/temp/test.txt';
if (file_put_contents(\$testFile, 'test')) {
    echo 'Write test successful!' . PHP_EOL;
    unlink(\$testFile);
} else {
    echo 'Write test failed!' . PHP_EOL;
}
"

echo "Done!"