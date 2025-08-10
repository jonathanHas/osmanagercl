# OSManager CL Documentation

Welcome to the comprehensive documentation for OSManager CL. This documentation is organized to help you quickly find the information you need.

📚 **New to the docs?** Start with the [Documentation Usage Guide](./DOCUMENTATION_GUIDE.md) to understand how to navigate and contribute to documentation.

## 🆕 Latest Updates (January 2025)

### Product Health Dashboard (2025-01-06)
Auto-loading dashboard with critical product performance insights:

- **🚨 Good Sellers Gone Silent**: Identifies high performers with no recent sales
- **🐌 Slow Movers**: Products with lowest sales velocity over 60 days  
- **⚠️ Stagnant Stock**: Products with zero sales in last 30 days
- **📊 Inventory Alerts**: High-velocity products needing stock attention
- **📦 Real-time Stock Levels**: Current stock displayed for all dashboard products
- **🔗 Direct Product Links**: Click any product name to navigate to edit page
- **⚡ Auto-Loading**: Dashboard starts fetching data immediately on page load
- **🎨 Visual Indicators**: Color-coded cards by severity (red, orange, yellow, blue)

### Universal Categories Management System
Complete category management system that generalizes the Coffee Fresh module to work with ALL categories:

- **📂 Universal Interface**: Manage any category with the same powerful tools
- **📊 Sales Analytics**: Pre-aggregated data for instant performance metrics across all categories
- **👁️ Till Visibility Control**: Toggle products on/off POS per category
- **✏️ Inline Product Management**: Edit prices and display names without page refresh
- **🔍 Advanced Search & Filter**: Find products and categories quickly
- **📈 Performance Optimized**: Sub-second response times with optimized repository patterns

**Key Benefits**:
- Consistent management interface across all product types
- No need for separate modules per category
- Scalable to unlimited categories
- Maintains backward compatibility with existing modules

See [Categories Management](./features/categories-management.md) for complete details.

### Independent Health Foods Integration
Complete integration for Independent Health Foods with delivery system, product images, and website links:

- **🇮🇪 Irish VAT Support**: Automatic calculation and normalization of Irish VAT rates (0%, 9%, 13.5%, 23%)
- **🏷️ Auto Tax Categories**: Intelligent tax category selection for POS integration
- **📦 Case-to-Unit Conversion**: Smart pricing conversion from case to unit costs
- **📋 Enhanced CSV Processing**: Multi-format support with automatic format detection
- **🖼️ Product Images**: Automatic CDN image display with smart path detection
- **🔗 Website Integration**: Direct product search links to Independent's website
- **✨ Visual UX**: Green indicators, image previews, and click-to-view modals

**Key Benefits**:
- Eliminates manual tax category selection for Irish products
- Ensures accurate unit pricing from case-based supplier data  
- Visual product verification with automatic image loading
- Quick access to supplier website for product details
- Reduces data entry errors with intelligent form pre-population
- Streamlines Irish supplier delivery processing workflow

See [Delivery System](./features/delivery-system.md) and [Supplier Integration](./features/supplier-integration.md) for complete details.

## 📚 Documentation Structure

### 🏗️ Architecture & Design
Core system architecture and design patterns.

- **[Architecture Overview](./architecture/overview.md)** - System design, patterns, and principles
- **[Database Design](./architecture/database-design.md)** - Schema design and relationships
- **[API Design](./architecture/api-design.md)** - RESTful API principles and standards

### 🚀 Features
Detailed documentation for each major feature.

- **[Categories Management](./features/categories-management.md)** - 🆕 Universal category management system *(Enhanced 2025-01-06)*
  - Works with any product category in the POS system
  - Consistent interface for sales analytics and product management
  - **Product Health Dashboard** with auto-loading critical insights
  - Good Sellers Gone Silent, Slow Movers, Stagnant Stock alerts
  - Real-time stock levels and product links in dashboard
  - Till visibility control per category
  - Inline editing of prices and display names
  - Subcategory navigation support
  - **Enhanced Sales Analytics**: Fixed charts, sortable columns, expandable product details
  - **Interactive Charts**: Day of week tooltips, responsive design
  - Performance optimized with pre-aggregated data

