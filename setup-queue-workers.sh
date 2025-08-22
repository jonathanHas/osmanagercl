#!/bin/bash

# =============================================================================
# Queue Worker Setup Script for OS Manager
# Sets up supervisor-managed queue workers for any environment
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
NUM_WORKERS=${4:-2}

# Auto-detect environment name from path
if [[ "$APP_PATH" == *"test"* ]]; then
    ENV_NAME="test"
    SERVICE_NAME="osmanager-test-queue-worker"
elif [[ "$APP_PATH" == *"staging"* ]]; then
    ENV_NAME="staging"
    SERVICE_NAME="osmanager-staging-queue-worker"
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

# Check if running as root or with sudo access
check_sudo() {
    if [[ $EUID -eq 0 ]]; then
        info "Running as root"
        return 0
    elif sudo -n true 2>/dev/null; then
        info "Sudo access available"
        return 0
    else
        error "This script requires sudo access for supervisor configuration"
        echo "Please run with sudo or ensure sudo is configured"
        exit 1
    fi
}

# Verify application directory
verify_app_directory() {
    log "🔍 Verifying application directory..."
    
    if [[ ! -d "$APP_PATH" ]]; then
        error "Application directory not found: $APP_PATH"
        exit 1
    fi
    
    if [[ ! -f "$APP_PATH/artisan" ]]; then
        error "Laravel artisan command not found in: $APP_PATH"
        exit 1
    fi
    
    success "Application directory verified: $APP_PATH"
}

# Check if supervisor is installed
check_supervisor() {
    log "🔧 Checking supervisor installation..."
    
    if command -v supervisorctl &> /dev/null; then
        SUPERVISOR_VERSION=$(supervisorctl version 2>/dev/null || echo "unknown")
        success "Supervisor is installed (version: $SUPERVISOR_VERSION)"
    else
        error "Supervisor is not installed"
        echo
        echo "To install supervisor:"
        echo "  Ubuntu/Debian: sudo apt-get install supervisor"
        echo "  CentOS/RHEL:   sudo yum install supervisor"
        echo
        exit 1
    fi
}

# Generate supervisor configuration
generate_supervisor_config() {
    log "📝 Generating supervisor configuration..."
    
    SUPERVISOR_CONF="/etc/supervisor/conf.d/${SERVICE_NAME}.conf"
    LOG_FILE="$APP_PATH/storage/logs/queue-worker.log"
    
    # Create supervisor config
    sudo tee "$SUPERVISOR_CONF" > /dev/null << EOF
# =============================================================================
# OS Manager Queue Worker Configuration - $ENV_NAME Environment
# Generated: $(date)
# =============================================================================

[program:$SERVICE_NAME]
process_name=%(program_name)s_%(process_num)02d
command=php $APP_PATH/artisan queue:work --sleep=3 --tries=3 --max-time=3600 --timeout=300
directory=$APP_PATH
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=$WEB_USER
numprocs=$NUM_WORKERS
redirect_stderr=true
stdout_logfile=$LOG_FILE
stdout_logfile_maxbytes=50MB
stdout_logfile_backups=10
stopwaitsecs=10

# Environment variables
environment=LARAVEL_QUEUE_WORKER="true"

# =============================================================================
# Configuration Details:
# - Environment: $ENV_NAME
# - Workers: $NUM_WORKERS processes
# - User: $WEB_USER
# - Log: $LOG_FILE
# - Job timeout: 5 minutes
# - Worker restart: Every hour (prevents memory leaks)
# =============================================================================
EOF

    if [[ $? -eq 0 ]]; then
        success "Supervisor configuration created: $SUPERVISOR_CONF"
    else
        error "Failed to create supervisor configuration"
        exit 1
    fi
}

# Update supervisor and start workers
start_queue_workers() {
    log "🚀 Starting queue workers..."
    
    # Stop existing workers if running
    if sudo supervisorctl status "$SERVICE_NAME:*" >/dev/null 2>&1; then
        info "Stopping existing queue workers..."
        sudo supervisorctl stop "$SERVICE_NAME:*"
    fi
    
    # Update supervisor configuration
    log "Updating supervisor configuration..."
    if ! sudo supervisorctl reread; then
        error "Failed to reload supervisor configuration"
        exit 1
    fi
    
    if ! sudo supervisorctl update; then
        error "Failed to update supervisor services"
        exit 1
    fi
    
    # Start queue workers
    log "Starting queue workers..."
    if sudo supervisorctl start "$SERVICE_NAME:*"; then
        success "Queue workers started successfully"
    else
        error "Failed to start queue workers"
        exit 1
    fi
}

