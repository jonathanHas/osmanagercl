# Invoice Parser Integration Guide (Phase 2)

## Overview

This document outlines the integration plan for connecting the Python invoice parser with the Laravel bulk upload system. The parser will automatically extract data from uploaded invoice files, supporting both text-based PDFs and scanned documents requiring OCR.

## Architecture

```
Laravel App → Queue Job → Python Parser → JSON Response → Database
     ↓            ↓            ↓              ↓            ↓
 Upload UI    Redis Queue   Extract Data   Validate    Store Result
```

## Python Parser Requirements

### Dependencies

```bash
# Core parsing libraries
pip install pdfplumber      # PDF text extraction
pip install pytesseract     # OCR for scanned documents
pip install pdf2image       # Convert PDF to images for OCR
pip install invoice2data    # Template-based extraction
pip install pandas          # Data processing
pip install python-dateutil # Date parsing

# Document processing
pip install python-docx     # Word document processing
pip install xlrd            # Excel file processing (.xls)
pip install openpyxl        # Excel file processing (.xlsx)

# Image processing
pip install Pillow          # Image manipulation
pip install opencv-python   # Advanced image processing

# System dependencies
apt-get install tesseract-ocr
apt-get install poppler-utils  # For pdf2image
apt-get install libreoffice   # For .doc to .docx conversion
```

### Expected Parser Interface

The Python parser should accept command-line arguments:

```bash
# Supported file types
python3 /path/to/invoice_parser.py --file /path/to/invoice.pdf --output json
python3 /path/to/invoice_parser.py --file /path/to/invoice.jpg --output json
python3 /path/to/invoice_parser.py --file /path/to/invoice.doc --output json
python3 /path/to/invoice_parser.py --file /path/to/invoice.docx --output json
python3 /path/to/invoice_parser.py --file /path/to/invoice.xls --output json
python3 /path/to/invoice_parser.py --file /path/to/invoice.xlsx --output json
```

### Expected JSON Output Format

```json
{
    "success": true,
    "confidence": 0.85,
    "data": {
        "invoice_number": "INV-2024-001234",
        "invoice_date": "2024-03-15",
        "due_date": "2024-04-15",
        "supplier": {
            "name": "Supplier Company Ltd",
            "vat_number": "IE1234567X",
            "address": "123 Main St, Dublin",
            "email": "accounts@supplier.com"
        },
        "amounts": {
            "subtotal": 1000.00,
            "vat_amount": 230.00,
            "total": 1230.00
        },
        "vat_lines": [
            {
                "description": "Goods at standard rate",
                "net_amount": 1000.00,
                "vat_rate": 0.23,
                "vat_amount": 230.00,
                "gross_amount": 1230.00
            }
        ],
        "line_items": [
            {
                "description": "Product A",
                "quantity": 10,
                "unit_price": 100.00,
                "total": 1000.00,
                "vat_rate": 0.23
            }
        ],
        "metadata": {
            "parsing_method": "template",
            "template_used": "supplier_standard",
            "ocr_used": false,
            "processing_time": 1.23
        }
    },
    "errors": []
}
```

### Error Response Format

```json
{
    "success": false,
    "confidence": 0.0,
    "data": null,
    "errors": [
        {
            "code": "PARSE_ERROR",
            "message": "Could not extract invoice number",
            "field": "invoice_number"
        }
    ]
}
```

## Laravel Integration

### 1. Create Parsing Service

