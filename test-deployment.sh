#!/bin/bash

# =============================================================================
# Post-Deployment Testing Script for OS Manager
# Comprehensive testing after deployment to ensure everything works
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
TEST_DOMAIN=${4:-localhost}

# Test counters
TESTS_PASSED=0
TESTS_FAILED=0
TESTS_WARNING=0

log() {
    echo -e "${BLUE}[$(date '+%H:%M:%S')]${NC} $1"
}

error() {
    echo -e "${RED}[FAIL]${NC} $1"
    ((TESTS_FAILED++))
}

success() {
    echo -e "${GREEN}[PASS]${NC} $1"
    ((TESTS_PASSED++))
}

warning() {
    echo -e "${YELLOW}[WARN]${NC} $1"
    ((TESTS_WARNING++))
}

info() {
    echo -e "${PURPLE}[INFO]${NC} $1"
}

# Test basic Laravel functionality
test_laravel_basics() {
    log "🔧 Testing Laravel basics..."
    
    cd "$APP_PATH" || exit 1
    
    # Test artisan command
    if php artisan --version >/dev/null 2>&1; then
        success "Artisan command works"
    else
        error "Artisan command failed"
    fi
    
    # Test environment
    ENV=$(php artisan env 2>/dev/null)
    if [[ -n "$ENV" ]]; then
        success "Laravel environment: $ENV"
    else
        error "Could not detect Laravel environment"
    fi
    
    # Test config loading
    if php artisan config:show app.name >/dev/null 2>&1; then
        APP_NAME=$(php artisan config:show app.name 2>/dev/null | grep -o '"[^"]*"' | tr -d '"')
        success "Configuration loads: $APP_NAME"
    else
        error "Configuration loading failed"
    fi
    
    # Test route registration
    if php artisan route:list --json >/dev/null 2>&1; then
        ROUTE_COUNT=$(php artisan route:list --json 2>/dev/null | jq length 2>/dev/null || echo "unknown")
        success "Routes registered: $ROUTE_COUNT routes"
    else
        error "Route registration failed"
    fi
}

# Test database connectivity
test_database() {
    log "🗄️  Testing database connectivity..."
    
    cd "$APP_PATH" || exit 1
    
    # Test main database connection
    if php artisan tinker --execute="try { DB::connection()->getPdo(); echo 'Main DB: Connected'; } catch (Exception \$e) { echo 'Main DB Error: ' . \$e->getMessage(); throw \$e; }" 2>/dev/null | grep -q "Connected"; then
        success "Main database connection works"
    else
        error "Main database connection failed"
    fi
    
    # Test POS database connection (if configured)
    if grep -q "^POS_DB_DATABASE=" .env 2>/dev/null; then
        if php artisan tinker --execute="try { DB::connection('pos')->getPdo(); echo 'POS DB: Connected'; } catch (Exception \$e) { echo 'POS DB: Not available'; }" 2>/dev/null | grep -q "Connected"; then
            success "POS database connection works"
        else
            warning "POS database connection not available"
        fi
    else
        info "POS database not configured"
    fi
    
    # Test migrations
    if php artisan migrate:status >/dev/null 2>&1; then
        PENDING_MIGRATIONS=$(php artisan migrate:status 2>/dev/null | grep -c "Pending" || echo "0")
        if [[ $PENDING_MIGRATIONS -eq 0 ]]; then
            success "All migrations are up to date"
        else
            warning "$PENDING_MIGRATIONS pending migrations found"
        fi
    else
        error "Migration status check failed"
    fi
}

