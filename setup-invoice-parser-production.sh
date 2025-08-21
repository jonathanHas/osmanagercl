#!/bin/bash

# =============================================================================
# Production Invoice Parser Setup Script
# Enhanced version of scripts/invoice-parser/setup.sh for production deployment
# =============================================================================

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
APP_PATH=${1:-/var/www/html/osmanager}
WEB_USER=${2:-www-data}
WEB_GROUP=${3:-www-data}

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

# Check if running as root
check_sudo() {
    if [[ $EUID -ne 0 ]]; then
        error "This script must be run as root or with sudo"
        echo "Usage: sudo $0 [app_path] [web_user] [web_group]"
        exit 1
    fi
}

# Validate application directory
validate_app_directory() {
    log "🔍 Validating application directory: $APP_PATH"
    
    if [[ ! -d "$APP_PATH" ]]; then
        error "Application directory does not exist: $APP_PATH"
        exit 1
    fi
    
    if [[ ! -f "$APP_PATH/artisan" ]]; then
        error "Not a Laravel application: $APP_PATH"
        exit 1
    fi
    
    PARSER_DIR="$APP_PATH/scripts/invoice-parser"
    if [[ ! -d "$PARSER_DIR" ]]; then
        error "Invoice parser directory not found: $PARSER_DIR"
        exit 1
    fi
    
    success "Application and parser directories validated."
}

# Check system requirements
check_system_requirements() {
    log "📋 Checking system requirements..."
    
    # Check Python version
    if ! command -v python3 &> /dev/null; then
        error "Python 3 is not installed"
        echo "Install with: sudo apt-get update && sudo apt-get install python3 python3-pip python3-venv"
        exit 1
    fi
    
    PYTHON_VERSION=$(python3 -c 'import sys; print(".".join(map(str, sys.version_info[:2])))')
    log "Python version: $PYTHON_VERSION"
    
    if [[ $(echo "$PYTHON_VERSION >= 3.8" | bc -l 2>/dev/null || echo "0") -eq 0 ]]; then
        if python3 -c "import sys; exit(0 if sys.version_info >= (3, 8) else 1)" 2>/dev/null; then
            success "Python version is compatible"
        else
            error "Python version $PYTHON_VERSION is too old (need 3.8+)"
            exit 1
        fi
    else
        success "Python version is compatible"
    fi
    
    # Check pip
    if ! command -v pip3 &> /dev/null; then
        warning "pip3 not found, installing..."
        apt-get update
        apt-get install -y python3-pip
    fi
    
    success "System requirements validated."
}

