# Known Issues & Solutions

This document tracks known issues that have been identified and resolved in the OSManager CL application. Understanding these past issues helps prevent similar problems in the future.

**Quick Navigation:**
- [Frontend Issues](#frontend-issues)
- [Database & Transaction Issues](#database--transaction-issues)
- [File Upload & Permissions Issues](#file-upload--permissions-issues)
- [Document Conversion Issues](#document-conversion-issues)
- [Validation Issues](#validation-issues)

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

## Additional Resources

For comprehensive troubleshooting procedures, see:
- [Troubleshooting Guide](./troubleshooting.md)
- [AI Assistant Guide](./ai-assistant-guide.md)
- [Performance Optimization Guide](./performance-optimization-guide.md)

---

**Note**: This document tracks issues that have been resolved. For current bugs or feature requests, please use the issue tracking system.
