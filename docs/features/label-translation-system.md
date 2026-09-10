# Label Translation System (AI-Powered)

Scan imported product labels with a phone camera, translate to English using Google Gemini AI, and print directly to a Zebra thermal printer with allergen highlighting.

## Overview

The label translation system enables staff to quickly create English-language retail labels for imported products. It uses:
- Phone camera capture for label scanning
- Google Gemini 2.5 Flash for image analysis and ZPL code generation
- Direct printing to a Zebra GX430t thermal printer via CUPS/IPP

## Features

### Camera Capture
- **Take Photo** button opens the rear camera directly on mobile Chrome
- **Choose from Gallery** option for selecting existing images
- Image preview before upload with filename and size display
- Uploaded images gallery showing all previously captured labels
- **Browser Support**: Chrome on Android (recommended). Firefox does not support the HTML `capture` attribute.

### AI Translation & ZPL Generation
- Gemini analyzes the product label photo and generates ZPL II printer code
- All text is translated to English
- **14 EU allergens** highlighted using CAPITAL LETTERS (HSE compliant emphasis):
  Cereals (Gluten), Crustaceans, Eggs, Fish, Peanuts, Soybeans, Milk, Nuts, Celery, Mustard, Sesame, Sulphites, Lupin, Molluscs
- Label content includes: Product Name, Ingredients (wrapped text), Nutrition (with EU-required "Per 100g/100ml" prefix), Storage & Origin
- Images are resized client-side to max 1600px (JPEG 85%) before upload, solving PHP upload size limits for phone photos (3-5MB)
- Server further resizes to max 1200px (JPEG 80%) before sending to Gemini to reduce API latency

### ZPL Preview & Code Viewer
- Client-side ZPL rendering via `zpl-renderer-js` WASM engine (~9MB bundle)
- Live preview updates when changing label size, font scale, or label data fields
- **ZPL Code dropdown**: Collapsible viewer on the review step to inspect raw ZPL code for debugging
- Production-safe loading: 15-second timeout for WASM bundle download over slow/VPN connections

### Dynamic Label Layout
- **Auto-fit scaling**: If content overflows label height, font scale is automatically reduced until it fits
- **Word-wrap line estimation**: Simulates ZPL word-boundary wrapping with 10% safety margin for accurate Y-position tracking
- **Consistent `^FB` and Y-advance**: All sections use the same estimated line count for both the ZPL `^FB` maxLines command and the vertical space allocation, preventing overlap
- **Uncapped sections**: Ingredients, nutrition, storage, and address fields use as many lines as needed (no artificial caps)
- **Compact product name**: Uses body font (not name font) at 1 line, maximising space for ingredients and nutrition
- **Two label sizes**: Large (76×50mm) and Small (56×30mm), configurable in `config/label-sizes.php`

### Print Step Adjustments
- **Label size and text scale controls** available directly on the print step (Step 5)
- When scanning an existing product, users can go straight to print and still adjust label size and font scale
- Same controls as the review step: label size buttons and A-/A+ text size slider with range 50–200%
- Changes call `regenerate()` to rebuild ZPL with auto-fit before printing

