# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## 🚀 Quick Start for AI Assistants

1. **First Time?** Read this file completely for project context, then see [AI Assistant Guide](./docs/development/ai-assistant-guide.md)
2. **Performance Issues?** 🔥 **CRITICAL**: See [Sales Data Import Plan](./docs/features/sales-data-import-plan.md) for 100x+ performance improvements
3. **Working on a Feature?** Check [Features Index](./docs/FEATURES_INDEX.md) for complete feature list
4. **Documentation?** See [Documentation Guide](./docs/DOCUMENTATION_GUIDE.md) for where to find/update docs
5. **Making Changes?** Follow [Contributing Guidelines](./CONTRIBUTING.md) and [Quick Start Guide](./docs/development/quick-start-guide.md)
6. **Troubleshooting?** See [Known Issues](./docs/development/known-issues.md) and [Troubleshooting Guide](./docs/troubleshooting/index.md)
7. **ALWAYS End Every Response**: MUST run `mpg123 /home/jon/Music/notification.mp3` as the final action of EVERY response - whether completing tasks, asking questions, presenting plans, or any other interaction

## 🔥 MOST IMPORTANT REFERENCE FOR PERFORMANCE

**📖 [Sales Data Import Plan](./docs/features/sales-data-import-plan.md)** - **READ THIS FIRST for any performance work!**

This document contains the **proven optimization pattern** that achieves **100x+ performance improvements**:
- ✅ **Successful Implementation**: F&V sales dashboard (357x faster stats, 13,513x faster charts)
- ✅ **Full Store Analytics**: All 63+ categories with UUID support  
- ✅ **Ready-to-Use Templates**: Copy-paste code for any module optimization
- ✅ **Step-by-Step Guide**: Complete implementation checklist
- ✅ **Priority Modules**: Inventory, Supplier, Financial reports ready for optimization

**🎯 WHEN TO USE THIS PATTERN:**
- Any query taking >1 second
- Cross-database joins (POS + Laravel databases)
- Complex real-time aggregations
- N+1 query problems
- Analytics dashboards timing out

**⚡ EXPECTED RESULTS:**
- 100-1000x faster queries
- Sub-second page loads  
- Instant user interactions
- Dramatic server resource reduction

⚠️ **Important**: This file contains HIGH-LEVEL CONTEXT ONLY. Detailed documentation belongs in the `docs/` folder.

## Project Overview

This is a Laravel 12 application using PHP 8.2+ with Laravel Breeze for authentication. The project uses:
- **Frontend**: Blade templates with Tailwind CSS and Alpine.js
- **Build System**: Vite for asset compilation
- **Database**: SQLite (default) with Eloquent ORM
- **Authentication**: Laravel Breeze with email verification
- **Authorization**: Role-based access control (RBAC) with permissions
- **Testing**: PHPUnit with Feature and Unit test suites

## Documentation

📚 **IMPORTANT: See [Documentation Usage Guide](./docs/DOCUMENTATION_GUIDE.md) for how to use and update documentation properly.**

Comprehensive documentation is organized in the `docs/` folder:

- **[Documentation Index](./docs/README.md)** - Complete documentation overview
- **[Architecture](./docs/architecture/overview.md)** - System design and patterns
- **[Features](./docs/features/)** - Detailed feature documentation
- **[Development](./docs/development/)** - Setup and development guides
- **[API Reference](./docs/api/)** - API documentation

## Planning

- **[Planning Documents](./planning/README.md)** - Future features and improvements
- **[Contributing](./CONTRIBUTING.md)** - Development guidelines and standards
- **[Changelog](./CHANGELOG.md)** - Version history and releases

## Essential Development Commands

For complete development setup and commands, see **[Quick Start Guide](./docs/development/quick-start-guide.md)**.

### Quick Reference
```bash
# Start development (all services)
composer run dev

# Run tests
php artisan test

# Format code
./vendor/bin/pint

# Clear caches
php artisan optimize:clear
```

## Architecture Overview

