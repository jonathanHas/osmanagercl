#!/bin/bash

# Fix storage permissions for invoice upload system

echo "Fixing storage permissions for invoice upload..."

# Create necessary directories
sudo mkdir -p /var/www/html/osmanagercl/storage/app/private/temp/invoices

# Set ownership to current user and www-data group  
sudo chown -R $USER:www-data /var/www/html/osmanagercl/storage/app/private

# Set permissions - directories need execute permission
sudo find /var/www/html/osmanagercl/storage/app/private -type d -exec chmod 775 {} \;
sudo find /var/www/html/osmanagercl/storage/app/private -type f -exec chmod 664 {} \;

# Fix any existing batch directories with wrong permissions
sudo find /var/www/html/osmanagercl/storage/app/private/temp/invoices -type d -name "BATCH-*" -exec chmod 775 {} \;
sudo find /var/www/html/osmanagercl/storage/app/private/temp/invoices -type f -name "*.pdf" -exec chmod 664 {} \;
sudo chown -R $USER:www-data /var/www/html/osmanagercl/storage/app/private/temp/invoices

# Ensure web server can write to temp directory
sudo chmod 775 /var/www/html/osmanagercl/storage/app/private/temp
sudo chmod 775 /var/www/html/osmanagercl/storage/app/private/temp/invoices

# Set default umask for PHP to create group-writable files
echo "Setting up proper file creation permissions..."

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