- **[Sales Data Import](./features/sales-data-import.md)** - 🚀 Lightning-fast sales analytics system
  - 100x+ performance improvement over cross-database queries
  - Pre-aggregated daily and monthly sales data
  - Automated data synchronization with POS system
  - Sub-20ms response times for all analytics queries
  - Complete CLI suite for data management

- **[POS Integration](./features/pos-integration.md)** - uniCenta POS database integration
  - Product, Supplier, and Stock models
  - Real-time inventory synchronization
  - Read-only access patterns

- **[Delivery System](./features/delivery-system.md)** - Multi-format delivery verification *(Enhanced)*
  - Multi-format CSV support (Udea & Independent Irish Health Foods)
  - Automatic format detection and case-to-unit conversion
  - Irish VAT rate calculation and tax category auto-selection
  - Mobile-optimized barcode scanning
  - Discrepancy tracking and reporting
  - Stock update automation

- **[Pricing System](./features/pricing-system.md)** - Advanced pricing management
  - VAT-inclusive pricing with 4-decimal precision
  - Live supplier price comparison
  - Margin analysis and optimization
  - Quick pricing actions

- **[Supplier Integration](./features/supplier-integration.md)** - Multi-supplier connectivity *(Enhanced)*
  - Udea (Dutch): Full image CDN and price scraping integration
  - Independent Health Foods: Complete integration with images, website links, and VAT processing
  - Product image CDN integration with smart path detection
  - Live price scraping and barcode extraction
  - Extensible architecture for additional suppliers

- **[User Roles & Permissions](./features/user-roles-permissions.md)** - 🆕 Role-based access control system
  - Three-tier role system: Admin, Manager, Employee
  - 30+ granular permissions organized by modules
  - Middleware protection for routes
  - Flexible authorization in controllers and views
  - User management and role assignment

- **[Label System](./features/label-system.md)** - Comprehensive label printing system *(Updated)*
  - Dynamic barcode generation with Code128 format
  - Enhanced 4x9 grid layout with 3-row structure
  - Smart responsive text sizing (5 tiers)
  - Improved typography and € symbol handling
  - Template-based label layouts with A4 optimization
  - Event-driven re-queuing functionality
  - Real-time print queue management
  - Smart product filtering based on print history

- **[Product Management](./features/product-management.md)** - Product catalog operations *(Enhanced)*
  - Cross-database VegDetails integration with POS system
  - Real-time class, country, and unit data synchronization
  - Dual search system for availability management
  - CRUD operations with UUID support
  - Inline editing for product names, pricing, and tax categories
  - **Automatic tax category selection** for Irish VAT rates from delivery data
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

- **[Coffee Module](./features/coffee-module.md)** - Coffee Fresh product management *(New)*
  - Till visibility control via PRODUCTS_CAT
  - Inline price and display name editing
  - Optimized sales analytics with charts
  - Context-aware navigation
  - Alpine.js reactive UI components

- **[Coffee KDS System](./features/kds-coffee-system.md)** - Real-time Kitchen Display System *(New)*
  - 2-3 second order detection from POS
  - Direct database polling for optimal performance
  - Audio notifications for new orders
  - One-click order completion
  - Real-time system status monitoring
  - Mobile-responsive design for kitchen displays

### 🏢 Management Systems
Administrative and operational management tools.

- **[Receipts Management](./management/receipts.md)** - 🆕 Complete till review and transaction analysis *(New)*
  - POS transaction review with advanced filtering
  - Color-coded payment type analysis (Cash, Card, Free, Debt)
  - Interactive clickable summary cards for instant filtering
  - Real-time analytics with caching optimization
  - Export capabilities and audit trail
  - Alpine.js reactive interface with Tailwind CSS

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
- **[Delivery Endpoints](./api/delivery-endpoints.md)** - Multi-format delivery processing API *(New)*
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