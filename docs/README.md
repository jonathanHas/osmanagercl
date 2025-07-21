# Documentation

This folder contains detailed documentation for specific areas of the codebase to keep the main CLAUDE.md file focused and organized.

## Available Documentation

### Development & Integration
- **[pos-integration.md](./pos-integration.md)** - POS Database Integration (uniCenta)
  - Product, Supplier, SupplierLink, Stocking, and StockCurrent models
  - Relationships and usage examples
  - Database schema information
  - Future integration opportunities (VAT, Categories, etc.)

- **[pricing-system.md](./pricing-system.md)** - Product Pricing System
  - Consolidated pricing interface with supplier integration
  - VAT-inclusive pricing with 4-decimal precision
  - Live price comparison and competitive analysis
  - Quick action buttons and advanced pricing strategies
  - High-precision VAT calculations and storage

- **[supplier-integration-plan.md](./supplier-integration-plan.md)** - Supplier External Integration
  - External product images from supplier CDNs
  - Supplier website links and search integration
  - Live price comparison with Udea supplier
  - Customer price extraction and analysis
  - Implementation phases and current status

- **[delivery-verification-system.md](./delivery-verification-system.md)** - Delivery Verification System
  - Complete CSV import and scanning workflow
  - Real-time barcode scanning with mobile optimization
  - Discrepancy tracking and reporting
  - Supplier image integration with hover previews
  - Database schema and API documentation
  - Comprehensive troubleshooting guide

### Deployment & Production
- **[production-deployment.md](./production-deployment.md)** - Production Deployment Guide
  - Critical database indexes for performance
  - Environment configuration for production
  - Performance optimization requirements
  - Deployment checklist and rollback procedures
  - Troubleshooting and monitoring guidelines

## Contributing

When adding new features or systems:

1. Create a new `.md` file in this `docs/` folder for the specific area
2. Update this README.md to include a link to the new documentation
3. Keep the main `CLAUDE.md` focused on general project setup and common commands
4. Reference the specific documentation from `CLAUDE.md` when relevant

## File Naming Convention

Use descriptive, lowercase names with hyphens:
- `pos-integration.md` - POS database integration
- `api-endpoints.md` - API documentation
- `frontend-components.md` - Frontend component library
- `testing-guide.md` - Testing strategies and examples