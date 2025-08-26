# Invoice Editing System

**Status**: ✅ Implemented (2025-08-26)  
**Version**: 1.0  
**Dependencies**: Laravel Invoices System, InvoiceVatLine Model

## Overview

The Invoice Editing System provides comprehensive tools for modifying existing invoices, including VAT line amounts, categories, and supplier details. The system ensures data integrity through proper validation and automatic recalculation of totals.

## Key Features

### 1. Invoice Details Editing

#### Basic Invoice Information
- **Invoice Number**: Editable with uniqueness validation
- **Invoice Date**: Date picker with VAT rate recalculation on change
- **Supplier Selection**: Dropdown with auto-population of supplier name
- **Supplier Name**: Manual override capability for historical accuracy
- **Due Date**: Optional field with validation (must be after invoice date)
- **Expense Category**: Categorization for reporting purposes
- **Notes**: Free-text field for additional information

### 2. VAT Lines Management

#### Dynamic VAT Line Editing
- **Add/Remove VAT Lines**: Dynamic addition and removal of VAT lines
- **VAT Category Selection**: Dropdown with standard Irish VAT rates:
  - Standard Rate (23%)
  - Reduced Rate (13.5%)
  - Second Reduced Rate (9%)
  - Zero Rate (0%)
- **Net Amount Input**: Supports positive and negative values for credit notes
- **Automatic Calculations**: VAT and gross amounts calculated in real-time
- **Line Reordering**: Automatic line number management

#### VAT Calculations
- **Rate Application**: VAT rates automatically applied based on category
- **Real-time Updates**: JavaScript calculations update display immediately
- **Server-side Validation**: Backend validation ensures data integrity
- **Rounding**: Proper monetary rounding to 2 decimal places

### 3. Total Recalculation System

#### Automatic Total Updates
- **VAT Line Changes**: Invoice totals automatically recalculated when VAT lines change
- **Relationship Refresh**: Invoice relationships refreshed before calculation
- **Data Integrity**: Transaction-safe updates with rollback on failure
- **Audit Trail**: All changes tracked with user attribution

## Technical Implementation

### Frontend Components

#### Alpine.js Form Management
```javascript
// Core form data structure
invoiceForm() {
    return {
        vatLines: [],
        totals: {
            net: calculated_value,
            vat: calculated_value,
            gross: calculated_value
        },
        vatBreakdown: {
            // Per-category breakdown
        }
    }
}
```

#### Real-time Calculations
- **Net Amount Changes**: Trigger VAT and gross recalculation
- **Category Changes**: Update VAT rate and recalculate amounts  
- **Dynamic Totals**: Computed properties update summary automatically
- **VAT Breakdown**: Per-category VAT summary with rates

### Backend Processing

#### Controller Update Method
```php
public function update(Request $request, Invoice $invoice)
{
    // Validation with support for negative amounts
    $validated = $request->validate([
        'vat_lines.*.net_amount' => 'required|numeric', // No min:0 constraint
        // ... other validations
    ]);
    
    // Process VAT lines with proper rate setting
    foreach ($validated['vat_lines'] as $index => $lineData) {
        $line->update([
            'vat_category' => $lineData['vat_category'],
            'net_amount' => $lineData['net_amount'],
            'vat_rate' => InvoiceVatLine::getDefaultVatRate($lineData['vat_category']),
            // ... other fields
        ]);
    }
    
    // Refresh and recalculate totals
    $invoice->refresh();
    $invoice->load('vatLines');
    $invoice->calculateTotals();
}
```

#### Model Calculations
```php
// InvoiceVatLine model auto-calculations
protected static function boot()
{
    static::updating(function ($vatLine) {
        if ($vatLine->isDirty(['net_amount', 'vat_rate'])) {
            $vatLine->calculateAmounts();
        }
    });
}

public function calculateAmounts(): void
{
    $this->vat_amount = round($this->net_amount * $this->vat_rate, 2);
    $this->gross_amount = round($this->net_amount + $this->vat_amount, 2);
}
```

## User Interface Features

### Form Layout
- **Responsive Design**: Works on desktop and tablet devices
- **Split Layout**: Main form on left, summary sidebar on right
- **Visual Feedback**: Color-coded status indicators and error messages
- **Accessibility**: Proper form labels and keyboard navigation

### VAT Lines Table
- **Compact Display**: Efficient use of screen space
- **Inline Editing**: Direct editing within table rows
- **Hidden ID Fields**: Proper form submission for existing lines
- **Action Buttons**: Delete functionality with confirmation

### Summary Sidebar
- **Live Totals**: Real-time calculation display
- **VAT Breakdown**: Per-category VAT analysis
- **Action Buttons**: Save, cancel, and view options
- **Sticky Positioning**: Sidebar remains visible while scrolling

## Data Validation & Security

### Input Validation
- **Server-side Validation**: Complete validation in controller
- **Negative Values**: Support for credit notes and adjustments
- **Decimal Precision**: Proper handling of monetary values
- **Required Fields**: Appropriate field requirements

### Data Integrity
- **Transaction Safety**: All updates wrapped in database transactions
- **Relationship Integrity**: Proper foreign key constraints
- **Audit Trail**: User tracking for all modifications
- **Rollback Capability**: Error handling with transaction rollback

## Known Limitations

### Current Constraints
- **Single Currency**: System designed for Euro (€) only
- **VAT Categories**: Limited to Irish VAT system rates
- **Line Editing**: No bulk line operations (copy/paste multiple lines)

### Future Enhancements
- **Multi-currency Support**: International invoice handling
- **Advanced VAT**: Support for other EU countries' VAT systems
- **Bulk Operations**: Mass editing of multiple VAT lines
- **Import/Export**: CSV import/export for bulk modifications

## Error Handling

### Common Issues
- **Validation Errors**: Clear error messages for invalid input
- **Calculation Errors**: Fallback for JavaScript calculation failures
- **Network Issues**: Proper handling of connection problems
- **Permission Errors**: Role-based access control validation

### Troubleshooting
- **Cache Issues**: Clear view cache after template changes: `php artisan view:clear`
- **JavaScript Errors**: Check browser console for Alpine.js issues  
- **Total Mismatches**: Ensure VAT lines are properly refreshed before calculation
- **Negative Values**: Verify `min="0"` constraint removed from input fields

## Testing Considerations

### Test Scenarios
- **Basic Editing**: Modify net amounts and verify total updates
- **Category Changes**: Change VAT categories and verify rate application
- **Negative Values**: Test credit notes with negative amounts
- **Multiple Lines**: Add/remove multiple VAT lines
- **Validation**: Test invalid input handling
- **Concurrent Edits**: Test simultaneous editing by multiple users

### Data Integrity Tests  
- **Transaction Rollback**: Verify proper rollback on errors
- **Relationship Consistency**: Ensure VAT lines properly linked to invoices
- **Audit Trail**: Verify user attribution for all changes
- **Total Accuracy**: Verify invoice totals match sum of VAT lines

## Integration Points

### Related Systems
- **VAT Returns**: Edited invoices properly reflected in VAT calculations
- **Payment Management**: Payment status preserved during editing
- **Supplier Integration**: Supplier relationships maintained
- **Audit System**: All changes properly logged for compliance

This system provides a robust foundation for invoice editing while maintaining data integrity and providing excellent user experience through real-time feedback and validation.