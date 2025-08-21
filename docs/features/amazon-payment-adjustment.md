# Amazon Invoice Payment Adjustment System

## Overview

Amazon invoices often show estimated EUR amounts that differ from actual bank charges due to exchange rate fluctuations. This system provides a Laravel-based workflow to handle these payment differences properly while maintaining accurate VAT reporting.

## How It Works

### 1. Parser Stage (Python)
The Amazon parser (`parsers/amazon.py`) extracts data as-is from the invoice:

```python
{
    "EUR_VAT_Found": True,
    "EUR_VAT_Amount": "4.19",      # Estimated VAT from invoice
    "EUR_Total_Found": 22.29,      # Total shown on invoice
    "VAT 23%": "18.22",           # Calculated net from VAT
    "Currency": "EUR",
    "Currency Note": "EUR amounts used - VAT: €4.19 at 23%"
}
```

### 2. Laravel Detection
When processing Amazon invoices, Laravel checks:
- `supplier_detected` = "Amazon"
- `parsed_data.EUR_VAT_Found` = true
- Marks invoice as needing payment adjustment

### 3. User Interface Workflow

#### Bulk Upload Preview Page
```php
@if($file->supplier_detected === 'Amazon' && $file->parsed_data['EUR_VAT_Found'])
    <div class="payment-adjustment-alert">
        <h4>Payment Adjustment Needed</h4>
        <p>EUR VAT detected: €{{ $file->parsed_data['EUR_VAT_Amount'] }}</p>
        <p>Invoice total: €{{ $file->parsed_data['EUR_Total_Found'] }}</p>
        
        <label>Actual amount paid (from bank statement):</label>
        <input type="number" step="0.01" name="actual_payment[{{ $file->id }}]" 
               placeholder="e.g., 22.65" />
    </div>
@endif
```

#### Processing Logic
```php
if ($actualPayment = $request->input("actual_payment.{$file->id}")) {
    $this->adjustAmazonPayment($file, $actualPayment);
}
```

### 4. Payment Adjustment Calculation

```php
class AmazonPaymentAdjustmentService
{
    public function adjustPayment(InvoiceUploadFile $file, float $actualPaid): array
    {
        $parsed = $file->parsed_data;
        $vatAmount = (float) $parsed['EUR_VAT_Amount'];
        $invoiceTotal = (float) $parsed['EUR_Total_Found'];
        
        // Calculate correct VAT breakdown
        $netAt23 = $vatAmount / 0.23;          // €18.22
        $paymentDifference = $actualPaid - ($netAt23 + $vatAmount);  // €0.24
        
        return [
            'vat_breakdown' => [
                'vat_0' => ['net' => max(0, $paymentDifference), 'vat' => 0.00],
                'vat_9' => ['net' => 0.00, 'vat' => 0.00],
                'vat_13_5' => ['net' => 0.00, 'vat' => 0.00],
                'vat_23' => ['net' => $netAt23, 'vat' => $vatAmount],
            ],
            'total_amount' => $actualPaid,
            'adjustment_note' => "Payment adjusted from €{$invoiceTotal} to €{$actualPaid} (€{$paymentDifference} exchange difference)"
        ];
    }
}
```

## Example Scenario

### Invoice Shows:
- Items Subtotal: EUR 18.10
- Estimated VAT: EUR 4.19
- Grand Total: EUR 22.29

### Bank Statement Shows:
- Actual Payment: EUR 22.65

### Result After Adjustment:
- VAT 23%: €18.22 (net from VAT calculation)
- VAT 0%: €0.24 (payment difference for exchange rate)
- Total: €22.65 ✅

## Implementation Checklist

### Database
- [ ] No schema changes needed (uses existing `parsed_data` field)
- [ ] Optional: Add `payment_adjusted` boolean flag
- [ ] Optional: Add `adjustment_notes` text field

### Laravel Services
- [ ] Create `AmazonPaymentAdjustmentService`
- [ ] Modify `InvoiceCreationService` to detect adjustment needs
- [ ] Update bulk upload controller

### User Interface
- [ ] Add payment input fields to bulk upload preview
- [ ] Show clear indicators for adjustment needed
- [ ] Display adjustment calculations
- [ ] Validation for payment amounts

### Parser Integration
- [x] Amazon parser extracts EUR VAT amounts
- [x] Parser returns structured data with EUR flags
- [x] Parser maintains separation of concerns

## Benefits

1. **Clean Architecture**: Parser extracts data, Laravel handles business logic
2. **User Control**: Manual input ensures accuracy
3. **Audit Trail**: Clear record of adjustments made
4. **Flexibility**: Can extend to other suppliers with similar issues
5. **VAT Compliance**: Proper allocation of exchange differences

## Testing

### Test Cases
1. Amazon invoice with 0% VAT (existing functionality)
2. Amazon invoice with EUR VAT, no adjustment needed
3. Amazon invoice with EUR VAT, payment adjustment required
4. Edge cases: negative differences, large differences

### Validation
- Payment amount must be positive
- Payment amount should be within reasonable range of invoice total
- VAT calculations must sum correctly