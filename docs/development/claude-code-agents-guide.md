# Claude Code Agents Guide for OSManager CL

This guide explains how to effectively use Claude Code's `/agents` feature to accelerate development of the OSManager CL Laravel application.

## What are Claude Code Agents?

Agents (or subagents) are specialized AI assistants within Claude Code that help with specific development tasks. Each agent:
- Has focused expertise in a particular area
- Maintains its own context window
- Can access specific tools as configured
- Works independently or in coordination with other agents

## Creating Agents for Laravel Development

### 1. Access the Agents Feature
```
/agents
```

### 2. Choose Agent Scope
- **Project Agents**: Saved in `.claude/agents/` for team sharing
- **User Agents**: Personal agents for your workflow

## Recommended Agents for OSManager CL

### 1. **Laravel Test Generator**

**Name**: `laravel-test-writer`

**Description**: "Laravel testing expert specializing in PHPUnit tests, feature tests, and test-driven development for Laravel applications."

**System Prompt**:
```
You are a Laravel testing specialist with expertise in PHPUnit, Laravel's testing helpers, and TDD best practices. Your responsibilities:

1. Generate comprehensive test suites for:
   - Services (especially OrderService, DeliveryService)
   - Controllers (focus on AJAX endpoints)
   - Models (including cross-database relationships)
   - Feature tests for user workflows

2. Follow Laravel testing conventions:
   - Use appropriate assertions (assertDatabaseHas, assertJson, etc.)
   - Mock external dependencies properly
   - Test edge cases and error conditions
   - Include authorization tests

3. Consider the dual database architecture:
   - Test POS database interactions
   - Verify cross-database relationships
   - Handle connection failures gracefully

Always check existing test patterns in the tests/ directory before creating new tests.
```

**Usage Example**:
```
@laravel-test-writer Generate comprehensive tests for the OrderService class, focusing on the calculateProductSuggestion method and case unit calculations
```

### 2. **Code Consistency Enforcer**

**Name**: `laravel-standards`

**Description**: "Enforces Laravel best practices, coding standards, and architectural patterns across the codebase."

**System Prompt**:
```
You are a Laravel code quality expert responsible for maintaining consistency across the OSManager CL codebase. Focus on:

1. Laravel Conventions:
   - Service layer patterns (check app/Services/)
   - Repository pattern usage (check app/Repositories/)
   - Proper Eloquent model usage (prefer models over direct DB access)
   - Controller responsibility (thin controllers, fat services)

2. Alpine.js Standards:
   - Consistent x-data structure
   - Proper event handling (@click vs x-on:click)
   - State management patterns

3. Security Patterns:
   - CSRF protection on all forms
   - Proper authorization checks
   - Input validation and sanitization
   - XSS prevention in Blade templates

4. Key Project Rules:
   - Always use Eloquent models (e.g., exists:App\Models\Supplier,SupplierID)
   - Follow modularization guidelines (see docs/features/modularization-phases.md)
   - Maintain dual database architecture patterns

Review code for violations and suggest improvements based on existing patterns.
```

**Usage Example**:
```
@laravel-standards Review the OrderController for consistency with our service layer patterns and security best practices
```

### 3. **Performance Optimizer**

**Name**: `laravel-performance`

**Description**: "Database query optimization and performance analysis expert for Laravel applications with complex data relationships."

**System Prompt**:
```
You are a Laravel performance optimization specialist with expertise in:

1. Database Query Optimization:
   - Identify N+1 query problems
   - Optimize eager loading strategies
   - Analyze cross-database query performance
   - Suggest appropriate indexes

2. Code Performance:
   - Optimize collection operations
   - Identify memory-intensive operations
   - Suggest caching strategies
   - Batch processing improvements

3. Frontend Performance:
   - Alpine.js reactivity optimization
   - Reduce unnecessary re-renders
   - Optimize AJAX calls
   - Asset loading strategies

Focus areas for OSManager CL:
- Order generation performance (279 queries per order)
- POS database query optimization
- Supplier product synchronization
- Large dataset handling in views

Always measure performance impact and provide benchmarks.
```

**Usage Example**:
```
@laravel-performance Analyze the order generation process in OrderService and suggest optimizations for reducing database queries
```

### 4. **Security Auditor**

**Name**: `laravel-security`

**Description**: "Security vulnerability detection and prevention specialist for Laravel applications."

**System Prompt**:
```
You are a Laravel security expert responsible for identifying and preventing vulnerabilities. Focus on:

1. Common Laravel Security Issues:
   - Mass assignment vulnerabilities
   - SQL injection (especially in cross-database queries)
   - XSS in Blade templates
   - CSRF token validation
   - Authentication/authorization flaws

2. AJAX Security:
   - Validate all 15+ AJAX endpoints in OrderController
   - Check request validation
   - Verify authorization middleware
   - Rate limiting for resource-intensive operations

3. Data Protection:
   - Sensitive data exposure
   - Proper encryption usage
   - Secure file uploads
   - API token management

4. OSManager-Specific Concerns:
   - POS database read-only enforcement
   - Supplier API credential protection
   - Barcode injection prevention
   - Order manipulation protection

Provide actionable fixes with code examples.
```

**Usage Example**:
```
@laravel-security Audit the order management AJAX endpoints for security vulnerabilities and authorization issues
```

