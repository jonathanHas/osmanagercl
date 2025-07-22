# Architecture Overview

## Introduction

OSManager CL is built on Laravel 12, following modern web application architecture principles. The system is designed for scalability, maintainability, and seamless integration with external systems, particularly the uniCenta POS system.

## System Architecture

```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│                 │     │                 │     │                 │
│   Web Browser   │────▶│  Laravel App    │────▶│   Databases     │
│   (Frontend)    │     │   (Backend)     │     │                 │
│                 │     │                 │     │  ┌───────────┐  │
└─────────────────┘     │  ┌───────────┐  │     │  │  SQLite   │  │
                        │  │Controllers│  │     │  │ (Primary) │  │
┌─────────────────┐     │  └─────┬─────┘  │     │  └───────────┘  │
│                 │     │        │        │     │                 │
│  Mobile Device  │────▶│  ┌─────▼─────┐  │     │  ┌───────────┐  │
│  (Scanner)      │     │  │ Services  │  │────▶│  │  MySQL    │  │
│                 │     │  └─────┬─────┘  │     │  │   (POS)   │  │
└─────────────────┘     │        │        │     │  └───────────┘  │
                        │  ┌─────▼─────┐  │     │                 │
┌─────────────────┐     │  │   Models  │  │     └─────────────────┘
│                 │     │  └───────────┘  │
│ External APIs   │────▶│                 │     ┌─────────────────┐
│ (Suppliers)     │     │  ┌───────────┐  │     │                 │
│                 │     │  │   Jobs    │  │────▶│  Redis Queue    │
└─────────────────┘     │  └───────────┘  │     │                 │
                        │                 │     └─────────────────┘
                        └─────────────────┘
```

## Core Design Patterns

### 1. MVC Architecture
Laravel's Model-View-Controller pattern provides clear separation of concerns:
- **Models**: Data representation and business logic
- **Views**: Presentation layer (Blade templates)
- **Controllers**: Request handling and response coordination

### 2. Service Layer Pattern
Complex business logic is extracted into service classes:
```php
app/Services/
├── DeliveryService.php      # Delivery processing logic
├── SupplierService.php      # Supplier integration
├── UdeaScrapingService.php  # External data retrieval
└── PricingService.php       # Price calculations
```

### 3. Repository Pattern
Data access is abstracted through repositories:
```php
app/Repositories/
├── ProductRepository.php    # Product data access
├── SalesRepository.php      # Sales analytics
└── StockRepository.php      # Inventory queries
```

### 4. Dual Database Architecture
- **Primary Database** (SQLite/MySQL): Application data, users, settings
- **Secondary Database** (MySQL): Read-only POS integration

## Key Components

### Authentication & Authorization
- **Laravel Breeze**: Provides authentication scaffolding
- **Username/Email Login**: Flexible authentication
- **Role-Based Access**: Extensible permission system

### Frontend Architecture
- **Blade Templates**: Server-side rendering
- **Alpine.js**: Reactive UI components
- **Tailwind CSS**: Utility-first styling
- **Mobile-First**: Responsive design principles

### API Design
- **RESTful Endpoints**: Standard HTTP verbs and status codes
- **JSON Responses**: Consistent response format
- **Validation**: Request validation using Form Requests
- **Rate Limiting**: Built-in throttling

### Background Processing
- **Laravel Queues**: Asynchronous job processing
- **Job Types**:
  - Barcode retrieval from suppliers
  - Stock level synchronization
  - Report generation
  - Email notifications

### External Integrations

#### POS Integration
- Read-only access to uniCenta database
- Real-time stock level queries
- Product and supplier data synchronization

#### Supplier Integration
- Web scraping for price data
- CDN integration for product images
- Barcode extraction automation
- Extensible supplier configuration

## Data Flow

### Typical Request Lifecycle
1. **Request**: User initiates action (e.g., scan barcode)
2. **Routing**: Laravel routes to appropriate controller
3. **Validation**: Form request validates input
4. **Authorization**: Middleware checks permissions
5. **Processing**: Controller delegates to service
6. **Data Access**: Service uses repository/model
7. **Response**: JSON or view returned to user

### Delivery Processing Flow
```
CSV Upload → Parse & Import → Create Delivery Records
     ↓
Barcode Retrieval Jobs → Queue Processing → Update Items
     ↓
Scanning Interface → Real-time Updates → Progress Tracking
     ↓
Completion → Stock Updates → POS Synchronization
```

## Security Architecture

### Application Security
- **CSRF Protection**: All forms include CSRF tokens
- **XSS Prevention**: Blade auto-escaping
- **SQL Injection**: Eloquent parameterized queries
- **Authentication**: Secure session management

### Data Security
- **Encryption**: Sensitive data encrypted at rest
- **HTTPS**: Enforced in production
- **API Security**: Bearer token authentication
- **Input Validation**: Comprehensive validation rules

## Performance Optimization

### Caching Strategy
- **Route Caching**: Optimized route registration
- **Config Caching**: Compiled configuration
- **View Caching**: Compiled Blade templates
- **Data Caching**: Redis/file-based caching

### Database Optimization
- **Eager Loading**: N+1 query prevention
- **Indexes**: Strategic index placement
- **Query Optimization**: Efficient Eloquent queries
- **Connection Pooling**: Reused database connections

### Asset Optimization
- **Vite Bundling**: Optimized JavaScript/CSS
- **Lazy Loading**: Images loaded on demand
- **CDN Usage**: External assets from CDNs
- **Compression**: Gzip/Brotli compression

## Scalability Considerations

### Horizontal Scaling
- **Stateless Design**: Sessions in Redis/database
- **Queue Distribution**: Multiple queue workers
- **Load Balancing**: Application server distribution

### Vertical Scaling
- **Resource Monitoring**: Memory and CPU tracking
- **Database Optimization**: Query performance tuning
- **Caching Layers**: Reduced database load

## Development Principles

### SOLID Principles
- **Single Responsibility**: Focused classes and methods
- **Open/Closed**: Extensible without modification
- **Liskov Substitution**: Proper inheritance
- **Interface Segregation**: Specific interfaces
- **Dependency Inversion**: Abstraction over concretion

### Laravel Best Practices
- **Convention over Configuration**: Follow Laravel standards
- **DRY (Don't Repeat Yourself)**: Reusable components
- **Fat Models, Skinny Controllers**: Logic in appropriate layers
- **Testing**: Comprehensive test coverage

## Technology Stack

### Backend
- **PHP 8.2+**: Modern PHP features
- **Laravel 12**: Latest framework version
- **Eloquent ORM**: Database abstraction
- **Composer**: Dependency management

### Frontend
- **Blade Templates**: Server-side rendering
- **Alpine.js 3.x**: Reactive components
- **Tailwind CSS 3.x**: Utility-first CSS
- **Vite**: Build tool and dev server

### Infrastructure
- **Nginx/Apache**: Web server
- **MySQL/SQLite**: Databases
- **Redis**: Caching and queues
- **Supervisor**: Queue worker management

## Future Architecture Considerations

### Microservices Potential
- Extract supplier integration into separate service
- Dedicated inventory management service
- Independent pricing engine

### API Gateway
- Centralized API management
- Rate limiting and authentication
- Request routing and transformation

### Event-Driven Architecture
- Event sourcing for audit trails
- CQRS for read/write separation
- Message queue integration

## Related Documentation
- [Database Design](./database-design.md)
- [API Design](./api-design.md)
- [Deployment Architecture](../deployment/production-guide.md)