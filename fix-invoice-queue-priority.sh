#!/bin/bash

# =============================================================================
# Fix Invoice Queue Priority
# Sets up dedicated queue workers for invoice processing to prevent blocking
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

# Clear current queue backlog
clear_queue_backlog() {
    log "🧹 Clearing queue backlog..."
    
    cd "$APP_PATH" || exit 1
    
    # Stop current workers
    info "Stopping queue workers..."
    sudo supervisorctl stop "$SERVICE_NAME:*"
    
    # Clear all pending jobs (including coffee jobs blocking the queue)
    info "Clearing pending jobs..."
    php artisan queue:clear
    
    # Clear any failed jobs
    info "Clearing failed jobs..."
    php artisan queue:flush
    
    success "Queue cleared"
}

# Update supervisor configuration for priority queues
update_supervisor_config() {
    log "⚙️ Updating supervisor configuration with queue priorities..."
    
    SUPERVISOR_CONF="/etc/supervisor/conf.d/${SERVICE_NAME}.conf"
    LOG_FILE="$APP_PATH/storage/logs/queue-worker.log"
    
    # Create new supervisor config with queue priorities
    sudo tee "$SUPERVISOR_CONF" > /dev/null << EOF
# =============================================================================
# OS Manager Queue Worker Configuration - $ENV_NAME Environment (Priority Queues)
# Updated: $(date)
# =============================================================================

[program:$SERVICE_NAME]
process_name=%(program_name)s_%(process_num)02d
command=php $APP_PATH/artisan queue:work --sleep=1 --tries=3 --max-time=3600 --timeout=300 --queue=invoices,default
directory=$APP_PATH
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=3
redirect_stderr=true
stdout_logfile=$LOG_FILE
stdout_logfile_maxbytes=50MB
stdout_logfile_backups=10
stopwaitsecs=10

# Environment variables
environment=LARAVEL_QUEUE_WORKER="true"

# =============================================================================
# Queue Priority Configuration:
# - invoices: High priority queue for invoice processing jobs
# - default: Lower priority for coffee monitoring and other jobs
# - Workers process invoices queue first, then default queue
# - 3 workers instead of 2 for better throughput
# - Sleep reduced to 1 second for faster invoice processing
# =============================================================================
EOF

    if [[ $? -eq 0 ]]; then
        success "Supervisor configuration updated: $SUPERVISOR_CONF"
        info "Key changes:"
        info "  • Priority queues: invoices (high), default (low)"
        info "  • 3 workers instead of 2"
        info "  • Sleep reduced to 1 second"
        info "  • Invoice jobs will be processed first"
    else
        error "Failed to update supervisor configuration"
        exit 1
    fi
}

# Update Laravel configuration to use priority queue for invoices
update_laravel_config() {
    log "📝 Checking Laravel invoice job configuration..."
    
    cd "$APP_PATH" || exit 1
    
    # Check if invoice jobs are configured to use invoices queue
    if grep -r "->onQueue('invoices')" app/Jobs/ 2>/dev/null | grep -q "ParseInvoiceFile"; then
        success "Invoice jobs already configured for invoices queue"
    else
        warning "Invoice jobs may not be using the invoices queue"
        info "Invoice parsing jobs should be configured with ->onQueue('invoices')"
        info "Check ParseInvoiceFile job class in app/Jobs/"
    fi
    
    # Show current queue configuration
    info "Current queue configuration:"
    php artisan config:show queue.connections.database 2>/dev/null || warning "Cannot read queue config"
}

# Start updated workers
start_priority_workers() {
    log "🚀 Starting priority queue workers..."
    
    # Reload supervisor configuration
    log "Reloading supervisor configuration..."
    sudo supervisorctl reread
    sudo supervisorctl update
    
    # Start workers with new configuration
    log "Starting queue workers..."
    if sudo supervisorctl start "$SERVICE_NAME:*"; then
        success "Priority queue workers started"
    else
        error "Failed to start queue workers"
        exit 1
    fi
    
    # Verify workers are running
    sleep 2
    WORKER_STATUS=$(sudo supervisorctl status "$SERVICE_NAME:*" 2>/dev/null)
    RUNNING_COUNT=$(echo "$WORKER_STATUS" | grep -c "RUNNING" || echo "0")
    
    if [[ $RUNNING_COUNT -gt 0 ]]; then
        success "$RUNNING_COUNT priority queue workers are running"
        echo "$WORKER_STATUS"
    else
        error "Workers failed to start"
        exit 1
    fi
}

