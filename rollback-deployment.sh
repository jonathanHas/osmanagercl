#!/bin/bash

# =============================================================================
# Emergency Rollback Script for OS Manager
# Quick recovery from failed deployments
# =============================================================================

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
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

info() {
    echo -e "${PURPLE}[INFO]${NC} $1"
}

# Check if running as root
check_permissions() {
    if [[ $EUID -ne 0 ]]; then
        error "This script must be run as root or with sudo"
        echo "Usage: sudo $0 [app_path] [web_user] [web_group]"
        exit 1
    fi
}

# Display warning and get confirmation
confirm_rollback() {
    echo
    echo "========================================"
    echo "        ⚠️  EMERGENCY ROLLBACK  ⚠️"
    echo "========================================"
    echo "Application: $APP_PATH"
    echo "Web User: $WEB_USER"
    echo "========================================"
    echo
    warning "This will attempt to recover from a failed deployment by:"
    echo "1. Stopping all services"
    echo "2. Restoring database from latest backup"
    echo "3. Reverting to previous code version (if available)"
    echo "4. Fixing permissions"
    echo "5. Restarting services"
    echo
    error "⚠️  THIS IS DESTRUCTIVE AND WILL LOSE RECENT CHANGES!"
    echo
    read -p "Are you sure you want to proceed? Type 'ROLLBACK' to continue: " CONFIRM
    
    if [[ $CONFIRM != "ROLLBACK" ]]; then
        error "Rollback cancelled by user."
        exit 1
    fi
    
    echo
    log "🔄 Starting emergency rollback process..."
}

# Stop all services safely
stop_services() {
    log "🛑 Stopping services..."
    
    # Stop queue workers
    if command -v supervisorctl &> /dev/null; then
        log "Stopping supervisor workers..."
        supervisorctl stop all 2>/dev/null || true
    fi
    
    # Stop Laravel queue workers
    if [[ -d "$APP_PATH" ]]; then
        cd "$APP_PATH" || true
        php artisan queue:restart 2>/dev/null || true
        log "Laravel queue workers restarted"
    fi
    
    # Stop web server temporarily (optional)
    read -p "Stop web server during rollback? [y/N] " STOP_WEB
    if [[ $STOP_WEB == "y" || $STOP_WEB == "Y" ]]; then
        if systemctl is-active --quiet apache2; then
            systemctl stop apache2
            log "Apache2 stopped"
            WEB_SERVER_STOPPED="apache2"
        elif systemctl is-active --quiet nginx; then
            systemctl stop nginx
            log "Nginx stopped"
            WEB_SERVER_STOPPED="nginx"
        fi
    fi
    
    success "Services stopped."
}

