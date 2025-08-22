#!/bin/bash

# =============================================================================
# Setup Dedicated Queue Workers
# Replaces priority queue system with dedicated workers for true independence
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
    OLD_SERVICE="osmanager-test-queue-worker"
    COFFEE_SERVICE="osmanager-test-coffee-worker"
    INVOICE_SERVICE="osmanager-test-invoice-worker"
    CONFIG_FILE="osmanager-test-dedicated-workers.conf"
else
    ENV_NAME="production"
    OLD_SERVICE="osmanager-queue-worker"
    COFFEE_SERVICE="osmanager-coffee-worker"
    INVOICE_SERVICE="osmanager-invoice-worker"
    CONFIG_FILE="osmanager-dedicated-workers.conf"
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

# Check sudo access
check_sudo() {
    if ! sudo -n true 2>/dev/null; then
        error "This script requires sudo access for supervisor management"
        exit 1
    fi
}

# Backup current configuration
backup_current_config() {
    log "📋 Backing up current configuration..."
    
    CURRENT_CONFIG="/etc/supervisor/conf.d/${OLD_SERVICE}.conf"
    
    if [[ -f "$CURRENT_CONFIG" ]]; then
        BACKUP_FILE="${CURRENT_CONFIG}.backup-$(date +%Y%m%d_%H%M%S)"
        sudo cp "$CURRENT_CONFIG" "$BACKUP_FILE"
        success "Current config backed up to: $BACKUP_FILE"
    else
        info "No existing configuration found"
    fi
}

# Install dedicated workers configuration
install_dedicated_config() {
    log "⚙️ Installing dedicated workers configuration..."
    
    if [[ ! -f "$CONFIG_FILE" ]]; then
        error "Configuration file not found: $CONFIG_FILE"
        error "Make sure you've copied the config file to this directory"
        exit 1
    fi
    
    # Copy configuration
    SUPERVISOR_CONF="/etc/supervisor/conf.d/${CONFIG_FILE}"
    sudo cp "$CONFIG_FILE" "$SUPERVISOR_CONF"
    
    if [[ $? -eq 0 ]]; then
        success "Dedicated workers configuration installed: $SUPERVISOR_CONF"
    else
        error "Failed to install configuration"
        exit 1
    fi
}

# Stop old workers
stop_old_workers() {
    log "🛑 Stopping old queue workers..."
    
    # Stop old unified workers if they exist
    if sudo supervisorctl status "$OLD_SERVICE:*" >/dev/null 2>&1; then
        sudo supervisorctl stop "$OLD_SERVICE:*"
        success "Stopped old unified workers"
    else
        info "No old workers found to stop"
    fi
}

# Update supervisor and start new workers
start_dedicated_workers() {
    log "🚀 Starting dedicated queue workers..."
    
    # Update supervisor configuration
    log "Updating supervisor configuration..."
    sudo supervisorctl reread
    sudo supervisorctl update
    
    # Start coffee workers
    log "Starting coffee workers..."
    sudo supervisorctl start "$COFFEE_SERVICE:*"
    
    # Start invoice workers
    log "Starting invoice workers..."
    sudo supervisorctl start "$INVOICE_SERVICE:*"
    
    success "Dedicated workers started"
}

# Verify workers are running
verify_workers() {
    log "✅ Verifying dedicated workers..."
    
    sleep 3  # Give workers time to start
    
    echo
    info "Supervisor status:"
    sudo supervisorctl status | grep -E "(coffee-worker|invoice-worker)"
    
    echo
    info "Running processes:"
    ps aux | grep "queue:work" | grep "$APP_PATH" | head -10
    
    # Count workers
    COFFEE_COUNT=$(sudo supervisorctl status "$COFFEE_SERVICE:*" 2>/dev/null | grep -c "RUNNING" || echo "0")
    INVOICE_COUNT=$(sudo supervisorctl status "$INVOICE_SERVICE:*" 2>/dev/null | grep -c "RUNNING" || echo "0")
    
    echo
    if [[ $COFFEE_COUNT -gt 0 && $INVOICE_COUNT -gt 0 ]]; then
        success "✅ $COFFEE_COUNT coffee workers running"
        success "✅ $INVOICE_COUNT invoice workers running"
        success "🎉 Dedicated workers setup complete!"
    else
        error "❌ Some workers failed to start"
        error "Coffee workers: $COFFEE_COUNT, Invoice workers: $INVOICE_COUNT"
        exit 1
    fi
}

