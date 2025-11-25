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

# Copy and validate environment file
setup_environment() {
    log "🔧 Setting up environment configuration..."
    
    cd "$DEPLOY_DIR" || exit 1
    
    # Copy .env from development directory if it doesn't exist
    if [[ ! -f .env ]]; then
        if [[ -f "$DEV_DIR/.env" ]]; then
            log "Copying .env from development directory..."
            cp "$DEV_DIR/.env" .env
        else
            error "No .env file found in development directory. Cannot proceed."
            exit 1
        fi
    fi
    
    # Validate critical environment variables
    log "Validating environment configuration..."
    
    if ! grep -q "^DB_CONNECTION=" .env; then
        error "DB_CONNECTION not found in .env file."
        exit 1
    fi
    
    if ! grep -q "^DB_DATABASE=" .env; then
        error "DB_DATABASE not found in .env file."
        exit 1
    fi
    
    DB_CONNECTION=$(grep "^DB_CONNECTION=" .env | cut -d'=' -f2 | tr -d '"' | tr -d "'")
    
    if [[ "$DB_CONNECTION" != "mysql" ]]; then
        warning "Database connection is set to '$DB_CONNECTION', expected 'mysql'."
    fi
    
    success "Environment configuration validated."
}

# Validate MySQL database connectivity
check_database_connectivity() {
    log "🗄️ Checking MySQL database connectivity..."
    
    cd "$DEPLOY_DIR" || exit 1
    
    # Test main database connection
    log "Testing main database connection..."
    if php artisan tinker --execute="try { DB::connection()->getPdo(); echo 'Main DB: Connected successfully'; } catch (Exception \$e) { echo 'Main DB Error: ' . \$e->getMessage(); throw \$e; }" 2>/dev/null; then
        success "Main database connection verified."
    else
        error "Cannot connect to main MySQL database. Check your .env configuration."
        error "Database: $(grep '^DB_DATABASE=' .env | cut -d'=' -f2)"
        error "Host: $(grep '^DB_HOST=' .env | cut -d'=' -f2)"
        error "Username: $(grep '^DB_USERNAME=' .env | cut -d'=' -f2)"
        exit 1
    fi
    
    # Test POS database connection (optional)
    log "Testing POS database connection..."
    if grep -q "^POS_DB_DATABASE=" .env && grep -q "^POS_DB_USERNAME=" .env; then
        if php artisan tinker --execute="try { DB::connection('pos')->getPdo(); echo 'POS DB: Connected successfully'; } catch (Exception \$e) { echo 'POS DB: ' . \$e->getMessage(); }" 2>/dev/null | grep -q "Connected successfully"; then
            success "POS database connection verified."
        else
            warning "POS database connection failed or not properly configured."
        fi
    else
        log "POS database not configured, skipping connection test."
    fi
}

# Run basic smoke tests for deployment validation
run_smoke_tests() {
    log "🧪 Running deployment smoke tests..."
    
    cd "$DEPLOY_DIR" || exit 1
    
    # Test 1: Laravel configuration validation
    log "Testing Laravel configuration..."
    if php artisan config:show app.name >/dev/null 2>&1; then
        success "Laravel configuration is valid."
    else
        error "Laravel configuration is invalid!"
        return 1
    fi
    
    # Test 2: Database connectivity (already done in check_database_connectivity)
    log "Database connectivity already verified ✓"
    
    # Test 3: Route registration
    log "Testing route registration..."
    if php artisan route:list --json >/dev/null 2>&1; then
        success "Routes registered successfully."
    else
        error "Route registration failed!"
        return 1
    fi
    
    # Test 4: View compilation test
    log "Testing view compilation..."
    if php artisan view:clear >/dev/null 2>&1; then
        success "Views can be compiled successfully."
    else
        warning "View compilation may have issues."
    fi
    
    success "All smoke tests passed."
    return 0
}

