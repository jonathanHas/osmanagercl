# OSManager CL

A Laravel 12 application for managing operations with integrated Point of Sale (POS) system connectivity.

## Features

- **Authentication**: Username or email login with Laravel Breeze
- **Dual Database Support**: 
  - Primary SQLite/MySQL database for application data
  - Secondary POS connection for uniCenta integration
- **POS Integration**: 
  - Product catalog with real-time stock levels
  - Supplier management and cost tracking
  - Inventory management integration
- **Product Management**: 
  - Product listings with supplier information
  - Real-time stock quantities from STOCKCURRENT
  - Margin calculations and cost analysis
- **Frontend**: Tailwind CSS with Alpine.js and admin layout
- **Testing**: PHPUnit with comprehensive test suite
- **Development**: Hot reloading with Vite

## Quick Start

```bash
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

# Start development
composer run dev
```

## Configuration

### Primary Database
The application uses SQLite by default. Configure in `.env`:
```
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
```

### POS Database (uniCenta)
Configure the POS database connection in `.env`:
```
POS_DB_HOST=127.0.0.1
POS_DB_PORT=3306
POS_DB_DATABASE=unicenta
POS_DB_USERNAME=readonly_user
POS_DB_PASSWORD=your_password
```

## Test Login

- **Username:** `admin`
- **Email:** `admin@osmanager.local`
- **Password:** `admin123`

## Product Management

The application provides read-only access to the uniCenta POS product catalog:

- View all products with pagination
- Search products by name, code, or reference
- Filter active (non-service) products
- View detailed product information
- Dashboard statistics showing product counts

### Demo Script
Test the POS connection without starting the web server:
```bash
php demo-products.php
```

## Built With Laravel

This application is built on Laravel - a web application framework with expressive, elegant syntax. Laravel provides:

- [Simple, fast routing engine](https://laravel.com/docs/routing)
- [Powerful dependency injection container](https://laravel.com/docs/container)
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent)
- Database agnostic [schema migrations](https://laravel.com/docs/migrations)
- [Robust background job processing](https://laravel.com/docs/queues)
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting)

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
