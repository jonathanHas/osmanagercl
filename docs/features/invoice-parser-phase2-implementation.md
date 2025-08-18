# Invoice Parser Phase 2 Implementation

## Overview

This document details the implementation of Phase 2 of the Invoice Bulk Upload System - Python parser integration. The system now automatically extracts structured data from uploaded invoice files using supplier-specific parsers.

## Implementation Status ✅

### Completed Components

1. **Python Parser Integration** ✅
   - Copied all 20+ supplier-specific parsers from existing system
   - Created Laravel-compatible wrapper (`invoice_parser_laravel.py`)
   - Preserves VAT breakdown logic (0%, 9%, 13.5%, 23%)
   - Maintains anomaly detection system
   - Returns JSON response for Laravel consumption

2. **Laravel Service Layer** ✅
   - `InvoiceParsingService` - Executes Python parser with venv support
   - Configuration checking and validation
   - Error handling and logging

3. **Queue Processing** ✅
   - `ParseInvoiceFile` job for async processing
   - Retry logic with exponential backoff
   - Batch statistics updates

4. **Database Schema** ✅
   - Added parsed data fields to `invoice_upload_files` table
   - Stores VAT breakdown as JSON
   - Tracks supplier detection and anomalies
   - Invoice metadata fields

5. **User Interface** ✅
   - "Start Processing" button in preview page
   - Real-time status updates
   - Parsed data viewer modal
   - Anomaly warnings display
   - VAT breakdown visualization

6. **Testing Tools** ✅
   - `php artisan invoice:test-parser` command
   - Configuration verification
   - Single file testing capability

## Directory Structure

```
/var/www/html/osmanagercl/
├── scripts/
│   └── invoice-parser/
│       ├── invoice_parser_laravel.py  # Main wrapper script
│       ├── parsers/                   # 20+ supplier parsers
│       │   ├── dynamis.py
│       │   ├── udea.py
│       │   ├── independent.py
│       │   └── ... (all others)
│       ├── utils.py                   # PDF/OCR utilities
│       ├── parse_doc_file.py          # DOC file support
│       ├── requirements.txt           # Python dependencies
│       ├── setup.sh                   # Setup script
│       └── venv/                      # Virtual environment (created on setup)
├── app/
│   ├── Services/
│   │   └── InvoiceParsingService.php  # Parser execution service
│   ├── Jobs/
│   │   └── ParseInvoiceFile.php       # Queue job
│   └── Console/Commands/
│       └── TestInvoiceParser.php      # Testing command
```

## Setup Instructions

### 1. Initial Setup

```bash
# Navigate to parser directory
cd /var/www/html/osmanagercl/scripts/invoice-parser

# Run setup script
./setup.sh

# This will:
# - Create Python virtual environment
# - Install all required packages
# - Check system dependencies
# - Display environment variables to add
```

### 2. Environment Configuration

Add to `.env`:

```env
# Python Parser Configuration
PYTHON_EXECUTABLE=/usr/bin/python3
PYTHON_PARSER_DIR=/var/www/html/osmanagercl/scripts/invoice-parser
PYTHON_VENV_PATH=/var/www/html/osmanagercl/scripts/invoice-parser/venv
INVOICE_PARSER_SCRIPT=/var/www/html/osmanagercl/scripts/invoice-parser/invoice_parser_laravel.py
INVOICE_PARSER_TIMEOUT=60
INVOICE_PARSER_ENABLE_OCR=true
INVOICE_PARSING_QUEUE=default
```

### 3. Test Configuration

```bash
# Test parser configuration
php artisan invoice:test-parser

# Test with a specific file
php artisan invoice:test-parser /path/to/invoice.pdf
```

### 4. Queue Worker

```bash
# Start queue worker for processing
php artisan queue:work --queue=default
```

## Usage Workflow

1. **Upload Files**: Use bulk upload interface to upload invoice files
2. **Preview**: Review uploaded files in preview page
3. **Start Processing**: Click "Start Processing" button
4. **Monitor Progress**: Page auto-refreshes showing parsing status
5. **Review Results**: Click "View Data" to see parsed information
6. **Handle Warnings**: Review any anomaly warnings before proceeding

## Parsed Data Structure

The parser extracts and stores:

```php
[
    'supplier_name' => 'Dynamis',
    'invoice_date' => '2024-03-15',
    'invoice_number' => null,  // Not extracted yet
    'is_tax_free' => false,
    'is_credit_note' => false,
    'total_amount' => 1230.00,
    'vat_breakdown' => [
        'vat_0' => 0.00,
        'vat_9' => 0.00,
        'vat_13_5' => 0.00,
        'vat_23' => 1230.00
    ],
    'warnings' => [],  // Anomaly detection results
    'metadata' => [
        'parsing_method' => 'pdfplumber',
        'ocr_used' => false,
        'supplier_detected' => 'Dynamis',
        'processing_time' => 1.23
    ]
]
```