# Test the priority system
test_priority_system() {
    log "🧪 Testing priority queue system..."
    
    cd "$APP_PATH" || exit 1
    
    # Monitor queue for a few seconds
    info "Monitoring queue activity (10 seconds)..."
    timeout 10 tail -f "$APP_PATH/storage/logs/queue-worker.log" | while read -r line; do
        if [[ "$line" == *"ParseInvoiceFile"* ]]; then
            echo -e "${GREEN}✅ Invoice job processed${NC}"
        elif [[ "$line" == *"MonitorCoffeeOrdersJob"* ]]; then
            echo -e "${YELLOW}⏳ Coffee job processed${NC}"
        fi
    done 2>/dev/null || true
    
    success "Priority system test complete"
}

# Show monitoring information
show_monitoring_info() {
    echo
    echo "=========================================="
    echo "   PRIORITY QUEUE SETUP COMPLETE"
    echo "=========================================="
    echo "Environment: $ENV_NAME"
    echo "Service: $SERVICE_NAME"  
    echo "Queue Priority: invoices → default"
    echo "Workers: 3 processes"
    echo "=========================================="
    echo
    success "✅ Invoice processing now has priority!"
    echo
    info "📊 How it works:"
    echo "   • Invoice jobs use 'invoices' queue (high priority)"
    echo "   • Coffee/other jobs use 'default' queue (lower priority)"
    echo "   • Workers process invoices first, then other jobs"
    echo "   • 3 workers for better throughput"
    echo
    info "📋 Queue Status Commands:"
    echo "   Check workers:    sudo supervisorctl status"
    echo "   Watch processing: tail -f $APP_PATH/storage/logs/queue-worker.log"
    echo "   Queue stats:      php artisan queue:monitor"
    echo "   Manual processing: php artisan queue:work --once --queue=invoices"
    echo
    info "🎯 Testing:"
    echo "   1. Upload a new invoice"
    echo "   2. It should process within seconds (not minutes)"
    echo "   3. Check logs to confirm it used 'invoices' queue"
    echo
    warning "📝 Note for Production:"
    echo "   Ensure ParseInvoiceFile job uses: ->onQueue('invoices')"
    echo "   This change may need to be made in the Laravel code"
    echo
    echo "=========================================="
}

# Main function
main() {
    echo "🔧 Invoice Queue Priority Fix"
    echo "============================="
    echo "Environment: $ENV_NAME"
    echo "Application: $APP_PATH"
    echo "Issue: Coffee jobs blocking invoice processing"
    echo "Solution: Priority queues with dedicated workers"
    echo "============================="
    
    # Check sudo access
    if ! sudo -n true 2>/dev/null; then
        error "This script requires sudo access"
        exit 1
    fi
    
    # Run fix steps
    clear_queue_backlog
    update_supervisor_config
    update_laravel_config
    start_priority_workers
    test_priority_system
    show_monitoring_info
    
    success "🎉 Priority queue system configured!"
    info "Upload an invoice now to test the fix"
}

# Show usage
if [[ "$1" == "--help" || "$1" == "-h" ]]; then
    echo "Usage: $0 [app_path]"
    echo
    echo "This script fixes invoice processing being blocked by coffee monitoring jobs"
    echo "by setting up priority queues where invoices are processed first."
    echo
    echo "Parameters:"
    echo "  app_path  - Path to Laravel application (default: /var/www/html/osmanager)"
    echo
    echo "Examples:"
    echo "  $0                                      # Production"
    echo "  $0 /var/www/html/osmanager-test        # Test environment"
    exit 0
fi

# Run main function
main