```php
<?php

namespace App\Services;

use App\Models\InvoiceUploadFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;

class InvoiceParsingService
{
    protected string $parserPath;
    protected string $pythonPath;
    
    public function __construct()
    {
        $this->parserPath = config('invoices.parsing.python_parser_path');
        $this->pythonPath = config('invoices.parsing.python_executable');
    }
    
    public function parseFile(InvoiceUploadFile $file): array
    {
        $filePath = $file->temp_file_path;
        
        // Execute Python parser
        $result = Process::timeout(60)->run([
            $this->pythonPath,
            $this->parserPath,
            '--file', $filePath,
            '--output', 'json'
        ]);
        
        if (!$result->successful()) {
            Log::error('Parser failed', [
                'file_id' => $file->id,
                'error' => $result->errorOutput()
            ]);
            
            throw new \Exception('Parser execution failed: ' . $result->errorOutput());
        }
        
        $output = json_decode($result->output(), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON from parser: ' . json_last_error_msg());
        }
        
        return $output;
    }
    
    public function processParserOutput(InvoiceUploadFile $file, array $output): void
    {
        if ($output['success']) {
            $file->markAsParsed($output['data'], $output['confidence']);
        } else {
            $file->parsing_errors = $output['errors'];
            $file->status = 'failed';
            $file->error_message = 'Parsing failed: ' . ($output['errors'][0]['message'] ?? 'Unknown error');
            $file->save();
        }
    }
}
```

### 2. Create Queue Job

```php
<?php

namespace App\Jobs;

use App\Models\InvoiceUploadFile;
use App\Services\InvoiceParsingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ParseInvoiceFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public function __construct(
        protected InvoiceUploadFile $file
    ) {}
    
    public function handle(InvoiceParsingService $parser): void
    {
        try {
            $this->file->markAsParsing();
            
            $result = $parser->parseFile($this->file);
            $parser->processParserOutput($this->file, $result);
            
            // Update batch statistics
            $this->file->bulkUpload->updateStatistics();
            
        } catch (\Exception $e) {
            $this->file->markAsFailed($e->getMessage());
            $this->file->bulkUpload->updateStatistics();
            
            throw $e; // Re-throw for retry logic
        }
    }
    
    public function failed(\Throwable $exception): void
    {
        $this->file->markAsFailed('Job failed: ' . $exception->getMessage());
    }
}
```

### 3. Controller Method for Starting Processing

```php
public function startProcessing($batchId)
{
    $batch = InvoiceBulkUpload::where('batch_id', $batchId)
        ->where('user_id', auth()->id())
        ->firstOrFail();
    
    if ($batch->status !== 'uploaded') {
        return response()->json([
            'success' => false,
            'error' => 'Batch is not ready for processing'
        ], 400);
    }
    
    $batch->markAsStarted();
    
    // Queue parsing jobs for each file
    foreach ($batch->files as $file) {
        if ($file->status === 'uploaded') {
            ParseInvoiceFile::dispatch($file);
        }
    }
    
    return response()->json([
        'success' => true,
        'message' => 'Processing started for ' . $batch->files->count() . ' files'
    ]);
}
```

## Python Parser Template

