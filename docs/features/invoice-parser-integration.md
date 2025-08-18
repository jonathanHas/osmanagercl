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

# Image processing
pip install Pillow          # Image manipulation
pip install opencv-python   # Advanced image processing

# System dependencies
apt-get install tesseract-ocr
apt-get install poppler-utils  # For pdf2image
```

### Expected Parser Interface

The Python parser should accept command-line arguments:

```bash
python3 /path/to/invoice_parser.py --file /path/to/invoice.pdf --output json
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
QUEUE_CONNECTION=redis
INVOICE_PARSING_QUEUE=invoice-parsing
```

## Testing Strategy

### Unit Tests
- Test parsing service methods
- Test data validation
- Test error handling

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

## Next Steps

1. Implement Python parser with your existing code
2. Create Laravel service and job
3. Add processing button to UI
4. Test with real invoices
5. Iterate based on results