# Find and restore database backup
restore_database() {
    log "💾 Searching for database backups..."
    
    cd "$APP_PATH" || exit 1
    
    if [[ ! -f ".env" ]]; then
        error "No .env file found, cannot restore database"
        return 1
    fi
    
    # Find backup files
    BACKUP_FILES=($(find storage -name "backup_*.sql" -type f 2>/dev/null | sort -r | head -5))
    
    if [[ ${#BACKUP_FILES[@]} -eq 0 ]]; then
        warning "No database backup files found"
        return 1
    fi
    
    echo
    log "Found ${#BACKUP_FILES[@]} backup files:"
    for i in "${!BACKUP_FILES[@]}"; do
        BACKUP_FILE="${BACKUP_FILES[$i]}"
        BACKUP_DATE=$(basename "$BACKUP_FILE" .sql | sed 's/backup_//')
        BACKUP_SIZE=$(du -h "$BACKUP_FILE" 2>/dev/null | cut -f1)
        echo "  $((i+1)). $BACKUP_FILE (${BACKUP_SIZE}, ${BACKUP_DATE})"
    done
    
    echo
    read -p "Select backup to restore [1-${#BACKUP_FILES[@]}] or 0 to skip: " BACKUP_CHOICE
    
    if [[ $BACKUP_CHOICE -eq 0 ]]; then
        warning "Database restore skipped"
        return 0
    fi
    
    if [[ $BACKUP_CHOICE -lt 1 || $BACKUP_CHOICE -gt ${#BACKUP_FILES[@]} ]]; then
        error "Invalid backup selection"
        return 1
    fi
    
    SELECTED_BACKUP="${BACKUP_FILES[$((BACKUP_CHOICE-1))]}"
    log "Restoring database from: $SELECTED_BACKUP"
    
    # Extract database credentials
    DB_NAME=$(grep "^DB_DATABASE=" .env | cut -d'=' -f2 | tr -d '"' | tr -d "'")
    DB_USER=$(grep "^DB_USERNAME=" .env | cut -d'=' -f2 | tr -d '"' | tr -d "'")
    DB_PASS=$(grep "^DB_PASSWORD=" .env | cut -d'=' -f2 | tr -d '"' | tr -d "'")
    DB_HOST=$(grep "^DB_HOST=" .env | cut -d'=' -f2 | tr -d '"' | tr -d "'")
    
    if [[ -z "$DB_NAME" || -z "$DB_USER" ]]; then
        error "Cannot extract database credentials from .env"
        return 1
    fi
    
    # Create current backup before restore
    CURRENT_BACKUP="storage/backup_before_rollback_$(date +%Y%m%d_%H%M%S).sql"
    log "Creating backup of current database: $CURRENT_BACKUP"
    if mysqldump -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$CURRENT_BACKUP" 2>/dev/null; then
        success "Current database backed up"
    else
        warning "Could not backup current database"
    fi
    
    # Restore from backup
    log "Restoring database..."
    if mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$SELECTED_BACKUP" 2>/dev/null; then
        success "Database restored successfully"
        return 0
    else
        error "Database restoration failed"
        return 1
    fi
}

# Revert code to previous version
revert_code() {
    log "📋 Checking for code version options..."
    
    cd "$APP_PATH" || exit 1
    
    # Check if it's a git repository
    if [[ -d ".git" ]]; then
        log "Git repository detected"
        
        # Show recent commits
        echo
        log "Recent commits:"
        git log --oneline -10 2>/dev/null || true
        echo
        
        read -p "Revert to previous commit? [y/N] " REVERT_GIT
        if [[ $REVERT_GIT == "y" || $REVERT_GIT == "Y" ]]; then
            # Get current commit
            CURRENT_COMMIT=$(git rev-parse HEAD 2>/dev/null)
            
            # Get previous commit
            PREVIOUS_COMMIT=$(git rev-parse HEAD~1 2>/dev/null)
            
            if [[ -n "$PREVIOUS_COMMIT" ]]; then
                log "Reverting to commit: $PREVIOUS_COMMIT"
                
                # Create a backup branch
                BACKUP_BRANCH="backup-before-rollback-$(date +%Y%m%d_%H%M%S)"
                git branch "$BACKUP_BRANCH" HEAD 2>/dev/null || true
                
                # Reset to previous commit
                if git reset --hard "$PREVIOUS_COMMIT" 2>/dev/null; then
                    success "Code reverted to previous commit"
                    info "Backup branch created: $BACKUP_BRANCH"
                else
                    error "Git revert failed"
                    return 1
                fi
            else
                warning "No previous commit found"
            fi
        fi
    else
        log "Not a git repository, checking for backup directories..."
        
        # Look for backup directories
        BACKUP_DIRS=($(find .. -maxdepth 1 -name "*backup*" -o -name "*old*" -type d 2>/dev/null | head -5))
        
        if [[ ${#BACKUP_DIRS[@]} -gt 0 ]]; then
            echo
            log "Found potential backup directories:"
            for i in "${!BACKUP_DIRS[@]}"; do
                echo "  $((i+1)). ${BACKUP_DIRS[$i]}"
            done
            echo
            read -p "Restore from backup directory? [1-${#BACKUP_DIRS[@]}] or 0 to skip: " BACKUP_DIR_CHOICE
            
            if [[ $BACKUP_DIR_CHOICE -gt 0 && $BACKUP_DIR_CHOICE -le ${#BACKUP_DIRS[@]} ]]; then
                SELECTED_BACKUP_DIR="${BACKUP_DIRS[$((BACKUP_DIR_CHOICE-1))]}"
                log "Restoring from: $SELECTED_BACKUP_DIR"
                
                # Create current backup
                CURRENT_BACKUP_DIR="../$(basename "$APP_PATH")-backup-$(date +%Y%m%d_%H%M%S)"
                mv "$APP_PATH" "$CURRENT_BACKUP_DIR" 2>/dev/null || true
                
                # Restore from backup
                cp -r "$SELECTED_BACKUP_DIR" "$APP_PATH" 2>/dev/null
                
                if [[ $? -eq 0 ]]; then
                    success "Code restored from backup directory"
                    info "Current code backed up to: $CURRENT_BACKUP_DIR"
                else
                    error "Failed to restore from backup directory"
                    # Try to restore original
                    mv "$CURRENT_BACKUP_DIR" "$APP_PATH" 2>/dev/null || true
                    return 1
                fi
            fi
        else
            warning "No backup options found for code revert"
        fi
    fi
}

# Fix file permissions
fix_permissions() {
    log "🔐 Fixing file permissions..."
    
    cd "$APP_PATH" || exit 1
    
    # Set basic ownership
    chown -R "$WEB_USER:$WEB_GROUP" .
    
    # Set directory permissions
    find . -type d -exec chmod 755 {} \;
    
    # Set file permissions
    find . -type f -exec chmod 644 {} \;
    
    # Make scripts executable
    find . -name "*.sh" -exec chmod +x {} \;
    chmod +x artisan 2>/dev/null || true
    
    # Set storage permissions
    chmod -R 775 storage bootstrap/cache 2>/dev/null || true
    
    # Set parser permissions if exists
    if [[ -d "scripts/invoice-parser" ]]; then
        chown -R "$WEB_USER:$WEB_GROUP" scripts/invoice-parser
        chmod -R 755 scripts/invoice-parser
        chmod +x scripts/invoice-parser/venv/bin/* 2>/dev/null || true
    fi
    
    success "Permissions fixed."
}

# Clear all caches
clear_caches() {
    log "🧹 Clearing all caches..."
    
    cd "$APP_PATH" || exit 1
    
    # Clear Laravel caches
    php artisan config:clear 2>/dev/null || true
    php artisan route:clear 2>/dev/null || true
    php artisan view:clear 2>/dev/null || true
    php artisan cache:clear 2>/dev/null || true
    php artisan queue:clear 2>/dev/null || true
    
    # Clear OPcache if available
    if command -v php &> /dev/null; then
        php -r "if (function_exists('opcache_reset')) { opcache_reset(); echo 'OPcache cleared\n'; }" 2>/dev/null || true
    fi
    
    success "Caches cleared."
}

# Restart services
restart_services() {
    log "🔄 Restarting services..."
    
    # Restart web server if it was stopped
    if [[ -n "$WEB_SERVER_STOPPED" ]]; then
        log "Restarting $WEB_SERVER_STOPPED..."
        systemctl start "$WEB_SERVER_STOPPED"
        if systemctl is-active --quiet "$WEB_SERVER_STOPPED"; then
            success "$WEB_SERVER_STOPPED restarted successfully"
        else
            error "$WEB_SERVER_STOPPED failed to start"
        fi
    fi
    
    # Restart supervisor workers
    if command -v supervisorctl &> /dev/null; then
        log "Restarting supervisor workers..."
        supervisorctl reread 2>/dev/null || true
        supervisorctl update 2>/dev/null || true
        supervisorctl start all 2>/dev/null || true
    fi
    
    # Restart queue workers
    cd "$APP_PATH" || exit 1
    php artisan queue:restart 2>/dev/null || true
    
    success "Services restarted."
}

# Verify rollback success
verify_rollback() {
    log "🔍 Verifying rollback success..."
    
    cd "$APP_PATH" || exit 1
    
    # Test basic Laravel functionality
    if php artisan --version >/dev/null 2>&1; then
        success "Laravel is responding"
    else
        error "Laravel is not responding"
        return 1
    fi
    
    # Test database connection
    if php artisan tinker --execute="DB::connection()->getPdo(); echo 'DB: OK';" 2>/dev/null | grep -q "DB: OK"; then
        success "Database connection works"
    else
        warning "Database connection may have issues"
    fi
    
    # Test file permissions
    if sudo -u "$WEB_USER" touch "storage/logs/rollback-test-$(date +%s).txt" 2>/dev/null; then
        sudo -u "$WEB_USER" rm "storage/logs/rollback-test-$(date +%s).txt" 2>/dev/null
        success "File permissions work"
    else
        warning "File permissions may have issues"
    fi
    
    # Test web server response (if possible)
    if command -v curl &> /dev/null; then
        HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "http://localhost" 2>/dev/null || echo "000")
        case $HTTP_STATUS in
            "200"|"302"|"301")
                success "Web server is responding (HTTP $HTTP_STATUS)"
                ;;
            *)
                warning "Web server response: HTTP $HTTP_STATUS"
                ;;
        esac
    fi
    
    return 0
}

# Create rollback report
create_rollback_report() {
    log "📊 Creating rollback report..."
    
    REPORT_FILE="$APP_PATH/storage/logs/rollback-report-$(date +%Y%m%d_%H%M%S).log"
    
    cat > "$REPORT_FILE" << EOF
OS Manager Emergency Rollback Report
===================================
Date: $(date)
Application Path: $APP_PATH
Web User: $WEB_USER
Performed by: $(whoami)

Rollback Actions Taken:
- Services stopped: Yes
- Database restored: $(if [[ -n "$SELECTED_BACKUP" ]]; then echo "Yes ($SELECTED_BACKUP)"; else echo "No/Skipped"; fi)
- Code reverted: $(if [[ -n "$PREVIOUS_COMMIT" || -n "$SELECTED_BACKUP_DIR" ]]; then echo "Yes"; else echo "No/Skipped"; fi)
- Permissions fixed: Yes
- Caches cleared: Yes
- Services restarted: Yes

System Status After Rollback:
- Laravel responding: $(if php artisan --version >/dev/null 2>&1; then echo "Yes"; else echo "No"; fi)
- Database connected: $(if php artisan tinker --execute="DB::connection()->getPdo(); echo 'OK';" 2>/dev/null | grep -q "OK"; then echo "Yes"; else echo "No"; fi)
- Web server: $(if systemctl is-active --quiet apache2; then echo "Apache2 running"; elif systemctl is-active --quiet nginx; then echo "Nginx running"; else echo "Unknown"; fi)

Recommendations:
- Monitor application logs: tail -f $APP_PATH/storage/logs/laravel.log
- Test invoice upload functionality manually
- Verify queue workers are processing jobs
- Consider investigating root cause of deployment failure

Generated by: rollback-deployment.sh
EOF
    
    info "Rollback report saved to: $REPORT_FILE"
}

# Display final status
display_final_status() {
    echo
    echo "========================================"
    echo "         ROLLBACK COMPLETED"
    echo "========================================"
    echo "Application: $APP_PATH"
    echo "Rollback Date: $(date)"
    echo "========================================"
    
    # Test key functionality
    cd "$APP_PATH" || exit 1
    
    if php artisan --version >/dev/null 2>&1; then
        success "✅ Laravel is responding"
        LARAVEL_OK=true
    else
        error "❌ Laravel is not responding"
        LARAVEL_OK=false
    fi
    
    if php artisan tinker --execute="DB::connection()->getPdo(); echo 'OK';" 2>/dev/null | grep -q "OK"; then
        success "✅ Database is connected"
        DB_OK=true
    else
        error "❌ Database connection failed"
        DB_OK=false
    fi
    
    if [[ $LARAVEL_OK == true && $DB_OK == true ]]; then
        echo
        success "🎉 ROLLBACK SUCCESSFUL!"
        echo
        echo "✅ Your application has been restored to a working state."
        echo
        echo "Next steps:"
        echo "1. Monitor logs: tail -f $APP_PATH/storage/logs/laravel.log"
        echo "2. Test critical functionality manually"
        echo "3. Investigate what caused the deployment failure"
        echo "4. Plan a more careful deployment strategy"
        echo "5. Consider implementing automated testing"
        
    else
        echo
        error "⚠️  ROLLBACK PARTIALLY SUCCESSFUL"
        echo
        echo "❌ Some issues remain. Manual intervention may be required."
        echo
        echo "Troubleshooting steps:"
        echo "1. Check Laravel logs: tail -f $APP_PATH/storage/logs/laravel.log"
        echo "2. Verify .env configuration"
        echo "3. Check file permissions: sudo ./fix-all-permissions.sh $APP_PATH"
        echo "4. Restart web server: sudo systemctl restart apache2"
        echo "5. Contact system administrator if issues persist"
    fi
    
    echo "========================================"
}

# Main function
main() {
    echo "🚨 OS Manager Emergency Rollback"
    echo "================================"
    
    # Step 1: Check permissions
    check_permissions
    
    # Step 2: Confirm rollback
    confirm_rollback
    
    # Step 3: Stop services
    stop_services
    
    # Step 4: Restore database
    restore_database
    
    # Step 5: Revert code
    revert_code
    
    # Step 6: Fix permissions
    fix_permissions
    
    # Step 7: Clear caches
    clear_caches
    
    # Step 8: Restart services
    restart_services
    
    # Step 9: Verify rollback
    verify_rollback
    
    # Step 10: Create report
    create_rollback_report
    
    # Step 11: Display final status
    display_final_status
    
    success "🔄 Emergency rollback process completed."
    
    # Play notification sound
    if command -v mpg123 >/dev/null 2>&1; then
        mpg123 /home/jon/Music/notification.mp3 2>/dev/null || true
    fi
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
    echo
    echo "⚠️  WARNING: This script will:"
    echo "- Stop services temporarily"
    echo "- Restore database from backup (data loss possible)"
    echo "- Revert code to previous version"
    echo "- Clear all caches"
    echo
    echo "Only use this script in emergency situations!"
}

# Handle script arguments
if [[ "$1" == "--help" || "$1" == "-h" ]]; then
    usage
    exit 0
fi

# Run main function
main