For detailed architecture information, see **[Quick Start Guide](./docs/development/quick-start-guide.md#architecture-overview)**.

### Tech Stack
- **Framework**: Laravel 12 with PHP 8.2+
- **Frontend**: Blade templates, Tailwind CSS, Alpine.js, Vite
- **Database**: SQLite (default) with Eloquent ORM
- **Authentication**: Laravel Breeze with email verification
- **Authorization**: Role-based access control (RBAC)
- **Testing**: PHPUnit (Feature and Unit tests)

### Key Architectural Patterns
- **Dual Database Support**: Primary (Laravel) + POS (uniCenta) connections
- **Repository Pattern**: Clean data access layer (e.g., `ProductRepository`)
- **Service Layer**: Business logic in service classes
- **Performance Optimization**: Pre-aggregated data tables for 100x+ speed improvements

### Development Best Practices

**Core Principles:**
- **Always use Eloquent models** - Never access tables directly
- **Use model-based validation** - `exists:App\Models\ModelName,column` not `exists:table_name,column`
- **Service layer for business logic** - Keep controllers thin
- **Repository pattern for data access** - Consistent data queries
- **Write tests** - For all new features and bug fixes

For detailed guidelines, see:
- **[AI Assistant Guide](./docs/development/ai-assistant-guide.md)** - Complete development guidelines
- **[Contributing Guidelines](./CONTRIBUTING.md)** - Coding standards

## Database

- **Primary**: SQLite by default (`database/database.sqlite`)
- **POS Database**: uniCenta connection (read-only)
- See [POS Integration Documentation](./docs/features/pos-integration.md) for details
- See [Quick Start Guide](./docs/development/quick-start-guide.md#database-operations) for common operations

## Troubleshooting

- **[Known Issues](./docs/development/known-issues.md)** - Previously resolved issues and solutions
- **[Troubleshooting Guide](./docs/development/troubleshooting.md)** - Comprehensive debugging procedures
- **[Label Template Issues](./docs/troubleshooting/label-template-layout-issues.md)** - Grid 4x9 Custom layout diagnostics

## Features Overview

For a complete list of all features with detailed descriptions, see **[Features Index](./docs/FEATURES_INDEX.md)**.

### Feature Categories

**Product Management**
- Auto-Barcode Suggestion System
- Barcode Editing
- Categories Management
- Product CRUD with inline editing
- Label System with Scanner
- Pricing Management
- Coffee Module
- F&V Price Sync

**Supplier Management**
- Supplier Integration
- Delivery Verification
- Unified Supplier Management

**Financial Systems**
- Invoice Bulk Upload
- Invoice Document Viewing
- Invoice Payment Management
- Bank Reconciliation (AI-powered)
- VAT Returns & Dashboard
- OSAccounts Integration

**Analytics & Reporting**
- Sales Accounting Reports
- Bank Statement Analysis
- Cash Reconciliation

**AI & Automation**
- Multi-Provider AI Integration (Gemini, Mistral, OpenAI)
- Camera Invoice Capture
- AI Diagnostics & Settings

**POS Integration**
- Receipts Management
- Coffee KDS (Kitchen Display)
- User Roles & Permissions

See **[Features Index](./docs/FEATURES_INDEX.md)** for complete details on each feature.

## 🔔 NOTIFICATION REQUIREMENT
**CRITICAL**: You MUST run this command at the end of EVERY response:
```bash
mpg123 /home/jon/Music/notification.mp3
```
This alerts the user that you have finished your response. Do this without fail in ALL scenarios:
- ✅ After completing any task or coding work
- ✅ When presenting a plan and waiting for approval
- ✅ When asking questions or waiting for user input
- ✅ When providing explanations or analysis
- ✅ After ANY response - no exceptions

**ALWAYS play the notification sound as the last action of every single response.**

## Guidelines for AI Assistants

For comprehensive guidelines, see **[AI Assistant Guide](./docs/development/ai-assistant-guide.md)**.

### Quick Reference

**Code Generation:**
- Always use Eloquent models - never access tables directly
- Edit existing files instead of creating new ones
- Use service/repository layers for business logic
- Write tests for new features

**Performance Optimization:**
- **PROACTIVELY suggest** performance improvements
- Reference [Sales Data Import Plan](./docs/features/sales-data-import-plan.md) for 100x+ improvements
- Use OptimizedSalesRepository patterns for analytics/dashboards
- Pre-aggregate data for cross-database queries

**Specialized Agents:**
- **User Roles Agent** - For authentication/authorization work
- **Independent Delivery Agent** - For supplier delivery integration

**Common Pitfalls:**
- Don't access database tables directly
- Don't put business logic in controllers
- Don't ignore existing patterns
- Don't forget to run tests

**Key Commands:**
```bash
./vendor/bin/pint              # Format code
php artisan test               # Run tests
php artisan optimize:clear     # Clear caches
```

**Where to Find Information:**
- 🔥 **Performance**: [Sales Data Import Plan](./docs/features/sales-data-import-plan.md)
- **AI Integration**: [AI Integration](./docs/features/ai-integration.md) - Multi-provider AI config, camera capture, diagnostics
- **Features**: [Features Index](./docs/FEATURES_INDEX.md)
- **Troubleshooting**: [Known Issues](./docs/development/known-issues.md)
- **Development**: [Quick Start Guide](./docs/development/quick-start-guide.md)

For complete guidelines, see **[AI Assistant Guide](./docs/development/ai-assistant-guide.md)**.

