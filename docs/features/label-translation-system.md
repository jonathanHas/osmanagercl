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
- Label content includes: Product Name, Ingredients (wrapped text), Nutrition Table, Storage & Net Weight
- Images are resized client-side to max 1600px (JPEG 85%) before upload, solving PHP upload size limits for phone photos (3-5MB)
- Server further resizes to max 1200px (JPEG 80%) before sending to Gemini to reduce API latency

### Zebra Printer Integration
- Direct printing via `lp` command to networked Zebra printer over CUPS/IPP
- Label size: 50mm x 76mm (600 x 900 dots at 300dpi)
- ZPL code visible on review page for debugging
- Test print button for verifying printer connectivity

## Routes

| Method | URL | Controller Method | Name |
|--------|-----|-------------------|------|
| GET | `/labels/camera-test` | `cameraTest` | `labels.camera-test` |
| POST | `/labels/camera-upload` | `uploadPhoto` | `labels.camera-upload` |
| POST | `/labels/print-zpl` | `printZpl` | `labels.print-zpl` |
| POST | `/labels/test-print` | `testPrint` | `labels.test-print` |

## Configuration

### Environment Variables (`.env`)

```env
# Google Gemini API
GEMINI_API_KEY=your-api-key-here

# Zebra Printer
ZEBRA_PRINTER_HOST=10.42.1.71
ZEBRA_PRINTER_PORT=631
ZEBRA_PRINTER_NAME=ZTC-GX430t
```

### Config Files
- `config/gemini.php` — API key, base URL, request timeout (default 120s)
- `config/services.php` — `zebra` block with host, port, printer name

## Technical Details

### Files

**Controller:**
- `app/Http/Controllers/LabelAreaController.php` — `cameraTest()`, `uploadPhoto()`, `printZpl()`, `testPrint()`

**Views:**
- `resources/views/labels/camera-test.blade.php` — Camera capture page with gallery and printer debug
- `resources/views/labels/review.blade.php` — ZPL review and print page

**Routes:**
- `routes/web.php` — Routes within auth middleware group

### Dependencies
- `google-gemini-php/laravel` — Gemini API client for Laravel
- PHP GD extension — Image resizing before API calls
- CUPS (`lp` command) — Printer communication

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