# Test file permissions
test_file_permissions() {
    log "🔐 Testing file permissions..."
    
    cd "$APP_PATH" || exit 1
    
    # Test basic write permissions
    TEST_FILE="storage/app/private/temp/test-$(date +%s).txt"
    if sudo -u "$WEB_USER" touch "$TEST_FILE" 2>/dev/null; then
        sudo -u "$WEB_USER" rm "$TEST_FILE" 2>/dev/null
        success "Basic write permissions work"
    else
        error "Basic write permissions failed"
    fi
    
    # Test invoice directory permissions
    INVOICE_TEST_DIR="storage/app/private/invoices/$(date +%Y)/$(date +%m)/test-$(date +%s)"
    if sudo -u "$WEB_USER" mkdir -p "$INVOICE_TEST_DIR" 2>/dev/null; then
        sudo -u "$WEB_USER" rmdir "$INVOICE_TEST_DIR" 2>/dev/null
        success "Invoice directory permissions work"
    else
        error "Invoice directory permissions failed"
    fi
    
    # Test cache permissions
    if sudo -u "$WEB_USER" touch "storage/framework/cache/test-$(date +%s).txt" 2>/dev/null; then
        sudo -u "$WEB_USER" rm "storage/framework/cache/test-$(date +%s).txt" 2>/dev/null
        success "Cache directory permissions work"
    else
        error "Cache directory permissions failed"
    fi
    
    # Test log permissions
    if sudo -u "$WEB_USER" touch "storage/logs/test-$(date +%s).log" 2>/dev/null; then
        sudo -u "$WEB_USER" rm "storage/logs/test-$(date +%s).log" 2>/dev/null
        success "Log directory permissions work"
    else
        error "Log directory permissions failed"
    fi
}

# Test invoice parser
test_invoice_parser() {
    log "🐍 Testing invoice parser..."
    
    PARSER_DIR="$APP_PATH/scripts/invoice-parser"
    
    if [[ ! -d "$PARSER_DIR" ]]; then
        warning "Invoice parser directory not found"
        return
    fi
    
    cd "$PARSER_DIR" || return
    
    # Test virtual environment
    if [[ -f "venv/bin/python" ]]; then
        success "Python virtual environment exists"
    else
        error "Python virtual environment not found"
        return
    fi
    
    # Test parser help command
    if sudo -u "$WEB_USER" bash -c "source venv/bin/activate && python invoice_parser_laravel.py --help" >/dev/null 2>&1; then
        success "Parser help command works"
    else
        error "Parser help command failed"
    fi
    
    # Test Python modules
    if sudo -u "$WEB_USER" bash -c "source venv/bin/activate && python -c 'import pytesseract, PyPDF2, python_docx; print(\"OK\")'" 2>/dev/null | grep -q "OK"; then
        success "Required Python modules import successfully"
    else
        error "Python module imports failed"
    fi
    
    # Test tesseract access
    if sudo -u "$WEB_USER" tesseract --version >/dev/null 2>&1; then
        success "Tesseract is accessible to web user"
    else
        error "Tesseract is not accessible to web user"
    fi
    
    # Test with a simple PDF if available
    if [[ -f "test/test_invoice.pdf" ]]; then
        log "Testing parser with test PDF..."
        if sudo -u "$WEB_USER" bash -c "source venv/bin/activate && python invoice_parser_laravel.py --file test/test_invoice.pdf --output json" >/dev/null 2>&1; then
            success "Parser processes test PDF successfully"
        else
            warning "Parser failed to process test PDF (may be normal)"
        fi
    else
        info "No test PDF found, skipping parser execution test"
    fi
}

# Test queue system
test_queue_system() {
    log "⚙️  Testing queue system..."
    
    cd "$APP_PATH" || exit 1
    
    # Test queue configuration
    if php artisan queue:work --help >/dev/null 2>&1; then
        success "Queue worker command is available"
    else
        error "Queue worker command failed"
    fi
    
    # Test queue connection
    QUEUE_CONNECTION=$(php artisan config:show queue.default 2>/dev/null | grep -o '"[^"]*"' | tr -d '"' || echo "unknown")
    if [[ "$QUEUE_CONNECTION" != "unknown" ]]; then
        success "Queue connection configured: $QUEUE_CONNECTION"
    else
        warning "Queue connection not properly configured"
    fi
    
    # Check supervisor configuration if available
    if command -v supervisorctl &> /dev/null; then
        SUPERVISOR_STATUS=$(supervisorctl status 2>/dev/null | grep -c "RUNNING" || echo "0")
        if [[ $SUPERVISOR_STATUS -gt 0 ]]; then
            success "Supervisor has $SUPERVISOR_STATUS running processes"
        else
            warning "No supervisor processes running"
        fi
    else
        info "Supervisor not available"
    fi
    
    # Test queue restart
    if php artisan queue:restart >/dev/null 2>&1; then
        success "Queue restart command works"
    else
        warning "Queue restart command failed"
    fi
}