```python
#!/usr/bin/env python3
"""
Invoice Parser for Laravel Integration
Extracts structured data from PDF invoices
"""

import argparse
import json
import sys
from datetime import datetime
from typing import Dict, Any, Optional
import re

import pdfplumber
import pytesseract
from PIL import Image
from pdf2image import convert_from_path

class InvoiceParser:
    def __init__(self, file_path: str):
        self.file_path = file_path
        self.confidence = 1.0
        self.used_ocr = False
        
    def parse(self) -> Dict[str, Any]:
        """Main parsing method"""
        try:
            # Try text extraction first
            data = self.extract_from_text()
            
            if not self.is_valid_data(data):
                # Fall back to OCR
                data = self.extract_with_ocr()
                self.used_ocr = True
                self.confidence = 0.7
                
            return self.format_response(True, data)
            
        except Exception as e:
            return self.format_response(False, None, [
                {'code': 'PARSE_ERROR', 'message': str(e)}
            ])
    
    def extract_from_text(self) -> Dict[str, Any]:
        """Extract data from text-based PDF"""
        with pdfplumber.open(self.file_path) as pdf:
            text = ""
            for page in pdf.pages:
                text += page.extract_text() or ""
                
        return self.parse_text(text)
    
    def extract_with_ocr(self) -> Dict[str, Any]:
        """Extract data using OCR for scanned documents"""
        # Convert PDF to images
        images = convert_from_path(self.file_path)
        
        text = ""
        for image in images:
            # Perform OCR
            text += pytesseract.image_to_string(image)
            
        return self.parse_text(text)
    
    def parse_text(self, text: str) -> Dict[str, Any]:
        """Parse extracted text into structured data"""
        data = {
            'invoice_number': self.find_invoice_number(text),
            'invoice_date': self.find_date(text, 'invoice'),
            'due_date': self.find_date(text, 'due'),
            'supplier': self.find_supplier_info(text),
            'amounts': self.find_amounts(text),
            'vat_lines': self.find_vat_lines(text),
            'line_items': self.find_line_items(text)
        }
        
        return data
    
    def find_invoice_number(self, text: str) -> Optional[str]:
        """Extract invoice number using patterns"""
        patterns = [
            r'Invoice\s*#?\s*:?\s*([A-Z0-9\-]+)',
            r'Invoice Number\s*:?\s*([A-Z0-9\-]+)',
            r'INV\s*-?\s*([0-9]+)',
        ]
        
        for pattern in patterns:
            match = re.search(pattern, text, re.IGNORECASE)
            if match:
                return match.group(1)
        
        return None
    
    def find_date(self, text: str, date_type: str) -> Optional[str]:
        """Extract dates from text"""
        # Implementation depends on expected date formats
        # This is a simplified example
        if date_type == 'invoice':
            pattern = r'Invoice Date\s*:?\s*(\d{1,2}[-/]\d{1,2}[-/]\d{2,4})'
        else:
            pattern = r'Due Date\s*:?\s*(\d{1,2}[-/]\d{1,2}[-/]\d{2,4})'
            
        match = re.search(pattern, text, re.IGNORECASE)
        if match:
            # Parse and standardize date format
            return self.standardize_date(match.group(1))
        
        return None
    
    def find_supplier_info(self, text: str) -> Dict[str, Optional[str]]:
        """Extract supplier information"""
        return {
            'name': self.find_supplier_name(text),
            'vat_number': self.find_vat_number(text),
            'address': None,  # Complex extraction
            'email': self.find_email(text)
        }
    
    def find_amounts(self, text: str) -> Dict[str, float]:
        """Extract monetary amounts"""
        amounts = {
            'subtotal': 0.0,
            'vat_amount': 0.0,
            'total': 0.0
        }
        
        # Find total amount (most reliable)
        total_pattern = r'Total\s*:?\s*€?\s*([0-9,]+\.?\d*)'
        match = re.search(total_pattern, text, re.IGNORECASE)
        if match:
            amounts['total'] = self.parse_amount(match.group(1))
        
        # Find VAT amount
        vat_pattern = r'VAT\s*:?\s*€?\s*([0-9,]+\.?\d*)'
        match = re.search(vat_pattern, text, re.IGNORECASE)
        if match:
            amounts['vat_amount'] = self.parse_amount(match.group(1))
            amounts['subtotal'] = amounts['total'] - amounts['vat_amount']
        
        return amounts
    
    def find_vat_lines(self, text: str) -> list:
        """Extract VAT breakdown"""
        # This would need to be customized based on invoice formats
        vat_lines = []
        
        # Look for Irish VAT rates
        if '23%' in text or '0.23' in text:
            # Extract amounts at 23% rate
            pass
        if '13.5%' in text or '0.135' in text:
            # Extract amounts at 13.5% rate
            pass
            
        return vat_lines
    
    def find_line_items(self, text: str) -> list:
        """Extract individual line items"""
        # This is complex and depends heavily on invoice format
        # Would need table extraction logic
        return []
    
    def find_vat_number(self, text: str) -> Optional[str]:
        """Extract VAT number"""
        # Irish VAT pattern
        pattern = r'(IE\d{7}[A-Z]{1,2})'
        match = re.search(pattern, text)
        return match.group(1) if match else None
    
    def find_email(self, text: str) -> Optional[str]:
        """Extract email address"""
        pattern = r'([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})'
        match = re.search(pattern, text)
        return match.group(1) if match else None
    
    def find_supplier_name(self, text: str) -> Optional[str]:
        """Extract supplier name - this is complex and may need templates"""
        # Simplified: look for company identifiers
        lines = text.split('\n')
        for line in lines[:10]:  # Check first 10 lines
            if 'Ltd' in line or 'Limited' in line or 'Company' in line:
                return line.strip()
        return None
    
    def parse_amount(self, amount_str: str) -> float:
        """Parse amount string to float"""
        # Remove currency symbols and spaces
        amount_str = amount_str.replace('€', '').replace(',', '').strip()
        try:
            return float(amount_str)
        except ValueError:
            return 0.0
    
    def standardize_date(self, date_str: str) -> str:
        """Convert date to ISO format"""
        # This would need proper date parsing logic
        # For now, return as-is
        return date_str
    
    def is_valid_data(self, data: Dict[str, Any]) -> bool:
        """Check if extracted data is valid"""
        # Minimum requirements
        return (
            data.get('invoice_number') is not None and
            data.get('amounts', {}).get('total', 0) > 0
        )
    
    def format_response(self, success: bool, data: Optional[Dict], 
                        errors: list = None) -> Dict[str, Any]:
        """Format response for Laravel"""
        response = {
            'success': success,
            'confidence': self.confidence if success else 0.0,
            'data': data,
            'errors': errors or []
        }
        
        if data and success:
            response['data']['metadata'] = {
                'parsing_method': 'ocr' if self.used_ocr else 'text',
                'ocr_used': self.used_ocr,
                'processing_time': 0.0  # Would need timing logic
            }
        
        return response

def main():
    parser = argparse.ArgumentParser(description='Parse invoice PDF files')
    parser.add_argument('--file', required=True, help='Path to PDF file')
    parser.add_argument('--output', default='json', choices=['json', 'text'],
                       help='Output format')
    
    args = parser.parse_args()
    
    invoice_parser = InvoiceParser(args.file)
    result = invoice_parser.parse()
    
    if args.output == 'json':
        print(json.dumps(result, indent=2))
    else:
        print(result)
    
    # Exit with appropriate code
    sys.exit(0 if result['success'] else 1)

if __name__ == '__main__':
    main()
```

