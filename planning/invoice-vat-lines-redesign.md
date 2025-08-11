# Plan: Redesign Invoice Structure for VAT Line Management

## Problem Analysis
The current invoice items system uses a product/inventory model (quantity × unit price) which feels unnatural for expense management. Since invoices already have expense categories at the header level, we need simple **VAT lines** rather than detailed item descriptions.

## Proposed Solution: Replace Invoice Items with VAT Lines

### 1. Database Changes
- **Drop** current `invoice_items` table 
- **Create** new `invoice_vat_lines` table with:
  - `vat_category` (Standard, Reduced, Zero, Second Reduced)
  - `net_amount` (amount before VAT)
  - `vat_rate` (actual rate at time of invoice)
  - `vat_amount` (calculated VAT)
  - `gross_amount` (net + VAT)
  - `line_number` (for ordering multiple VAT categories)

### 2. Model Changes
- Create `InvoiceVatLine` model to replace `InvoiceItem`
- Update `Invoice` model relationships
- Simplify VAT calculations (net_amount × VAT rate)
- Remove quantity/unit price concepts entirely

### 3. UI/UX Changes
- **Remove**: Quantity, Unit Price, and Description columns
- **Simplify**: Net Amount → VAT Category → Auto-calculate VAT & Gross
- **Rename**: "Invoice Items" → "VAT Lines" 
- **Focus**: Pure VAT breakdown without item descriptions
- **Streamline**: Add VAT line for different tax rates

### 4. Import Command Updates
- Update OSAccounts import to create VAT lines directly
- Map OSAccounts VAT categories to our VAT lines
- One line per VAT rate used in the invoice
- Preserve existing invoice totals and VAT breakdowns

### 5. Form Improvements
- **Single net amount field** per VAT category
- **VAT category dropdown** with automatic rate lookup
- **Real-time VAT calculation** as you type amounts
- **VAT-focused interface**: Net → VAT → Gross per line
- **Expense category stays at invoice level** (not per line)

### 6. Example Invoice Structure
```
Invoice #9550 - Office Supplies (expense category at header)
VAT Lines:
- Standard Rate (23%): €457.50 net + €105.23 VAT = €562.73 gross
- [Additional lines if multiple VAT rates used]

Total: €457.50 net + €105.23 VAT = €562.73 gross
```

## Benefits
- ✅ **Pure VAT focus** - no confusing product concepts
- ✅ **Cleaner interface** - just amounts and VAT rates
- ✅ **Accounting-accurate** - matches how VAT works
- ✅ **Faster entry** - fewer fields to complete
- ✅ **Better alignment** with tax reporting requirements
- ✅ **Simpler calculations** - direct amount × rate
- ✅ **Expense category at invoice level** where it belongs

## Migration Strategy
1. **Backup existing data** before changes
2. **Create VAT lines** from current invoice totals (group by VAT rate)
3. **Update all UI forms** to focus on VAT lines
4. **Test with imported OSAccounts data**
5. **Remove old item-based code**

This approach treats invoices as **VAT return entries** rather than product sales, which is much more appropriate for expense management.

## Implementation Checklist
- [ ] Create new invoice_vat_lines migration and drop invoice_items
- [ ] Create InvoiceVatLine model to replace InvoiceItem
- [ ] Update Invoice model relationships for VAT lines
- [ ] Update create/edit invoice forms for VAT line interface
- [ ] Update invoice show page to display VAT lines
- [ ] Update OSAccounts import command for VAT lines
- [ ] Test VAT line functionality with existing data

## Date Created
2025-08-10

## Status
Approved - Ready for implementation