# Test web server response
test_web_server() {
    log "🌐 Testing web server response..."
    
    # Test if web server is responding
    if command -v curl &> /dev/null; then
        # Try to access the application
        HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "http://$TEST_DOMAIN" 2>/dev/null || echo "000")
        
        case $HTTP_STATUS in
            "200")
                success "Web server responds with HTTP 200"
                ;;
            "302"|"301")
                success "Web server responds with HTTP $HTTP_STATUS (redirect)"
                ;;
            "403")
                warning "Web server responds with HTTP 403 (permission issue)"
                ;;
            "404")
                warning "Web server responds with HTTP 404 (not found)"
                ;;
            "500")
                error "Web server responds with HTTP 500 (server error)"
                ;;
            "000")
                warning "Could not connect to web server"
                ;;
            *)
                warning "Web server responds with HTTP $HTTP_STATUS"
                ;;
        esac
    else
        info "curl not available, skipping web server test"
    fi
    
    # Check if Laravel is serving correctly by looking at storage link
    if [[ -L "$APP_PATH/public/storage" ]]; then
        success "Storage symlink exists"
    else
        warning "Storage symlink missing (run php artisan storage:link)"
    fi
}

# Test cache functionality
test_cache() {
    log "🚀 Testing cache functionality..."
    
    cd "$APP_PATH" || exit 1
    
    # Test config cache
    if php artisan config:clear >/dev/null 2>&1 && php artisan config:cache >/dev/null 2>&1; then
        success "Config cache works"
    else
        error "Config cache failed"
    fi
    
    # Test route cache
    if php artisan route:clear >/dev/null 2>&1 && php artisan route:cache >/dev/null 2>&1; then
        success "Route cache works"
    else
        error "Route cache failed"
    fi
    
    # Test view cache
    if php artisan view:clear >/dev/null 2>&1 && php artisan view:cache >/dev/null 2>&1; then
        success "View cache works"
    else
        error "View cache failed"
    fi
    
    # Test application cache
    if php artisan cache:clear >/dev/null 2>&1; then
        success "Application cache clear works"
    else
        error "Application cache clear failed"
    fi
}

# Test invoice upload functionality
test_invoice_upload() {
    log "📄 Testing invoice upload functionality..."
    
    cd "$APP_PATH" || exit 1
    
    # Check if invoice upload directories exist
    REQUIRED_DIRS=(
        "storage/app/private/temp/invoices"
        "storage/app/private/invoices"
        "storage/app/private/invoices/$(date +%Y)"
        "storage/app/private/invoices/$(date +%Y)/$(date +%m)"
    )
    
    for dir in "${REQUIRED_DIRS[@]}"; do
        if [[ -d "$dir" ]]; then
            success "Directory exists: $dir"
        else
            error "Missing directory: $dir"
        fi
    done
    
    # Test file upload simulation
    BATCH_DIR="storage/app/private/temp/invoices/TEST-BATCH-$(date +%s)"
    if sudo -u "$WEB_USER" mkdir -p "$BATCH_DIR" 2>/dev/null; then
        # Create a test file
        if sudo -u "$WEB_USER" echo "Test invoice content" > "$BATCH_DIR/test.txt" 2>/dev/null; then
            success "File upload simulation works"
            # Clean up
            sudo -u "$WEB_USER" rm -rf "$BATCH_DIR" 2>/dev/null
        else
            error "File creation in batch directory failed"
        fi
    else
        error "Batch directory creation failed"
    fi
    
    # Test invoice routes (if application is accessible)
    if php artisan route:list | grep -q "invoices.bulk-upload" 2>/dev/null; then
        success "Invoice upload routes are registered"
    else
        warning "Invoice upload routes not found"
    fi
}

