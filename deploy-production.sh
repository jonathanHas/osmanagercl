#!/bin/bash

# =============================================================================
# OS Manager Production Deployment Script
# Enhanced version with invoice parser setup and comprehensive permissions
# =============================================================================

# === CONFIGURATION ===
DEV_DIR=/var/www/html/osmanagercl               # Development directory
DEPLOY_DIR=~/deployments/osmanagercl            # Clean staging copy
PROD_USER=jon
PROD_HOST=lilThink2
PROD_PATH=/var/www/html/osmanager               # Production path
TEST_PATH=/var/www/html/osmanager-test          # Test environment path

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
NC='\033[0m' # No Color

# Logging functions
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

info() {
    echo -e "${PURPLE}[INFO]${NC} $1"
}

# Determine deployment target
select_deployment_target() {
    echo
    log "🎯 Select deployment target:"
    echo "  1) Test environment ($TEST_PATH)"
    echo "  2) Production environment ($PROD_PATH)"
    echo
    read -p "Choose target [1-2]: " TARGET_CHOICE
    
    case $TARGET_CHOICE in
        "1")
            DEPLOY_TARGET=$TEST_PATH
            TARGET_NAME="TEST"
            warning "Deploying to TEST environment: $DEPLOY_TARGET"
            ;;
        "2")
            DEPLOY_TARGET=$PROD_PATH
            TARGET_NAME="PRODUCTION"
            error "⚠️  DEPLOYING TO PRODUCTION: $DEPLOY_TARGET"
            echo
            read -p "Are you absolutely sure? Type 'DEPLOY' to continue: " PROD_CONFIRM
            if [[ $PROD_CONFIRM != "DEPLOY" ]]; then
                error "Production deployment cancelled."
                exit 1
            fi
            ;;
        *)
            error "Invalid choice. Exiting."
            exit 1
            ;;
    esac
    
    success "Target selected: $TARGET_NAME ($DEPLOY_TARGET)"
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

# Setup clean deployment directory
setup_deployment_directory() {
    log "📥 Setting up clean deployment directory..."
    
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
        exit 1
    fi
    
    # Commit and push latest changes
    echo
    warning "⚠️  You are about to deploy from branch: $CURRENT_BRANCH"
    read -p "Continue with deployment from this branch? [y/N] " CONFIRM_BRANCH
    
    if [[ $CONFIRM_BRANCH != "y" && $CONFIRM_BRANCH != "Y" ]]; then
        error "Deployment cancelled by user."
        exit 1
    fi
    
    log "🔄 Committing latest changes..."
    git status
    echo
    read -p "Enter commit message: " COMMIT_MSG
    
    if [[ -z "$COMMIT_MSG" ]]; then
        error "Commit message cannot be empty."
        exit 1
    fi
    
    git add -A
    git commit -m "$COMMIT_MSG" || warning "No changes to commit"
    git push origin "$CURRENT_BRANCH"
    
    # Setup clean deployment directory
    if [[ ! -d "$DEPLOY_DIR" ]]; then
        log "Creating deployment directory..."
        mkdir -p "$DEPLOY_DIR"
        cd "$DEPLOY_DIR" || exit 1
        git clone "$DEV_DIR" .
        git checkout "$CURRENT_BRANCH"
    else
        cd "$DEPLOY_DIR" || exit 1
        git fetch origin
        git checkout "$CURRENT_BRANCH"
        git pull origin "$CURRENT_BRANCH"
    fi
    
    success "Deployment directory ready: $DEPLOY_DIR"
}

# Install dependencies and setup environment
setup_local_environment() {
    log "📦 Setting up local deployment environment..."
    
    cd "$DEPLOY_DIR" || exit 1
    
    # Install PHP dependencies
    log "Installing Composer dependencies..."
    composer install --no-dev --optimize-autoloader --no-interaction
    
    # Copy environment file if needed
    if [[ ! -f .env ]]; then
        if [[ -f "$DEV_DIR/.env" ]]; then
            log "Copying .env from development directory..."
            cp "$DEV_DIR/.env" .env
        else
            error "No .env file found. Cannot proceed."
            exit 1
        fi
    fi
    
    # Build frontend assets
    read -p "🎨 Rebuild frontend assets (npm run build)? [y/N] " REBUILD_ASSETS
    if [[ $REBUILD_ASSETS == "y" || $REBUILD_ASSETS == "Y" ]]; then
        log "Building frontend assets..."
        npm install --production=false
        npm run build
        success "Frontend assets built."
    fi
    
    success "Local environment setup complete."
}

