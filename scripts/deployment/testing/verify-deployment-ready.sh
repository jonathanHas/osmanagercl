#!/bin/bash

# =============================================================================
# Pre-Deployment Verification Script for OS Manager
# Checks all requirements before deployment to prevent issues
# =============================================================================

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
NC='\033[0m' # No Color

# Configuration
TARGET_DIR=${1:-/var/www/html/osmanager}
WEB_USER=${2:-www-data}
WEB_GROUP=${3:-www-data}

# Counters
CHECKS_PASSED=0
CHECKS_FAILED=0
CHECKS_WARNING=0

log() {
    echo -e "${BLUE}[$(date '+%H:%M:%S')]${NC} $1"
}

error() {
    echo -e "${RED}[FAIL]${NC} $1"
    ((CHECKS_FAILED++))
}

success() {
    echo -e "${GREEN}[PASS]${NC} $1"
    ((CHECKS_PASSED++))
}

warning() {
    echo -e "${YELLOW}[WARN]${NC} $1"
    ((CHECKS_WARNING++))
}

info() {
    echo -e "${PURPLE}[INFO]${NC} $1"
}

# Check system information
check_system_info() {
    log "🖥️  Checking system information..."
    
    info "System: $(uname -a)"
    info "Hostname: $(hostname)"
    info "Current user: $(whoami)"
    info "Date: $(date)"
    
    # Check available disk space
    DISK_USAGE=$(df -h "$TARGET_DIR" 2>/dev/null | tail -1 | awk '{print $5}' | sed 's/%//')
    if [[ -n "$DISK_USAGE" ]]; then
        if [[ $DISK_USAGE -lt 90 ]]; then
            success "Disk space: ${DISK_USAGE}% used (sufficient)"
        else
            warning "Disk space: ${DISK_USAGE}% used (getting full)"
        fi
    else
        warning "Could not check disk space for $TARGET_DIR"
    fi
    
    # Check memory
    MEM_AVAILABLE=$(free -m | awk 'NR==2{printf "%.1f", $7/1024}')
    if [[ $(echo "$MEM_AVAILABLE > 0.5" | bc -l 2>/dev/null || echo "1") -eq 1 ]]; then
        success "Memory: ${MEM_AVAILABLE}GB available"
    else
        warning "Memory: ${MEM_AVAILABLE}GB available (low)"
    fi
}

# Check PHP version and extensions
check_php() {
    log "🐘 Checking PHP requirements..."
    
    # Check PHP version
    if command -v php &> /dev/null; then
        PHP_VERSION=$(php -v | head -n1 | cut -d' ' -f2 | cut -d'.' -f1,2)
        info "PHP version: $PHP_VERSION"
        
        if php -v | head -n1 | grep -q "8.[2-9]"; then
            success "PHP version is compatible (8.2+)"
        else
            error "PHP version $PHP_VERSION is too old (need 8.2+)"
        fi
    else
        error "PHP is not installed"
    fi
    
    # Check required PHP extensions
    REQUIRED_EXTENSIONS=(
        "bcmath"
        "ctype"
        "fileinfo"
        "json"
        "mbstring"
        "openssl"
        "pdo"
        "tokenizer"
        "xml"
        "curl"
        "gd"
        "zip"
        "mysqli"
        "pdo_mysql"
    )
    
    log "Checking PHP extensions..."
    for ext in "${REQUIRED_EXTENSIONS[@]}"; do
        if php -m | grep -q "^$ext$"; then
            success "PHP extension: $ext"
        else
            error "Missing PHP extension: $ext"
        fi
    done
    
    # Check PHP configuration
    log "Checking PHP configuration..."
    
    # Check memory limit
    MEMORY_LIMIT=$(php -r "echo ini_get('memory_limit');")
    if [[ "$MEMORY_LIMIT" == "-1" ]] || [[ $(echo "$MEMORY_LIMIT" | sed 's/[GM]//g') -ge 512 ]]; then
        success "PHP memory limit: $MEMORY_LIMIT"
    else
        warning "PHP memory limit is low: $MEMORY_LIMIT"
    fi
    
    # Check max execution time
    MAX_EXEC_TIME=$(php -r "echo ini_get('max_execution_time');")
    if [[ $MAX_EXEC_TIME -eq 0 ]] || [[ $MAX_EXEC_TIME -ge 300 ]]; then
        success "PHP max execution time: $MAX_EXEC_TIME"
    else
        warning "PHP max execution time is low: $MAX_EXEC_TIME"
    fi
    
    # Check file upload settings
    UPLOAD_MAX_FILESIZE=$(php -r "echo ini_get('upload_max_filesize');")
    POST_MAX_SIZE=$(php -r "echo ini_get('post_max_size');")
    success "PHP upload max filesize: $UPLOAD_MAX_FILESIZE"
    success "PHP post max size: $POST_MAX_SIZE"
}