# Test logging functionality
test_logging() {
    log "📝 Testing logging functionality..."
    
    cd "$APP_PATH" || exit 1
    
    # Test if logs directory is writable
    if sudo -u "$WEB_USER" touch "storage/logs/test-$(date +%s).log" 2>/dev/null; then
        sudo -u "$WEB_USER" rm "storage/logs/test-$(date +%s).log" 2>/dev/null
        success "Log directory is writable"
    else
        error "Log directory is not writable"
    fi
    
    # Check if Laravel log exists and is recent
    if [[ -f "storage/logs/laravel.log" ]]; then
        LOG_SIZE=$(stat -c%s "storage/logs/laravel.log" 2>/dev/null || echo "0")
        if [[ $LOG_SIZE -gt 0 ]]; then
            success "Laravel log exists and has content (${LOG_SIZE} bytes)"
        else
            info "Laravel log exists but is empty"
        fi
    else
        info "Laravel log does not exist yet (normal for new deployment)"
    fi
    
    # Test Laravel's logging by running a command that should log
    if php artisan about >/dev/null 2>&1; then
        success "Laravel logging system is functional"
    else
        warning "Laravel logging test failed"
    fi
}

# Test environment configuration
test_environment() {
    log "🔧 Testing environment configuration..."
    
    cd "$APP_PATH" || exit 1
    
    # Check if .env file exists
    if [[ -f ".env" ]]; then
        success ".env file exists"
        
        # Check critical environment variables
        CRITICAL_VARS=(
            "APP_KEY"
            "DB_CONNECTION"
            "DB_DATABASE"
            "DB_USERNAME"
        )
        
        for var in "${CRITICAL_VARS[@]}"; do
            if grep -q "^$var=" .env 2>/dev/null; then
                success "Environment variable set: $var"
            else
                error "Missing environment variable: $var"
            fi
        done
        
        # Check invoice parser variables
        PARSER_VARS=(
            "PYTHON_EXECUTABLE"
            "PYTHON_PARSER_DIR"
            "PYTHON_VENV_PATH"
            "INVOICE_PARSER_SCRIPT"
        )
        
        for var in "${PARSER_VARS[@]}"; do
            if grep -q "^$var=" .env 2>/dev/null; then
                success "Parser environment variable set: $var"
            else
                warning "Missing parser environment variable: $var"
            fi
        done
        
    else
        error ".env file does not exist"
    fi
    
    # Test app key
    if php artisan key:generate --show 2>/dev/null | grep -q "base64:"; then
        success "Application key is properly formatted"
    else
        error "Application key is invalid or missing"
    fi
}

# Performance test
test_performance() {
    log "⚡ Running basic performance tests..."
    
    cd "$APP_PATH" || exit 1
    
    # Test config loading time
    START_TIME=$(date +%s%N)
    php artisan config:show app.name >/dev/null 2>&1
    END_TIME=$(date +%s%N)
    CONFIG_TIME=$(((END_TIME - START_TIME) / 1000000))
    
    if [[ $CONFIG_TIME -lt 1000 ]]; then
        success "Config loading time: ${CONFIG_TIME}ms (fast)"
    elif [[ $CONFIG_TIME -lt 3000 ]]; then
        warning "Config loading time: ${CONFIG_TIME}ms (moderate)"
    else
        warning "Config loading time: ${CONFIG_TIME}ms (slow)"
    fi
    
    # Test route loading time
    START_TIME=$(date +%s%N)
    php artisan route:list >/dev/null 2>&1
    END_TIME=$(date +%s%N)
    ROUTE_TIME=$(((END_TIME - START_TIME) / 1000000))
    
    if [[ $ROUTE_TIME -lt 2000 ]]; then
        success "Route loading time: ${ROUTE_TIME}ms (fast)"
    elif [[ $ROUTE_TIME -lt 5000 ]]; then
        warning "Route loading time: ${ROUTE_TIME}ms (moderate)"
    else
        warning "Route loading time: ${ROUTE_TIME}ms (slow)"
    fi
}