## Implementation Steps

### Phase 2.1: Basic Integration
1. Install Python dependencies on server
2. Create/adapt Python parser script
3. Create Laravel parsing service
4. Create queue job for processing
5. Add "Start Processing" button to preview page
6. Test with sample invoices

### Phase 2.2: Review Interface
1. Create review page for parsed data
2. Display extracted fields with confidence scores
3. Allow inline editing of parsed values
4. Side-by-side view with original document
5. Approve/reject functionality

### Phase 2.3: Invoice Creation
1. Convert approved data to invoice records
2. Auto-match suppliers
3. Create VAT lines
4. Move files to permanent storage
5. Link as invoice attachments

### Phase 2.4: Advanced Features
1. Template management for known suppliers
2. Machine learning improvements
3. Duplicate detection
4. Batch approval workflows
5. Email notifications

## Configuration Updates

Add to `config/invoices.php`:

```php
'parsing' => [
    'python_parser_path' => env('INVOICE_PARSER_PATH', base_path('scripts/invoice_parser.py')),
    'python_executable' => env('PYTHON_EXECUTABLE', 'python3'),
    'max_parse_time' => 60,
    'enable_ocr' => true,
    'ocr_confidence_threshold' => 70,
    'queue_name' => 'invoice-parsing',
    'max_retries' => 3,
],
```

## Queue Configuration

Add to `.env`:

```env
# Invoice Parser
INVOICE_PARSER_PATH=/path/to/invoice_parser.py
PYTHON_EXECUTABLE=/usr/bin/python3

# Queue for parsing
# Requires a worker on this queue: php artisan queue:work --queue=invoices
QUEUE_CONNECTION=database
INVOICE_PARSING_QUEUE=invoices
```

## Testing Strategy

### Unit Tests
- Test parsing service methods
- Test data validation
- Test error handling