# Install system dependencies
install_system_dependencies() {
    log "📦 Installing system dependencies..."
    
    # Update package list
    log "Updating package list..."
    apt-get update
    
    DEPENDENCIES=()
    MISSING_DEPS=()
    
    # Check for tesseract-ocr
    if ! command -v tesseract &> /dev/null; then
        MISSING_DEPS+=("tesseract-ocr")
    fi
    
    # Check for poppler-utils
    if ! command -v pdftotext &> /dev/null; then
        MISSING_DEPS+=("poppler-utils")
    fi
    
    # Check for libreoffice
    if ! command -v libreoffice &> /dev/null; then
        MISSING_DEPS+=("libreoffice")
    fi
    
    # Check for build essentials (needed for some Python packages)
    if ! dpkg -l | grep -q "build-essential"; then
        MISSING_DEPS+=("build-essential")
    fi
    
    # Check for Python development headers
    if ! dpkg -l | grep -q "python3-dev"; then
        MISSING_DEPS+=("python3-dev")
    fi
    
    if [[ ${#MISSING_DEPS[@]} -eq 0 ]]; then
        success "All system dependencies are already installed."
    else
        log "Installing missing dependencies: ${MISSING_DEPS[*]}"
        if apt-get install -y "${MISSING_DEPS[@]}"; then
            success "System dependencies installed successfully."
        else
            error "Failed to install system dependencies"
            exit 1
        fi
    fi
    
    # Verify installations
    log "Verifying system dependencies..."
    
    if command -v tesseract &> /dev/null; then
        success "✅ tesseract-ocr is installed ($(tesseract --version | head -1))"
    else
        error "❌ tesseract-ocr installation failed"
        exit 1
    fi
    
    if command -v pdftotext &> /dev/null; then
        success "✅ poppler-utils is installed"
    else
        error "❌ poppler-utils installation failed"
        exit 1
    fi
    
    if command -v libreoffice &> /dev/null; then
        success "✅ libreoffice is installed"
    else
        error "❌ libreoffice installation failed"
        exit 1
    fi
}

# Setup Python virtual environment
setup_virtual_environment() {
    log "🐍 Setting up Python virtual environment..."
    
    PARSER_DIR="$APP_PATH/scripts/invoice-parser"
    cd "$PARSER_DIR" || exit 1
    
    # Remove existing venv if it exists
    if [[ -d "venv" ]]; then
        warning "Removing existing virtual environment..."
        rm -rf venv
    fi
    
    # Create virtual environment
    log "Creating virtual environment..."
    if python3 -m venv venv; then
        success "Virtual environment created."
    else
        error "Failed to create virtual environment"
        exit 1
    fi
    
    # Activate virtual environment
    log "Activating virtual environment..."
    source venv/bin/activate
    
    # Upgrade pip
    log "Upgrading pip..."
    if pip install --upgrade pip; then
        success "Pip upgraded successfully."
    else
        warning "Pip upgrade failed, continuing..."
    fi
    
    success "Virtual environment setup complete."
}

# Install Python packages
install_python_packages() {
    log "📚 Installing Python packages..."
    
    PARSER_DIR="$APP_PATH/scripts/invoice-parser"
    cd "$PARSER_DIR" || exit 1
    
    # Activate virtual environment
    source venv/bin/activate
    
    # Check if requirements.txt exists
    if [[ ! -f "requirements.txt" ]]; then
        error "requirements.txt not found in $PARSER_DIR"
        exit 1
    fi
    
    log "Installing packages from requirements.txt..."
    if pip install -r requirements.txt; then
        success "Python packages installed successfully."
    else
        error "Failed to install Python packages"
        exit 1
    fi
    
    # List installed packages for verification
    log "Installed packages:"
    pip list | head -10
    
    success "Python package installation complete."
}

# Set proper permissions
set_parser_permissions() {
    log "🔐 Setting parser permissions..."
    
    PARSER_DIR="$APP_PATH/scripts/invoice-parser"
    cd "$PARSER_DIR" || exit 1
    
    # Set ownership to web server user
    log "Setting ownership to $WEB_USER:$WEB_GROUP..."
    chown -R "$WEB_USER:$WEB_GROUP" .
    
    # Set directory permissions
    log "Setting directory permissions..."
    find . -type d -exec chmod 755 {} \;
    
    # Set file permissions
    log "Setting file permissions..."
    find . -type f -exec chmod 644 {} \;
    
    # Make Python scripts executable
    log "Making Python scripts executable..."
    find . -name "*.py" -exec chmod +x {} \;
    find . -name "*.sh" -exec chmod +x {} \;
    
    # Set virtual environment permissions
    if [[ -d "venv" ]]; then
        log "Setting virtual environment permissions..."
        chmod -R 755 venv
        
        # Make Python binaries executable
        if [[ -d "venv/bin" ]]; then
            chmod +x venv/bin/*
        fi
    fi
    
    success "Parser permissions set."
}

# Test parser installation
test_parser_installation() {
    log "🧪 Testing parser installation..."
    
    PARSER_DIR="$APP_PATH/scripts/invoice-parser"
    cd "$PARSER_DIR" || exit 1
    
    # Test basic parser functionality
    log "Testing parser help command..."
    if sudo -u "$WEB_USER" bash -c "source venv/bin/activate && python invoice_parser_laravel.py --help" >/dev/null 2>&1; then
        success "✅ Parser help command works"
    else
        error "❌ Parser help command failed"
        exit 1
    fi
    
    # Test Python modules import
    log "Testing Python module imports..."
    if sudo -u "$WEB_USER" bash -c "source venv/bin/activate && python -c 'import pytesseract, PyPDF2, python_docx; print(\"All modules imported successfully\")'" 2>/dev/null; then
        success "✅ Required Python modules can be imported"
    else
        warning "⚠️  Some Python modules may have import issues"
    fi
    
    # Test tesseract access
    log "Testing tesseract access..."
    if sudo -u "$WEB_USER" tesseract --version >/dev/null 2>&1; then
        success "✅ Tesseract is accessible to web user"
    else
        warning "⚠️  Tesseract may not be accessible to web user"
    fi
    
    success "Parser installation tests complete."
}

# Configure Laravel environment
configure_laravel_environment() {
    log "⚙️ Configuring Laravel environment variables..."
    
    cd "$APP_PATH" || exit 1
    
    ENV_FILE=".env"
    if [[ ! -f "$ENV_FILE" ]]; then
        warning ".env file not found, skipping environment configuration"
        return
    fi
    
    PARSER_DIR="$APP_PATH/scripts/invoice-parser"
    
    # Environment variables to set
    ENV_VARS=(
        "PYTHON_EXECUTABLE=/usr/bin/python3"
        "PYTHON_PARSER_DIR=$PARSER_DIR"
        "PYTHON_VENV_PATH=$PARSER_DIR/venv"
        "INVOICE_PARSER_SCRIPT=$PARSER_DIR/invoice_parser_laravel.py"
        "TESSERACT_PATH=/usr/bin/tesseract"
        "PDFTOTEXT_PATH=/usr/bin/pdftotext"
        "LIBREOFFICE_PATH=/usr/bin/libreoffice"
    )
    
    for env_var in "${ENV_VARS[@]}"; do
        VAR_NAME=$(echo "$env_var" | cut -d'=' -f1)
        VAR_VALUE=$(echo "$env_var" | cut -d'=' -f2-)
        
        # Check if variable already exists
        if grep -q "^$VAR_NAME=" "$ENV_FILE"; then
            # Update existing variable
            log "Updating $VAR_NAME in .env..."
            sed -i "s|^$VAR_NAME=.*|$env_var|" "$ENV_FILE"
        else
            # Add new variable
            log "Adding $VAR_NAME to .env..."
            echo "$env_var" >> "$ENV_FILE"
        fi
    done
    
    # Ensure proper .env permissions
    chown "$WEB_USER:$WEB_GROUP" "$ENV_FILE"
    chmod 644 "$ENV_FILE"
    
    success "Laravel environment configured."
}

# Create test invoice for validation
create_test_invoice() {
    log "📄 Creating test invoice for validation..."
    
    PARSER_DIR="$APP_PATH/scripts/invoice-parser"
    TEST_DIR="$PARSER_DIR/test"
    
    # Create test directory
    mkdir -p "$TEST_DIR"
    
    # Create a simple test PDF content
    cat > "$TEST_DIR/test_invoice.txt" << 'EOF'
INVOICE

Invoice Number: TEST-001
Date: 2025-01-01
Due Date: 2025-01-31

Bill To:
Test Company
123 Test Street
Test City, TC 12345

Description                     Amount
Test Item 1                     €100.00
Test Item 2                     €50.00
                               --------
Subtotal                       €150.00
VAT (23%)                      €34.50
                               --------
Total                          €184.50

Thank you for your business!
EOF
    
    # Convert to PDF if possible (optional)
    if command -v libreoffice &> /dev/null; then
        cd "$TEST_DIR" || exit 1
        log "Converting test invoice to PDF..."
        if sudo -u "$WEB_USER" libreoffice --headless --convert-to pdf test_invoice.txt 2>/dev/null; then
            success "✅ Test PDF created successfully"
            rm test_invoice.txt  # Remove text version
        else
            warning "⚠️  PDF conversion failed, keeping text version"
        fi
    fi
    
    # Set permissions on test files
    chown -R "$WEB_USER:$WEB_GROUP" "$TEST_DIR"
    chmod -R 644 "$TEST_DIR"/*
    
    success "Test invoice created."
}

# Display configuration summary
display_summary() {
    echo
    echo "========================================"
    echo "    INVOICE PARSER SETUP SUMMARY"
    echo "========================================"
    echo "Application Path: $APP_PATH"
    echo "Parser Directory: $APP_PATH/scripts/invoice-parser"
    echo "Web User: $WEB_USER"
    echo "Web Group: $WEB_GROUP"
    echo "========================================"
    
    # Show Python version
    PYTHON_VERSION=$(python3 --version 2>&1)
    echo "Python: $PYTHON_VERSION"
    
    # Show virtual environment status
    VENV_PATH="$APP_PATH/scripts/invoice-parser/venv"
    if [[ -d "$VENV_PATH" ]]; then
        echo "Virtual Environment: ✅ Created"
        VENV_PYTHON_VERSION=$(sudo -u "$WEB_USER" "$VENV_PATH/bin/python" --version 2>&1)
        echo "  Version: $VENV_PYTHON_VERSION"
    else
        echo "Virtual Environment: ❌ Not found"
    fi
    
    # Show system dependencies
    echo "System Dependencies:"
    if command -v tesseract &> /dev/null; then
        echo "  tesseract-ocr: ✅ $(tesseract --version | head -1)"
    else
        echo "  tesseract-ocr: ❌ Not installed"
    fi
    
    if command -v pdftotext &> /dev/null; then
        echo "  poppler-utils: ✅ Installed"
    else
        echo "  poppler-utils: ❌ Not installed"
    fi
    
    if command -v libreoffice &> /dev/null; then
        echo "  libreoffice: ✅ Installed"
    else
        echo "  libreoffice: ❌ Not installed"
    fi
    
    echo "========================================"
    
    # Show next steps
    echo "✅ Next steps:"
    echo "1. Test parser with: sudo -u $WEB_USER bash -c 'cd $APP_PATH/scripts/invoice-parser && source venv/bin/activate && python invoice_parser_laravel.py --help'"
    echo "2. Upload test invoices through Laravel interface"
    echo "3. Monitor Laravel logs: tail -f $APP_PATH/storage/logs/laravel.log"
    echo "4. Check queue worker status: php artisan queue:work --once"
    echo
}

# Main function
main() {
    echo "🐍 OS Manager Invoice Parser Production Setup"
    echo "=============================================="
    
    # Step 1: Check sudo permissions
    check_sudo
    
    # Step 2: Validate application directory
    validate_app_directory
    
    # Step 3: Check system requirements
    check_system_requirements
    
    # Step 4: Install system dependencies
    install_system_dependencies
    
    # Step 5: Setup virtual environment
    setup_virtual_environment
    
    # Step 6: Install Python packages
    install_python_packages
    
    # Step 7: Set proper permissions
    set_parser_permissions
    
    # Step 8: Test parser installation
    test_parser_installation
    
    # Step 9: Configure Laravel environment
    configure_laravel_environment
    
    # Step 10: Create test invoice
    create_test_invoice
    
    # Step 11: Display summary
    display_summary
    
    success "🎉 Invoice parser setup completed successfully!"
    
    echo
    warning "⚠️  Important notes:"
    echo "1. Restart your queue workers: php artisan queue:restart"
    echo "2. Clear Laravel config cache: php artisan config:clear"
    echo "3. Test invoice upload functionality"
    echo "4. Monitor logs for any issues"
    echo
}

# Script usage
usage() {
    echo "Usage: sudo $0 [app_path] [web_user] [web_group]"
    echo
    echo "Parameters:"
    echo "  app_path  - Path to Laravel application (default: /var/www/html/osmanager)"
    echo "  web_user  - Web server user (default: www-data)"
    echo "  web_group - Web server group (default: www-data)"
    echo
    echo "Examples:"
    echo "  sudo $0"
    echo "  sudo $0 /var/www/html/osmanager-test"
    echo "  sudo $0 /var/www/html/osmanager www-data www-data"
}

# Handle script arguments
if [[ "$1" == "--help" || "$1" == "-h" ]]; then
    usage
    exit 0
fi

# Run main function
main