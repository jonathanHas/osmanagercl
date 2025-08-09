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
- [x] Supplier configuration added
- [x] CSV parser implemented
- [x] Validation rules created
- [x] Database integration complete
- [x] Web scraping service created
- [x] UI adaptations done
- [x] Testing complete
- [x] Documentation updated

## Recent Enhancements (2025-08-08)

### Independent Delivery Review Improvements
The Independent delivery review interface has been enhanced with user experience improvements:

#### Price Editor Modal Enhancements
- **Gross Price Default**: Modal now defaults to gross price input mode instead of net
- **RSP Pre-filling**: Automatically fills Recommended Selling Price from Independent supplier data
- **Faster Workflow**: Eliminates manual mode switching and price entry steps

#### Product Navigation
- **Clickable Product Names**: Product names now link to product detail pages
- **Smart Links**: Only displays links for products matched in the POS system
- **Context Preservation**: Maintains delivery workflow while allowing detailed product review

#### Quick Cost Updates
- **Visual Indicators**: Arrow buttons appear next to current costs when delivery costs differ
- **One-Click Updates**: Direct cost synchronization from delivery cost to product cost
- **Smart Conditions**: Only shows for non-completed deliveries with significant differences (>€0.01)
- **Confirmation Dialogs**: Clear confirmations showing exact cost changes
- **Real-time Feedback**: Success messages and automatic page refresh

#### Technical Implementation
```javascript
// Enhanced price editor with RSP support
function openPriceEditor(itemId, productCode, description, currentNetPrice, deliveryCost, taxRate, rspPrice) {
    document.getElementById('grossPriceInput').value = rspPrice > 0 ? rspPrice.toFixed(2) : '';
    document.getElementById('priceInputMode').value = 'gross';
    togglePriceMode();
}

// Quick cost update functionality
async function updateProductCost(productId, newCost, productName) {
    const response = await fetch(`/products/${productId}/cost`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ cost_price: newCost })
    });
}
```

#### Backend Enhancements
- **ProductController@updateCost**: Enhanced to handle both AJAX JSON and form requests
- **Dual Response Support**: Returns JSON for AJAX, redirects for forms
- **Validation**: Comprehensive error handling and validation

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