## Supported Suppliers

The system includes specific parsers for:

- **EU Suppliers**: Dynamis, Udea
- **Tech Services**: DigitalOcean, Linode, JetBrains, OpenAI
- **Telecom**: Three Ireland
- **Food Suppliers**: 
  - Independent Irish Health Foods
  - Slieve Bloom Organics
  - Garryhinch Wood Exotics
  - Mossfield Organic Farm
  - Breadelicious
  - Ardu Artisan Bakery
  - Coolnagrower
  - Merry Mill
  - Oldyard Organics
- **Other**: Oxigen, Kellys, Klee Paper, Vico, Flogas, Imbibe Coffee
- **Default**: Fallback parser for unknown suppliers

## Anomaly Detection

The system detects and warns about:

- All VAT amounts being zero (parsing failure)
- Unusually high VAT amounts (>€50,000)
- Negative VAT amounts
- Missing or invalid invoice dates
- Suspiciously low total amounts

Files with anomalies are marked for review rather than automatic processing.

## API Endpoints

| Method | Route | Description |
|--------|-------|-------------|
| POST | `/invoices/bulk-upload/{batchId}/process` | Start processing batch |
| GET | `/invoices/bulk-upload/check-parser` | Check parser configuration |
| GET | `/invoices/bulk-upload/status/{batchId}` | Get processing status |

## Monitoring & Debugging

### Check Parser Logs

```bash
# Laravel logs
tail -f storage/logs/laravel.log

# Queue failures
php artisan queue:failed
```

### Common Issues

1. **"Virtual environment not found"**
   - Run `./scripts/invoice-parser/setup.sh`

2. **"Python not found"**
   - Ensure Python 3.8+ is installed
   - Update `PYTHON_EXECUTABLE` in `.env`

3. **"Parser script failed"**
   - Check file permissions
   - Verify all parser modules are present
   - Check Python package installation

4. **OCR not working**
   - Install tesseract: `sudo apt-get install tesseract-ocr`
   - For better OCR: `sudo apt-get install tesseract-ocr-gle`

## Performance Considerations

- **Processing Time**: ~1-3 seconds per PDF (text-based)
- **OCR Processing**: ~5-10 seconds per page
- **Queue Workers**: Run multiple workers for parallel processing
- **Memory Usage**: ~50MB per parser instance

## Security Notes

- Parser runs with restricted permissions
- Temporary files cleaned automatically
- Input validation prevents path traversal
- Sandboxed Python execution

## Next Steps (Phase 3)

1. **Invoice Creation**
   - Convert parsed data to invoice records
   - Auto-match suppliers by name/VAT
   - Move files to permanent storage

2. **Advanced Features**
   - Template learning for suppliers
   - Duplicate invoice detection
   - Batch approval workflows
   - Email notifications

3. **Improvements**
   - Extract invoice numbers
   - Parse line items
   - Handle multi-page invoices better
   - Add more supplier parsers

## Testing

### Manual Testing

1. Upload sample invoices from different suppliers
2. Verify supplier detection accuracy
3. Check VAT breakdown calculations
4. Test anomaly detection triggers
5. Verify OCR fallback for scanned documents

### Automated Testing

```bash
# Run feature tests
php artisan test --filter=InvoiceParsing

# Test specific supplier
php artisan invoice:test-parser /path/to/dynamis-invoice.pdf
```

## Troubleshooting

### Parser Not Working

1. Check configuration: `php artisan invoice:test-parser`
2. Verify Python packages: `source venv/bin/activate && pip list`
3. Test parser directly: `python invoice_parser_laravel.py --file test.pdf`
4. Check queue is running: `php artisan queue:work`

### Known Issues Fixed

1. **Status endpoint 404**: Fixed JavaScript fetch URL in preview template
2. **Backoff method error**: Changed from `backoff(int $attempt)` to `backoff(): array`
3. **Logging attribute error**: Changed `logging.setLevel()` to `logging.getLogger().setLevel()`
4. **Queue congestion**: Clear stuck jobs with `php artisan queue:clear`

### Slow Processing

1. Enable queue workers: Multiple workers for parallel processing
2. Check OCR usage: Text-based PDFs are much faster
3. Monitor memory: Ensure adequate RAM for Python processes

## Support

For issues or questions:
1. Check Laravel logs: `storage/logs/laravel.log`
2. Test parser configuration: `php artisan invoice:test-parser`
3. Review this documentation
4. Check Python parser logs in debug mode