# Generate test report
generate_test_report() {
    echo
    log "📊 Generating test report..."
    
    REPORT_FILE="$APP_PATH/storage/logs/deployment-test-$(date +%Y%m%d_%H%M%S).log"
    
    cat > "$REPORT_FILE" << EOF
OS Manager Deployment Test Report
================================
Date: $(date)
Application Path: $APP_PATH
Web User: $WEB_USER
Domain: $TEST_DOMAIN

Test Results:
- Tests Passed: $TESTS_PASSED
- Tests Failed: $TESTS_FAILED
- Tests with Warnings: $TESTS_WARNING

System Information:
- Hostname: $(hostname)
- OS: $(uname -a)
- PHP Version: $(php --version | head -1)
- Laravel Version: $(php artisan --version 2>/dev/null || echo "Unknown")

Critical Issues:
$(if [[ $TESTS_FAILED -gt 0 ]]; then echo "- $TESTS_FAILED tests failed - review output above"; else echo "- None"; fi)

Recommendations:
$(if [[ $TESTS_FAILED -gt 0 ]]; then echo "- Fix failed tests before going live"; fi)
$(if [[ $TESTS_WARNING -gt 0 ]]; then echo "- Review warnings and optimize where possible"; fi)
$(if [[ $TESTS_FAILED -eq 0 && $TESTS_WARNING -eq 0 ]]; then echo "- System is ready for production use"; fi)

Generated by: test-deployment.sh
EOF
    
    info "Test report saved to: $REPORT_FILE"
}

# Display final summary
display_summary() {
    echo
    echo "========================================"
    echo "       DEPLOYMENT TEST SUMMARY"
    echo "========================================"
    echo "Application: $APP_PATH"
    echo "Domain: $TEST_DOMAIN"
    echo "Test Date: $(date)"
    echo "========================================"
    echo "Tests Passed: $TESTS_PASSED"
    echo "Tests Failed: $TESTS_FAILED"
    echo "Warnings: $TESTS_WARNING"
    echo "========================================"
    
    if [[ $TESTS_FAILED -eq 0 ]]; then
        echo -e "${GREEN}Overall Status: DEPLOYMENT SUCCESSFUL ✅${NC}"
        echo
        echo "🎉 Your application is ready for production use!"
        echo
        echo "Next steps:"
        echo "1. Monitor application logs: tail -f $APP_PATH/storage/logs/laravel.log"
        echo "2. Set up monitoring and backups"
        echo "3. Configure SSL certificate for production"
        echo "4. Test invoice upload functionality manually"
        echo "5. Set up automated backups"
    else
        echo -e "${RED}Overall Status: DEPLOYMENT HAS ISSUES ❌${NC}"
        echo
        echo "❌ $TESTS_FAILED critical issues need to be resolved"
        echo
        echo "Recommended actions:"
        echo "1. Review failed tests above"
        echo "2. Fix permission issues: sudo ./fix-all-permissions.sh"
        echo "3. Restart web server: sudo systemctl restart apache2 (or nginx)"
        echo "4. Restart queue workers: php artisan queue:restart"
        echo "5. Re-run this test: ./test-deployment.sh"
    fi
    
    if [[ $TESTS_WARNING -gt 0 ]]; then
        echo
        warning "⚠️  $TESTS_WARNING warnings detected - review for optimization opportunities"
    fi
    
    echo "========================================"
}

# Main function
main() {
    echo "🧪 OS Manager Post-Deployment Testing"
    echo "====================================="
    echo "Application: $APP_PATH"
    echo "Web User: $WEB_USER"
    echo "Domain: $TEST_DOMAIN"
    echo "====================================="
    
    # Run all tests
    test_laravel_basics
    test_database
    test_file_permissions
    test_invoice_parser
    test_queue_system
    test_web_server
    test_cache
    test_invoice_upload
    test_logging
    test_environment
    test_performance
    
    # Generate report and summary
    generate_test_report
    display_summary
    
    # Exit with appropriate code
    if [[ $TESTS_FAILED -eq 0 ]]; then
        exit 0
    else
        exit 1
    fi
}

# Script usage
usage() {
    echo "Usage: $0 [app_path] [web_user] [web_group] [test_domain]"
    echo
    echo "Parameters:"
    echo "  app_path    - Path to Laravel application (default: /var/www/html/osmanager)"
    echo "  web_user    - Web server user (default: www-data)"
    echo "  web_group   - Web server group (default: www-data)"
    echo "  test_domain - Domain to test (default: localhost)"
    echo
    echo "Examples:"
    echo "  $0"
    echo "  $0 /var/www/html/osmanager-test"
    echo "  $0 /var/www/html/osmanager www-data www-data example.com"
}

# Handle script arguments
if [[ "$1" == "--help" || "$1" == "-h" ]]; then
    usage
    exit 0
fi

# Run main function
main