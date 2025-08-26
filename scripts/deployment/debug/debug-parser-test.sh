#!/bin/bash

# =============================================================================
# Debug Script for Invoice Parser Testing Issues
# Helps identify why parser tests fail in deployment scripts but work manually
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

test_with_command() {
    local description="$1"
    local command="$2"
    local suppress_output="$3"
    
    log "Testing: $description"
    info "Command: $command"
    
    if [[ "$suppress_output" == "suppress" ]]; then
        if eval "$command" >/dev/null 2>&1; then
            success "✅ PASSED (output suppressed)"
        else
            error "❌ FAILED (output suppressed)"
            log "Re-running with full output to see error:"
            eval "$command"
        fi
    else
        if eval "$command"; then
            success "✅ PASSED"
        else
            error "❌ FAILED"
        fi
    fi
    echo "----------------------------------------"
}

main() {
    echo "🐛 Parser Test Debug Script"
    echo "=========================="
    echo "Application: $APP_PATH"
    echo "Web User: $WEB_USER"
    echo "Current User: $(whoami)"
    echo "=========================="
    echo
    
    PARSER_DIR="$APP_PATH/scripts/invoice-parser"
    
    log "Parser Directory: $PARSER_DIR"
    
    # Check if parser directory exists
    if [[ ! -d "$PARSER_DIR" ]]; then
        error "Parser directory not found: $PARSER_DIR"
        exit 1
    fi
    
    cd "$PARSER_DIR" || exit 1
    
    # Test 1: Check virtual environment
    test_with_command "Virtual environment exists" "[[ -f 'venv/bin/python' ]]"
    
    # Test 2: Current user - basic activation
    test_with_command "Current user: Virtual env activation" "source venv/bin/activate && echo 'Virtual env activated'"
    
    # Test 3: Current user - Python version
    test_with_command "Current user: Python version" "source venv/bin/activate && python --version"
    
    # Test 4: Current user - pip list
    test_with_command "Current user: Installed packages" "source venv/bin/activate && pip list | head -10"
    
    # Test 5: Current user - individual module imports
    test_with_command "Current user: Import pytesseract" "source venv/bin/activate && python -c 'import pytesseract; print(\"pytesseract OK\")'"
    
    test_with_command "Current user: Import PyPDF2" "source venv/bin/activate && python -c 'import PyPDF2; print(\"PyPDF2 OK\")'"
    
    test_with_command "Current user: Import docx" "source venv/bin/activate && python -c 'import docx; print(\"docx OK\")'"
    
    # Test 6: Current user - all modules together
    test_with_command "Current user: All modules import" "source venv/bin/activate && python -c 'import pytesseract, PyPDF2, docx; print(\"All modules OK\")'"
    
    # Test 7: Current user - parser help command
    test_with_command "Current user: Parser help command" "source venv/bin/activate && python invoice_parser_laravel.py --help" suppress
    
    echo
    log "Now testing with sudo -u $WEB_USER..."
    echo
    
    # Test 8: Check sudo access
    if ! sudo -n true 2>/dev/null; then
        warning "Sudo requires password - some tests may fail"
    fi
    
    # Test 9: WWW-Data user - check basic access
    test_with_command "WWW-Data user: Basic command" "sudo -u $WEB_USER whoami"
    
    # Test 10: WWW-Data user - check directory access
    test_with_command "WWW-Data user: Directory access" "sudo -u $WEB_USER ls -la ."
    
    # Test 11: WWW-Data user - virtual env activation (verbose)
    log "WWW-Data user: Testing virtual environment activation (verbose)"
    info "Command: sudo -u $WEB_USER bash -c \"source venv/bin/activate && echo 'Virtual env activated'\""
    if sudo -u "$WEB_USER" bash -c "source venv/bin/activate && echo 'Virtual env activated'"; then
        success "✅ PASSED"
    else
        error "❌ FAILED"
    fi
    echo "----------------------------------------"
    
    # Test 12: WWW-Data user - Python version
    test_with_command "WWW-Data user: Python version" "sudo -u $WEB_USER bash -c \"source venv/bin/activate && python --version\""
    
    # Test 13: WWW-Data user - individual module imports with full output
    log "WWW-Data user: Testing individual module imports (full output)"
    
    info "Testing pytesseract import..."
    sudo -u "$WEB_USER" bash -c "source venv/bin/activate && python -c 'import pytesseract; print(\"pytesseract OK\")'"
    echo
    
    info "Testing PyPDF2 import..."
    sudo -u "$WEB_USER" bash -c "source venv/bin/activate && python -c 'import PyPDF2; print(\"PyPDF2 OK\")'"
    echo
    
    info "Testing docx import..."
    sudo -u "$WEB_USER" bash -c "source venv/bin/activate && python -c 'import docx; print(\"docx OK\")'"
    echo
    
    # Test 14: WWW-Data user - all modules (full output)
    log "WWW-Data user: Testing all modules import (full output)"
    info "Command: sudo -u $WEB_USER bash -c \"source venv/bin/activate && python -c 'import pytesseract, PyPDF2, docx; print(\"All modules OK\")'\" 2>&1"
    sudo -u "$WEB_USER" bash -c "source venv/bin/activate && python -c 'import pytesseract, PyPDF2, docx; print(\"All modules OK\")'" 2>&1
    IMPORT_EXIT_CODE=$?
    if [[ $IMPORT_EXIT_CODE -eq 0 ]]; then
        success "✅ All modules imported successfully"
    else
        error "❌ Module import failed with exit code: $IMPORT_EXIT_CODE"
    fi
    echo "----------------------------------------"
    
    # Test 15: Replicate exact test-deployment.sh command
    log "Replicating exact test-deployment.sh command"
    info "Command: sudo -u $WEB_USER bash -c \"source venv/bin/activate && python -c 'import pytesseract, PyPDF2, docx; print(\"OK\")'\""
    TEST_OUTPUT=$(sudo -u "$WEB_USER" bash -c "source venv/bin/activate && python -c 'import pytesseract, PyPDF2, docx; print(\"OK\")'" 2>/dev/null)
    info "Output: '$TEST_OUTPUT'"
    
    if echo "$TEST_OUTPUT" | grep -q "OK"; then
        success "✅ Exact test-deployment.sh command works"
    else
        error "❌ Exact test-deployment.sh command failed"
        log "Running without output suppression to see error:"
        sudo -u "$WEB_USER" bash -c "source venv/bin/activate && python -c 'import pytesseract, PyPDF2, docx; print(\"OK\")'"
    fi
    echo "----------------------------------------"
    
    # Test 16: Environment variables
    log "Checking environment variables"
    info "Current user PATH: $PATH"
    info "WWW-Data user PATH:"
    sudo -u "$WEB_USER" bash -c "echo \$PATH"
    
    # Test 17: File permissions
    log "Checking file permissions"
    info "Virtual environment permissions:"
    ls -la venv/bin/python venv/bin/activate 2>/dev/null || info "Some files not found"
    
    echo
    log "🔍 Debug analysis complete!"
    echo "If tests pass manually but fail in test-deployment.sh, check:"
    echo "1. Output parsing (grep -q \"OK\")"
    echo "2. Shell escaping differences"
    echo "3. Environment variable differences"
    echo "4. Working directory context"
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
    echo "  $0"
    echo "  $0 /var/www/html/osmanager-test"
    echo "  $0 /var/www/html/osmanagercl jon"
    exit 0
fi

# Run main function
main