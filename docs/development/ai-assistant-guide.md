# AI Assistant Guide

This guide provides important context and guidelines for AI assistants working with this codebase.

**Quick Navigation:**
- [Code Generation Guidelines](#code-generation-guidelines)
- [Specialized Agents](#specialized-agents)
- [Performance Optimization](#performance-optimization)
- [Documentation Guidelines](#documentation-guidelines)
- [Common Pitfalls to Avoid](#common-pitfalls-to-avoid)
- [Key Commands](#key-commands)
- [Where to Find Information](#where-to-find-information)
- [Quick Reference for Performance Work](#quick-reference-for-performance-work)

---

## Code Generation Guidelines

### Core Principles
- **Always check for existing code** before creating new files
- **Prefer editing existing files** over creating new ones
- **Follow Laravel conventions** and existing patterns in the codebase
- **Use appropriate service/repository layers** for business logic
- **Write tests** for new features

### Laravel Best Practices
- **Always Use Eloquent Models**: Check for existing models before accessing database tables directly
  - Use `exists:App\Models\ModelName,column` in validation rules instead of `exists:table_name,column`
  - Models handle database connections, table names, and configurations automatically
  - Example: Use `exists:App\Models\Supplier,SupplierID` not `exists:SUPPLIERS,SupplierID`
- **Follow Laravel Conventions**: Leverage Eloquent relationships and model configurations
- **Database Access**: Prefer model-based queries over raw database calls for consistency
- **Service Layer**: Extract complex business logic into service classes
- **Repository Pattern**: Use repositories for data access when appropriate
- **Testing**: Write tests for new features and bug fixes

For detailed coding standards, see [Contributing Guidelines](../../CONTRIBUTING.md).

---

## Specialized Agents

The following specialized agents are available for specific tasks:

### User Roles Agent
**File:** `.claude/agents/user_roles_agent.md`

Comprehensive specialist for user roles and permissions system. Use when working on:
- Authentication features
- Authorization logic
- Role-based access control
- Permission management

### Independent Delivery Agent
**File:** `.claude/agents/independent-delivery-implementer.md`

Specialist for Independent supplier delivery integration.

---

## Performance Optimization

### 🔥 PROACTIVELY SUGGEST WHEN:

AI assistants should **proactively** suggest performance improvements in these scenarios:

1. **User mentions slow queries or timeouts** → Immediately reference [Sales Data Import Plan](../features/sales-data-import-plan.md)
2. **Working on analytics/dashboards** → Suggest OptimizedSalesRepository pattern
3. **Cross-database queries identified** → Recommend pre-aggregation approach
4. **Any module shows performance issues** → Apply proven 100x+ optimization pattern
5. **Building new reporting features** → Start with optimized architecture from day one

**ALWAYS suggest performance improvements proactively - don't wait for user to ask!**

### Key Resources
- **[Sales Data Import Plan](../features/sales-data-import-plan.md)** - 100x+ performance improvements (MOST IMPORTANT!)
- **[Performance Optimization Guide](./performance-optimization-guide.md)** - Complete optimization strategies
- **[Architecture Overview](../architecture/overview.md)** - System design and optimization patterns

### Expected Results
- 100-1000x faster queries
- Sub-second page loads
- Instant user interactions
- Dramatic server resource reduction

---

## Documentation Guidelines

### When to Update Documentation
- **Update documentation** when changing functionality
- **Use the appropriate documentation file** based on the feature area
- **Follow documentation templates** in `docs/templates/`
- **Keep CLAUDE.md focused** - detailed information belongs in feature docs

### Documentation Structure
See [Documentation Usage Guide](../DOCUMENTATION_GUIDE.md) for complete guidance on:
- Where to find specific documentation
- How to update existing docs
- How to create new documentation
- Documentation templates and standards

---

## Common Pitfalls to Avoid

### ❌ Don't Do This:
- **Don't access database tables directly** - use Eloquent models
- **Don't put business logic in controllers** - use services
- **Don't create files unless absolutely necessary**
- **Don't ignore existing patterns** - maintain consistency
- **Don't forget to run tests after changes**

### ✅ Do This Instead:
- Use Eloquent models for all database access
- Extract business logic into service classes
- Edit existing files when possible
- Follow established patterns in the codebase
- Run tests frequently during development

### Frontend-Specific Pitfalls

#### Alpine.js and Blade Conflicts
- **ParseError with Alpine.js @error directive**: Alpine.js event handlers like `@error`, `@click`, etc. can conflict with Blade directives
  - **Solution**: Escape with double `@@` (e.g., `@@error` instead of `@error`) to prevent Blade compilation

- **Template literal conflicts**: Mixing JavaScript template literals (backticks) with Blade syntax causes parsing issues
  - **Solution**: Use string concatenation: `'{{ route('name') }}' + variable` instead of `` `{{ route('name') }}/${variable}` ``

- **Alpine.js Template Tag Errors**: Never use `x-show` on `<template>` tags - causes "can't access property 'after'" errors
  - **Solution**: Template tags are compile-time constructs. Use `<template x-for>` only, control visibility with regular HTML elements

#### View and Cache Issues
- **View cache issues**: If templates aren't updating after changes, run `php artisan view:clear` and `php artisan optimize:clear`

- **HTML Entity Rendering in Display Names**: Product display names with HTML entities may not render correctly
  - **Solution**: Use `{!! nl2br(html_entity_decode($variable)) !!}` instead of `{{ strip_tags(html_entity_decode($variable)) }}`

For comprehensive troubleshooting, see [Known Issues](./known-issues.md) and [Troubleshooting Guide](./troubleshooting.md).

---

## Key Commands

### Always Run After Changes
```bash
# Format code
./vendor/bin/pint

# Run tests
php artisan test

# Check routes
php artisan route:list

# Debug models
php artisan tinker
```

### Clear Caches if Issues Arise
```bash
php artisan optimize:clear

# Or individually:
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Development Workflow
For complete development commands, see [Quick Start Guide](./quick-start-guide.md).

---

## Where to Find Information

### 🔥 Most Important
- **Performance Optimization**: [Sales Data Import Plan](../features/sales-data-import-plan.md) (MOST IMPORTANT!)
- **Performance Guide**: [Performance Optimization Guide](./performance-optimization-guide.md)
- **Architecture Decisions**: [Architecture Overview](../architecture/overview.md) (includes optimization patterns)

### Documentation Locations
- **Feature Details**: `docs/features/`
- **Management Systems**: `docs/management/` (Receipts, future Inventory/Staff/Customer systems)
- **API Documentation**: `docs/api/`
- **Development Guides**: `docs/development/`
- **Planning Documents**: `planning/`

### Quick Access
- **Complete Feature List**: [Features Index](../FEATURES_INDEX.md)
- **Development Setup**: [Quick Start Guide](./quick-start-guide.md)
- **Troubleshooting**: [Known Issues](./known-issues.md) and [Troubleshooting Guide](./troubleshooting.md)
- **Contributing**: [Contributing Guidelines](../../CONTRIBUTING.md)

---

## Quick Reference for Performance Work

### Common Optimization Patterns

#### Analytics/Dashboards
Use `OptimizedSalesRepository` patterns from F&V implementation

#### Cross-Database Queries
See sales data import system examples

#### New Reporting Features
Start with pre-aggregated table design

#### Slow Module Optimization
Follow the proven 6-step process in [Performance Guide](./performance-optimization-guide.md)

#### Integration Templates
Copy from [Sales Data Import Plan](../features/sales-data-import-plan.md) integration examples

### When to Use Optimization Pattern

**Use this pattern when:**
- Any query taking >1 second
- Cross-database joins (POS + Laravel databases)
- Complex real-time aggregations
- N+1 query problems
- Analytics dashboards timing out

**Expected Results:**
- 100-1000x faster queries
- Sub-second page loads
- Instant user interactions
- Dramatic server resource reduction

---

## Additional Context

### Project Tech Stack
- **Framework**: Laravel 12 with PHP 8.2+
- **Frontend**: Blade templates with Tailwind CSS and Alpine.js
- **Database**: SQLite (default) with Eloquent ORM
- **Authentication**: Laravel Breeze with email verification
- **Authorization**: Role-based access control (RBAC) with permissions
- **Testing**: PHPUnit with Feature and Unit test suites

### Dual Database Architecture
The application uses two database connections:
- **Primary Database**: SQLite/MySQL for application data
- **POS Database**: uniCenta POS data (read-only connection)

See [POS Integration Documentation](../features/pos-integration.md) for details.

---

**Remember**: This guide provides context for AI assistants. For detailed information about any feature or system, refer to the specific documentation files rather than summarizing here.
