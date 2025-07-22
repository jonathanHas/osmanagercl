# OSManager CL

A comprehensive retail operations management system with integrated Point of Sale (POS) connectivity, supplier integration, and delivery verification capabilities.

## Overview

OSManager CL is a Laravel-based application designed to streamline retail operations by integrating with uniCenta POS systems and providing advanced features for inventory management, supplier integration, and delivery processing.

### Key Features

- **🛒 POS Integration**: Real-time product catalog synchronization with uniCenta POS
- **📦 Delivery Verification**: Mobile-optimized barcode scanning and discrepancy tracking
- **💰 Advanced Pricing**: VAT-inclusive pricing with 4-decimal precision and margin analysis
- **🏪 Supplier Integration**: External product images and live price comparisons
- **📊 Inventory Management**: Real-time stock levels and movement tracking
- **🔐 Secure Authentication**: Username/email login with role-based access control
- **📱 Mobile-First Design**: Responsive admin interface optimized for all devices

## Tech Stack

- **Backend**: Laravel 12, PHP 8.2+
- **Frontend**: Blade templates, Tailwind CSS, Alpine.js
- **Database**: SQLite (primary), MySQL (POS integration)
- **Build Tools**: Vite, npm
- **Testing**: PHPUnit
- **Authentication**: Laravel Breeze

## Quick Start

```bash
# Clone the repository
git clone <repository-url>
cd osmanagercl

# Install dependencies
composer install
npm install

# Setup environment
cp .env.example .env
php artisan key:generate

# Setup database
touch database/database.sqlite
php artisan migrate
php artisan db:seed --class=AdminUserSeeder

# Configure POS database connection in .env
# POS_DB_HOST=127.0.0.1
# POS_DB_DATABASE=unicenta
# POS_DB_USERNAME=your_username
# POS_DB_PASSWORD=your_password

# Start development server
composer run dev
```

### Test Credentials
- **Username**: `admin`
- **Email**: `admin@osmanager.local`
- **Password**: `admin123`

## Project Structure

```
osmanagercl/
├── app/                    # Application logic
│   ├── Http/Controllers/   # Request handlers
│   ├── Models/            # Eloquent models
│   ├── Services/          # Business logic
│   └── Repositories/      # Data access layer
├── resources/             # Views and assets
│   ├── views/            # Blade templates
│   ├── js/               # JavaScript files
│   └── css/              # Stylesheets
├── routes/               # Application routes
├── database/             # Migrations and seeds
├── tests/                # Test suites
└── docs/                 # Documentation
```

## Documentation

Comprehensive documentation is available in the `docs/` directory:

- **[Documentation Index](./docs/README.md)** - Complete documentation overview
- **[Development Setup](./docs/development/setup.md)** - Detailed setup instructions
- **[Architecture Overview](./docs/architecture/overview.md)** - System design and patterns
- **[API Documentation](./docs/api/endpoints.md)** - REST API reference

### Feature Documentation
- [POS Integration](./docs/features/pos-integration.md)
- [Delivery System](./docs/features/delivery-system.md)
- [Pricing System](./docs/features/pricing-system.md)
- [Supplier Integration](./docs/features/supplier-integration.md)

## Development

### Common Commands

```bash
# Run tests
composer test

# Format code
./vendor/bin/pint

# Clear caches
php artisan optimize:clear

# View routes
php artisan route:list
```

For detailed development instructions, see [Development Guide](./docs/development/setup.md).

## Contributing

We welcome contributions! Please see our [Contributing Guidelines](./CONTRIBUTING.md) for details on:
- Code style and standards
- Development workflow
- Testing requirements
- Pull request process

## Deployment

For production deployment instructions, see [Production Deployment Guide](./docs/deployment/production-guide.md).

## License

This project is proprietary software. All rights reserved.

## Support

For support, documentation, or questions:
- Check the [documentation](./docs/)
- Review [troubleshooting guide](./docs/development/troubleshooting.md)
- Contact the development team

---

Built with ❤️ using [Laravel](https://laravel.com) - The PHP Framework for Web Artisans