# System Requirements

OSManager CL system requirements for development and production environments.

## Core Requirements

### PHP & Laravel
- **PHP**: 8.2+
- **Laravel**: 12.x
- **Composer**: Latest stable version

### Database Systems
- **SQLite**: Primary database (development)
- **MySQL**: 8.0+ (production, POS integration)

### Frontend Assets
- **Node.js**: 18.x or higher
- **NPM**: Latest stable version
- **Vite**: Asset building and compilation

### Document Processing
- **LibreOffice**: Required for DOC/XLS to PDF conversion
  ```bash
  # Ubuntu/Debian
  sudo apt-get install libreoffice
  
  # CentOS/RHEL
  sudo yum install libreoffice
  
  # macOS
  brew install --cask libreoffice
  ```

### Optional Components
- **Redis**: Queue processing (production)
- **Python 3.8+**: Invoice parser integration
- **Supervisor**: Process management (production)

## Feature-Specific Requirements

### Invoice Document Viewing
- **LibreOffice**: Essential for viewing DOC, DOCX, XLS, XLSX files in browser
- **Storage**: Adequate disk space for converted PDF cache files
- **Memory**: 512MB+ recommended for document conversion process

### F&V System
- **MySQL Connection**: Secondary database connection for POS integration
- **GD Extension**: Image processing for product photos

### Label System
- **Font Support**: System fonts for label generation
- **PDF Libraries**: For label template rendering

### Barcode Scanning
- **Camera Access**: Web browser camera permissions
- **HTTPS**: Required for camera access in modern browsers

## Development Environment Setup

### Quick Setup Commands
```bash
# Install PHP dependencies
composer install

# Install Node.js dependencies
npm install

# Set up environment
cp .env.example .env
php artisan key:generate

# Database setup
touch database/database.sqlite
php artisan migrate

# Build assets
npm run build

# Start development server
php artisan serve
```

### Production Environment
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **Process Manager**: Supervisor for queue workers
- **SSL Certificate**: HTTPS required for barcode scanning
- **File Permissions**: Proper storage directory permissions for www-data

### Performance Considerations
- **Memory**: 2GB+ RAM recommended for production
- **Storage**: SSD recommended for database performance
- **CPU**: Multi-core recommended for document conversion

## Verification Commands

Test core functionality:
```bash
# Check PHP version and extensions
php -v
php -m | grep -E "(pdo_sqlite|pdo_mysql|gd)"

# Check Node.js and npm
node --version
npm --version

# Check LibreOffice
soffice --version

# Test database connectivity
php artisan tinker --execute="DB::connection()->getPdo(); echo 'Database OK';"

# Test asset building
npm run build
```

## Troubleshooting

### LibreOffice Issues
If document conversion fails:
1. Verify LibreOffice installation: `soffice --version`
2. Check for headless support: `soffice --headless --version`
3. Ensure proper permissions for www-data user
4. Review Laravel logs for conversion errors

### Database Connection Issues
1. Check .env configuration
2. Verify database server is running
3. Test connection with mysql client
4. Check user permissions

See [Troubleshooting Guide](./troubleshooting.md) for detailed solutions.