# Check web server
check_web_server() {
    log "🌐 Checking web server..."
    
    # Check if web server is running
    if systemctl is-active --quiet apache2; then
        success "Apache2 is running"
        WEB_SERVER="apache2"
    elif systemctl is-active --quiet nginx; then
        success "Nginx is running"
        WEB_SERVER="nginx"
    else
        warning "No web server (Apache2/Nginx) appears to be running"
        WEB_SERVER="unknown"
    fi
    
    # Check web server user
    if id "$WEB_USER" &>/dev/null; then
        success "Web server user exists: $WEB_USER"
    else
        error "Web server user does not exist: $WEB_USER"
    fi
    
    if getent group "$WEB_GROUP" &>/dev/null; then
        success "Web server group exists: $WEB_GROUP"
    else
        error "Web server group does not exist: $WEB_GROUP"
    fi
}

# Check MySQL database
check_mysql() {
    log "🗄️  Checking MySQL database..."
    
    # Check if MySQL is running
    if systemctl is-active --quiet mysql; then
        success "MySQL service is running"
    elif systemctl is-active --quiet mariadb; then
        success "MariaDB service is running"
    else
        error "MySQL/MariaDB service is not running"
        return
    fi
    
    # Check MySQL version
    if command -v mysql &> /dev/null; then
        MYSQL_VERSION=$(mysql --version 2>/dev/null | head -1)
        info "MySQL version: $MYSQL_VERSION"
        success "MySQL client is available"
    else
        error "MySQL client is not installed"
    fi
    
    # Check if we can connect (if .env exists in target)
    if [[ -f "$TARGET_DIR/.env" ]]; then
        log "Testing database connection from existing .env..."
        cd "$TARGET_DIR" 2>/dev/null
        if [[ $? -eq 0 ]] && php artisan tinker --execute="DB::connection()->getPdo(); echo 'Database connection successful';" 2>/dev/null | grep -q "successful"; then
            success "Database connection test passed"
        else
            warning "Database connection test failed (may be normal if app not deployed yet)"
        fi
    else
        info "No .env file found, skipping database connection test"
    fi
}

# Check Python and dependencies
check_python() {
    log "🐍 Checking Python requirements..."
    
    # Check Python version
    if command -v python3 &> /dev/null; then
        PYTHON_VERSION=$(python3 --version 2>&1)
        info "Python version: $PYTHON_VERSION"
        
        if python3 -c "import sys; exit(0 if sys.version_info >= (3, 8) else 1)" 2>/dev/null; then
            success "Python version is compatible (3.8+)"
        else
            error "Python version is too old (need 3.8+)"
        fi
    else
        error "Python 3 is not installed"
    fi
    
    # Check pip
    if command -v pip3 &> /dev/null; then
        success "pip3 is available"
    else
        error "pip3 is not installed"
    fi
    
    # Check virtual environment support
    if python3 -c "import venv" 2>/dev/null; then
        success "Python venv module is available"
    else
        error "Python venv module is not available"
    fi
}

# Check invoice parser dependencies
check_parser_dependencies() {
    log "📄 Checking invoice parser dependencies..."
    
    # Check tesseract-ocr
    if command -v tesseract &> /dev/null; then
        TESSERACT_VERSION=$(tesseract --version 2>&1 | head -1)
        success "tesseract-ocr: $TESSERACT_VERSION"
    else
        error "tesseract-ocr is not installed"
        info "  Install with: sudo apt-get install tesseract-ocr"
    fi
    
    # Check poppler-utils
    if command -v pdftotext &> /dev/null; then
        success "poppler-utils is installed"
    else
        error "poppler-utils is not installed"
        info "  Install with: sudo apt-get install poppler-utils"
    fi
    
    # Check libreoffice
    if command -v libreoffice &> /dev/null; then
        success "libreoffice is installed"
    else
        warning "libreoffice is not installed (needed for .doc files)"
        info "  Install with: sudo apt-get install libreoffice"
    fi
    
    # Check build tools (needed for Python packages)
    if command -v gcc &> /dev/null; then
        success "build-essential is available"
    else
        error "build-essential is not installed"
        info "  Install with: sudo apt-get install build-essential"
    fi
    
    # Check Python development headers
    if dpkg -l | grep -q "python3-dev"; then
        success "python3-dev is installed"
    else
        error "python3-dev is not installed"
        info "  Install with: sudo apt-get install python3-dev"
    fi
}