### Zebra Printer Integration
- All printing goes through `ZebraPrintService` (`app/Services/ZebraPrintService.php`) — one `lp` invocation to the networked Zebra over CUPS/IPP
- Label size: 50mm x 76mm (600 x 900 dots at 300dpi)
- Test print button for verifying printer connectivity
- **The print spool lives on the printer host** (`ZEBRA_PRINTER_HOST`), not on the web server. Clearing the local CUPS queue has no effect on stuck label jobs — see [Printer Queue](#printer-queue) below

### Delivery Auto-Printing
Translated labels print in bulk from the delivery match page (`/delivery-legacy/match?delID=…&supplierID=…`).

- **What appears**: a scanned product is listed when it has a translation with `auto_print = true` and non-empty `zpl_content`. The **newest** translation per product code wins, and *then* `auto_print` is checked — never the other way round, or an older enabled row would resurrect a product whose current translation was switched off (and print its stale ZPL)
- **How many print**: `scanned − already printed for this delivery`, capped at 99 per product per job. Pressing Print a second time prints **nothing** unless more has been scanned since
- **Print ledger**: every batch is recorded in `delivery_label_prints` before the job reaches CUPS. Recording first means a lost or timed-out response can never cause a duplicate; only a *definitive* refusal (lp ran and returned no request id) stamps `failed_at` and releases the labels back to outstanding
- **Reprints**: already-printed products sit behind a "Show already-printed" disclosure in the review modal, unticked. Ticking one is an explicit reprint of the full scanned quantity and asks for confirmation
- **Undo**: `POST /delivery-legacy/undo-last-print` deletes the most recent batch, putting those labels back on the outstanding list. Gated behind `deliveries.manage`
- **Idempotency**: each submit attempt carries a client-generated `idempotency_key`, reused across retries. A repeat of the same key returns `already_printed` without printing. A `Cache::lock` per delivery serialises concurrent presses, and a unique index on `(idempotency_key, barcode)` catches the race
- **Auto-print toggle**: per-translation, on the Translated Labels tab at `/labels/zebra?view=translations`. A new translation **inherits** the toggle from the product's previous translation rather than defaulting to on, so re-translating a disabled product keeps it disabled

### Printer Queue
A **Printer Queue** card on `/labels/zebra` lists the jobs actually queued on the printer host, with per-job Cancel and Cancel all.

Equivalent from a shell:
```bash
lpstat -h 10.42.1.71:631 -o          # list queued jobs
cancel -h 10.42.1.71:631 -a ZTC-GX430t   # clear the queue
```

Note the `testPrint` `lpstat` diagnostic mode queries the **local** daemon (no `-h`) and will not show this queue; use the `queue` or `printer` modes instead.

## Routes

| Method | URL | Controller Method | Name |
|--------|-----|-------------------|------|
| GET | `/labels/camera-test` | `cameraTest` | `labels.camera-test` |
| POST | `/labels/camera-upload` | `uploadPhoto` | `labels.camera-upload` |
| POST | `/labels/print-zpl` | `printZpl` | `labels.print-zpl` |
| POST | `/labels/test-print` | `testPrint` | `labels.test-print` |
| GET | `/labels/printer-queue` | `printerQueue` | `labels.printer-queue` |
| POST | `/labels/printer-cancel` | `cancelPrinterJob` | `labels.printer-cancel` |
| POST | `/labels/translate/{translation}/print` | `LabelTranslationController@print` | `labels.translate.print` |
| PATCH | `/labels/translate/{translation}/auto-print` | `LabelTranslationController@toggleAutoPrint` | `labels.translate.toggle-auto-print` |
| POST | `/delivery-legacy/print-translations` | `DeliveryLegacyController@printTranslations` | `delivery-legacy.print-translations` |
| GET | `/delivery-legacy/translatable-products` | `DeliveryLegacyController@translatableProducts` | `delivery-legacy.translatable-products` |
| POST | `/delivery-legacy/undo-last-print` | `DeliveryLegacyController@undoLastPrint` | `delivery-legacy.undo-last-print` |

## Configuration

### Environment Variables (`.env`)

```env
# Google Gemini API
GEMINI_API_KEY=your-api-key-here

# Zebra Printer
ZEBRA_PRINTER_HOST=10.42.1.71
ZEBRA_PRINTER_PORT=631
ZEBRA_PRINTER_NAME=ZTC-GX430t
ZEBRA_PRINTER_TIMEOUT=15
```

`ZEBRA_PRINTER_TIMEOUT` (seconds) caps every `lp`/`lpstat`/`cancel` call. Keep it well under the PHP/nginx request timeout so an unreachable printer fails fast and the UI can say so, rather than hanging the request.

### Config Files
- `config/gemini.php` — API key, base URL, request timeout (default 120s)
- `config/services.php` — `zebra` block with host, port, printer name, timeout

## Technical Details

### Files

**Controller:**
- `app/Http/Controllers/LabelAreaController.php` — `cameraTest()`, `uploadPhoto()`, `printZpl()`, `testPrint()`, `printerQueue()`, `cancelPrinterJob()`
- `app/Http/Controllers/LabelTranslationController.php` — `index()`, `save()`, `upload()`, `regenerateZpl()`, `print()`, `toggleAutoPrint()`
- `app/Http/Controllers/DeliveryLegacyController.php` — `printTranslations()`, `translatableProducts()`, `undoLastPrint()`

**Services:**
- `app/Services/ZplGeneratorService.php` — ZPL generation with auto-fit scaling and dynamic line estimation
- `app/Services/ZebraPrintService.php` — the single path to the printer: `sendRaw()`, `queue()`, `printerStatus()`, `cancel()`, `cancelAll()`. All shell arguments are escaped and every command is `timeout`-guarded. Used by `DeliveryLegacyController`, `LabelTranslationController`, `ZebraLabelController`, `VoucherController` and `LabelAreaController` — do not shell out to `lp` anywhere else
- `app/Services/ZebraPrintResult.php` — outcome of one `lp` call. `timedOut` is deliberately distinct from a plain failure: when the printer stops responding CUPS may still have accepted the job, so callers must not treat it as "nothing printed" and offer a retry that duplicates

**Models:**
- `app/Models/ProductTranslation.php` — Stored translations with label data, ZPL content, and photos. `latestPerProductCode()` returns the newest row per code **without** filtering on `auto_print` — callers filter afterwards
- `app/Models/DeliveryLabelPrint.php` — Per-delivery print ledger. `printedQuantitiesFor()`, `hasIdempotencyKey()`, `markBatchResult()`, `latestBatchFor()`
- `app/Models/ZebraLabel.php` — `setZplQuantity()` writes `^PQ{n},0,0,Y`. The third parameter is *replicates of each serial number* and must stay `0`; these labels carry no `^SN`, so a non-zero value is meaningless and doubles output on some GX firmware

**Config:**
- `config/label-sizes.php` — Label dimensions, font sizes, and layout parameters for large/small labels

**Views:**
- `resources/views/labels/camera-test.blade.php` — Camera capture page with gallery and printer debug
- `resources/views/labels/translate.blade.php` — Multi-step translation workflow (scan, photos, review, print)
- `resources/views/labels/review.blade.php` — ZPL review and print page

**Routes:**
- `routes/web.php` — Routes within auth middleware group

### Database

**`product_translations`** — one row per saved translation. `auto_print` (bool, default true) controls inclusion in delivery auto-printing; it is an *inclusion* flag, never a record of having printed.

**`delivery_label_prints`** — the print ledger. One row per (barcode, batch):

| Column | Purpose |
|--------|---------|
| `delivery_id` | POS `deliveriesScan.ID` (uuid on the `pos` connection, so no FK) |
| `barcode`, `product_translation_id`, `quantity` | What was sent |
| `batch_uuid` | One modal press = one `lp` job = one batch |
| `idempotency_key` | Client-generated per submit attempt; unique with `barcode` |
| `cups_job_id`, `lp_output` | Parsed from `request id is …` |
| `printed_at`, `failed_at` | A stamped `failed_at` releases the quantity back to outstanding |
| `forced` | Printed via the "reprint anyway" override |

Outstanding per product is `SUM(deliveriesScanItems.quantity) − SUM(successful ledger quantity)`. Nothing mutates the POS scan rows — they are the POS's data.

### Testing

- `tests/Feature/DeliveryTranslatedLabelPrintingTest.php` — the ledger, delta printing, idempotency, force, refusal rollback, timeout retention, the `auto_print` ordering rule, undo
- `tests/Feature/LabelTranslationSaveTest.php` — `auto_print` inheritance on save
- `tests/Unit/ZebraPrintServiceTest.php` — command construction, job-id parsing, timeout detection
- `tests/Unit/ZebraLabelSetZplQuantityTest.php` — `^PQ` handling

Tests bind a fake `ZebraPrintService` via `usingRunner()` so nothing ever shells out. `ProductTranslation` and `DeliveryLabelPrint` pin the `mysql` connection, which under phpunit is an in-memory sqlite database — `tests/Concerns/AliasesMysqlConnection.php` points that connection name at the same PDO `RefreshDatabase` already migrated. Use that trait for any test touching these models.

### Dependencies
- `google-gemini-php/laravel` — Gemini API client for Laravel
- PHP GD extension — Image resizing before API calls
- CUPS (`lp`, `lpstat`, `cancel`) — Printer communication, via `ZebraPrintService`

### Image Processing Pipeline
1. Phone captures image (~3-5MB)
2. **Client-side resize**: Browser canvas scales to max 1600px, outputs JPEG at 85% quality (~200-500KB)
3. Resized image uploaded and stored to `storage/app/public/labels/`
4. **Server-side resize**: PHP GD scales to max 1200px, JPEG quality 80% (~100-200KB)
5. Base64 encoded and sent to Gemini
6. ZPL response stripped of markdown fencing
7. ZPL displayed on review page and sent to printer via temp file + `lp`

## Usage

1. Navigate to `/labels/camera-test` on a mobile phone (Chrome recommended)
2. Tap **Take Photo** and photograph the foreign-language product label
3. Tap **Upload & Process** — wait for Gemini to generate the ZPL
4. Review the generated ZPL code on the review page
5. Tap **Print to Zebra** to send the label to the printer

## Related Features

- **[Label System](./label-system.md)** — F&V label printing and queue management
- **Zebra Label Storage** (`/zebra-labels`) — Upload and print ZebraDesigner .prn exports with editable fields. See [Features Index](../FEATURES_INDEX.md#zebra-label-storage-new-2026-03-12) for details.
- **[Test Pages Registry](../test.md)** — All label-related test pages and cleanup instructions