### 5. **Documentation Maintainer**

**Name**: `laravel-docs`

**Description**: "Technical documentation expert for Laravel projects, specializing in API docs, architecture decisions, and developer guides."

**System Prompt**:
```
You are a technical documentation specialist for the OSManager CL project. Your responsibilities:

1. API Documentation:
   - Document all AJAX endpoints with request/response examples
   - Include authentication requirements
   - Error response documentation
   - Rate limiting information

2. Architecture Documentation:
   - Update docs/architecture/ with design decisions
   - Document service layer patterns
   - Explain dual database architecture
   - Component interaction diagrams

3. Developer Guides:
   - Step-by-step feature implementation
   - Troubleshooting guides
   - Best practices documentation
   - Code examples

4. Follow Documentation Standards:
   - Use templates in docs/templates/
   - Maintain README.md index
   - Update CHANGELOG.md
   - Keep CLAUDE.md high-level

Always check docs/DOCUMENTATION_GUIDE.md for standards.
```

**Usage Example**:
```
@laravel-docs Document the order management AJAX API endpoints with request/response examples
```

## Using Agents Effectively

### 1. **Chaining Agents**
Combine agents for comprehensive tasks:
```
# First, analyze performance
@laravel-performance Review OrderService for bottlenecks

# Then generate tests for the optimized code
@laravel-test-writer Create performance regression tests for OrderService
```

### 2. **Project-Specific Context**
Always provide context about your current work:
```
@laravel-standards I'm working on the modularization-phase1 branch. Review my changes to ensure they follow our component patterns
```

### 3. **Iterative Improvement**
Use agents throughout development:
- Before coding: Architecture and design review
- During coding: Standards enforcement
- After coding: Performance and security audit
- Before commit: Test generation and documentation

### 4. **Agent Collaboration**
Agents can work together:
```
# Security finds an issue
@laravel-security Found XSS vulnerability in order notes field

# Test writer creates a test
@laravel-test-writer Create a test that verifies XSS protection in order notes

# Docs updates the security guide
@laravel-docs Update security documentation with XSS prevention example
```

## Best Practices

### 1. **Start Simple**
Begin with Claude's suggested agents, then customize based on your needs.

### 2. **Maintain Agent Prompts**
Store agent configurations in `.claude/agents/` for team consistency.

### 3. **Focused Agents**
Create agents with single responsibilities rather than all-purpose agents.

### 4. **Regular Reviews**
Periodically review and update agent prompts based on project evolution.

### 5. **Context Preservation**
Use agents for complex tasks to preserve your main conversation context.

## Example Workflow for New Feature

1. **Planning Phase**
   ```
   @laravel-standards Review my feature plan for adding inventory management
   ```

2. **Implementation Phase**
   ```
   @laravel-performance Suggest optimal database schema for inventory tracking
   ```

3. **Testing Phase**
   ```
   @laravel-test-writer Generate tests for the new InventoryService
   ```

4. **Security Phase**
   ```
   @laravel-security Audit the inventory management endpoints
   ```

5. **Documentation Phase**
   ```
   @laravel-docs Create user and API documentation for inventory feature
   ```

## Tips for OSManager CL Development

1. **Leverage Existing Patterns**: Agents should understand your service/repository architecture
2. **Dual Database Awareness**: Ensure agents know about POS integration constraints
3. **Modularization Focus**: Agents should follow your component-based approach
4. **Performance Priority**: With 279 queries per order, optimization is crucial
5. **Security First**: Multiple AJAX endpoints require careful security review

## Saving and Sharing Agents

### Project-Level Agents
Save in `.claude/agents/[agent-name].md`:
```markdown
# Laravel Test Generator

Specialized agent for generating PHPUnit tests for Laravel applications...
[Full configuration]
```

### Version Control
Include `.claude/agents/` in your repository to share agents with your team.

## Real-World Example: Independent Delivery Implementer Agent

We've created a feature-specific agent for implementing the Independent supplier delivery system. This demonstrates how agents can be used for complex feature development:

### Agent: `independent-delivery-implementer`

Located at: `.claude/agents/independent-delivery-implementer.md`

**Purpose**: Implements delivery upload for Independent supplier based on existing Udea patterns

**Key Features**:
- Understands the existing Udea implementation
- Adapts patterns for Independent's CSV format
- Tracks progress across multiple sessions
- Maintains architectural consistency

**Usage Example**:
```bash
# Start implementation
@independent-delivery-implementer Create the CSV parser for Independent format

# Continue in another session
@independent-delivery-implementer What's the current implementation status?

# Get specific help
@independent-delivery-implementer How should I handle the "x/y" quantity notation?
```

This agent maintains complete context about:
- Independent's CSV format (Code, Product, Ordered/Qty, RSP, Price, Tax, Value)
- Special parsing needs (quantity notation, product attributes)
- Files to modify and patterns to follow
- Progress tracking checklist

## Conclusion

Claude Code agents provide powerful, specialized assistance for Laravel development. By creating focused agents for testing, security, performance, and documentation, you can maintain high code quality while accelerating development of the OSManager CL application.

Remember: Agents are tools to enhance your workflow, not replace your expertise. Use them to handle repetitive tasks and maintain consistency while you focus on architecture and business logic.