Supplier parsers are pure `parse_invoice(text, filename)` functions, so they are tested directly
against committed fixtures of extracted invoice text (bank details redacted) under
`scripts/invoice-parser/tests/fixtures/<supplier>/`:

```bash
scripts/invoice-parser/venv/bin/python -m pytest scripts/invoice-parser/tests/ -v
```

When a supplier changes layout, add a fixture for the new layout **and keep the old one** — both
tend to stay in circulation across the archive.

### Integration Tests
- Test Python script execution
- Test queue job processing
- Test database updates

### End-to-End Tests
- Upload files → Parse → Review → Create invoices
- Test various invoice formats
- Test error scenarios

## Performance Optimization

1. **Parallel Processing**: Process multiple files simultaneously
2. **Caching**: Cache supplier templates
3. **OCR Optimization**: Pre-process images for better OCR
4. **Queue Priority**: High priority for smaller files
5. **Resource Limits**: Prevent memory exhaustion

## Monitoring

Track these metrics:
- Parse success rate per supplier
- Average confidence scores
- Processing time per file
- OCR usage percentage
- Queue backlog size

## Troubleshooting

### Parser Not Found
```bash
# Check Python installation
which python3
python3 --version

# Test parser directly
python3 /path/to/invoice_parser.py --file test.pdf --output json
```

### Queue Not Processing
```bash
# Check queue worker
php artisan queue:work --queue=invoice-parsing

# Check Redis connection
redis-cli ping
```

### Low Confidence Scores
- Improve image quality for OCR
- Add supplier-specific templates
- Train on more examples

## Security Considerations

1. **Sandbox Python Execution**: Use Docker or restricted user
2. **File Validation**: Verify file integrity before parsing
3. **Input Sanitization**: Clean parsed data before storage
4. **Resource Limits**: Prevent DOS via large files
5. **Audit Logging**: Track all parsing activities

## Implemented Parsers

The following supplier-specific parsers have been implemented:

### Udea Invoice Parser
Located at `scripts/invoice-parser/parsers/invoice_udea.py`, this parser handles UDEA B.V. supplier invoices with:
- Invoice header extraction (number, date, totals, VAT status, "Total products" expected)
- Product line classification by Gb.rek account codes
- **Partial line extraction** when Gb.rek is corrupted (extracts article, quantity, total, description)
- **Decimal quantity support** for weighted items (e.g., `60.212` for 6 units × 0.212kg)
- **"Total products" validation** comparing parsed totals against PDF-stated value
- Barrel/deposit tracking
- Freight/cost extraction
- 100% line capture with `parse_status: "full"` or `"partial"`

See [Udea Invoice Parser Documentation](./udea-invoice-parser.md) for full details.

### Delivery PDFs
The `scripts/invoice-parser/parsers/delivery_udea.py` parser handles Udea delivery PDFs for the delivery verification system.

See [Delivery System Documentation](./delivery-system.md) for details.

### BreaDelicious Invoice Parser

`scripts/invoice-parser/parsers/breadelicious.py` handles **two layouts**, because BreaDelicious
changed invoicing software between 2026-06-28 and 2026-07-06. Both remain in the archive, so the
parser dispatches on the text: `Issue date:` means the legacy layout, otherwise the current one.

| | Fakturownia (to 2026-06-28) | Xero (from 2026-07-06) |
|---|---|---|
| Date | `Issue date: 2025-12-28` | `InvoiceDate` with `9Aug2026` on the next line |
| Invoice number | `2026/0829` | `INV-0180` |
| Line amounts | net | **VAT-inclusive** |
| VAT | explicit `Net / Rate / VAT / Gross` summary table | stated once as `INCLUDES SALES ON TAX 13.5%` |
| Total | `Total gross price EUR` | `TOTAL EUR` |

Points that matter when this layout changes again:

- On the Xero layout the tax column reads `13.5%` for VAT-bearing lines, `Tax on Sales` for
  zero-rated bread (which wraps, so only `Taxon` lands on the item line once pdfplumber strips
  intra-word spaces), and is **absent entirely** on zero-quantity lines.