# Verify workers are running
verify_workers() {
    log "✅ Verifying queue workers..."
    
    sleep 2  # Give workers time to start
    
    # Check supervisor status
    WORKER_STATUS=$(sudo supervisorctl status "$SERVICE_NAME:*" 2>/dev/null)
    if [[ $? -eq 0 ]]; then
        echo "$WORKER_STATUS"
        
        # Count running workers
        RUNNING_COUNT=$(echo "$WORKER_STATUS" | grep -c "RUNNING" || echo "0")
        if [[ $RUNNING_COUNT -gt 0 ]]; then
            success "$RUNNING_COUNT queue workers are running"
        else
            error "No queue workers are running"
            echo
            echo "Check supervisor logs for details:"
            echo "  sudo tail -f /var/log/supervisor/supervisord.log"
            exit 1
        fi
    else
        error "Cannot check worker status"
        exit 1
    fi
}

# Test queue functionality
test_queue_functionality() {
    log "🧪 Testing queue functionality..."
    
    cd "$APP_PATH" || exit 1
    
    # Test queue:work command
    if php artisan queue:work --help >/dev/null 2>&1; then
        success "Queue worker command is available"
    else
        error "Queue worker command failed"
        return 1
    fi
    
    # Check queue connection
    QUEUE_CONNECTION=$(php artisan config:show queue.default 2>/dev/null | grep -o '"[^"]*"' | tr -d '"' || echo "unknown")
    if [[ "$QUEUE_CONNECTION" != "unknown" ]]; then
        success "Queue connection configured: $QUEUE_CONNECTION"
    else
        warning "Queue connection may not be properly configured"
    fi
    
    # Test Laravel environment
    ENV=$(php artisan env 2>/dev/null || echo "unknown")
    info "Laravel environment: $ENV"
}

# Show monitoring information
show_monitoring_info() {
    echo
    echo "=========================================="
    echo "   QUEUE WORKER SETUP COMPLETE"
    echo "=========================================="
    echo "Environment: $ENV_NAME"
    echo "Service Name: $SERVICE_NAME"
    echo "Application: $APP_PATH"
    echo "Workers: $NUM_WORKERS processes"
    echo "User: $WEB_USER"
    echo "=========================================="
    echo
    success "✅ Queue workers are now running and will automatically:"
    echo "   • Start when the server boots"
    echo "   • Restart if they crash"
    echo "   • Process invoice parsing jobs"
    echo "   • Handle all background tasks"
    echo
    info "📊 Monitoring Commands:"
    echo "   Check status:     sudo supervisorctl status"
    echo "   View worker logs: tail -f $APP_PATH/storage/logs/queue-worker.log"
    echo "   View app logs:    tail -f $APP_PATH/storage/logs/laravel.log"
    echo "   Restart workers:  sudo supervisorctl restart $SERVICE_NAME:*"
    echo "   Stop workers:     sudo supervisorctl stop $SERVICE_NAME:*"
    echo "   Start workers:    sudo supervisorctl start $SERVICE_NAME:*"
    echo
    info "🔧 Management Commands:"
    echo "   Laravel queue:    php artisan queue:work --once  # Process one job"
    echo "   Queue stats:      php artisan queue:monitor      # Monitor queues" 
    echo "   Clear failed:     php artisan queue:flush        # Clear failed jobs"
    echo "   Restart queue:    php artisan queue:restart      # Restart all workers"
    echo
    warning "📋 Next Steps:"
    echo "   1. Upload a test invoice to verify parsing works"
    echo "   2. Monitor logs to ensure jobs are processing"
    echo "   3. Set up log rotation if not already configured"
    echo
    echo "=========================================="
}

# Show usage information
usage() {
    echo "Usage: $0 [app_path] [web_user] [web_group] [num_workers]"
    echo
    echo "Parameters:"
    echo "  app_path     - Path to Laravel application (default: /var/www/html/osmanager)"
    echo "  web_user     - Web server user (default: www-data)"
    echo "  web_group    - Web server group (default: www-data)"
    echo "  num_workers  - Number of worker processes (default: 2)"
    echo
    echo "Examples:"
    echo "  $0                                           # Production with defaults"
    echo "  $0 /var/www/html/osmanager-test             # Test environment"
    echo "  $0 /var/www/html/osmanager www-data www-data 4  # Production with 4 workers"
    echo
    echo "Auto-detected Environment: $ENV_NAME"
    echo "Service Name: $SERVICE_NAME"
}

# Main function
main() {
    echo "🔄 OS Manager Queue Worker Setup"
    echo "================================"
    echo "Environment: $ENV_NAME"
    echo "Application: $APP_PATH"
    echo "Service: $SERVICE_NAME"
    echo "Workers: $NUM_WORKERS"
    echo "User: $WEB_USER"
    echo "================================"
    
    # Run setup steps
    check_sudo
    verify_app_directory
    check_supervisor
    generate_supervisor_config
    start_queue_workers
    verify_workers
    test_queue_functionality
    show_monitoring_info
    
    success "🎉 Queue worker setup completed successfully!"
}

# Handle script arguments
if [[ "$1" == "--help" || "$1" == "-h" ]]; then
    usage
    exit 0
fi

# Run main function
main