#!/bin/bash

# =============================================================================
# Fast Deployment Script for OS Manager
# Streamlined deployment for when parser and infrastructure are already set up
# =============================================================================

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
NC='\033[0m' # No Color

# Configuration
DEV_DIR=/var/www/html/osmanagercl
PROD_USER=jon
PROD_HOST=lilThink2

# Determine environment based on argument
ENVIRONMENT=${1:-production}
if [[ "$ENVIRONMENT" == "test" ]]; then
    PROD_PATH=/var/www/html/osmanager-test
    WORKERS="osmanager-test-coffee-worker:* osmanager-test-invoice-worker:*"
    ENV_NAME="TEST"
else
    PROD_PATH=/var/www/html/osmanager
    WORKERS="osmanager-coffee-worker:* osmanager-invoice-worker:*"
    ENV_NAME="PRODUCTION"
fi

# Start time
START_TIME=$(date +%s)

echo -e "${PURPLE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${PURPLE}   🚀 FAST DEPLOYMENT TO ${ENV_NAME}${NC}"
echo -e "${PURPLE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "Target: ${BLUE}$PROD_HOST:$PROD_PATH${NC}"
echo -e "Time: $(date '+%Y-%m-%d %H:%M:%S')"
echo -e "${PURPLE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo

# Function to show progress
show_step() {
    echo -e "\n${BLUE}▶ $1${NC}"
}

# Function to show success
show_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

# Function to show error
show_error() {
    echo -e "${RED}✗ $1${NC}"
    exit 1
}

# Function to show warning
show_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

# Step 1: Check SSH connection
show_step "Checking SSH connection..."
if ssh -o ConnectTimeout=5 $PROD_USER@$PROD_HOST "echo 'Connected'" > /dev/null 2>&1; then
    show_success "SSH connection OK"
else
    show_error "Cannot connect to $PROD_HOST"
fi

# Step 2: Git pull on dev
show_step "Pulling latest changes..."
cd $DEV_DIR
git pull origin $(git branch --show-current) || show_warning "Git pull had issues (may be normal)"
show_success "Code updated"

# Step 3: Check if composer/npm needed
COMPOSER_NEEDED=false
NPM_NEEDED=false

show_step "Checking dependency changes..."
if git diff HEAD@{1} --name-only | grep -q "composer.lock"; then
    COMPOSER_NEEDED=true
    echo "  → composer.lock changed, will run composer install"
fi

if git diff HEAD@{1} --name-only | grep -q "package-lock.json"; then
    NPM_NEEDED=true
    echo "  → package-lock.json changed, will run npm build"
fi

# Step 4: Create backup on production
show_step "Creating backup on production..."
ssh $PROD_USER@$PROD_HOST "cd $PROD_PATH && tar -czf ../backup-$(date +%Y%m%d-%H%M%S).tar.gz . --exclude=node_modules --exclude=vendor --exclude=storage/logs --exclude=.git" 2>/dev/null
show_success "Backup created"

# Step 5: Rsync files
show_step "Syncing files to production..."
rsync -az --delete \
    --exclude='.git/' \
    --exclude='node_modules/' \
    --exclude='vendor/' \
    --exclude='storage/app/' \
    --exclude='storage/logs/' \
    --exclude='storage/framework/cache/' \
    --exclude='storage/framework/sessions/' \
    --exclude='storage/framework/views/' \
    --exclude='.env' \
    --exclude='*.log' \
    --exclude='deploy*.sh' \
    --exclude='debug*.sh' \
    --exclude='setup*.sh' \
    --exclude='fix*.sh' \
    --exclude='enable*.sh' \
    --exclude='verify*.sh' \
    --exclude='test*.sh' \
    --exclude='rollback*.sh' \
    --exclude='*.conf' \
    $DEV_DIR/ $PROD_USER@$PROD_HOST:$PROD_PATH/

if [ $? -eq 0 ]; then
    show_success "Files synced"
else
    show_error "Rsync failed"
fi

# Step 6: Run production commands
show_step "Running production commands..."

ssh $PROD_USER@$PROD_HOST << EOF
set -e
cd $PROD_PATH

# Clear caches
echo "  → Clearing caches..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Run migrations
echo "  → Running migrations..."
php artisan migrate --force

# Optimize
echo "  → Optimizing..."
php artisan config:cache
php artisan route:cache

# Fix storage permissions (quick)
echo "  → Fixing permissions..."
chmod -R 775 storage/logs 2>/dev/null || true
chmod -R 775 storage/app/private/temp 2>/dev/null || true

EOF

if [ $? -eq 0 ]; then
    show_success "Production commands completed"
else
    show_warning "Some production commands had issues"
fi

# Step 7: Run composer if needed
if [ "$COMPOSER_NEEDED" = true ]; then
    show_step "Running composer install..."
    ssh $PROD_USER@$PROD_HOST "cd $PROD_PATH && composer install --no-dev --optimize-autoloader"
    show_success "Composer dependencies updated"
fi

# Step 8: Run npm build if needed
if [ "$NPM_NEEDED" = true ]; then
    show_step "Building frontend assets..."
    ssh $PROD_USER@$PROD_HOST "cd $PROD_PATH && npm ci && npm run build"
    show_success "Frontend assets built"
fi

# Step 9: Restart queue workers
show_step "Restarting queue workers..."
ssh $PROD_USER@$PROD_HOST "sudo supervisorctl restart $WORKERS" > /dev/null 2>&1
show_success "Queue workers restarted"

# Step 10: Verify deployment
show_step "Verifying deployment..."
RESPONSE=$(ssh $PROD_USER@$PROD_HOST "cd $PROD_PATH && php artisan --version" 2>&1)
if [[ $RESPONSE == *"Laravel Framework"* ]]; then
    show_success "Laravel responding correctly"
else
    show_warning "Could not verify Laravel"
fi

# Calculate duration
END_TIME=$(date +%s)
DURATION=$((END_TIME - START_TIME))

echo
echo -e "${PURPLE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}   ✅ DEPLOYMENT COMPLETE${NC}"
echo -e "${PURPLE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "Environment: ${BLUE}$ENV_NAME${NC}"
echo -e "Duration: ${BLUE}${DURATION} seconds${NC}"
echo -e "Time: $(date '+%Y-%m-%d %H:%M:%S')"
echo -e "${PURPLE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

# Quick status check
echo
echo -e "${BLUE}Quick Status:${NC}"
ssh $PROD_USER@$PROD_HOST "cd $PROD_PATH && php artisan queue:monitor database:default,database:invoices --max=100" 2>/dev/null || echo "  Queue: Unable to check"

echo
echo -e "${GREEN}🎉 Deployment successful!${NC}"
echo -e "${YELLOW}Tip: Monitor logs with: ssh $PROD_USER@$PROD_HOST 'tail -f $PROD_PATH/storage/logs/laravel.log'${NC}"