- Credit lines are parenthesised: `(4.80)` means −4.80.
- Because every line amount is gross, the buckets must sum to `TOTAL EUR`. The parser asserts this
  and, on a mismatch, assigns the difference to 0% and emits a `Parse_Warnings` entry, which drops
  confidence to 0.50 so the file lands in review rather than importing silently.

### Beechlawn Invoice Parser

`scripts/invoice-parser/parsers/beechlawn.py` handles **two layouts**, because Beechlawn changed
Xero invoice template between 2026-07-27 and 2026-08-31. Both remain in the archive, so the parser
dispatches on the text: a `TOTAL EUR` line means the legacy layout, otherwise the current one.

| | Legacy (to 2026-07-27) | Current (from 2026-08-31) |
|---|---|---|
| pdfplumber spacing | intra-word spaces **stripped** (`InvoiceDate`, `TOTALEUR`) | spaces **preserved** |
| Date | `InvoiceDate` label with `27Jul2026` on the next line | `Issue date` column, `31 Aug 2026` |
| Invoice number | `InvoiceNumber` + `INV-31546` | `Invoice number` + `INV-32108` |
| Columns | `Description Quantity UnitPrice Tax AmountEUR` | `Description Quantity Price Amount` — **no Tax column** |
| Tax label | literal `ZeroRated` per line | absent |
| Totals | `Subtotal` / `TOTAL EUR` | `Subtotal` / `Total` / `Amount due` |
| Quantity | always 2dp (`8.00`) | bare or 1dp (`8`, `2.7`) |

Points that matter when this layout changes again:

- Beechlawn supplies certified organic produce only, so every line on every archived invoice is
  zero-rated and both layouts price their lines **net**. The whole invoice lands in the 0% bucket
  and the parser returns an all-zero `VAT Amounts` with `Total_VAT: 0.0`, which makes the
  dispatcher use the invoice's own printed total verbatim.
- If a VAT-bearing line ever appears, `Subtotal` and `Total` diverge. The parser cannot tell from
  the template alone whether Xero printed the line amounts net or gross, so it emits a
  `Parse_Warnings` entry instead of guessing; confidence drops to 0.50 and the file lands in
  review. Resolve that by looking at a real example before changing the arithmetic.
- The current layout prints the **due** date as `20 Sept 2026`. `datetime`'s `%b` only accepts the
  three-letter form, so the parser uses its own month map keyed on the first three letters. The
  issue date is selected as the last date preceding the `INV-`/`CN-` reference, with the earliest
  date in the document as a fallback.
- The line regex anchors on the trailing pair of 2dp numbers rather than the first number it
  meets, which is what lets descriptions carrying their own digits parse — `KALE, CURLY (1kg) -
  B146`, `SALAD LEAF (1 kg) - B204`, `Prepacked Red Beetroot Bunch 650g`.

### Bean2Cup Invoice Parser

`scripts/invoice-parser/parsers/bean2cup.py` handles bean2cup tech support limited — coffee
machine parts and servicing. One layout, unchanged across the whole archive (2025-09-18 to
2026-09-01), and unrelated to the Xero templates the produce suppliers use.

Parts are charged at 23% and call-out/labour at 13.5%, so most invoices span two rates.

| | |
|---|---|
| Date | `Invoice Date 14/07/2026`, already `DD/MM/YYYY`. `Due Date` is printed adjacent to it and is usually identical — take the labelled invoice date, not whichever comes first. |
| Invoice number | `Invoice Number INV-14869` |
| Line columns | `Code Description Qty/Hrs Price/Rate [Discount] VAT % Net` — the **Discount column only appears when a line carries one** |
| VAT summary | `Standard 23.00% (23.00%) € 206.70 € 47.54`, one row per rate, giving net and VAT directly |
| Totals | `Total Net` / `Total VAT` / `TOTAL €` |

Points that matter when this layout changes:

- **The VAT summary table is the source of truth**, not the line items. It states net and VAT per
  rate, so the parser returns them as `VAT Amounts` rather than recomputing. Line items are only a
  fallback, and using them raises a `Parse_Warnings` entry so the file lands in review.
- The right-hand totals block is interleaved onto the same extracted lines as the summary rows
  (`... € 206.70 € 47.54 Total VAT 47.54`), and `TOTAL € 623.00` sometimes shares a line with the
  Standard row and sometimes does not. Match the summary *row*, not the whole line.
- `TOTAL` is distinguished from `Total Net` and `Total VAT` only by the `€` that follows it.
- Amounts carry thousands separators once they pass €1,000 (`€ 1,076.00`).
- The Code column wraps over several lines (`CAFETTO` / `MFC` / `GREEN` / `MILK` / ...), so the
  item table has many continuation lines that carry no figures. The fallback bounds its scan
  between the `Code Description` header row and the `VAT Rate` row.
- A fully discounted line nets to `0.00` and must not reach any bucket — INV-14996 prices a call
  out at 90.00 with a 90.00 discount.
- The parser cross-checks its own figures against all three printed totals and warns on any
  mismatch, which drops confidence to 0.50 and routes the file to review.

### Meadow & Moss Invoice Parser

`scripts/invoice-parser/parsers/meadow_moss.py` handles Meadow & Moss — cut flower bouquets
delivered weekly. One layout, unchanged since 2026-07-23.

| | |
|---|---|
| Date | `Invoice Date: 31/08/2026`, sharing an extracted line with `Payment Due:` |
| Invoice number | `Invoice No: 005` — a bare sequence number, not an `INV-` reference, sharing a line with `Payment Terms:` |
| Line columns | `Item Delivery Date Qty Unit Price Line Total` |
| Amounts | euro-signed with the minus ahead of the sign: `€9.16`, `-€35.24` |
| Totals | `Subtotal` / `Tax` / `Total`, interleaved onto the payment-details lines |

Points that matter when this layout changes:

