#!/bin/bash

# =============================================================================
# Enable Invoice Priority Queue
# Simple script to activate the built-in invoice queue priority system
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

# Auto-detect environment name from path
if [[ "$APP_PATH" == *"test"* ]]; then
    ENV_NAME="test"
    SERVICE_NAME="osmanager-test-queue-worker"
else
    ENV_NAME="production"
    SERVICE_NAME="osmanager-queue-worker"
fi

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

# Check current configuration
check_current_config() {
    log "🔍 Checking current configuration..."
    
    cd "$APP_PATH" || exit 1
    
    # Check if INVOICE_PARSING_QUEUE is already set
    if grep -q "^INVOICE_PARSING_QUEUE=" .env 2>/dev/null; then
        CURRENT_QUEUE=$(grep "^INVOICE_PARSING_QUEUE=" .env | cut -d'=' -f2)
        info "Current invoice parsing queue: $CURRENT_QUEUE"
        
        if [[ "$CURRENT_QUEUE" == "invoices" ]]; then
            success "Invoice priority queue already configured"
            return 0
        fi
    else
        info "INVOICE_PARSING_QUEUE not set (using default queue)"
    fi
    
    # Check Laravel config
    CONFIGURED_QUEUE=$(php artisan config:show invoices.parsing.queue_name 2>/dev/null | grep -o '"[^"]*"' | tr -d '"' || echo "default")
    info "Laravel config shows queue: $CONFIGURED_QUEUE"
    
    return 1
}

# Add environment variable
add_env_variable() {
    log "⚙️ Configuring invoice priority queue..."
    
    cd "$APP_PATH" || exit 1
    
    # Add or update INVOICE_PARSING_QUEUE
    if grep -q "^INVOICE_PARSING_QUEUE=" .env 2>/dev/null; then
        # Update existing
        sed -i 's/^INVOICE_PARSING_QUEUE=.*/INVOICE_PARSING_QUEUE=invoices/' .env
        success "Updated INVOICE_PARSING_QUEUE=invoices in .env"
    else
        # Add new
        echo "" >> .env
        echo "# Invoice Processing Queue Priority" >> .env
        echo "INVOICE_PARSING_QUEUE=invoices" >> .env
        success "Added INVOICE_PARSING_QUEUE=invoices to .env"
    fi
    
    # Clear Laravel config cache
    php artisan config:clear >/dev/null 2>&1
    
    # Verify the change
    NEW_QUEUE=$(php artisan config:show invoices.parsing.queue_name 2>/dev/null | grep -o '"[^"]*"' | tr -d '"' || echo "error")
    if [[ "$NEW_QUEUE" == "invoices" ]]; then
        success "Laravel now configured to use 'invoices' queue"
    else
        error "Failed to update Laravel configuration"
        exit 1
    fi
}

# Update supervisor for queue priority
update_supervisor_config() {
    log "📝 Updating supervisor for queue priority..."
    
    SUPERVISOR_CONF="/etc/supervisor/conf.d/${SERVICE_NAME}.conf"
    
    if [[ ! -f "$SUPERVISOR_CONF" ]]; then
        error "Supervisor config not found: $SUPERVISOR_CONF"
        info "Run setup-queue-workers.sh first"
        exit 1
    fi
    
    # Check if already configured for priority
    if grep -q "queue=invoices,default" "$SUPERVISOR_CONF"; then
        success "Supervisor already configured for queue priority"
        return 0
    fi
    
    # Update command line to include queue priority
    sudo sed -i 's|queue:work --sleep=3|queue:work --sleep=1 --queue=invoices,default|g' "$SUPERVISOR_CONF"
    
    if grep -q "queue=invoices,default" "$SUPERVISOR_CONF"; then
        success "Updated supervisor for queue priority (invoices first, then default)"
        
        # Reload supervisor
        log "Reloading supervisor configuration..."
        sudo supervisorctl reread
        sudo supervisorctl update
        
        # Restart workers to apply changes
        log "Restarting queue workers..."
        sudo supervisorctl restart "$SERVICE_NAME:*"
        
        success "Queue workers restarted with priority configuration"
    else
        error "Failed to update supervisor configuration"
        exit 1
    fi
}