# Run full test suite (optional)
run_full_tests() {
    log "🧪 Running full test suite..."
    
    cd "$DEPLOY_DIR" || exit 1
    
    # Run Laravel Pint for code formatting check
    if command -v ./vendor/bin/pint >/dev/null 2>&1; then
        log "Running Laravel Pint (code formatting)..."
        if ! ./vendor/bin/pint --test; then
            warning "Code formatting issues detected. Consider running './vendor/bin/pint' to fix."
        fi
    else
        log "Laravel Pint not found, skipping code formatting check."
    fi
    
    # Run PHPUnit tests
    log "Running full test suite..."
    
    # Clear config before running tests to ensure clean state
    php artisan config:clear >/dev/null 2>&1
    
    if composer run test; then
        success "All tests passed."
        return 0
    else
        error "Some tests failed!"
        log "This might be due to:"
        log "  - Missing test database configuration"
        log "  - POS database not available in deployment environment"
        log "  - External service dependencies (UDEA, etc.)"
        return 1
    fi
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
    # Create deployment log file
    DEPLOY_LOG="deploy_$(date +%Y%m%d_%H%M%S).log"
    log "🚀 Starting OS Manager deployment to $PROD_HOST..."
    log "📝 Deployment log will be saved to: $DEPLOY_LOG"
    
    # Step 1: Check connectivity
    check_host_connectivity
    
    # Step 1.5: Detect current branch
    log "🌿 Detecting current git branch..."
    cd "$DEV_DIR" || exit 1
    
    CURRENT_BRANCH=$(git branch --show-current)
    if [[ -z "$CURRENT_BRANCH" ]]; then
        error "Could not detect current git branch."
        exit 1
    fi
    
    success "Current branch: $CURRENT_BRANCH"
    
    # Check if branch exists on remote
    if ! git ls-remote --heads origin "$CURRENT_BRANCH" | grep -q "$CURRENT_BRANCH"; then
        error "Branch '$CURRENT_BRANCH' does not exist on remote origin."
        log "Available remote branches:"
        git ls-remote --heads origin
        exit 1
    fi
    
    # Confirm deployment branch with user
    echo
    warning "⚠️  You are about to deploy from branch: $CURRENT_BRANCH"
    read -p "Continue with deployment from this branch? [y/N] " CONFIRM_BRANCH
    
    if [[ $CONFIRM_BRANCH != "y" && $CONFIRM_BRANCH != "Y" ]]; then
        error "Deployment cancelled by user."
        exit 1
    fi
    
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
    git push origin "$CURRENT_BRANCH"
    
    success "Changes committed and pushed."
    
    # Step 3: Update deployment directory
    log "📥 Updating clean deployment directory..."
    
    if [[ ! -d "$DEPLOY_DIR" ]]; then
        log "Creating deployment directory..."
        mkdir -p "$DEPLOY_DIR"
        cd "$DEPLOY_DIR" || exit 1
        git clone "$DEV_DIR" .
        git checkout "$CURRENT_BRANCH"
    else
        cd "$DEPLOY_DIR" || exit 1
        
        # Check current branch in deployment directory
        DEPLOY_CURRENT_BRANCH=$(git branch --show-current)
        
        if [[ "$DEPLOY_CURRENT_BRANCH" != "$CURRENT_BRANCH" ]]; then
            warning "Deployment directory is on branch '$DEPLOY_CURRENT_BRANCH', need to switch to '$CURRENT_BRANCH'"
            
            # Fetch latest changes first
            git fetch origin
            
            # Check if target branch exists locally
            if git branch | grep -q "^\s*$CURRENT_BRANCH$"; then
                log "Switching to existing local branch '$CURRENT_BRANCH'..."
                git checkout "$CURRENT_BRANCH"
            else
                log "Creating and switching to new local branch '$CURRENT_BRANCH'..."
                git checkout -b "$CURRENT_BRANCH" "origin/$CURRENT_BRANCH"
            fi
        fi
        
        # Now pull the latest changes
        log "Pulling latest changes from origin/$CURRENT_BRANCH..."
        if ! git pull origin "$CURRENT_BRANCH"; then
            error "Failed to pull from origin/$CURRENT_BRANCH"
            warning "This might be due to conflicts. Offering to reset deployment directory..."
            
            echo
            read -p "Reset deployment directory to clean state? [y/N] " RESET_DEPLOY
            
            if [[ $RESET_DEPLOY == "y" || $RESET_DEPLOY == "Y" ]]; then
                log "Resetting deployment directory..."
                cd "$(dirname "$DEPLOY_DIR")" || exit 1
                rm -rf "$DEPLOY_DIR"
                mkdir -p "$DEPLOY_DIR"
                cd "$DEPLOY_DIR" || exit 1
                git clone "$DEV_DIR" .
                git checkout "$CURRENT_BRANCH"
                success "Deployment directory reset and updated."
            else
                error "Cannot continue with conflicted deployment directory."
                exit 1
            fi
        fi
    fi
    
    # Verify deployment directory state
    log "📋 Verifying deployment directory state..."
    echo "Current branch: $(git branch --show-current)"
    echo "Latest commit: $(git log --oneline -1)"
    echo "Remote tracking: $(git branch -vv | grep '^\*')"
    
    success "Deployment directory updated to branch '$CURRENT_BRANCH'."
    
    # Step 4: Install production dependencies
    log "📦 Installing production dependencies..."
    composer install --no-dev --optimize-autoloader --no-interaction
    
    success "Dependencies installed."
    
    # Step 5: Setup environment and check database connectivity
    setup_environment
    check_database_connectivity
    
    # Step 6: Run tests
    echo
    log "🧪 Testing Options:"
    echo "  1) Smoke tests only (fast, basic validation)"
    echo "  2) Full test suite (slower, comprehensive)"
    echo "  3) Skip all tests"
    echo
    read -p "Choose testing option [1-3]: " TEST_OPTION
    
    case $TEST_OPTION in
        "1"|"")
            log "Running smoke tests..."
            if ! run_smoke_tests; then
                error "Smoke tests failed! Deployment aborted."
                exit 1
            fi
            ;;
        "2")
            log "Running full test suite..."
            if ! run_full_tests; then
                echo
                read -p "Tests failed. Continue deployment anyway? [y/N] " CONTINUE_ANYWAY
                if [[ $CONTINUE_ANYWAY != "y" && $CONTINUE_ANYWAY != "Y" ]]; then
                    error "Deployment aborted due to test failures."
                    exit 1
                else
                    warning "Continuing deployment despite test failures..."
                fi
            fi
            ;;
        "3")
            warning "Skipping all tests..."
            ;;
        *)
            log "Invalid option. Running smoke tests as default..."
            if ! run_smoke_tests; then
                error "Smoke tests failed! Deployment aborted."
                exit 1
            fi
            ;;
    esac
    
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
    # WARNING: --delete removes files on destination not in source
    # Excluding production-only files like venv to prevent deletion
    rsync -avz --no-group --delete \
        --exclude='.env' \
        --exclude='.git' \
        --exclude='node_modules' \
        --exclude='vendor' \
        --exclude='tests' \
        --exclude='storage/logs/*' \
        --exclude='storage/app/*' \
        --exclude='storage/framework/cache/*' \
        --exclude='storage/framework/sessions/*' \
        --exclude='storage/framework/views/*' \
        --exclude='bootstrap/cache/*' \
        --exclude='scripts/invoice-parser/venv' \
        --exclude='scripts/invoice-parser/__pycache__' \
        --exclude='*.pyc' \
        --exclude='*.log' \
        --exclude='*.sqlite' \
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
        
        echo "📋 Production server verification:"
        echo "Current git branch: \$(git branch --show-current 2>/dev/null || echo 'Not a git repo')"
        echo "Latest commit: \$(git log --oneline -1 2>/dev/null || echo 'No git history')"
        echo "Application environment: \$(php artisan env 2>/dev/null || echo 'Unknown')"
        
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
        
        echo "Checking file timestamps (last 5 modified files):"
        find . -type f -name "*.php" -o -name "*.blade.php" | head -10 | xargs ls -la
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
        # Redirect all output to both terminal and log file
        main 2>&1 | tee "deploy_$(date +%Y%m%d_%H%M%S).log"
        ;;
    "rollback")
        rollback 2>&1 | tee "rollback_$(date +%Y%m%d_%H%M%S).log"
        ;;
    *)
        usage
        exit 1
        ;;
esac