# Independent Delivery Implementer Agent

You are implementing the Independent supplier delivery upload system for OSManager CL, based on the existing Udea implementation patterns.

## Context & Background

The OSManager CL Laravel application already has a working delivery upload system for the Udea supplier. Your task is to implement similar functionality for the Independent supplier while adapting to their specific requirements.

## Independent CSV Format

The Independent supplier provides CSV files with the following structure:
```
Code,Product,Ordered,Qty,RSP,Price,Tax,Value
49036A,All About KombuchaRaspberry Can (Org)(DRS) 1x330ml,6,6,3.7,2.15,2.97,12.9
```

### Key Format Details:
- **Code**: Supplier product code (e.g., "49036A")
- **Product**: Full product name with attributes like (Org), (DRS), and packaging
- **Ordered/Qty**: Sometimes shown as "x/y" format where x=ordered, y=delivered
- **RSP**: Recommended Selling Price
- **Price**: Unit cost price
- **Tax**: Tax amount per unit
- **Value**: Total line value

## Implementation Roadmap

### Phase 1: Core Infrastructure
1. Add Independent supplier configuration to `config/suppliers.php`
2. Create CSV parser method in DeliveryService: `parseIndependentCsv()`
3. Add validation rules for Independent CSV format
4. Implement quantity parsing for "x/y" notation

### Phase 2: Data Processing
1. Map Independent product codes to POS products
2. Handle product attribute extraction from names
3. Calculate proper totals considering tax column
4. Create/update delivery items with correct values

### Phase 3: Web Integration
1. Create `IndependentScrapingService` for website integration
2. Implement authentication if required
3. Add barcode retrieval functionality
4. Configure image URL patterns

### Phase 4: UI Adaptations
1. Update delivery upload form to support Independent
2. Adapt scanning interface if needed
3. Add Independent-specific help text
4. Test complete workflow

## Key Files to Work With

### Existing Files to Reference:
- `app/Http/Controllers/DeliveryController.php` - Main controller
- `app/Services/DeliveryService.php` - Core business logic
- `app/Services/UdeaScrapingService.php` - Example scraping service
- `config/suppliers.php` - Supplier configurations
- `resources/views/deliveries/` - UI templates
- `database/migrations/*deliveries*.php` - Database schema

### New Files to Create:
- `app/Services/IndependentScrapingService.php` - Web scraping
- Tests for Independent-specific functionality

## Implementation Guidelines

### 1. Follow Existing Patterns
- Use the same database tables (deliveries, delivery_items)
- Maintain service layer separation
- Keep controller methods thin
- Use database transactions for imports

### 2. Adapt for Independent Requirements
- Custom CSV parsing logic for their format
- Handle "x/y" quantity notation properly
- Extract product attributes from names
- Different website scraping approach

### 3. Code Quality Standards
- Comprehensive error handling
- Input validation for all user data
- Logging for debugging
- Test coverage for new functionality

### 4. Security Considerations
- Validate file uploads thoroughly
- Sanitize all imported data
- Use prepared statements
- Rate limit web scraping

## Independent-Specific Considerations

### Product Name Parsing
Products include multiple attributes in names:
- (Org) = Organic
- (DRS) = Deposit Return Scheme
- Package sizes (e.g., "1x330ml", "6x250g")

### Quantity Handling
The "Ordered/Qty" notation needs special parsing:
- "6" = simple quantity
- "0/1" = ordered 0, received 1
- "1/2" = ordered 1, received 2

### Tax Calculations
Unlike Udea, tax is a separate column and needs to be:
- Added to price for total calculations
- Stored separately for reporting
- Handled in POS product updates

## Progress Tracking

As you implement, track:
- [ ] Supplier configuration added
- [ ] CSV parser implemented
- [ ] Validation rules created
- [ ] Database integration complete
- [ ] Web scraping service created
- [ ] UI adaptations done
- [ ] Testing complete
- [ ] Documentation updated

## Testing Checklist

- [ ] CSV upload with valid file
- [ ] Invalid CSV format handling
- [ ] Quantity notation parsing
- [ ] Product matching accuracy
- [ ] Tax calculation correctness
- [ ] Error recovery scenarios
- [ ] Performance with large files

## Common Commands

```bash
# Test CSV parsing
php artisan tinker
>>> $service = app(App\Services\DeliveryService::class);
>>> $service->parseIndependentCsv($filePath);

# Run specific tests
php artisan test --filter IndependentDeliveryTest

# Clear caches after config changes
php artisan config:clear
```

## Notes & Decisions

Document implementation decisions here as you progress:
- 
- 
- 

Remember to update this agent configuration as you learn more about Independent's specific requirements and make architectural decisions.