# Clear queue backlog
clear_queue_backlog() {
    log "🧹 Clearing queue backlog..."
    
    cd "$APP_PATH" || exit 1
    
    # Check current queue size
    PENDING_JOBS=$(php artisan tinker --execute="echo DB::table('jobs')->count();" 2>/dev/null || echo "0")
    
    if [[ "$PENDING_JOBS" -gt 0 ]]; then
        warning "$PENDING_JOBS jobs in queue"
        
        echo "Clear all pending jobs? This will remove:"
        echo "  - Coffee monitoring jobs (safe to clear)"
        echo "  - Any pending invoice jobs (will need to re-upload)"
        echo
        read -p "Clear queue? (y/N): " -n 1 -r
        echo
        
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            php artisan queue:clear
            php artisan queue:flush
            success "Queue cleared"
        else
            info "Queue not cleared - new uploads will use priority system"
        fi
    else
        success "No jobs in queue"
    fi
}

# Test the configuration
test_configuration() {
    log "🧪 Testing configuration..."
    
    cd "$APP_PATH" || exit 1
    
    # Check that ParseInvoiceFile would use invoices queue
    TEST_QUEUE=$(php artisan tinker --execute="
        \$job = new App\Jobs\ParseInvoiceFile(new App\Models\InvoiceUploadFile());
        echo \$job->queue ?? 'default';
    " 2>/dev/null || echo "error")
    
    if [[ "$TEST_QUEUE" == "invoices" ]]; then
        success "✅ ParseInvoiceFile will use 'invoices' queue"
    else
        warning "⚠️ ParseInvoiceFile test returned: $TEST_QUEUE"
    fi
    
    # Check supervisor status
    SUPERVISOR_STATUS=$(sudo supervisorctl status "$SERVICE_NAME:*" 2>/dev/null | grep -c "RUNNING" || echo "0")
    if [[ $SUPERVISOR_STATUS -gt 0 ]]; then
        success "✅ $SUPERVISOR_STATUS queue workers running"
    else
        error "❌ No queue workers running"
    fi
}

# Show final status
show_final_status() {
    echo
    echo "=========================================="
    echo "   INVOICE PRIORITY QUEUE ENABLED"
    echo "=========================================="
    echo "Environment: $ENV_NAME"
    echo "Queue Priority: invoices → default"
    echo "=========================================="
    echo
    success "✅ Configuration complete!"
    echo
    info "📊 How it works now:"
    echo "   • Invoice parsing jobs → 'invoices' queue (high priority)"
    echo "   • Coffee/other jobs → 'default' queue (lower priority)"
    echo "   • Workers process 'invoices' first, then 'default'"
    echo
    info "🧪 Test it:"
    echo "   1. Upload a new invoice"
    echo "   2. Should process within seconds"
    echo "   3. Check logs: tail -f $APP_PATH/storage/logs/queue-worker.log"
    echo
    info "📋 Monitor:"
    echo "   Queue status: php artisan queue:monitor"
    echo "   Worker status: sudo supervisorctl status"
    echo
    echo "=========================================="
}

# Main function
main() {
    echo "🚀 Enable Invoice Priority Queue"
    echo "==============================="
    echo "Environment: $ENV_NAME"
    echo "Application: $APP_PATH"
    echo "==============================="
    
    # Check if we need to configure
    if check_current_config; then
        log "Priority queue already enabled - checking workers..."
        update_supervisor_config
        test_configuration
        show_final_status
        return 0
    fi
    
    # Run setup steps
    add_env_variable
    update_supervisor_config
    clear_queue_backlog
    test_configuration
    show_final_status
    
    success "🎉 Invoice priority queue is now active!"
}

# Show usage
if [[ "$1" == "--help" || "$1" == "-h" ]]; then
    echo "Usage: $0 [app_path]"
    echo
    echo "Enables the built-in invoice parsing priority queue system."
    echo "Invoice jobs will be processed before coffee monitoring jobs."
    echo
    echo "Parameters:"
    echo "  app_path  - Path to Laravel application (default: /var/www/html/osmanager)"
    echo
    echo "Examples:"
    echo "  $0                                      # Production"
    echo "  $0 /var/www/html/osmanager-test        # Test environment"
    echo
    echo "This script:"
    echo "  1. Sets INVOICE_PARSING_QUEUE=invoices in .env"
    echo "  2. Updates queue workers to prioritize invoices queue"
    echo "  3. Clears any queue backlog (optional)"
    echo "  4. Tests the configuration"
    exit 0
fi

# Check sudo access
if ! sudo -n true 2>/dev/null; then
    error "This script requires sudo access for supervisor management"
    exit 1
fi

# Run main function
main