# Remove old configuration
cleanup_old_config() {
    log "🧹 Cleaning up old configuration..."
    
    OLD_CONFIG="/etc/supervisor/conf.d/${OLD_SERVICE}.conf"
    
    if [[ -f "$OLD_CONFIG" ]]; then
        echo
        read -p "Remove old unified worker configuration? (y/N): " -n 1 -r
        echo
        
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            sudo rm "$OLD_CONFIG"
            sudo supervisorctl reread
            sudo supervisorctl update
            success "Old configuration removed"
        else
            info "Old configuration kept (will not interfere)"
        fi
    fi
}

# Show monitoring information
show_monitoring_info() {
    echo
    echo "=========================================="
    echo "   DEDICATED WORKERS SETUP COMPLETE"
    echo "=========================================="
    echo "Environment: $ENV_NAME"
    echo "Application: $APP_PATH"
    echo "=========================================="
    echo
    success "✅ True queue independence achieved!"
    echo
    info "📊 Worker Configuration:"
    echo "   • Coffee workers: Handle coffee orders (default queue)"
    echo "   • Invoice workers: Handle invoice processing (invoices queue)"
    echo "   • No blocking: 20 invoices won't delay coffee orders"
    echo "   • No interference: Both systems run independently"
    echo
    info "📋 Monitoring Commands:"
    echo "   Worker status:    sudo supervisorctl status"
    echo "   Coffee logs:      tail -f $APP_PATH/storage/logs/coffee-worker.log"
    echo "   Invoice logs:     tail -f $APP_PATH/storage/logs/invoice-worker.log"
    echo "   All processes:    ps aux | grep 'queue:work'"
    echo
    info "🔧 Management Commands:"
    echo "   Restart coffee:   sudo supervisorctl restart $COFFEE_SERVICE:*"
    echo "   Restart invoice:  sudo supervisorctl restart $INVOICE_SERVICE:*"
    echo "   Stop all:         sudo supervisorctl stop $COFFEE_SERVICE:* $INVOICE_SERVICE:*"
    echo
    warning "🧪 Test It:"
    echo "   1. Upload multiple invoices - should process quickly"
    echo "   2. Coffee orders should still work normally"
    echo "   3. Check both log files to see independent processing"
    echo
    echo "=========================================="
}

# Main function
main() {
    echo "🔄 Setup Dedicated Queue Workers"
    echo "================================"
    echo "Environment: $ENV_NAME"
    echo "Application: $APP_PATH"
    echo "Coffee Service: $COFFEE_SERVICE"
    echo "Invoice Service: $INVOICE_SERVICE"
    echo "================================"
    
    # Run setup steps
    check_sudo
    backup_current_config
    install_dedicated_config
    stop_old_workers
    start_dedicated_workers
    verify_workers
    cleanup_old_config
    show_monitoring_info
    
    success "🎉 Dedicated workers are now running!"
}

# Show usage
if [[ "$1" == "--help" || "$1" == "-h" ]]; then
    echo "Usage: $0 [app_path]"
    echo
    echo "Sets up dedicated queue workers for true independence between"
    echo "coffee order processing and invoice processing."
    echo
    echo "Parameters:"
    echo "  app_path  - Path to Laravel application"
    echo "            - /var/www/html/osmanager (production)"
    echo "            - /var/www/html/osmanager-test (test)"
    echo
    echo "Examples:"
    echo "  $0 /var/www/html/osmanager-test    # Test environment"
    echo "  $0 /var/www/html/osmanager         # Production"
    exit 0
fi

# Run main function
main