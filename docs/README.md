# OSManager CL Documentation

Welcome to the comprehensive documentation for OSManager CL. This documentation is organized to help you quickly find the information you need.

📚 **New to the docs?** Start with the [Documentation Usage Guide](./DOCUMENTATION_GUIDE.md) to understand how to navigate and contribute to documentation.

## 📚 Documentation Structure

### 🏗️ Architecture & Design
Core system architecture and design patterns.

- **[Architecture Overview](./architecture/overview.md)** - System design, patterns, and principles
- **[Database Design](./architecture/database-design.md)** - Schema design and relationships
- **[API Design](./architecture/api-design.md)** - RESTful API principles and standards

### 🚀 Features
Detailed documentation for each major feature.

- **[Sales Data Import](./features/sales-data-import.md)** - 🚀 **NEW** Lightning-fast sales analytics system
  - 100x+ performance improvement over cross-database queries
  - Pre-aggregated daily and monthly sales data
  - Automated data synchronization with POS system
  - Sub-20ms response times for all analytics queries
  - Complete CLI suite for data management

- **[POS Integration](./features/pos-integration.md)** - uniCenta POS database integration
  - Product, Supplier, and Stock models
  - Real-time inventory synchronization
  - Read-only access patterns

- **[Delivery System](./features/delivery-system.md)** - Comprehensive delivery verification
  - CSV import and parsing
  - Mobile-optimized barcode scanning
  - Discrepancy tracking and reporting
  - Stock update automation

- **[Pricing System](./features/pricing-system.md)** - Advanced pricing management
  - VAT-inclusive pricing with 4-decimal precision
  - Live supplier price comparison
  - Margin analysis and optimization
  - Quick pricing actions

- **[Supplier Integration](./features/supplier-integration.md)** - External supplier connectivity
  - Product image CDN integration
  - Live price scraping
  - Barcode extraction
  - Multi-supplier support

- **[Label System](./features/label-system.md)** - Comprehensive label printing system *(Updated)*
  - Dynamic barcode generation with Code128 format
  - Enhanced 4x9 grid layout with 3-row structure
  - Smart responsive text sizing (5 tiers)
  - Improved typography and € symbol handling
  - Template-based label layouts with A4 optimization
  - Event-driven re-queuing functionality
  - Real-time print queue management
  - Smart product filtering based on print history

- **[Product Management](./features/product-management.md)** - Product catalog operations
  - Cross-database VegDetails integration with POS system
  - Real-time class, country, and unit data synchronization
  - Dual search system for availability management
  - CRUD operations with UUID support
  - Inline editing for product names, pricing, and tax categories
  - Stocking management with visual indicators
  - Delivery-integrated product creation workflows
  - Smart context-aware navigation

- **[Packaging Structure](./features/packaging-structure.md)** - Retail vs wholesale packaging
  - Units per retail package vs packages per case
  - CSV import handling
  - Total units calculation
  - Supplier linking
  - Category management
  - Search and filtering

### 💻 Development
Guides for developers working on the project.

- **[Setup Guide](./development/setup.md)** - Complete development environment setup
- **[Testing Guide](./development/testing.md)** - Testing strategies and examples
- **[Coding Standards](./development/coding-standards.md)** - Code style and best practices
- **[Performance Optimization Guide](./development/performance-optimization-guide.md)** - 🚀 **NEW** Apply 100x+ performance improvements to any module
- **[Troubleshooting](./development/troubleshooting.md)** - Common issues and solutions

### 🚢 Deployment
Production deployment and operations.

- **[Production Guide](./deployment/production-guide.md)** - Step-by-step deployment
- **[Environment Configuration](./deployment/environment-config.md)** - Production settings
- **[Monitoring](./deployment/monitoring.md)** - Application monitoring and alerts

### 🔌 API Reference
Complete API documentation.

- **[Product Endpoints](./api/product-endpoints.md)** - Product management API endpoints
- **[Fruit & Veg Endpoints](./api/fruit-veg-endpoints.md)** - Specialized fruit and vegetable operations
- **[API Endpoints](./api/endpoints.md)** - All available endpoints
- **[Authentication](./api/authentication.md)** - API authentication methods
- **[Webhooks](./api/webhooks.md)** - Webhook events and payloads

### 📝 Templates
Documentation templates for consistency.

- **[Feature Template](./templates/feature-template.md)** - For documenting new features
- **[API Endpoint Template](./templates/api-endpoint-template.md)** - For API documentation
- **[Planning Template](./templates/planning-template.md)** - For project planning

## 🎯 Quick Links

### For New Developers
1. Start with [Architecture Overview](./architecture/overview.md)
2. Follow the [Setup Guide](./development/setup.md)
3. Review [Coding Standards](./development/coding-standards.md)
4. Read about key features you'll work on

### For System Administrators
1. Review [Production Guide](./deployment/production-guide.md)
2. Configure using [Environment Config](./deployment/environment-config.md)
3. Set up [Monitoring](./deployment/monitoring.md)
4. Keep [Troubleshooting](./development/troubleshooting.md) handy

### For API Consumers
1. Start with [API Design](./architecture/api-design.md)
2. Set up [Authentication](./api/authentication.md)
3. Explore [API Endpoints](./api/endpoints.md)
4. Subscribe to [Webhooks](./api/webhooks.md) if needed

## 📋 Documentation Standards

When contributing to documentation:

1. **Use Templates**: Start with appropriate template from `templates/`
2. **Be Concise**: Clear, direct explanations
3. **Include Examples**: Code samples and use cases
4. **Stay Current**: Update docs with code changes
5. **Cross-Reference**: Link related documentation

### Markdown Conventions
- Use ATX-style headers (`#`, `##`, etc.)
- Include a table of contents for long documents
- Use code blocks with language hints
- Add diagrams where helpful (Mermaid supported)

## 🔄 Keeping Documentation Updated

Documentation should be updated:
- When adding new features
- When changing existing functionality
- When fixing bugs that affect behavior
- When improving performance or security
- During refactoring that changes architecture

## 🤝 Contributing

See our [Contributing Guidelines](../CONTRIBUTING.md) for information on:
- Documentation standards
- Pull request process
- Review requirements

## 📞 Getting Help

If you can't find what you need:
1. Search the documentation
2. Check the [Troubleshooting Guide](./development/troubleshooting.md)
3. Review closed GitHub issues
4. Contact the development team

---

*Documentation is a living resource. If something is unclear or missing, please contribute improvements!*