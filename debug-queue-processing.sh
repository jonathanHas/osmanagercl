#!/bin/bash

# =============================================================================
# Debug Script for Queue Processing Issues
# Diagnoses why invoice parsing jobs are stuck in pending status
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

# Auto-detect environment name from path
if [[ "$APP_PATH" == *"test"* ]]; then
    ENV_NAME="test"
    SERVICE_NAME="osmanager-test-queue-worker"
else
    ENV_NAME="production"
    SERVICE_NAME="osmanager-queue-worker"
fi

log() {
    echo -e "${BLUE}[DEBUG]${NC} $1"
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

# Check supervisor status
check_supervisor_status() {
    log "🔍 Checking supervisor queue workers..."
    
    if command -v supervisorctl &> /dev/null; then
        info "Supervisor status for $SERVICE_NAME:"
        sudo supervisorctl status "$SERVICE_NAME:*" 2>/dev/null || {
            error "No supervisor processes found for $SERVICE_NAME"
            info "Available supervisor processes:"
            sudo supervisorctl status | grep -i queue || info "No queue-related processes found"
        }
    else
        error "Supervisor not found"
    fi
    echo "----------------------------------------"
}

# Check Laravel queue status
check_laravel_queue() {
    log "⚙️ Checking Laravel queue system..."
    
    cd "$APP_PATH" || exit 1
    
    # Check queue configuration
    info "Queue configuration:"
    php artisan config:show queue.default 2>/dev/null || warning "Cannot read queue config"
    
    # Check if queue workers are responsive
    info "Testing queue worker command:"
    if php artisan queue:work --help >/dev/null 2>&1; then
        success "Queue worker command available"
    else
        error "Queue worker command failed"
    fi
    
    # Check queue connection
    info "Testing database connection:"
    if php artisan tinker --execute="DB::connection()->getPdo(); echo 'Connected';" 2>/dev/null | grep -q "Connected"; then
        success "Database connection working"
    else
        error "Database connection failed"
    fi
    echo "----------------------------------------"
}

# Check for jobs in queue
check_queue_jobs() {
    log "📋 Checking for jobs in queue..."
    
    cd "$APP_PATH" || exit 1
    
    # Check if jobs table exists and has pending jobs
    info "Checking jobs table:"
    PENDING_JOBS=$(php artisan tinker --execute="echo DB::table('jobs')->count();" 2>/dev/null || echo "error")
    if [[ "$PENDING_JOBS" == "error" ]]; then
        warning "Cannot check jobs table - may not exist"
        info "Run: php artisan migrate"
    elif [[ "$PENDING_JOBS" -gt 0 ]]; then
        warning "$PENDING_JOBS jobs pending in queue"
        
        # Show recent jobs
        info "Recent jobs (last 5):"
        php artisan tinker --execute="
        try {
            \$jobs = DB::table('jobs')->orderBy('id', 'desc')->limit(5)->get(['id', 'queue', 'payload', 'created_at']);
            foreach(\$jobs as \$job) {
                \$payload = json_decode(\$job->payload, true);
                \$command = \$payload['displayName'] ?? 'Unknown';
                echo \"ID: {\$job->id} | Queue: {\$job->queue} | Job: {\$command} | Created: {\$job->created_at}\n\";
            }
        } catch (Exception \$e) {
            echo 'Error: ' . \$e->getMessage();
        }
        " 2>/dev/null || warning "Cannot read jobs details"
    else
        info "No pending jobs in queue"
    fi
    
    # Check failed jobs
    FAILED_JOBS=$(php artisan tinker --execute="echo DB::table('failed_jobs')->count();" 2>/dev/null || echo "0")
    if [[ "$FAILED_JOBS" -gt 0 ]]; then
        warning "$FAILED_JOBS failed jobs found"
        info "Recent failed jobs:"
        php artisan tinker --execute="
        try {
            \$jobs = DB::table('failed_jobs')->orderBy('id', 'desc')->limit(3)->get(['id', 'connection', 'queue', 'payload', 'exception', 'failed_at']);
            foreach(\$jobs as \$job) {
                \$payload = json_decode(\$job->payload, true);
                \$command = \$payload['displayName'] ?? 'Unknown';
                echo \"ID: {\$job->id} | Job: {\$command} | Failed: {\$job->failed_at}\n\";
                echo \"Error: \" . substr(\$job->exception, 0, 200) . \"...\n\";
                echo \"---\n\";
            }
        } catch (Exception \$e) {
            echo 'Error: ' . \$e->getMessage();
        }
        " 2>/dev/null
    else
        success "No failed jobs"
    fi
    echo "----------------------------------------"
}

# Check specific invoice processing
check_invoice_processing() {
    log "📄 Checking invoice upload processing..."
    
    cd "$APP_PATH" || exit 1
    
    # Check invoice upload files table
    info "Recent invoice upload files:"
    php artisan tinker --execute="
    try {
        \$files = DB::table('invoice_upload_files')
                    ->orderBy('id', 'desc')
                    ->limit(5)
                    ->get(['id', 'batch_id', 'filename', 'status', 'created_at', 'error_message']);
        
        foreach(\$files as \$file) {
            echo \"ID: {\$file->id} | Batch: {\$file->batch_id} | File: {\$file->filename}\n\";
            echo \"Status: {\$file->status} | Created: {\$file->created_at}\n\";
            if (\$file->error_message) {
                echo \"Error: {\$file->error_message}\n\";
            }
            echo \"---\n\";
        }
    } catch (Exception \$e) {
        echo 'Error checking invoice files: ' . \$e->getMessage();
    }
    " 2>/dev/null || warning "Cannot check invoice upload files"
    echo "----------------------------------------"
}

# Test manual job processing
test_manual_processing() {
    log "🧪 Testing manual job processing..."
    
    cd "$APP_PATH" || exit 1
    
    info "Attempting to process one job manually:"
    timeout 10 php artisan queue:work --once --verbose 2>&1 || {
        warning "Manual job processing timed out or failed"
    }
    echo "----------------------------------------"
}

# Check file permissions for processing
check_file_permissions() {
    log "🔐 Checking file permissions..."
    
    cd "$APP_PATH" || exit 1
    
    # Check storage directories
    DIRS_TO_CHECK=(
        "storage/app/private/temp/invoices"
        "storage/app/private/invoices"
        "storage/logs"
        "scripts/invoice-parser"
    )
    
    for dir in "${DIRS_TO_CHECK[@]}"; do
        if [[ -d "$dir" ]]; then
            PERMISSIONS=$(ls -ld "$dir" | cut -d' ' -f1,3,4)
            info "$dir: $PERMISSIONS"
            
            # Test write access as web user
            if sudo -u "$WEB_USER" test -w "$dir" 2>/dev/null; then
                success "✅ $WEB_USER can write to $dir"
            else
                error "❌ $WEB_USER cannot write to $dir"
            fi
        else
            warning "Directory not found: $dir"
        fi
    done
    echo "----------------------------------------"
}

# Check parser functionality
check_parser_functionality() {
    log "🐍 Checking invoice parser functionality..."
    
    PARSER_DIR="$APP_PATH/scripts/invoice-parser"
    
    if [[ ! -d "$PARSER_DIR" ]]; then
        error "Parser directory not found: $PARSER_DIR"
        return 1
    fi
    
    cd "$PARSER_DIR" || return 1
    
    # Check virtual environment
    if [[ -f "venv/bin/python" ]]; then
        success "Virtual environment exists"
        
        # Test parser as web user
        info "Testing parser as $WEB_USER:"
        if sudo -u "$WEB_USER" bash -c "source venv/bin/activate && python invoice_parser_laravel.py --help" >/dev/null 2>&1; then
            success "✅ Parser works as $WEB_USER"
        else
            error "❌ Parser fails as $WEB_USER"
            info "Testing parser as current user:"
            if source venv/bin/activate && python invoice_parser_laravel.py --help >/dev/null 2>&1; then
                warning "Parser works as current user but fails as $WEB_USER (permission issue)"
            else
                error "Parser fails for all users"
            fi
        fi
    else
        error "Virtual environment not found"
    fi
    echo "----------------------------------------"
}

# Check log files
check_log_files() {
    log "📝 Checking log files for errors..."
    
    cd "$APP_PATH" || exit 1
    
    LOG_FILES=(
        "storage/logs/laravel.log"
        "storage/logs/queue-worker.log"
    )
    
    for log_file in "${LOG_FILES[@]}"; do
        if [[ -f "$log_file" ]]; then
            info "Checking $log_file (last 20 lines):"
            echo "$(tail -20 "$log_file")" | while read -r line; do
                if [[ "$line" == *"ERROR"* ]] || [[ "$line" == *"CRITICAL"* ]]; then
                    echo -e "${RED}$line${NC}"
                elif [[ "$line" == *"WARNING"* ]]; then
                    echo -e "${YELLOW}$line${NC}"
                else
                    echo "$line"
                fi
            done
            echo
        else
            warning "$log_file not found"
        fi
    done
    echo "----------------------------------------"
}

# Show process information
show_process_info() {
    log "🔄 Checking running processes..."
    
    info "PHP processes:"
    ps aux | grep -E "(php|artisan)" | grep -v grep || info "No PHP processes found"
    
    info "Queue-related processes:"
    ps aux | grep -i queue | grep -v grep || info "No queue processes found"
    echo "----------------------------------------"
}

# Provide recommendations
provide_recommendations() {
    log "💡 Diagnostic complete - Recommendations:"
    echo
    
    echo "🔧 IMMEDIATE ACTIONS TO TRY:"
    echo
    echo "1. **Restart queue workers:**"
    echo "   sudo supervisorctl restart $SERVICE_NAME:*"
    echo
    echo "2. **Process jobs manually to see errors:**"
    echo "   cd $APP_PATH"
    echo "   php artisan queue:work --once --verbose"
    echo
    echo "3. **Check for failed jobs and retry:**"
    echo "   php artisan queue:failed"
    echo "   php artisan queue:retry all"
    echo
    echo "4. **Clear and restart everything:**"
    echo "   php artisan queue:flush"
    echo "   php artisan queue:restart"
    echo "   sudo supervisorctl restart $SERVICE_NAME:*"
    echo
    echo "📊 MONITORING COMMANDS:"
    echo
    echo "   Watch queue processing: tail -f $APP_PATH/storage/logs/queue-worker.log"
    echo "   Watch application logs: tail -f $APP_PATH/storage/logs/laravel.log"
    echo "   Check supervisor status: sudo supervisorctl status"
    echo
    echo "🚨 IF STILL NOT WORKING:"
    echo
    echo "   1. Check Laravel .env configuration"
    echo "   2. Ensure database migrations are run: php artisan migrate"
    echo "   3. Test parser manually with a real file"
    echo "   4. Verify file upload permissions"
    echo
}

# Main function
main() {
    echo "🐛 Queue Processing Debug Script"
    echo "==============================="
    echo "Environment: $ENV_NAME"
    echo "Application: $APP_PATH"
    echo "Service: $SERVICE_NAME"
    echo "==============================="
    echo
    
    # Run all diagnostic steps
    check_supervisor_status
    check_laravel_queue
    check_queue_jobs
    check_invoice_processing
    test_manual_processing
    check_file_permissions
    check_parser_functionality
    check_log_files
    show_process_info
    provide_recommendations
    
    echo "==============================="
    echo "🔍 Debug analysis complete!"
    echo "==============================="
}

# Show usage
if [[ "$1" == "--help" || "$1" == "-h" ]]; then
    echo "Usage: $0 [app_path] [web_user]"
    echo
    echo "Parameters:"
    echo "  app_path  - Path to Laravel application (default: /var/www/html/osmanager)"
    echo "  web_user  - Web server user (default: www-data)"
    echo
    echo "Examples:"
    echo "  $0                                      # Production"
    echo "  $0 /var/www/html/osmanager-test        # Test environment"
    echo "  $0 /var/www/html/osmanagercl jon       # Development"
    exit 0
fi

# Run main function
main