# Check directory structure and permissions
check_directories() {
    log "📁 Checking directory structure..."
    
    # Check if target directory exists
    if [[ -d "$TARGET_DIR" ]]; then
        success "Target directory exists: $TARGET_DIR"
        
        # Check if it's a Laravel application
        if [[ -f "$TARGET_DIR/artisan" ]]; then
            success "Laravel application detected"
        else
            warning "Target directory is not a Laravel application"
        fi
        
        # Check permissions
        OWNER=$(stat -c "%U:%G" "$TARGET_DIR" 2>/dev/null)
        info "Directory owner: $OWNER"
        
        # Check if web server can write to storage
        if [[ -d "$TARGET_DIR/storage" ]]; then
            if sudo -u "$WEB_USER" test -w "$TARGET_DIR/storage" 2>/dev/null; then
                success "Web server can write to storage directory"
            else
                error "Web server cannot write to storage directory"
            fi
        else
            warning "Storage directory does not exist yet"
        fi
    else
        warning "Target directory does not exist: $TARGET_DIR"
        info "  Directory will be created during deployment"
    fi
    
    # Check parent directory permissions
    PARENT_DIR=$(dirname "$TARGET_DIR")
    if [[ -d "$PARENT_DIR" ]]; then
        if [[ -w "$PARENT_DIR" ]]; then
            success "Parent directory is writable: $PARENT_DIR"
        else
            error "Parent directory is not writable: $PARENT_DIR"
        fi
    else
        error "Parent directory does not exist: $PARENT_DIR"
    fi
}

# Check Composer
check_composer() {
    log "📦 Checking Composer..."
    
    if command -v composer &> /dev/null; then
        COMPOSER_VERSION=$(composer --version 2>/dev/null | head -1)
        success "Composer: $COMPOSER_VERSION"
    else
        error "Composer is not installed"
        info "  Install from: https://getcomposer.org/download/"
    fi
}

# Check Node.js and npm (for frontend assets)
check_nodejs() {
    log "📦 Checking Node.js and npm..."
    
    if command -v node &> /dev/null; then
        NODE_VERSION=$(node --version 2>/dev/null)
        success "Node.js: $NODE_VERSION"
    else
        warning "Node.js is not installed (needed for frontend assets)"
        info "  Install from: https://nodejs.org/"
    fi
    
    if command -v npm &> /dev/null; then
        NPM_VERSION=$(npm --version 2>/dev/null)
        success "npm: $NPM_VERSION"
    else
        warning "npm is not installed"
    fi
}

# Check supervisor (for queue workers)
check_supervisor() {
    log "⚙️  Checking Supervisor..."
    
    if command -v supervisorctl &> /dev/null; then
        success "Supervisor is installed"
        
        # Check if supervisor is running
        if systemctl is-active --quiet supervisor; then
            success "Supervisor service is running"
        else
            warning "Supervisor service is not running"
        fi
    else
        warning "Supervisor is not installed (recommended for queue workers)"
        info "  Install with: sudo apt-get install supervisor"
    fi
}

# Check Git
check_git() {
    log "📋 Checking Git..."
    
    if command -v git &> /dev/null; then
        GIT_VERSION=$(git --version 2>/dev/null)
        success "Git: $GIT_VERSION"
    else
        error "Git is not installed"
    fi
}

# Check network connectivity
check_network() {
    log "🌐 Checking network connectivity..."
    
    # Check if we can reach package repositories
    if ping -c 1 8.8.8.8 &> /dev/null; then
        success "Internet connectivity is available"
    else
        warning "Internet connectivity may be limited"
    fi
    
    # Check if we can reach package repositories
    if curl -s --head https://packagist.org/ | head -n1 | grep -q "200 OK"; then
        success "Can reach Packagist (Composer packages)"
    else
        warning "Cannot reach Packagist (may affect Composer)"
    fi
}

