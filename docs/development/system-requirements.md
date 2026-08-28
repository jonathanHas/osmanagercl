# System Requirements

OSManager CL system requirements for development and production environments.

## Core Requirements

### PHP & Laravel
- **PHP**: 8.2+
- **Laravel**: 12.x
- **Composer**: Latest stable version

### Database Systems

The application needs **three databases across two engines**. See the
[Fresh Install Guide](./fresh-install-guide.md) for setup.

- **MySQL / MariaDB**: the Laravel application database (`osmanagercl` in dev, `osmanager` in
  production) on port **3306**. MariaDB 10.11 on the reference machines
- **MySQL 5.7**: `unicenta2016` (uniCenta POS) and `OSAccounts`, both on port **3307**. In
  development these run in a pinned `mysql:5.7.33` Docker container — uniCenta's legacy schema
  does not load cleanly on MySQL 8 or MariaDB
- **SQLite**: not used. `database/database.sqlite` is a leftover 0-byte placeholder, and
  `config/database.php` sets the `default` connection fallback to `mysql`

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

# Database setup (create the MySQL/MariaDB database and set DB_* in .env first)
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