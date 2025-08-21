#!/bin/bash
#
# Setup script for Invoice Parser Python environment
# Run this after deployment to initialize the Python virtual environment
#

set -e  # Exit on error

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "Setting up Invoice Parser Python environment..."
echo "Working directory: $SCRIPT_DIR"

# Check Python version
if ! command -v python3 &> /dev/null; then
    echo "Error: Python 3 is not installed"
    echo "Please install Python 3.8 or higher"
    exit 1
fi

PYTHON_VERSION=$(python3 -c 'import sys; print(".".join(map(str, sys.version_info[:2])))')
echo "Python version: $PYTHON_VERSION"

# Create virtual environment
echo "Creating virtual environment..."
cd "$SCRIPT_DIR"
python3 -m venv venv

# Activate virtual environment
echo "Activating virtual environment..."
source venv/bin/activate

# Upgrade pip
echo "Upgrading pip..."
pip install --upgrade pip

# Install Python packages
echo "Installing Python packages..."
pip install -r requirements.txt

# Check for system dependencies
echo ""
echo "Checking system dependencies..."

# Check for tesseract
if command -v tesseract &> /dev/null; then
    echo "✓ tesseract-ocr is installed"
else
    echo "✗ tesseract-ocr is NOT installed"
    echo "  Install with: sudo apt-get install tesseract-ocr"
fi

# Check for poppler-utils
if command -v pdftotext &> /dev/null; then
    echo "✓ poppler-utils is installed"
else
    echo "✗ poppler-utils is NOT installed"
    echo "  Install with: sudo apt-get install poppler-utils"
fi

# Check for libreoffice
if command -v libreoffice &> /dev/null; then
    echo "✓ libreoffice is installed"
else
    echo "✗ libreoffice is NOT installed (needed for .doc files)"
    echo "  Install with: sudo apt-get install libreoffice"
fi

# Test the parser
echo ""
echo "Testing parser installation..."
python invoice_parser_laravel.py --help > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo "✓ Parser script is working"
else
    echo "✗ Parser script failed to run"
    exit 1
fi

echo ""
echo "Setup complete!"
echo ""
echo "To test the parser manually:"
echo "  cd $SCRIPT_DIR"
echo "  source venv/bin/activate"
echo "  python invoice_parser_laravel.py --file /path/to/invoice.pdf"
echo ""
echo "Add these to your Laravel .env file:"
echo "  PYTHON_EXECUTABLE=/usr/bin/python3"
echo "  PYTHON_PARSER_DIR=$SCRIPT_DIR"
echo "  PYTHON_VENV_PATH=$SCRIPT_DIR/venv"
echo "  INVOICE_PARSER_SCRIPT=$SCRIPT_DIR/invoice_parser_laravel.py"