# Generate recommendations
generate_recommendations() {
    echo
    log "📋 Generating recommendations..."
    
    if [[ $CHECKS_FAILED -gt 0 ]]; then
        echo
        error "❌ $CHECKS_FAILED critical issues found that must be resolved before deployment:"
        echo
        
        # Provide specific installation commands
        echo "To resolve common issues, try running:"
        echo
        echo "# Update package list"
        echo "sudo apt-get update"
        echo
        echo "# Install required packages"
        echo "sudo apt-get install -y php8.2 php8.2-cli php8.2-common php8.2-mysql php8.2-xml php8.2-curl php8.2-gd php8.2-mbstring php8.2-zip php8.2-bcmath"
        echo "sudo apt-get install -y mysql-server python3 python3-pip python3-venv python3-dev"
        echo "sudo apt-get install -y tesseract-ocr poppler-utils libreoffice build-essential"
        echo "sudo apt-get install -y supervisor git curl"
        echo
        echo "# Install Composer"
        echo "curl -sS https://getcomposer.org/installer | php"
        echo "sudo mv composer.phar /usr/local/bin/composer"
        echo
        echo "# Install Node.js (optional, for frontend assets)"
        echo "curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -"
        echo "sudo apt-get install -y nodejs"
        echo
    fi
    
    if [[ $CHECKS_WARNING -gt 0 ]]; then
        echo
        warning "⚠️  $CHECKS_WARNING warnings found. Deployment may proceed but some features might not work optimally."
        echo
    fi
    
    if [[ $CHECKS_FAILED -eq 0 ]]; then
        echo
        success "✅ All critical checks passed! System is ready for deployment."
        echo
        echo "Next steps:"
        echo "1. Run the deployment script: ./deploy-production.sh"
        echo "2. Or set up the parser manually: sudo ./setup-invoice-parser-production.sh"
        echo "3. Fix permissions if needed: sudo ./fix-all-permissions.sh"
        echo
    fi
}

# Display summary
display_summary() {
    echo
    echo "========================================"
    echo "    DEPLOYMENT READINESS SUMMARY"
    echo "========================================"
    echo "Target Directory: $TARGET_DIR"
    echo "Web User: $WEB_USER"
    echo "Web Group: $WEB_GROUP"
    echo "========================================"
    echo "Checks Passed: $CHECKS_PASSED"
    echo "Checks Failed: $CHECKS_FAILED"
    echo "Warnings: $CHECKS_WARNING"
    echo "========================================"
    
    if [[ $CHECKS_FAILED -eq 0 ]]; then
        echo -e "${GREEN}Status: READY FOR DEPLOYMENT${NC}"
    else
        echo -e "${RED}Status: NOT READY - ISSUES MUST BE RESOLVED${NC}"
    fi
    
    echo "========================================"
}

# Main function
main() {
    echo "🔍 OS Manager Deployment Readiness Check"
    echo "========================================="
    echo "Target: $TARGET_DIR"
    echo "Web User: $WEB_USER"
    echo "Web Group: $WEB_GROUP"
    echo "========================================="
    
    # Run all checks
    check_system_info
    check_php
    check_web_server
    check_mysql
    check_python
    check_parser_dependencies
    check_directories
    check_composer
    check_nodejs
    check_supervisor
    check_git
    check_network
    
    # Generate recommendations
    generate_recommendations
    
    # Display summary
    display_summary
    
    # Exit with appropriate code
    if [[ $CHECKS_FAILED -eq 0 ]]; then
        exit 0
    else
        exit 1
    fi
}

# Script usage
usage() {
    echo "Usage: $0 [target_dir] [web_user] [web_group]"
    echo
    echo "Parameters:"
    echo "  target_dir - Target deployment directory (default: /var/www/html/osmanager)"
    echo "  web_user   - Web server user (default: www-data)"
    echo "  web_group  - Web server group (default: www-data)"
    echo
    echo "Examples:"
    echo "  $0"
    echo "  $0 /var/www/html/osmanager-test"
    echo "  $0 /var/www/html/osmanager www-data www-data"
}

# Handle script arguments
if [[ "$1" == "--help" || "$1" == "-h" ]]; then
    usage
    exit 0
fi

# Run main function
main