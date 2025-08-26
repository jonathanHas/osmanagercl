#!/bin/bash

# =============================================================================
# Comprehensive Permission Fixing Script for OS Manager
# Handles all storage, invoice processing, and parser permissions
# =============================================================================

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
APP_PATH=${1:-/var/www/html/osmanager}
WEB_USER=${2:-www-data}
WEB_GROUP=${3:-www-data}

log() {
    echo -e "${BLUE}[$(date '+%H:%M:%S')]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

# Check if running as root or with sudo
check_permissions() {
    if [[ $EUID -ne 0 ]]; then
        error "This script must be run as root or with sudo"
        echo "Usage: sudo $0 [app_path] [web_user] [web_group]"
        echo "Example: sudo $0 /var/www/html/osmanager www-data www-data"
        exit 1
    fi
}

# Validate directories exist
validate_directories() {
    log "🔍 Validating application directory: $APP_PATH"
    
    if [[ ! -d "$APP_PATH" ]]; then
        error "Application directory does not exist: $APP_PATH"
        exit 1
    fi
    
    if [[ ! -f "$APP_PATH/artisan" ]]; then
        error "Not a Laravel application (artisan not found): $APP_PATH"
        exit 1
    fi
    
    success "Application directory validated."
}

# Check if user and group exist
validate_user_group() {
    log "👤 Validating user and group: $WEB_USER:$WEB_GROUP"
    
    if ! id "$WEB_USER" &>/dev/null; then
        error "User '$WEB_USER' does not exist"
        exit 1
    fi
    
    if ! getent group "$WEB_GROUP" &>/dev/null; then
        error "Group '$WEB_GROUP' does not exist"
        exit 1
    fi
    
    success "User and group validated."
}

# Create necessary directory structure
create_directory_structure() {
    log "📁 Creating invoice storage directory structure..."
    
    cd "$APP_PATH" || exit 1
    
    # Create all necessary directories
    DIRECTORIES=(
        "storage/app/private"
        "storage/app/private/temp"
        "storage/app/private/temp/invoices"
        "storage/app/private/invoices"
        "storage/app/private/invoices/attachments"
        "storage/framework/cache"
        "storage/framework/sessions"
        "storage/framework/views"
        "storage/logs"
        "bootstrap/cache"
    )
    
    for dir in "${DIRECTORIES[@]}"; do
        if [[ ! -d "$dir" ]]; then
            log "Creating directory: $dir"
            mkdir -p "$dir"
        fi
    done
    
    # Create year/month directory structure for current and next year
    CURRENT_YEAR=$(date +%Y)
    NEXT_YEAR=$((CURRENT_YEAR + 1))
    
    for year in $CURRENT_YEAR $NEXT_YEAR; do
        for month in {01..12}; do
            YEAR_MONTH_DIR="storage/app/private/invoices/$year/$month"
            if [[ ! -d "$YEAR_MONTH_DIR" ]]; then
                log "Creating directory: $YEAR_MONTH_DIR"
                mkdir -p "$YEAR_MONTH_DIR"
            fi
        done
    done
    
    success "Directory structure created."
}

# Set basic file ownership
set_basic_ownership() {
    log "👥 Setting basic file ownership to $WEB_USER:$WEB_GROUP..."
    
    cd "$APP_PATH" || exit 1
    
    # Set ownership for entire application
    chown -R "$WEB_USER:$WEB_GROUP" .
    
    success "Basic ownership set."
}

# Set file and directory permissions
set_file_permissions() {
    log "🔐 Setting file and directory permissions..."
    
    cd "$APP_PATH" || exit 1
    
    # Set directory permissions (755 for most, 775 for writable)
    log "Setting directory permissions..."
    find . -type d -exec chmod 755 {} \;
    
    # Set file permissions (644 for most files)
    log "Setting file permissions..."
    find . -type f -exec chmod 644 {} \;
    
    # Make scripts executable
    log "Making scripts executable..."
    find . -name "*.sh" -exec chmod +x {} \;
    chmod +x artisan
    
    success "Basic file permissions set."
}

# Set storage permissions
set_storage_permissions() {
    log "📦 Setting storage-specific permissions..."
    
    cd "$APP_PATH" || exit 1
    
    # Storage directories need to be writable by web server
    WRITABLE_DIRS=(
        "storage"
        "storage/app"
        "storage/app/private"
        "storage/app/private/temp"
        "storage/app/private/temp/invoices"
        "storage/app/private/invoices"
        "storage/framework"
        "storage/framework/cache"
        "storage/framework/sessions"
        "storage/framework/views"
        "storage/logs"
        "bootstrap/cache"
    )
    
    for dir in "${WRITABLE_DIRS[@]}"; do
        if [[ -d "$dir" ]]; then
            log "Setting writable permissions for: $dir"
            chmod -R 775 "$dir"
            chown -R "$WEB_USER:$WEB_GROUP" "$dir"
        fi
    done
    
    # Set proper permissions for invoice storage
    log "Setting invoice-specific permissions..."
    if [[ -d "storage/app/private/invoices" ]]; then
        find storage/app/private/invoices -type d -exec chmod 775 {} \;
        find storage/app/private/invoices -type f -exec chmod 664 {} \;
    fi
    
    success "Storage permissions set."
}

# Set invoice parser permissions
set_parser_permissions() {
    log "🐍 Setting invoice parser permissions..."
    
    cd "$APP_PATH" || exit 1
    
    if [[ -d "scripts/invoice-parser" ]]; then
        log "Found invoice parser directory"
        
        # Set ownership
        chown -R "$WEB_USER:$WEB_GROUP" scripts/invoice-parser
        
        # Set directory permissions
        find scripts/invoice-parser -type d -exec chmod 755 {} \;
        
        # Set file permissions
        find scripts/invoice-parser -type f -exec chmod 644 {} \;
        
        # Make Python scripts executable
        find scripts/invoice-parser -name "*.py" -exec chmod +x {} \;
        find scripts/invoice-parser -name "*.sh" -exec chmod +x {} \;
        
        # Set virtual environment permissions
        if [[ -d "scripts/invoice-parser/venv" ]]; then
            log "Setting virtual environment permissions..."
            chmod -R 755 scripts/invoice-parser/venv
            
            # Make Python binaries executable
            if [[ -d "scripts/invoice-parser/venv/bin" ]]; then
                chmod +x scripts/invoice-parser/venv/bin/*
            fi
        fi
        
        success "Invoice parser permissions set."
    else
        warning "Invoice parser directory not found, skipping parser permissions."
    fi
}

# Set queue worker permissions
set_queue_worker_permissions() {
    log "⚙️ Setting queue worker permissions..."
    
    cd "$APP_PATH" || exit 1
    
    # Ensure queue worker can write to all necessary locations
    QUEUE_WRITABLE_DIRS=(
        "storage/app/private/temp/invoices"
        "storage/app/private/invoices"
        "storage/logs"
    )
    
    for dir in "${QUEUE_WRITABLE_DIRS[@]}"; do
        if [[ -d "$dir" ]]; then
            log "Ensuring queue worker access to: $dir"
            chmod -R 775 "$dir"
            chown -R "$WEB_USER:$WEB_GROUP" "$dir"
        fi
    done
    
    success "Queue worker permissions set."
}

# Set special ACL permissions if available
set_acl_permissions() {
    if command -v setfacl &> /dev/null; then
        log "🔒 Setting ACL permissions for enhanced security..."
        
        cd "$APP_PATH" || exit 1
        
        # Set default ACLs for storage directories
        ACL_DIRS=(
            "storage/app/private"
            "storage/logs"
            "bootstrap/cache"
        )
        
        for dir in "${ACL_DIRS[@]}"; do
            if [[ -d "$dir" ]]; then
                log "Setting ACL for: $dir"
                setfacl -R -m "u:$WEB_USER:rwx" "$dir" 2>/dev/null || true
                setfacl -R -m "g:$WEB_GROUP:rwx" "$dir" 2>/dev/null || true
                setfacl -R -d -m "u:$WEB_USER:rwx" "$dir" 2>/dev/null || true
                setfacl -R -d -m "g:$WEB_GROUP:rwx" "$dir" 2>/dev/null || true
            fi
        done
        
        success "ACL permissions set."
    else
        warning "setfacl not available, skipping ACL permissions."
    fi
}

# Test permissions
test_permissions() {
    log "🧪 Testing permissions..."
    
    cd "$APP_PATH" || exit 1
    
    # Test basic write permissions
    log "Testing basic write permissions..."
    TEST_FILE="storage/app/private/temp/permission-test-$(date +%s).txt"
    
    if sudo -u "$WEB_USER" touch "$TEST_FILE" 2>/dev/null; then
        sudo -u "$WEB_USER" rm "$TEST_FILE" 2>/dev/null
        success "✅ Basic write permissions working"
    else
        error "❌ Basic write permissions failed"
        return 1
    fi
    
    # Test invoice directory permissions
    log "Testing invoice directory permissions..."
    INVOICE_TEST_DIR="storage/app/private/invoices/$(date +%Y)/$(date +%m)/test-$(date +%s)"
    
    if sudo -u "$WEB_USER" mkdir -p "$INVOICE_TEST_DIR" 2>/dev/null; then
        sudo -u "$WEB_USER" rmdir "$INVOICE_TEST_DIR" 2>/dev/null
        success "✅ Invoice directory permissions working"
    else
        error "❌ Invoice directory permissions failed"
        return 1
    fi
    
    # Test parser permissions if parser exists
    if [[ -f "scripts/invoice-parser/invoice_parser_laravel.py" ]]; then
        log "Testing parser permissions..."
        if sudo -u "$WEB_USER" python3 scripts/invoice-parser/invoice_parser_laravel.py --help >/dev/null 2>&1; then
            success "✅ Parser permissions working"
        else
            warning "⚠️  Parser permissions may have issues"
        fi
    fi
    
    success "Permission tests completed."
}

# Fix common permission issues
fix_common_issues() {
    log "🔧 Fixing common permission issues..."
    
    cd "$APP_PATH" || exit 1
    
    # Fix Laravel-specific permission issues
    log "Fixing Laravel-specific issues..."
    
    # Ensure bootstrap/cache is writable
    if [[ -d "bootstrap/cache" ]]; then
        chmod 775 bootstrap/cache
        chown "$WEB_USER:$WEB_GROUP" bootstrap/cache
    fi
    
    # Fix .env file permissions
    if [[ -f ".env" ]]; then
        chmod 644 .env
        chown "$WEB_USER:$WEB_GROUP" .env
    fi
    
    # Fix any files that might have become executable when they shouldn't be
    log "Fixing executable file permissions..."
    find storage -name "*.php" -exec chmod 644 {} \; 2>/dev/null || true
    find storage -name "*.txt" -exec chmod 644 {} \; 2>/dev/null || true
    find storage -name "*.log" -exec chmod 644 {} \; 2>/dev/null || true
    find storage -name "*.pdf" -exec chmod 644 {} \; 2>/dev/null || true
    
    # Ensure artisan is executable
    if [[ -f "artisan" ]]; then
        chmod +x artisan
    fi
    
    success "Common issues fixed."
}

# Display summary
display_summary() {
    echo
    echo "========================================"
    echo "    PERMISSION SUMMARY"
    echo "========================================"
    echo "Application Path: $APP_PATH"
    echo "Web User: $WEB_USER"
    echo "Web Group: $WEB_GROUP"
    echo "========================================"
    
    # Count directories and files
    TOTAL_DIRS=$(find "$APP_PATH" -type d | wc -l)
    TOTAL_FILES=$(find "$APP_PATH" -type f | wc -l)
    WRITABLE_DIRS=$(find "$APP_PATH/storage" -type d 2>/dev/null | wc -l)
    
    echo "Total directories: $TOTAL_DIRS"
    echo "Total files: $TOTAL_FILES"
    echo "Writable storage directories: $WRITABLE_DIRS"
    echo "========================================"
    
    # Show key directory permissions
    echo "Key directory permissions:"
    ls -la "$APP_PATH/storage/app/private/" 2>/dev/null || echo "  Invoice storage not yet created"
    echo "========================================"
}

# Main function
main() {
    echo "🔧 OS Manager Comprehensive Permission Fixing"
    echo "=============================================="
    
    # Step 1: Check permissions
    check_permissions
    
    # Step 2: Validate directories
    validate_directories
    
    # Step 3: Validate user/group
    validate_user_group
    
    # Step 4: Create directory structure
    create_directory_structure
    
    # Step 5: Set basic ownership
    set_basic_ownership
    
    # Step 6: Set file permissions
    set_file_permissions
    
    # Step 7: Set storage permissions
    set_storage_permissions
    
    # Step 8: Set parser permissions
    set_parser_permissions
    
    # Step 9: Set queue worker permissions
    set_queue_worker_permissions
    
    # Step 10: Set ACL permissions
    set_acl_permissions
    
    # Step 11: Fix common issues
    fix_common_issues
    
    # Step 12: Test permissions
    test_permissions
    
    # Step 13: Display summary
    display_summary
    
    success "🎉 All permissions have been fixed successfully!"
    
    echo
    echo "✅ Next steps:"
    echo "1. Test invoice upload functionality"
    echo "2. Test parser execution"
    echo "3. Monitor queue worker logs"
    echo "4. Check web server error logs if issues persist"
    echo
}

# Script usage
usage() {
    echo "Usage: sudo $0 [app_path] [web_user] [web_group]"
    echo
    echo "Parameters:"
    echo "  app_path  - Path to Laravel application (default: /var/www/html/osmanager)"
    echo "  web_user  - Web server user (default: www-data)"
    echo "  web_group - Web server group (default: www-data)"
    echo
    echo "Examples:"
    echo "  sudo $0"
    echo "  sudo $0 /var/www/html/osmanager-test"
    echo "  sudo $0 /var/www/html/osmanager www-data www-data"
}

# Handle script arguments
if [[ "$1" == "--help" || "$1" == "-h" ]]; then
    usage
    exit 0
fi

# Run main function
main