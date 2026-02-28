# Known Issues & Solutions

This document tracks known issues that have been identified and resolved in the OSManager CL application. Understanding these past issues helps prevent similar problems in the future.

**Quick Navigation:**
- [Frontend Issues](#frontend-issues)
- [Database & Transaction Issues](#database--transaction-issues)
- [File Upload & Permissions Issues](#file-upload--permissions-issues)
- [Document Conversion Issues](#document-conversion-issues)
- [Validation Issues](#validation-issues)
- [VAT & Tax Issues](#vat--tax-issues)
- [Invoice Parsing Issues](#invoice-parsing-issues)

---

## Frontend Issues

### Bank Reconciliation Checkbox Synchronization
**Status:** Fixed 2025-09-08

#### Problem
"Select All" button updated server-side selection array but individual checkboxes didn't visually update, causing UI/backend state mismatch.

#### Root Cause
Using `wire:click` with static `checked` attributes instead of reactive model binding.

#### Solution
Changed to `wire:model.live="selectedTransactions"` with proper array binding and added `updatedSelectedTransactions()` method for real-time bulk actions visibility updates.

---

### Alpine.js @error Directive ParseError
**Status:** Known Issue

#### Problem
Syntax error "unexpected end of file, expecting 'elseif' or 'else' or 'endif'" in Blade templates.

#### Root Cause
Alpine.js event handlers like `@error`, `@click`, etc. conflict with Blade directives.

#### Solution
Escape with double `@@` (e.g., `@@error` instead of `@error`) to prevent Blade compilation.

---

### Template Literal Conflicts
**Status:** Known Issue

#### Problem
Mixing JavaScript template literals (backticks) with Blade syntax causes parsing issues.

#### Root Cause
Blade tries to parse the template literal syntax.

#### Solution
Use string concatenation instead: `'{{ route('name') }}' + variable` rather than `` `{{ route('name') }}/${variable}` ``

---

### Alpine.js Template Tag Errors
**Status:** Fixed 2025-08-04

#### Problem
"can't access property 'after'" errors when using `x-show` on `<template>` tags.

#### Root Cause
Template tags are compile-time constructs that don't support runtime directives.

#### Solution
Never use `x-show` on `<template>` tags. Use `<template x-for>` only, control visibility with regular HTML elements.

---

### View Cache Issues
**Status:** Known Issue

#### Problem
Templates aren't updating after changes.

#### Root Cause
Laravel's view cache is not cleared.

#### Solution
Run `php artisan view:clear` and `php artisan optimize:clear`.

---

### HTML Entity Rendering in Display Names
**Status:** Known Issue

#### Problem
Product display names with HTML entities (like `<br>` tags) may not render correctly.

#### Root Cause
Using strip_tags with html_entity_decode.

#### Solution
Use `{!! nl2br(html_entity_decode($variable)) !!}` instead of `{{ strip_tags(html_entity_decode($variable)) }}`.

---

## Database & Transaction Issues

### F&V Price Updates Not Appearing on POS Till
**Status:** Fixed 2025-08-28

#### Problem
Price changes made in Laravel F&V interface don't reflect on the actual POS till system.

#### Root Causes
1. Laravel `DB::transaction()` only applies to default connection, causing POS database updates to not commit properly
2. Multiple database instances on different ports (3306 vs 3307)

#### Solution
Use separate transaction management for each database connection:
- `DB::beginTransaction()` for default connection
- `DB::connection('pos')->beginTransaction()` for POS connection
- Verify correct POS database port in `.env` file

Use the Price Sync Management tool at `/fruit-veg/price-sync` to identify and fix discrepancies.

---

## File Upload & Permissions Issues

### XLS Upload Rejected Despite Correct File Type
**Status:** Fixed 2026-02-28

#### Problem
Uploading `.xls` files (e.g., payroll reports) fails with "The file field must be a file of type: xls, xlsx" even though the file is a valid XLS.

#### Root Cause
Laravel's `mimes:xls,xlsx` validation uses PHP's `fileinfo` extension to detect the MIME type, then maps it back to a file extension. Old-style `.xls` files use the OLE2 compound document format, which `fileinfo` detects as `application/x-ole-storage` instead of `application/vnd.ms-excel`. This MIME type doesn't map to `xls`, so the validation fails.

#### Solution
Use `mimetypes:` validation instead of `mimes:` and include all known MIME types for Excel files:
```php
'file' => 'required|file|mimetypes:application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/octet-stream,application/x-ole-storage',
```

#### How to Detect
Check the actual MIME type with: `file --mime-type yourfile.xls`. If it returns `application/x-ole-storage`, the `mimes:xls` rule will reject it.

**Files Modified**: `WageController.php`

---

### PDF Upload Validation Failures
**Status:** Fixed 2025-09-04

#### Problem
Corrupted PDF headers from suppliers like Klee Paper caused upload failures with "file type not allowed" errors.

#### Root Cause
PDFs with malformed headers containing PostScript data before the PDF signature.

#### Solution
Implemented automatic PDF repair system:
- `PdfRepairService` service class
- `RepairablePdf` validation rule
- System detects and fixes corrupted headers during upload validation
- Transparently handles malformed PDFs

---

### Invoice Bulk Upload Attachments Not Saved
**Status:** Fixed 2025-08-19

#### Problem
Invoice amounts are created but attachment files aren't visible in invoice detail pages.

#### Root Cause
Directory permission issue. Queue worker runs as a different user than web server, causing "Unable to create directory" errors in `/storage/app/private/invoices/attachments/`.

#### Solution
`InvoiceCreationService` now uses year/month directory structure (`invoices/2025/08/[invoice_id]/`) instead of the restrictive `attachments/` folder.

#### How to Detect
Check logs for "Unable to create a directory" errors if this issue recurs.

---

### Invoice Attachment Path Mismatch
**Status:** Fixed 2025-08-20

#### Problem
Files upload successfully but attachments aren't created when making invoices from bulk upload.

#### Root Cause
Path mismatch between what's stored in database and what Laravel's Storage facade expects. Files stored in `storage/app/private/temp/invoices/...` but database storing only `temp/invoices/...`.

#### Solution
Fixed `InvoiceBulkUploadController.php` to store the full path returned by `storeAs()` method instead of manually constructed paths.

#### Best Practice
Always use paths returned by Laravel's Storage methods for consistency.

---

## Document Conversion Issues

### DOC/XLS Attachment Viewer Shows Blank Page
**Status:** Fixed 2025-09-03

#### Problem
Document conversion appears to work but viewer shows blank content.

#### Root Cause
Conversion files created via CLI (user `jon`) have incorrect permissions for web server (`www-data`) access.

#### Solution
1. Clear existing conversions
2. Let web server create new ones with proper permissions
3. LibreOffice conversion requires temporary HOME directory with proper environment variables for headless operation

---

## Validation Issues

### F&V Class Dropdown Not Working
**Status:** Fixed 2025-09-04

#### Problem
Users unable to change product class in fruit-veg/manage interface while country dropdown worked fine.

#### Root Cause
Validation rule mismatch - `VegClass` model uses primary key `ID` (uppercase) but validation rule checked `exists:App\Models\VegClass,id` (lowercase).

#### Solution
1. Fixed validation rule to use uppercase `ID`
2. Added `id` accessor to VegClass model for template compatibility
3. Class dropdown now works identically to country dropdown

---

### F&V Unit Dropdown Showing Wrong Options
**Status:** Fixed 2025-09-04

#### Problem
Unit dropdown showed 5 options from main database (kg, each, bunch, punnet, bag) when POS database only has 2 (kg, each), causing data inconsistency.

#### Root Cause
`VegUnit` model incorrectly referenced main database instead of POS database.

#### Solution
1. Created new `PosUnit` model connecting to POS `units` table
2. Updated `VegDetails` relationship and controller
3. Now shows only the correct 2 units from POS database, ensuring system consistency

---

## Image Upload Issues

### F&V Image Uploads Not Appearing Visually
**Status:** Fixed 2025-09-02

#### Problem
Image uploads appear successful but updated images don't show on the page.

#### Root Cause
Images save correctly to POS database but 24-hour browser cache prevents updated images from displaying.

#### Solution
1. Added cache-busting with server timestamps (`?t=timestamp`)
2. Dynamic cache control (5min vs 24hr)
3. Proper POS database transaction management
4. Automatic content-type detection

Images now appear immediately after upload.

---

## VAT & Tax Issues

### Sales VAT Breakdown Only Showing One Rate
**Status:** Fixed 2026-02-10

#### Problem
The Sales VAT Breakdown table on the VAT return creation page (`/management/vat-returns/create`) only displayed one VAT rate (0.0%) instead of all four Irish rates (0%, 9%, 13.5%, 23%). The single displayed row showed the 23% rate data but was labeled as "0.0%". Total row was correct.

#### Root Cause
PHP truncates float values used as array keys to integers. The `keyBy('vat_rate')` call in `getSalesVatData()` caused all rates between 0 and 1 (i.e., 0, 0.09, 0.135, 0.23) to collapse to integer key `0`. Each subsequent rate overwrote the previous entry, leaving only the last one (0.23 rate data) keyed as `0`.

```php
// BROKEN: float keys truncated to int
collect($salesData)->keyBy('vat_rate');
// Result: [0 => {23% data}] — only 1 entry, all others overwritten

// FIXED: string keys preserved
collect($salesData)->keyBy(fn ($item) => (string) $item->vat_rate);
// Result: ['0' => {0% data}, '0.09' => {9% data}, '0.135' => {13.5% data}, '0.23' => {23% data}]
```

#### Solution
Cast `vat_rate` to string in the `keyBy` callback for both the optimized and real-time data paths in `VatReturnController::getSalesVatData()`.

#### Files Modified
- `app/Http/Controllers/Management/VatReturnController.php` (lines 185, 221)

#### How to Detect
If the Sales VAT Breakdown table shows only one row with a "0.0%" label but has non-zero VAT amount, or the per-rate totals don't sum to match the TOTAL row, this issue is occurring.

#### General Lesson
**Never use `keyBy()` with float/decimal column values in PHP.** Always cast to string first, or use an alternative keying strategy. This is a well-known PHP gotcha where float array keys are silently truncated to integers.

---

### Paperin Adjustment Missing from 0% Rate Row in VAT Return
**Status:** Fixed 2026-02-19

#### Problem
The Sales VAT Breakdown on the VAT return page showed the 0.0% row with unadjusted net/gross figures (e.g., €138,399.44 instead of €136,322.34). The TOTAL row was correct, and the paperin adjustment footnote appeared, but the adjustment was not reflected in the individual 0% rate row.

#### Root Cause
A second `keyBy` gotcha — related to but distinct from the 2026-02-10 fix above. The `vat_rate` column in `sales_accounting_daily` is `decimal(8,4)`. SQLite returns this via PDO as a string like `"0.0000"`. The existing `(string)` cast preserved this verbatim, producing key `"0.0000"`. The paperin adjustment then checked `isset($totals['by_rate']['0'])`, which failed silently because the actual key was `"0.0000"`, not `"0"`.

```php
// BROKEN: (string) preserves SQLite's decimal format
$salesData->keyBy(fn ($item) => (string) $item->vat_rate);
// Key: "0.0000" — isset(['0']) fails

// FIXED: (float) normalizes first, then (string) gives clean key
$salesData->keyBy(fn ($item) => (string) (float) $item->vat_rate);
// Key: "0" — isset(['0']) succeeds
```

#### Solution
1. Normalize `keyBy` with `(string) (float)` double-cast in both optimized and real-time paths
2. Broadened `show()` recalculation condition to detect stale stored data (by_rate net sum != total_net) and auto-fix on next view

#### Files Modified
- `app/Http/Controllers/Management/VatReturnController.php`

#### How to Detect
If the 0.0% row net + paperin adjustment = what the 0.0% row should show, or if the sum of by_rate rows doesn't match the TOTAL row, this issue is occurring.

#### General Lesson
**When using `keyBy` with database decimal values, always normalize with `(string) (float)` cast.** A plain `(string)` cast is not sufficient — SQLite (and potentially other databases) may return decimal values with trailing zeros that don't match expected key strings.

---

## Invoice Parsing Issues

### Delivery Invoice Total Parsed Incorrectly (UK/US Number Format)
**Status:** Fixed 2026-02-05

#### Problem
Invoice stated total was parsed as `1.98` instead of `1978.38` for Independent supplier invoices. The discrepancy warning showed: "PDF states €1.98 but parsed items sum to €1978.38".

#### Root Cause
The `_clean_number_string()` function in `delivery_independent.py` incorrectly assumed European number format when both comma and period were present in a number string.

When parsing `1,978.38` (UK/US format where comma=thousands, period=decimal):
1. Code assumed European format (where period=thousands, comma=decimal)
2. Removed the period: `1,978.38` → `1,97838`
3. Replaced comma with period: `1,97838` → `1.97838`
4. Result: `1.97838` rounded to `1.98`

#### Solution
Modified the function to detect the format by checking which separator comes **last** in the number:
- If period comes last → UK/US format (comma is thousands separator)
- If comma comes last → European format (period is thousands separator)

```python
if ',' in value and '.' in value:
    last_comma = value.rfind(',')
    last_period = value.rfind('.')

    if last_period > last_comma:
        # UK/US format: 1,978.38
        value = value.replace(',', '')
    else:
        # European format: 1.978,38
        value = value.replace('.', '').replace(',', '.')
```

#### Files Modified
- `scripts/invoice-parser/parsers/delivery_independent.py` (lines 158-184)

#### How to Detect
Check delivery records for large discrepancies between `invoice_stated_total` and `calculated_total` where the stated total is suspiciously small (e.g., 1.98 vs 1978.38).

---

### Undefined Array Key "filename" on Single-File Delivery Upload
**Status:** Fixed 2026-02-28

#### Problem
Uploading a single PDF (e.g., Udea frozen delivery) that produced unparsed lines caused `Undefined array key "filename"` error on the delivery show page at `show.blade.php:246`.

#### Root Cause
The multi-file upload path in `DeliveryParsingService::parseMultipleDeliveryPdfs()` added the `filename` key to each unmatched line entry, but the single-file path in `DeliveryController::storePdf()` passed the raw parser output directly — which only contained `line_num` and `content`.

#### Solution
Added `filename` key to each unmatched line entry in the single-file path, mirroring the multi-file behaviour:
```php
$unmatchedLines = array_map(fn ($line) => array_merge(
    ['filename' => $originalFilenames[0] ?? 'unknown'],
    $line
), $unmatchedLines);
```

#### Files Modified
- `app/Http/Controllers/DeliveryController.php` (~line 582)

---

## Additional Resources

For comprehensive troubleshooting procedures, see:
- [Troubleshooting Guide](./troubleshooting.md)
- [AI Assistant Guide](./ai-assistant-guide.md)
- [Performance Optimization Guide](./performance-optimization-guide.md)

---

**Note**: This document tracks issues that have been resolved. For current bugs or feature requests, please use the issue tracking system.
