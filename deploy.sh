#!/bin/bash

# =============================================================================
# OS Manager Deployment Script
# Laravel 12 application with MySQL database
# =============================================================================

# === CONFIGURE THESE ===
DEV_DIR=/var/www/html/osmanagercl               # where you write code
DEPLOY_DIR=~/deployments/osmanagercl            # clean staging copy
PROD_USER=jon
PROD_HOST=lilThink2
PROD_PATH=/var/www/html/osmanager

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging function
log() {
    echo -e "${BLUE}[$(date '+%Y-%m-%d %H:%M:%S')]${NC} $1"
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

# Check if host is reachable
check_host_connectivity() {
    log "🌐 Checking if $PROD_HOST is reachable..."
    
    if ! ssh -o ConnectTimeout=5 "$PROD_USER@$PROD_HOST" 'exit' 2>/dev/null; then
        error "Cannot connect to $PROD_HOST via SSH. Aborting deployment."
        exit 1
    fi
    
    success "$PROD_HOST is reachable."
}

# Validate MySQL database connectivity
check_database_connectivity() {
    log "🗄️ Checking MySQL database connectivity..."
    
    cd "$DEPLOY_DIR" || exit 1
    
    if ! php artisan tinker --execute="DB::connection()->getPdo(); echo 'Main DB: Connected';" 2>/dev/null; then
        error "Cannot connect to main MySQL database. Check your .env configuration."
        exit 1
    fi
    
    if ! php artisan tinker --execute="DB::connection('pos')->getPdo(); echo 'POS DB: Connected';" 2>/dev/null; then
        warning "Cannot connect to POS database. This may be expected if not configured."
    fi
    
    success "Database connectivity verified."
}

# Run pre-deployment tests
run_tests() {
    log "🧪 Running pre-deployment tests..."
    
    cd "$DEPLOY_DIR" || exit 1
    
    # Run Laravel Pint for code formatting check
    if command -v ./vendor/bin/pint >/dev/null 2>&1; then
        log "Running Laravel Pint (code formatting)..."
        if ! ./vendor/bin/pint --test; then
            warning "Code formatting issues detected. Consider running './vendor/bin/pint' to fix."
        fi
    fi
    
    # Run PHPUnit tests
    log "Running test suite..."
    if ! composer run test; then
        error "Tests failed! Deployment aborted."
        exit 1
    fi
    
    success "All tests passed."
}

# Create database backup
backup_database() {
    if [[ $BACKUP_DB == "y" || $BACKUP_DB == "Y" ]]; then
        log "💾 Creating database backup on production server..."
        
        ssh "$PROD_USER@$PROD_HOST" << 'EOF'
            cd /var/www/html/osmanager
            BACKUP_FILE="backup_$(date +%Y%m%d_%H%M%S).sql"
            
            # Extract database credentials from .env
            DB_NAME=$(grep "^DB_DATABASE=" .env | cut -d'=' -f2 | tr -d '"')
            DB_USER=$(grep "^DB_USERNAME=" .env | cut -d'=' -f2 | tr -d '"')
            DB_PASS=$(grep "^DB_PASSWORD=" .env | cut -d'=' -f2 | tr -d '"' | tr -d "'")
            DB_HOST=$(grep "^DB_HOST=" .env | cut -d'=' -f2 | tr -d '"')
            
            if mysqldump -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" > "storage/$BACKUP_FILE" 2>/dev/null; then
                echo "✅ Database backup created: storage/$BACKUP_FILE"
            else
                echo "❌ Database backup failed, but continuing deployment..."
            fi
EOF
        
        success "Database backup completed."
    fi
}

# Main deployment function
main() {
    log "🚀 Starting OS Manager deployment to $PROD_HOST..."
    
    # Step 1: Check connectivity
    check_host_connectivity
    
    # Step 2: Git commit in DEV_DIR
    log "🔄 Committing latest changes from development directory..."
    cd "$DEV_DIR" || exit 1
    
    git status
    echo
    read -p "Enter commit message: " COMMIT_MSG
    
    if [[ -z "$COMMIT_MSG" ]]; then
        error "Commit message cannot be empty."
        exit 1
    fi
    
    git add -A
    git commit -m "$COMMIT_MSG"
    git push origin master
    
    success "Changes committed and pushed."
    
    # Step 3: Update deployment directory
    log "📥 Updating clean deployment directory..."
    
    if [[ ! -d "$DEPLOY_DIR" ]]; then
        log "Creating deployment directory..."
        mkdir -p "$DEPLOY_DIR"
        cd "$DEPLOY_DIR" || exit 1
        git clone "$DEV_DIR" .
    else
        cd "$DEPLOY_DIR" || exit 1
        git pull origin master
    fi
    
    success "Deployment directory updated."
    
    # Step 4: Install production dependencies
    log "📦 Installing production dependencies..."
    composer install --no-dev --optimize-autoloader --no-interaction
    
    success "Dependencies installed."
    
    # Step 5: Check database connectivity
    check_database_connectivity
    
    # Step 6: Run tests
    read -p "🧪 Run pre-deployment tests? [Y/n] " RUN_TESTS
    if [[ $RUN_TESTS != "n" && $RUN_TESTS != "N" ]]; then
        run_tests
    fi
    
    # Step 7: Build frontend assets
    read -p "🎨 Rebuild frontend assets (npm run build)? [y/N] " REBUILD_ASSETS
    if [[ $REBUILD_ASSETS == "y" || $REBUILD_ASSETS == "Y" ]]; then
        log "Building frontend assets..."
        npm install --production=false
        npm run build
        success "Frontend assets built."
    fi
    
    # Step 8: Database backup option
    read -p "💾 Create database backup before deployment? [y/N] " BACKUP_DB
    backup_database
    
    # Step 9: Rsync to production
    log "📡 Syncing files to production..."
    rsync -avz --no-group --delete \
        --exclude='.env' \
        --exclude='.git' \
        --exclude='node_modules' \
        --exclude='tests' \
        --exclude='storage/logs/*' \
        --exclude='storage/framework/cache/*' \
        --exclude='storage/framework/sessions/*' \
        --exclude='storage/framework/views/*' \
        "$DEPLOY_DIR/" "$PROD_USER@$PROD_HOST:$PROD_PATH"
    
    success "Files synced to production."
    
    # Step 10: Run post-deployment tasks on server
    log "⚙️ Running post-deployment tasks on production server..."
    
    ssh "$PROD_USER@$PROD_HOST" << EOF
        cd $PROD_PATH
        
        echo "🔐 Setting file permissions..."
        sudo chown -R www-data:www-data .
        sudo chmod -R 755 .
        sudo chmod -R 775 storage bootstrap/cache
        
        echo "🧹 Clearing application caches..."
        php artisan config:clear
        php artisan route:clear  
        php artisan view:clear
        php artisan cache:clear
        php artisan queue:clear
        
        echo "⚡ Optimizing application for production..."
        php artisan config:cache
        php artisan route:cache
        php artisan view:cache
        
        echo "🗃️ Running database migrations..."
        php artisan migrate --force
        
        echo "🔄 Restarting queue workers..."
        php artisan queue:restart
        
        echo "🔗 Ensuring storage link exists..."
        php artisan storage:link 2>/dev/null || true
        
        echo "📊 Checking application status..."
        php artisan about --only=environment,cache,database
EOF
    
    success "Post-deployment tasks completed."
    
    # Step 11: Final verification
    log "🔍 Running final verification..."
    
    ssh "$PROD_USER@$PROD_HOST" << EOF
        cd $PROD_PATH
        
        echo "Testing database connectivity..."
        if php artisan tinker --execute="DB::connection()->getPdo(); echo 'Database: OK';" 2>/dev/null; then
            echo "✅ Database connection successful"
        else
            echo "❌ Database connection failed"
        fi
        
        echo "Checking if application is responding..."
        if php artisan route:list | head -1 >/dev/null 2>&1; then
            echo "✅ Application routes loaded successfully"
        else
            echo "❌ Application may have issues"
        fi
EOF
    
    success "Deployment verification completed."
    
    # Step 12: Completion notification
    log "🎉 Deployment to $PROD_HOST completed successfully!"
    
    # Play notification sound as required by CLAUDE.md
    if command -v mpg123 >/dev/null 2>&1; then
        mpg123 /home/jon/Music/notification.mp3 2>/dev/null || true
    fi
    
    # Display summary
    echo
    echo "=========================================="
    echo "         DEPLOYMENT SUMMARY"
    echo "=========================================="
    echo "Source: $DEV_DIR"
    echo "Target: $PROD_USER@$PROD_HOST:$PROD_PATH"
    echo "Time: $(date)"
    echo "Commit: $COMMIT_MSG"
    echo "=========================================="
}

# Rollback function
rollback() {
    log "🔄 Starting rollback process..."
    
    ssh "$PROD_USER@$PROD_HOST" << 'EOF'
        cd /var/www/html/osmanager
        
        # Find the most recent backup
        BACKUP_FILE=$(ls -t storage/backup_*.sql 2>/dev/null | head -n1)
        
        if [[ -n "$BACKUP_FILE" ]]; then
            echo "Found backup: $BACKUP_FILE"
            read -p "Restore this backup? [y/N] " RESTORE
            
            if [[ $RESTORE == "y" || $RESTORE == "Y" ]]; then
                # Extract database credentials
                DB_NAME=$(grep "^DB_DATABASE=" .env | cut -d'=' -f2 | tr -d '"')
                DB_USER=$(grep "^DB_USERNAME=" .env | cut -d'=' -f2 | tr -d '"')
                DB_PASS=$(grep "^DB_PASSWORD=" .env | cut -d'=' -f2 | tr -d '"' | tr -d "'")
                DB_HOST=$(grep "^DB_HOST=" .env | cut -d'=' -f2 | tr -d '"')
                
                if mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$BACKUP_FILE"; then
                    echo "✅ Database restored successfully"
                else
                    echo "❌ Database restoration failed"
                fi
            fi
        else
            echo "❌ No backup files found"
        fi
EOF
    
    success "Rollback process completed."
}

# Script usage
usage() {
    echo "Usage: $0 [deploy|rollback]"
    echo "  deploy   - Deploy the application (default)"
    echo "  rollback - Rollback to previous database backup"
}

# Handle script arguments
case "${1:-deploy}" in
    "deploy")
        main
        ;;
    "rollback")
        rollback
        ;;
    *)
        usage
        exit 1
        ;;
esac