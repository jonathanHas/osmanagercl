# Contributing to OSManager CL

Thank you for your interest in contributing to OSManager CL! This document provides guidelines and standards to ensure consistency across the codebase.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Workflow](#development-workflow)
- [Coding Standards](#coding-standards)
- [Testing Requirements](#testing-requirements)
- [Documentation Standards](#documentation-standards)
- [Pull Request Process](#pull-request-process)
- [Commit Message Format](#commit-message-format)

## Code of Conduct

We are committed to providing a welcoming and inclusive environment. Please be respectful and constructive in all interactions.

## Getting Started

1. Fork the repository
2. Clone your fork locally
3. Set up the development environment following the [setup guide](./docs/development/setup.md)
4. Create a new branch for your feature/fix
5. Make your changes
6. Submit a pull request

## Development Workflow

### Branch Naming

Use descriptive branch names following this pattern:
- `feature/description` - New features
- `fix/description` - Bug fixes
- `docs/description` - Documentation updates
- `refactor/description` - Code refactoring
- `test/description` - Test additions/updates

Examples:
- `feature/supplier-price-alerts`
- `fix/delivery-scan-null-date`
- `docs/api-endpoints`

### Development Process

1. **Always** create a new branch from `master`
2. **Always** write tests for new functionality
3. **Always** update documentation for API changes
4. **Never** commit directly to `master`
5. **Never** commit sensitive data or credentials

## Coding Standards

### PHP Standards

We follow [PSR-12](https://www.php-fig.org/psr/psr-12/) coding standards with Laravel conventions:

```php
<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;

class ProductService
{
    /**
     * Get active products with stock
     */
    public function getActiveProductsWithStock(): Collection
    {
        return Product::active()
            ->inStock()
            ->orderBy('name')
            ->get();
    }
}
```

#### Key Points:
- Use type declarations for parameters and return types
- Document methods with clear PHPDoc blocks
- Follow Laravel naming conventions (camelCase methods, snake_case database)
- Use dependency injection over facades where practical
- Prefer Eloquent relationships over manual joins

### Laravel Best Practices

1. **Models**
   - Always use Eloquent models instead of direct DB queries
   - Define relationships, casts, and fillable properties
   - Use model events sparingly and document them clearly

2. **Controllers**
   - Keep controllers thin - business logic belongs in services
   - Use form requests for validation
   - Return consistent response formats

3. **Services**
   - Create service classes for complex business logic
   - Keep services focused on a single responsibility
   - Use dependency injection for testability

4. **Database**
   - Always create migrations for schema changes
   - Never modify existing migrations - create new ones
   - Use descriptive column names

### JavaScript Standards

For Alpine.js components:

```javascript
function deliveryScanner(deliveryId) {
    return {
        deliveryId: deliveryId,
        items: [],
        
        init() {
            this.loadItems();
        },
        
        async loadItems() {
            try {
                const response = await fetch(`/api/deliveries/${this.deliveryId}/items`);
                this.items = await response.json();
            } catch (error) {
                console.error('Failed to load items:', error);
            }
        }
    };
}
```

### CSS/Tailwind Standards

- Use Tailwind utility classes over custom CSS
- Extract repetitive patterns into Blade components
- Follow mobile-first responsive design
- Maintain consistent spacing and color usage

## Testing Requirements

### Test Coverage

All new features must include tests:
- Unit tests for services and models
- Feature tests for API endpoints
- Browser tests for critical user flows (optional)

### Writing Tests

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Delivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function authenticated_user_can_view_delivery(): void
    {
        $user = User::factory()->create();
        $delivery = Delivery::factory()->create();
        
        $response = $this->actingAs($user)
            ->get(route('deliveries.show', $delivery));
            
        $response->assertOk()
            ->assertViewIs('deliveries.show')
            ->assertViewHas('delivery', $delivery);
    }
}
```

### Running Tests

```bash
# Run all tests
composer test

# Run specific test suite
php artisan test --testsuite=Feature

# Run with coverage
php artisan test --coverage
```

## Documentation Standards

### Code Documentation

- Document all public methods with PHPDoc
- Include parameter types and descriptions
- Document complex algorithms or business logic
- Add inline comments for non-obvious code

### Feature Documentation

When adding new features, create/update documentation in `docs/features/`:

```markdown
# Feature Name

## Overview
Brief description of what the feature does and why it exists.

## Architecture
- Key components and their responsibilities
- Database schema changes
- Service dependencies

## Usage
### User Perspective
How users interact with the feature.

### Developer Perspective
Code examples and integration points.

## Configuration
Any configuration options or environment variables.

## Testing
How to test the feature, including example test cases.
```

### API Documentation

Document all API endpoints in `docs/api/endpoints.md`:

```markdown
## POST /api/deliveries/{delivery}/scan

Scan a product barcode for delivery verification.

### Request
```json
{
    "barcode": "8711521021925",
    "quantity": 1
}
```

### Response (200 OK)
```json
{
    "success": true,
    "item": {
        "id": 123,
        "description": "Product Name",
        "received_quantity": 5,
        "ordered_quantity": 10
    },
    "message": "Scanned: Product Name (5/10)"
}
```
```

## Pull Request Process

1. **Before Creating PR**
   - Ensure all tests pass
   - Run code formatter: `./vendor/bin/pint`
   - Update relevant documentation
   - Self-review your changes

2. **PR Description Template**
   ```markdown
   ## Description
   Brief description of changes
   
   ## Type of Change
   - [ ] Bug fix
   - [ ] New feature
   - [ ] Breaking change
   - [ ] Documentation update
   
   ## Testing
   - [ ] Tests pass locally
   - [ ] New tests added
   - [ ] Manual testing completed
   
   ## Checklist
   - [ ] Code follows style guidelines
   - [ ] Self-review completed
   - [ ] Documentation updated
   - [ ] No sensitive data exposed
   ```

3. **Review Process**
   - At least one approval required
   - Address all feedback constructively
   - Keep PR focused and reasonable in size
   - Squash commits before merging

## Commit Message Format

Follow the [Conventional Commits](https://www.conventionalcommits.org/) specification:

```
type(scope): subject

body (optional)

footer (optional)
```

### Types
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code style changes (formatting, etc.)
- `refactor`: Code refactoring
- `test`: Test additions or corrections
- `chore`: Maintenance tasks

### Examples
```
feat(delivery): add barcode scanning for mobile devices

fix(products): handle null supplier in price calculation

docs(api): add delivery endpoints documentation

refactor(services): extract supplier logic to dedicated service
```

## Questions or Issues?

If you have questions or run into issues:
1. Check existing documentation
2. Search closed issues/PRs
3. Ask in the development chat
4. Create a new issue with details

Thank you for contributing to OSManager CL!