# Pre-deployment verification
run_pre_deployment_checks() {
    log "🔍 Running pre-deployment verification..."
    
    # Run verification script on remote server
    ssh "$PROD_USER@$PROD_HOST" << EOF
        # Create verification script if it doesn't exist
        if [[ ! -f ~/verify-deployment-ready.sh ]]; then
            echo "Creating verification script..."
            cat > ~/verify-deployment-ready.sh << 'VERIFY_EOF'
#!/bin/bash

echo "🔍 Verifying deployment readiness..."

# Check PHP version
echo "Checking PHP version..."
PHP_VERSION=\$(php -v | head -n1 | cut -d' ' -f2 | cut -d'.' -f1,2)
if [[ \$(echo "\$PHP_VERSION >= 8.2" | bc -l) -eq 1 ]]; then
    echo "✅ PHP \$PHP_VERSION is compatible"
else
    echo "❌ PHP version \$PHP_VERSION is too old (need 8.2+)"
    exit 1
fi

# Check MySQL connectivity
echo "Checking MySQL..."
if systemctl is-active --quiet mysql; then
    echo "✅ MySQL service is running"
else
    echo "❌ MySQL service is not running"
    exit 1
fi

# Check Python
echo "Checking Python..."
if command -v python3 &> /dev/null; then
    PYTHON_VERSION=\$(python3 --version | cut -d' ' -f2 | cut -d'.' -f1,2)
    echo "✅ Python \$PYTHON_VERSION is available"
else
    echo "❌ Python 3 is not installed"
    exit 1
fi

# Check system dependencies for invoice parser
echo "Checking invoice parser dependencies..."
MISSING_DEPS=()

if ! command -v tesseract &> /dev/null; then
    MISSING_DEPS+=("tesseract-ocr")
fi

if ! command -v pdftotext &> /dev/null; then
    MISSING_DEPS+=("poppler-utils")
fi

if ! command -v libreoffice &> /dev/null; then
    MISSING_DEPS+=("libreoffice")
fi

if [[ \${#MISSING_DEPS[@]} -eq 0 ]]; then
    echo "✅ All invoice parser dependencies are installed"
else
    echo "❌ Missing dependencies: \${MISSING_DEPS[*]}"
    echo "Install with: sudo apt-get install \${MISSING_DEPS[*]}"
    exit 1
fi

# Check directory structure
echo "Checking directory structure..."
TARGET_DIR="\$1"
if [[ -z "\$TARGET_DIR" ]]; then
    echo "❌ No target directory specified"
    exit 1
fi

if [[ ! -d "\$TARGET_DIR" ]]; then
    echo "⚠️  Target directory does not exist: \$TARGET_DIR"
    echo "Creating directory..."
    sudo mkdir -p "\$TARGET_DIR"
    sudo chown -R www-data:www-data "\$TARGET_DIR"
fi

echo "✅ Pre-deployment verification complete"
VERIFY_EOF

            chmod +x ~/verify-deployment-ready.sh
        fi
        
        # Run verification
        ~/verify-deployment-ready.sh "$DEPLOY_TARGET"
EOF
    
    if [[ $? -eq 0 ]]; then
        success "Pre-deployment verification passed."
    else
        error "Pre-deployment verification failed!"
        exit 1
    fi
}

# Create database backup
backup_database() {
    if [[ $TARGET_NAME == "PRODUCTION" ]]; then
        log "💾 Creating database backup on production server..."
        
        ssh "$PROD_USER@$PROD_HOST" << EOF
            cd $DEPLOY_TARGET
            BACKUP_FILE="backup_\$(date +%Y%m%d_%H%M%S).sql"
            
            if [[ -f .env ]]; then
                # Extract database credentials from .env
                DB_NAME=\$(grep "^DB_DATABASE=" .env | cut -d'=' -f2 | tr -d '"')
                DB_USER=\$(grep "^DB_USERNAME=" .env | cut -d'=' -f2 | tr -d '"')
                DB_PASS=\$(grep "^DB_PASSWORD=" .env | cut -d'=' -f2 | tr -d '"' | tr -d "'")
                DB_HOST=\$(grep "^DB_HOST=" .env | cut -d'=' -f2 | tr -d '"')
                
                if mysqldump -h"\$DB_HOST" -u"\$DB_USER" -p"\$DB_PASS" "\$DB_NAME" > "storage/\$BACKUP_FILE" 2>/dev/null; then
                    echo "✅ Database backup created: storage/\$BACKUP_FILE"
                else
                    echo "❌ Database backup failed, but continuing deployment..."
                fi
            else
                echo "⚠️  No .env file found, skipping backup"
            fi
EOF
        
        success "Database backup completed."
    else
        log "Skipping database backup for test environment."
    fi
}

# Deploy files to server
deploy_files() {
    log "📡 Syncing files to $TARGET_NAME..."
    
    rsync -avz --no-group --delete \
        --exclude='.env' \
        --exclude='.git' \
        --exclude='node_modules' \
        --exclude='tests' \
        --exclude='storage/logs/*' \
        --exclude='storage/framework/cache/*' \
        --exclude='storage/framework/sessions/*' \
        --exclude='storage/framework/views/*' \
        --exclude='storage/app/private/temp/*' \
        "$DEPLOY_DIR/" "$PROD_USER@$PROD_HOST:$DEPLOY_TARGET"
    
    success "Files synced to $TARGET_NAME."
}

# Setup invoice parser on server
setup_invoice_parser() {
    log "🐍 Setting up invoice parser on server..."
    
    ssh "$PROD_USER@$PROD_HOST" << EOF
        cd $DEPLOY_TARGET/scripts/invoice-parser || exit 1
        
        # Check if virtual environment already exists
        if [[ -d "venv" && -f "venv/bin/python" ]]; then
            echo "✅ Virtual environment already exists, verifying functionality..."
            
            # Test if parser works
            if bash -c "source venv/bin/activate && python invoice_parser_laravel.py --help" >/dev/null 2>&1; then
                echo "✅ Invoice parser is already working, skipping setup"
                
                # Just ensure permissions are correct
                chown -R www-data:www-data . 2>/dev/null || true
                chmod -R 755 . 2>/dev/null || true
                chmod +x venv/bin/* 2>/dev/null || true
                
                echo "✅ Parser setup verification complete"
                exit 0
            else
                echo "⚠️  Virtual environment exists but parser test failed, attempting full setup..."
            fi
        else
            echo "📦 Virtual environment not found, running full setup..."
        fi
        
        # Check if python3-venv is available
        if ! python3 -m venv --help >/dev/null 2>&1; then
            echo "❌ python3-venv is not installed"
            echo "📋 Please run manually on server:"
            echo "   sudo apt-get update && sudo apt-get install -y python3-venv"
            echo "   Then re-run deployment or setup parser manually:"
            echo "   sudo ./setup-invoice-parser-production.sh $DEPLOY_TARGET www-data www-data"
            exit 1
        fi
        
        # Run the existing setup script
        if [[ -f setup.sh ]]; then
            echo "Running invoice parser setup..."
            bash setup.sh
            SETUP_EXIT_CODE=\$?
            
            if [[ \$SETUP_EXIT_CODE -eq 0 ]]; then
                echo "✅ Invoice parser setup successful"
            else
                echo "⚠️  Setup script completed with warnings (exit code: \$SETUP_EXIT_CODE)"
                echo "This may be due to permission issues in non-interactive SSH"
                echo "📋 To complete setup manually, run on server:"
                echo "   sudo ./setup-invoice-parser-production.sh $DEPLOY_TARGET www-data www-data"
            fi
        else
            echo "❌ Invoice parser setup script not found"
            exit 1
        fi
EOF
    
    SETUP_RESULT=$?
    
    if [[ $SETUP_RESULT -eq 0 ]]; then
        success "Invoice parser setup completed successfully."
    else
        warning "Invoice parser setup completed with issues."
        warning "You may need to run the setup manually:"
        warning "  ssh $PROD_USER@$PROD_HOST"
        warning "  cd $DEPLOY_TARGET"
        warning "  sudo ./setup-invoice-parser-production.sh $DEPLOY_TARGET www-data www-data"
        
        echo
        read -p "Continue deployment despite parser setup issues? [y/N] " CONTINUE_ANYWAY
        if [[ $CONTINUE_ANYWAY != "y" && $CONTINUE_ANYWAY != "Y" ]]; then
            error "Deployment stopped due to parser setup issues."
            exit 1
        else
            warning "Continuing deployment. Please fix parser setup manually later."
        fi
    fi
}

# Fix all permissions
fix_permissions() {
    log "🔐 Fixing all permissions on server..."
    
    ssh "$PROD_USER@$PROD_HOST" << EOF
        cd $DEPLOY_TARGET
        
        echo "Setting basic file permissions..."
        sudo chown -R www-data:www-data .
        sudo chmod -R 755 .
        sudo chmod -R 775 storage bootstrap/cache
        
        echo "Creating invoice storage directories..."
        sudo mkdir -p storage/app/private/temp/invoices
        sudo mkdir -p storage/app/private/invoices/2025/{01,02,03,04,05,06,07,08,09,10,11,12}
        sudo mkdir -p storage/app/private/invoices/attachments
        
        echo "Setting invoice directory permissions..."
        sudo chown -R www-data:www-data storage/app/private
        sudo chmod -R 775 storage/app/private
        
        # Set proper permissions for invoice parser
        if [[ -d scripts/invoice-parser ]]; then
            echo "Setting invoice parser permissions..."
            sudo chown -R www-data:www-data scripts/invoice-parser
            sudo chmod -R 755 scripts/invoice-parser
            sudo chmod +x scripts/invoice-parser/venv/bin/*
        fi
        
        # Test write permissions
        echo "Testing write permissions..."
        sudo -u www-data touch storage/app/private/temp/test.txt 2>/dev/null
        if [[ \$? -eq 0 ]]; then
            sudo -u www-data rm storage/app/private/temp/test.txt
            echo "✅ Write permissions working"
        else
            echo "❌ Write permissions failed"
            exit 1
        fi
EOF
    
    if [[ $? -eq 0 ]]; then
        success "Permissions fixed successfully."
    else
        error "Permission fixing failed!"
        exit 1
    fi
}

# Run post-deployment tasks
run_post_deployment_tasks() {
    log "⚙️ Running post-deployment tasks on server..."
    
    ssh "$PROD_USER@$PROD_HOST" << EOF
        cd $DEPLOY_TARGET
        
        echo "🧹 Clearing application caches..."
        php artisan config:clear
        php artisan route:clear  
        php artisan view:clear
        php artisan cache:clear
        php artisan queue:clear
        
        echo "⚡ Optimizing application..."
        php artisan config:cache
        php artisan route:cache
        php artisan view:cache
        
        echo "🗃️ Running database migrations..."
        php artisan migrate --force
        
        echo "🔗 Ensuring storage link exists..."
        php artisan storage:link 2>/dev/null || true
        
        echo "🔄 Restarting queue workers..."
        php artisan queue:restart
        
        # Restart supervisor if it exists
        if command -v supervisorctl &> /dev/null; then
            echo "🔄 Restarting supervisor workers..."
            sudo supervisorctl restart all 2>/dev/null || true
        fi
        
        echo "📊 Checking application status..."
        php artisan about --only=environment,cache,database 2>/dev/null || echo "Basic status check complete"
EOF
    
    success "Post-deployment tasks completed."
}

# Run deployment tests
run_deployment_tests() {
    log "🧪 Running deployment tests..."
    
    ssh "$PROD_USER@$PROD_HOST" << EOF
        cd $DEPLOY_TARGET
        
        echo "Testing database connectivity..."
        if php artisan tinker --execute="DB::connection()->getPdo(); echo 'Database: OK';" 2>/dev/null | grep -q "Database: OK"; then
            echo "✅ Database connection successful"
        else
            echo "❌ Database connection failed"
            exit 1
        fi
        
        echo "Testing application routes..."
        if php artisan route:list | head -1 >/dev/null 2>&1; then
            echo "✅ Application routes loaded successfully"
        else
            echo "❌ Application routes failed to load"
            exit 1
        fi
        
        echo "Testing invoice parser..."
        if [[ -f scripts/invoice-parser/invoice_parser_laravel.py ]]; then
            cd scripts/invoice-parser
            if sudo -u www-data bash -c "source venv/bin/activate && python invoice_parser_laravel.py --help" >/dev/null 2>&1; then
                echo "✅ Invoice parser working"
            else
                echo "❌ Invoice parser failed"
                exit 1
            fi
            cd ../..
        fi
        
        echo "Testing file permissions..."
        if sudo -u www-data touch storage/app/private/temp/permission-test.txt 2>/dev/null; then
            sudo -u www-data rm storage/app/private/temp/permission-test.txt
            echo "✅ File permissions working"
        else
            echo "❌ File permissions failed"
            exit 1
        fi
        
        echo "✅ All deployment tests passed"
EOF
    
    if [[ $? -eq 0 ]]; then
        success "All deployment tests passed."
    else
        error "Some deployment tests failed!"
        return 1
    fi
}

# Main deployment function
main() {
    # Create deployment log
    DEPLOY_LOG="deploy-production_$(date +%Y%m%d_%H%M%S).log"
    log "🚀 Starting enhanced OS Manager deployment..."
    log "📝 Deployment log: $DEPLOY_LOG"
    
    # Step 1: Select deployment target
    select_deployment_target
    
    # Step 2: Check connectivity
    check_host_connectivity
    
    # Step 3: Setup deployment directory
    setup_deployment_directory
    
    # Step 4: Setup local environment
    setup_local_environment
    
    # Step 5: Pre-deployment checks
    run_pre_deployment_checks
    
    # Step 6: Backup database (production only)
    backup_database
    
    # Step 7: Deploy files
    deploy_files
    
    # Step 8: Setup invoice parser
    setup_invoice_parser
    
    # Step 9: Fix permissions
    fix_permissions
    
    # Step 10: Post-deployment tasks
    run_post_deployment_tasks
    
    # Step 11: Run tests
    if ! run_deployment_tests; then
        echo
        read -p "Some tests failed. Continue anyway? [y/N] " CONTINUE_ANYWAY
        if [[ $CONTINUE_ANYWAY != "y" && $CONTINUE_ANYWAY != "Y" ]]; then
            error "Deployment aborted due to test failures."
            exit 1
        fi
    fi
    
    # Step 12: Success
    success "🎉 Deployment to $TARGET_NAME completed successfully!"
    
    # Play notification sound
    if command -v mpg123 >/dev/null 2>&1; then
        mpg123 /home/jon/Music/notification.mp3 2>/dev/null || true
    fi
    
    # Display summary
    echo
    echo "=========================================="
    echo "         DEPLOYMENT SUMMARY"
    echo "=========================================="
    echo "Target: $TARGET_NAME ($DEPLOY_TARGET)"
    echo "Time: $(date)"
    echo "Branch: $(cd "$DEV_DIR" && git branch --show-current)"
    echo "=========================================="
    
    if [[ $TARGET_NAME == "TEST" ]]; then
        echo
        info "🧪 Test deployment complete!"
        info "Next steps:"
        info "1. Test all functionality on $DEPLOY_TARGET"
        info "2. Run this script again with production target when ready"
    fi
}

# Script usage
usage() {
    echo "Usage: $0 [deploy]"
    echo "  deploy - Deploy the application with invoice parser setup"
}

# Handle script arguments
case "${1:-deploy}" in
    "deploy")
        # Redirect all output to both terminal and log file
        main 2>&1 | tee "deploy-production_$(date +%Y%m%d_%H%M%S).log"
        ;;
    *)
        usage
        exit 1
        ;;
esac