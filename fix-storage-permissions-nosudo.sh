#!/bin/bash

# Fix permissions for invoice storage directories
# This script fixes the permissions issue for bulk upload attachments

echo "Fixing permissions for invoice storage directories..."

# Fix the September 2025 directory that was created with wrong permissions
SEPT_DIR="/var/www/html/osmanagercl/storage/app/private/invoices/2025/09"

if [ -d "$SEPT_DIR" ]; then
    echo "Fixing permissions for September 2025 directory..."
    
    # Try to fix permissions (this may require the owner or group write access)
    chmod 775 "$SEPT_DIR" 2>/dev/null
    chgrp www-data "$SEPT_DIR" 2>/dev/null
    
    # Check if it worked
    if [ "$(stat -c '%a' "$SEPT_DIR")" == "775" ]; then
        echo "✓ Successfully fixed September directory permissions"
    else
        echo "⚠ Could not fix September directory permissions - may need manual intervention"
        echo "  Current permissions: $(ls -ld "$SEPT_DIR")"
        echo "  Run manually: sudo chmod 775 '$SEPT_DIR' && sudo chgrp www-data '$SEPT_DIR'"
    fi
else
    echo "September 2025 directory does not exist - no fix needed"
fi

# Ensure proper permissions for the invoices base directory structure
INVOICES_DIR="/var/www/html/osmanagercl/storage/app/private/invoices"

if [ -d "$INVOICES_DIR" ]; then
    echo "Checking base invoices directory permissions..."
    
    # Ensure the base directory has correct permissions
    find "$INVOICES_DIR" -type d -exec chmod 775 {} \; 2>/dev/null || echo "⚠ Some directories could not be changed"
    find "$INVOICES_DIR" -type f -exec chmod 664 {} \; 2>/dev/null || echo "⚠ Some files could not be changed"
    
    echo "✓ Base directory permissions check completed"
fi

echo "Permission fix script completed."
echo ""
echo "If manual intervention is needed, run:"
echo "sudo chmod 775 /var/www/html/osmanagercl/storage/app/private/invoices/2025/09/"
echo "sudo chgrp www-data /var/www/html/osmanagercl/storage/app/private/invoices/2025/09/"