# Changelog

All notable changes to OSManager CL will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Comprehensive documentation restructuring with new organization system
- CONTRIBUTING.md with coding standards and development guidelines
- Project-focused README.md replacing Laravel boilerplate

### Fixed
- Delivery scanning syntax errors in Blade templates
- Division by zero in progress bar calculations
- Null date handling in delivery views
- API data consistency between scan and quantity endpoints

### Changed
- Refactored DeliveryController to use consistent data formatting
- Moved complex PHP logic from Blade templates to controllers

## [0.3.0] - 2024-01-20

### Added
- Delivery verification system with CSV import and barcode scanning
- Real-time mobile-optimized scanning interface
- Discrepancy tracking and reporting for deliveries
- Product creation from unmatched delivery items
- Supplier image integration with hover previews
- Export functionality for delivery discrepancies

### Changed
- Enhanced product image support for new products without existing models
- Improved barcode extraction with multiple pattern support

## [0.2.0] - 2024-01-15

### Added
- Advanced pricing system with VAT-inclusive calculations
- 4-decimal precision storage for accurate VAT preservation
- Live supplier price comparison with Udea
- Quick action buttons for competitive pricing strategies
- Transport cost analysis (15% calculation)
- Customer price extraction from supplier pages

### Changed
- Consolidated pricing interface in product management
- Enhanced supplier integration with live data

## [0.1.0] - 2024-01-10

### Added
- Initial Laravel 12 application setup
- uniCenta POS database integration
- Product catalog with real-time stock levels
- Supplier management and cost tracking
- Admin dashboard with sidebar navigation
- Username/email authentication with Laravel Breeze
- Product search and filtering capabilities
- Supplier external integration for images and links

### Security
- Secure authentication system with email verification
- Role-based access control foundation

## Development Guidelines

When making changes:
1. Update this changelog in the Unreleased section
2. Follow the categories: Added, Changed, Deprecated, Removed, Fixed, Security
3. Reference issue numbers where applicable
4. Move Unreleased items to a new version section when releasing

[Unreleased]: https://github.com/yourusername/osmanagercl/compare/v0.3.0...HEAD
[0.3.0]: https://github.com/yourusername/osmanagercl/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/yourusername/osmanagercl/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/yourusername/osmanagercl/releases/tag/v0.1.0