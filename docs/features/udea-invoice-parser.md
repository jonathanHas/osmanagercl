# Udea Invoice Parser

This document covers the Udea invoice PDF parser, a debugging and data extraction tool for UDEA B.V. supplier invoices.

## Overview

The Udea Invoice Parser extracts structured data from UDEA B.V. invoice PDFs, providing:
- Invoice header information (number, date, totals, VAT status)
- Product line items with classification by account code (Gb.rek)
- Barrel/deposit tracking
- Freight/cost extraction
- Validation that line totals reconcile with invoice total
- Problem line reporting for debugging

This tool is primarily used for debugging and understanding invoice structure, with potential for future integration into the invoice bulk upload system.

## Features

### Invoice Header Extraction
- **Invoice Number**: Extracted from "Invoice number:" or "Factuurnummer" patterns
- **Invoice Date**: Parsed from DD.MM.YYYY format, converted to ISO format
- **Total Incl VAT**: Extracted from "Total including vat" line (supports negative amounts for credit notes)
- **VAT Amount**: Extracted from "No VAT over X Y" pattern (Y is the VAT)
- **Zero VAT Confirmation**: Boolean flag confirming EU zero-rated status
- **Credit Note Detection** (NEW! 2026-02-12): Negative totals automatically detected and flagged as `is_credit_note: true`

### Product Line Extraction
- **Article Code**: Supplier's product code
- **Description**: Product name with content/unit info
- **Quantity**: Number of units ordered
- **Unit Price**: Purchase price per unit
- **Line Total**: Total cost for the line
- **Gb.rek Code**: Account code used for classification
- **Country of Origin**: 2-letter country code

### Line Classification by Gb.rek
Lines are automatically classified based on their account code:

| Gb.rek | Category | Classification |
|--------|----------|----------------|
| 30252 | Dairy/Eggs EU | product_for_resale |
| 30262 | Cheese EU | product_for_resale |
| 30282 | Meat/Fish EU | product_for_resale |
| 30292 | Refrigerated EU | product_for_resale |
| 30302 | AGF (Fruit & Vegetables) | product_for_resale |
| 30322 | DKW (Dry goods) | product_for_resale |
| 30342 | Drogmetica/Cosmetics | product_for_resale |
| 30362 | Non-food | product_for_resale |
| 30372 | Other sales | product_for_resale |
| 30862 | Transport | freight_or_service |
| 34120 | Barrels/Deposits | deposit_or_returnable_packaging |

### Credit Note Support (NEW! 2026-02-12)
The parser handles Udea credit notes (e.g., returned crates and barrels):
- **Negative Totals**: Regex matches `Total including vat EUR -3873,20` (previously failed on the minus sign)
- **Automatic Detection**: Negative total triggers `is_credit_note: true` in output
- **Zero VAT**: Credit notes are typically at 0% VAT (EU zero-rated returns)

### Barrels/Deposits Extraction
- Automatic detection of "Barrels delivered" section
- Extracts barrel codes, quantities, and values
- Calculates total barrel deposit amount

### Costs/Freight Extraction
- Detects freight charges from "Costs" section
- Extracts order numbers and freight totals
- Validates against "Total Costs" line

### Validation
The parser validates that extracted data reconciles:
- Sum of product line totals
- Plus barrel deposit total
- Plus freight/costs total
- Should equal invoice total (within €0.50 tolerance)

### Partial Line Extraction (NEW! 2026-01-30)
When a line cannot be fully parsed (e.g., corrupted Gb.rek), the parser extracts what it can:
- **Article Code**: Always extracted from the line start
- **Quantity**: Supports decimal quantities (e.g., `60.212` for weighted items)
- **Line Total**: Extracted from end of line
- **Description**: Best-effort substring
- **parse_status**: Set to `"partial"` (vs `"full"` for complete lines)
- **line_type**: Set to `"unknown"` (safe - prevents misclassification)
- **gbrek**: Set to `null`
- **raw_line_text**: Original text for debugging

This ensures all lines contribute to totals reconciliation even when Gb.rek is corrupted.

### "Total products" Validation (NEW! 2026-01-30)
The parser now extracts the "Total products" line from the invoice and validates:
- Compares parsed products total against PDF-stated total
- Reports `products_mismatch: true/false` in validation
- Includes `products_expected` and `products_difference` fields
- Uses €0.50 tolerance for rounding differences

### Problem Line Reporting
Lines that couldn't be parsed at all (missing both article code AND total) are reported with:
- Original line text (truncated to 150 chars)
- Reason for failure (e.g., "cannot extract article_code or line_total")

## Technical Details

### File Location
```
scripts/invoice-parser/parsers/invoice_udea.py
```

### Dependencies
- Python 3.x
- pdfplumber (for PDF text extraction)
- Virtual environment at `scripts/invoice-parser/venv/`

### PDF Text Corruption Handling
Udea PDFs often have text extraction issues where characters get merged or corrupted. The parser handles common patterns:

| Corruption Pattern | Example | Fixed To |
|-------------------|---------|----------|
| Bio-Dynamisch merged | `Bio-Dynamis3c0h302` | `Bio-Dynamisch 30302` |
| Niet-biologisch merged | `Niet-biologis3c0h342` | `Niet-biologisch 30342` |
| Units merged with text | `1kilogramOnions` | `1kilogram Onions` |
| Scrambled Gb.rek | `3e0252` | `30252` |