- **The supplier is not VAT registered.** No VAT number appears on the invoice, the `Tax` row is
  printed with no amount at all, and `Subtotal` equals `Total`. The parser therefore returns
  `Tax Free: True` with the whole amount in the 0% bucket, following the convention stated for the
  AI parser in `InvoiceGeminiParsingService` ("if no VAT is charged at all, set `is_tax_free` to
  true and put the full amount under `vat_0`") and matching `coolnagrower.py`. If a `Tax` amount
  ever appears, or Subtotal diverges from Total, the parser warns instead of guessing a rate.
- **Negative lines are not credit notes.** Returned bouquets appear as `Credit Lrg Bouq ... -1
  €17.62 -€17.62` inside an otherwise ordinary invoice, so `Credit Note` keys off a negative
  invoice *total*, never off the word "Credit" appearing in a line description.
- The totals block is interleaved with the payment details (`Payment Methods  Subtotal €360.80`,
  `IBAN ...  Total €360.80`), so the total regexes match the label wherever it falls rather than
  anchoring to the start of a line. `\bTotal` is what keeps `Total` from matching inside
  `Subtotal`, and the header's `Line Total` carries no amount so it cannot match either.
- Delivery dates are inconsistently zero-padded (`6 August 2026` against `02 July 2026`). The line
  regex anchors on the trailing pair of euro amounts and absorbs the date into the description, so
  the formatting does not matter.
- **Name collision:** the `MOSSFIELD` branch sits earlier in `detect_supplier`. Meadow & Moss's
  email is `meadowandmossfarm@gmail.com` — `MOSSFARM`, not `MOSSFIELD` — so the two do not clash,
  but any future broadening of either token needs checking against the other.

### Independent Invoice Parser

`scripts/invoice-parser/parsers/independent.py` handles Independent Irish Health Foods — the
highest-volume supplier in the archive. Invoices run to several pages of product lines followed by
a VAT summary block on the last page:

```
Tax Code Rate Taxable Tax  DRS Totals        Gross Total: 2,241.03
0   0.00  1,354.42  0.00   DRS 15c 15 2.25   Tax:           181.70
1  23.00    652.44 150.09  DRS 25c  0 0.00
2  13.50    234.17  31.61                    Total:       2,422.73
```

Points that matter when this layout changes:

- **Independent round VAT per line**, so the stated Tax column does not equal
  `round(net x rate, 2)` on the aggregate — `round(652.44 x 0.23, 2)` is 150.06 against the 150.09
  printed. The parser therefore returns the stated figures as `VAT Amounts` / `Total_VAT` (see the
  contract section below). Measured across the archive, 82 of 144 invoices differ from the
  recomputed figure, always by 5c or less. All 144 reconcile exactly against the stated column.
- **Never whitelist the rate.** One invoice (2025-07-23, IN439078) prints its standard row as
  `22.50` while charging 23% (€37.90 on €164.75). The parser used to match only
  `0.00|9.00|13.50|23.00` and dropped the row silently, reporting a €201.93 total against the
  invoice's €404.58 at full confidence. Any unrecognised rate is now snapped onto the nearest
  known rate using `tax / taxable`, with a `Parse_Warnings` entry so it lands in review.
- The row scan is bounded to the summary block (from the `Tax Code ... Rate ... Taxable` header to
  `VAT Reg No`). That is what makes it safe for the row regex to accept any rate: several hundred
  product lines sit above the header and can never be mistaken for a summary row.
- `Gross Total:` ends in `Total:`, so the grand total needs `(?<!Gross )\bTotal:` — a naive match
  picks up the net figure instead.
- Amounts use UK/US thousands separators (`1,354.42`); see the delivery-parser entry in
  `docs/development/known-issues.md` for the separator-ambiguity bug this once caused.
- The parser cross-checks its rows against all three printed totals (`Gross Total`, `Tax`,
  `Total`) and warns on any mismatch, dropping confidence to 0.50.

### VAT-inclusive layouts: the `VAT Amounts` and `Total` contract

`invoice_parser_laravel.py` normally derives VAT as `round(net × rate, 2)`. That is wrong for
invoices whose issuer rounds VAT **per line**: BU-2026-001216 states €2.86 of VAT where the
aggregate calculation gives €2.85, and BU-2026-001100 states €6.62 against €6.61.

A parser may therefore return two optional keys, which the dispatcher honours:

- `'VAT Amounts'` — `{'0': 0.0, '9': 0.0, '13.5': 2.86, '23': 0.0}`, the VAT the invoice itself
  states per rate. Each present rate overwrites the computed `vat_breakdown[...]['vat']`.
- `'Total'` — the invoice's printed total. Used in place of the computed
  `Σ net × (1 + rate)` when the two differ by no more than €0.05; a larger gap is reported as a
  warning and the computed value is kept.

Both are opt-in, so parsers that do not return them are unaffected. `InvoiceCreationService`
writes `total_amount` as `subtotal + vat_amount`, so honouring the stated VAT is what makes the
stored invoice tie out to the paper one.

Parsers may also return `'Invoice Number'`, which the dispatcher maps to `invoice_number` →
`invoice_upload_files.parsed_invoice_number` → `invoices.supplier_invoice_reference`.

### Re-parsing stored invoices

`php artisan invoice:reparse` re-runs the parser against an invoice's stored attachment and prints
a before/after table. It is a dry run unless `--apply` is given, and it refuses to write to any
invoice that carries a `vat_return_id` or whose date falls inside a finalized VAT return period.

```bash
php artisan invoice:reparse BU-2026-001216                       # one invoice, dry run
php artisan invoice:reparse --supplier=135 --from=2026-07-01     # every invoice since a layout change
php artisan invoice:reparse --supplier=135 --from=2026-07-01 --apply
```

This is the tool to reach for when a supplier changes layout: fix the parser, dry-run the
supplier's back catalogue to confirm no regression on the old layout, then apply.

## Next Steps

1. Implement Python parser with your existing code
2. Create Laravel service and job
3. Add processing button to UI
4. Test with real invoices
5. Iterate based on results