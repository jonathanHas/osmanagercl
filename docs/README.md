# OSManager CL Documentation

Welcome to the comprehensive documentation for OSManager CL. This documentation is organized to help you quickly find the information you need.

**New to the docs?** Start with the [Documentation Usage Guide](./DOCUMENTATION_GUIDE.md) to understand how to navigate and contribute to documentation.

**Looking for updates?** See the [Release Notes](./RELEASE_NOTES.md) for the latest features and changes.

---

## Documentation Structure

### Architecture & Design
Core system architecture and design patterns.

- **[Architecture Overview](./architecture/overview.md)** - System design, patterns, and principles
- **[Database Design](./architecture/database-design.md)** - Schema design and relationships
- **[API Design](./architecture/api-design.md)** - RESTful API principles and standards

### Features
Detailed documentation for each major feature. See **[Features Index](./FEATURES_INDEX.md)** for the complete list.

**Product Management**
- [Categories Management](./features/categories-management.md) - Universal category management
- [Product Management](./features/product-management.md) - Product catalog operations
- [Label System](./features/label-system.md) - Label printing and queue management
- [Label Translation System](./features/label-translation-system.md) - AI-powered foreign label translation
- [Test Pages Registry](./test.md) - All test/debug pages with cleanup instructions
- [Pricing System](./features/pricing-system.md) - Advanced pricing with VAT

**Supplier & Delivery**
- [Delivery System](./features/delivery-system.md) - Multi-format delivery verification
- [Supplier Integration](./features/supplier-integration.md) - Multi-supplier connectivity
- [Supplier Management](./features/supplier-management.md) - Unified supplier management
- [Order Manager](./features/order-manager.md) - Stock monitoring for managed suppliers

**Financial Systems**
- [Bank Reconciliation System](./features/bank-reconciliation-system.md) - AI-powered reconciliation
- [Bank Statement Analysis](./features/bank-statement-analysis.md) - POS vs Bank reconciliation
- [VAT Returns Management](./features/vat-returns.md) - Irish Revenue VAT returns
- [VAT Dashboard](./features/vat-dashboard.md) - VAT return management
- [Invoice Bulk Upload](./features/invoice-bulk-upload-system.md) - Multi-file invoice upload
- [Cash Reconciliation](./features/cash-reconciliation.md) - End-of-day cash management
- Wages Management - Payroll import with P&L integration

**Voucher Management**
- [Voucher Management](./features/voucher-management.md) - Gift vouchers with barcodes, balances and till redemption

**Analytics & Reporting**
- [Sales Data Import](./features/sales-data-import.md) - Lightning-fast sales analytics
- [Sales Accounting Report](./features/sales-accounting-report.md) - VAT-compliant sales analysis

**Stock Management**
- [Stocking Scanner](./features/stocking.md) - Mobile store room scanner
- [Stock Check Review](./features/stock-check-review.md) - Category stock review and reconciliation
- [Destock Review](./features/destock-review.md) - Destock audit trail and restock suggestions

**AI & Automation**
- [AI Integration](./features/ai-integration.md) - Multi-provider AI for invoice parsing and label translation
- [Label Translation System](./features/label-translation-system.md) - AI-powered foreign label translation

**POS Integration**
- [POS Integration](./features/pos-integration.md) - uniCenta POS database integration
- [Coffee KDS System](./features/kds-coffee-system.md) - Real-time Kitchen Display
- [Kitchen Recipe Costing](./features/kitchen-recipe-costing.md) - Recipe costing with overheads

**User Management**
- [User Roles & Permissions](./features/user-roles-permissions.md) - Role-based access control
- [Receipts Management](./management/receipts.md) - Till review and transaction analysis

### Development
Guides for developers working on the project.

- **[Setup Guide](./development/setup.md)** - Complete development environment setup
- **[Quick Start Guide](./development/quick-start-guide.md)** - Get started quickly
- **[Testing Guide](./development/testing.md)** - Testing strategies and examples
- **[Coding Standards](./development/coding-standards.md)** - Code style and best practices
- **[Performance Optimization Guide](./development/performance-optimization-guide.md)** - Apply 100x+ performance improvements
- **[Troubleshooting](./troubleshooting/index.md)** - Common issues and solutions

### Deployment
Production deployment and operations.

- **[Production Guide](./deployment/production-guide.md)** - Step-by-step deployment
- **[Environment Configuration](./deployment/environment-config.md)** - Production settings
- **[Monitoring](./deployment/monitoring.md)** - Application monitoring and alerts

### API Reference
Complete API documentation.

- **[API Endpoints](./api/endpoints.md)** - All available endpoints
- **[Product Endpoints](./api/product-endpoints.md)** - Product management API
- **[Delivery Endpoints](./api/delivery-endpoints.md)** - Delivery processing API
- **[Authentication](./api/authentication.md)** - API authentication methods

### Templates
Documentation templates for consistency.

- **[Feature Template](./templates/feature-template.md)** - For documenting new features
- **[API Endpoint Template](./templates/api-endpoint-template.md)** - For API documentation

---

## Quick Links

### For New Developers
1. Start with [Architecture Overview](./architecture/overview.md)
2. Follow the [Setup Guide](./development/setup.md)
3. Review [Coding Standards](./development/coding-standards.md)
4. Read about key features you'll work on

### For System Administrators
1. Review [Production Guide](./deployment/production-guide.md)
2. Configure using [Environment Config](./deployment/environment-config.md)
3. Set up [Monitoring](./deployment/monitoring.md)
4. Keep [Troubleshooting](./troubleshooting/index.md) handy

### For API Consumers
1. Start with [API Design](./architecture/api-design.md)
2. Set up [Authentication](./api/authentication.md)
3. Explore [API Endpoints](./api/endpoints.md)

---

## Documentation Standards

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

---

## Contributing

See our [Contributing Guidelines](../CONTRIBUTING.md) for information on:
- Documentation standards
- Pull request process
- Review requirements

## Getting Help

If you can't find what you need:
1. Search the documentation
2. Check the [Troubleshooting Guide](./troubleshooting/index.md)
3. Review closed GitHub issues
4. Contact the development team

---

*Documentation is a living resource. If something is unclear or missing, please contribute improvements!*