### Parsing Patterns
The parser uses multiple regex patterns with fallback:
1. **Full Pattern**: Matches complete well-formed lines
2. **Merged Pattern**: Handles Gb.rek merged with quality text
3. **Extended Pattern**: Handles lines with extra fields after country code
4. **Fallback Pattern**: Matches date at start, Gb.rek somewhere, total at end
5. **Last Resort**: Finds Gb.rek even in heavily corrupted text (supports decimal quantities)
6. **Partial Extraction**: When Gb.rek cannot be found, extracts article, quantity, description, and total

### Decimal Quantity Support (NEW! 2026-01-30)
The parser handles decimal quantities that appear in weighted product lines:
- Format: `60.212` where `6` is ordered quantity and `0.212` is delivered weight (kg)
- Parsed as `quantity: 60.212` for accurate totals
- Common in meat, cheese, and other variable-weight products

## Usage

### Web Interface
1. Navigate to `/invoices/bulk-upload/preview/{batchId}`
2. For any PDF file, click the **"Parse Udea"** button
3. View results in modal showing:
   - Invoice header information
   - Validation status
   - Product lines (first 20)
   - Barrels/deposits
   - Costs/freight
   - Warnings
   - Problem lines that couldn't be parsed

### Command Line
```bash
# Basic usage
/var/www/html/osmanagercl/scripts/invoice-parser/venv/bin/python \
  scripts/invoice-parser/parsers/invoice_udea.py \
  path/to/invoice.pdf

# With debug output
/var/www/html/osmanagercl/scripts/invoice-parser/venv/bin/python \
  scripts/invoice-parser/parsers/invoice_udea.py \
  path/to/invoice.pdf --verbose --debug

# Output to file
/var/www/html/osmanagercl/scripts/invoice-parser/venv/bin/python \
  scripts/invoice-parser/parsers/invoice_udea.py \
  path/to/invoice.pdf -o output.json
```

### JSON Output Structure
```json
{
  "success": true,
  "supplier": "Udea",
  "header": {
    "invoice_number": "1118761",
    "invoice_date": "2026-01-17",
    "total_excl_vat": 4516.55,
    "vat_amount": 0.0,
    "total_incl_vat": 4516.55,
    "is_zero_vat": true,
    "total_products_expected": 4082.96
  },
  "lines": [
    {
      "article_code": "12047",
      "description": "125gram Blueberry",
      "quantity": 1,
      "unit_price": 2.07,
      "line_total": 24.84,
      "line_type": "product_for_resale",
      "gbrek": "30302",
      "parse_status": "full",
      "content": "125gram",
      "country": "CL",
      "date": "15.01.26"
    },
    {
      "article_code": "5008210",
      "description": "750millilitre Toilet-cleaner orange & jasmine...",
      "quantity": 1,
      "unit_price": 0.0,
      "line_total": 13.74,
      "line_type": "unknown",
      "gbrek": null,
      "parse_status": "partial",
      "raw_line_text": "15.01.265008210 1 6 750millilitreToilet-cleaner...",
      "date": "15.01.26"
    }
  ],
  "barrels": {
    "items": [...],
    "total": 140.04
  },
  "costs": {
    "items": [...],
    "total": 293.55
  },
  "validation": {
    "is_valid": true,
    "products_total": 4082.96,
    "products_expected": 4082.96,
    "products_mismatch": false,
    "products_difference": 0.0,
    "barrels_total": 140.04,
    "costs_total": 293.55,
    "calculated_total": 4516.55,
    "invoice_total": 4516.55,
    "difference": 0.0,
    "tolerance_ok": true
  },
  "problem_lines": [],
  "errors": [],
  "warnings": [],
  "metadata": {
    "filename": "UdeaFactuur1118761.pdf",
    "parsing_method": "pdfplumber",
    "text_length": 30742,
    "stats": {
      "total_lines_scanned": 307,
      "product_lines_parsed": 249,
      "partial_lines_parsed": 13,
      "barrel_lines_parsed": 0,
      "cost_lines_parsed": 1,
      "skipped_lines": 0
    }
  }
}
```

## Controller Integration

### Route
```php
POST /invoices/bulk-upload/{batchId}/file/{fileId}/parse-udea
```

### Controller Method
Located in `app/Http/Controllers/InvoiceBulkUploadController.php`:
- `parseUdeaInvoice($batchId, $fileId)` - Executes the Python parser and returns JSON results

### Error Handling
- Returns raw output if parser doesn't produce valid JSON
- Includes command executed for debugging
- Logs all parsing attempts with file details

## Accuracy

The parser now achieves **100% line capture** on standard Udea invoices:
- All product lines contribute to totals (either as `full` or `partial` parse status)
- ~95% of lines are fully parsed with complete Gb.rek classification
- ~5% are partially parsed (article code, total, description) with `line_type: "unknown"`
- Validation confirms parsed totals match "Total products" from invoice
- Problem lines (those missing BOTH article code AND total) are extremely rare

### Parse Status Breakdown
| Status | Description | Typical % |
|--------|-------------|-----------|
| `full` | Complete extraction including Gb.rek | ~95% |
| `partial` | Extracted without Gb.rek (corrupted text) | ~5% |
| problem_line | Cannot extract article code or total | <1% |

## Related Documentation

- [Delivery System](./delivery-system.md) - Uses the delivery_udea.py parser for delivery PDFs
- [Invoice Bulk Upload System](./invoice-bulk-upload-system.md) - Where this parser is accessed
- [Invoice Parser Integration](./invoice-parser-integration.md) - General invoice parsing infrastructure

## Future Enhancements

Potential improvements for future versions:
1. **Automatic invoice creation**: Use parsed data to pre-populate invoice fields
2. **Line item storage**: Store extracted line items in database for detailed VAT analysis
3. **Pattern learning**: Improve corruption handling based on problem line analysis
4. **Multi-page merging**: Better handling of lines split across PDF pages
