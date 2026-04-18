# AI Integration

Multi-provider AI integration for invoice parsing, label translation, and future AI-powered features. Supports Google Gemini, Mistral (vision and OCR), and OpenAI-compatible APIs with per-feature configuration.

## Overview

The AI integration layer provides a unified interface for using different AI providers across the application. Each feature (invoice parsing, label translation, etc.) can be independently configured to use a different provider and model, allowing admins to optimise for cost, speed, and accuracy per use case.

**Key design principles:**
- **Per-feature configuration** -- invoice parsing and label translation can use different providers
- **Database-backed settings** -- admins switch providers from the UI without touching server files
- **API keys in `.env` only** -- credentials never stored in the database
- **Fallback chain** -- DB settings > config file > sensible defaults
- **Queue-aware** -- settings changes automatically signal queue workers to restart

## Supported Providers

| Provider | Config Value | Capabilities | API Key Env Var |
|----------|-------------|--------------|-----------------|
| Google Gemini | `gemini` | Vision, text | `GEMINI_API_KEY` |
| Mistral Vision | `mistral` | Vision (pixtral models), text | `MISTRAL_API_KEY` |
| Mistral OCR | `mistral-ocr` | Two-step: OCR text extraction + chat structuring. Best for handwritten/paper documents | `MISTRAL_API_KEY` |
| OpenAI | `openai` | Vision, text | `OPENAI_API_KEY` |

## Features Using AI

### Invoice Parsing (Camera Capture)

Parses paper invoices photographed via phone camera. Extracts supplier, invoice number, date, total, VAT breakdown, and line items.

- **Feature key**: `invoice_parsing`
- **Default provider**: `mistral-ocr`
- **Processing**: Runs in queue jobs (`ParseInvoiceCameraImage`) for non-blocking capture
- **Supplier matching**: AI output is post-processed against known suppliers with fuzzy word-based matching
- **Date validation**: Flags dates > 2 months old, wrong year, or future dates
- **VAT handling**: Only assigns VAT rates explicitly shown on invoice; warns when guessing

### Invoice AI Fallback (Failed Parses)

Re-routes bulk-upload invoices that the Python parsers couldn't parse (status `failed`) or parsed with low confidence / anomalies (status `review`) through the AI pipeline. Driven by a "✨ Send to AI" button on the bulk-upload preview page.

- **Feature key**: `invoice_ai_fallback`
- **Default provider**: falls through to `invoice_parsing` (Camera Capture provider) when unset; can be overridden independently at `/tools/ai-diagnostics`
- **Processing**: Reuses `ParseInvoiceCameraImage` queue job with a `featureKey` argument
- **PDF support**: Only when provider is `mistral-ocr` -- uses Mistral's native `document_url` endpoint, no Ghostscript/Imagick dependency. For any other provider, PDFs return a structured error pointing users to `/tools/ai-diagnostics`
- **Image support**: All four providers work identically to Camera Capture
- **Excluded**: Files flagged as duplicates (which keep their "Delete Duplicate" action)
- **Data pipeline**: After AI extraction, output flows through the same `InvoiceParsingService::processParserOutput()` as every other parser -- confidence heuristics, duplicate detection, and auto-creation thresholds all apply unchanged

### Label Translation

Translates foreign food labels from photos. Extracts product name, ingredients (with EU allergen formatting), nutrition, storage, and origin.

- **Feature key**: `label_translation`
- **Default provider**: `gemini`
- **Processing**: Synchronous (user waits for result)
- **Used by**: `LabelTranslationController::upload()`, `LabelAreaController::uploadPhoto()`, `LabelAreaController::uploadPhoto2()`

## Configuration

### Environment Variables (`.env`)

```env
# API Keys (required -- one per provider you use)
GEMINI_API_KEY=your_gemini_key
MISTRAL_API_KEY=your_mistral_key
OPENAI_API_KEY=your_openai_key      # Only if using OpenAI provider
```

After changing `.env`, run:
```bash
php artisan config:clear
php artisan queue:restart
```

### Admin UI Configuration

Navigate to **System Tools > AI Diagnostics** (`/tools/ai-diagnostics`) to:

1. **View/change settings per feature** -- provider, model, base URL, timeout
2. **Test connections** -- text, vision, and OCR tests with response time
3. **View recent errors** -- filtered AI-related log entries

**Access control**: The diagnostics page and its sub-routes are restricted to the `admin` role via `role:admin` middleware. The sidebar entry is hidden for non-admins.

Settings are stored in the `app_settings` table with keys like `ai.invoice_parsing.provider`. Changes automatically restart queue workers.

### Visible Provider Badge

Every page that consumes an AI feature renders a small pill-shaped badge next to its title showing the currently configured provider (e.g. "AI: Mistral OCR", "AI: Google Gemini"). Hover reveals the model. The badge is a non-clickable `<span>` -- non-admins see the badge but have no link into the diagnostics page.

Implemented by `resources/views/components/ai-provider-badge.blade.php`. Used in:

- `resources/views/invoices/bulk-upload.blade.php` -- feature `invoice_parsing`
- `resources/views/invoices/bulk-upload-preview.blade.php` -- feature `invoice_ai_fallback`, labelled "AI Fallback"
- `resources/views/labels/translate.blade.php` -- feature `label_translation`, `variant="light"` for the light-themed header

### Config File Defaults

`config/invoices.php` section `ai_parsing` provides defaults when no DB setting exists:

```php
'ai_parsing' => [
    'provider' => env('INVOICE_AI_PROVIDER', 'mistral-ocr'),
    'api_key' => env('MISTRAL_API_KEY'),
    'base_url' => env('INVOICE_AI_BASE_URL', 'https://api.mistral.ai/v1'),
    'model' => env('INVOICE_AI_MODEL', 'mistral-small-latest'),
    'ocr_chat_model' => env('INVOICE_AI_OCR_CHAT_MODEL', 'mistral-small-latest'),
    'timeout' => env('INVOICE_AI_TIMEOUT', 120),
],
```

## Architecture

### Settings Resolution

```
AiSettingsService::get('invoice_parsing', 'provider')
    1. Check app_settings table: key = "ai.invoice_parsing.provider"
    2. Fall back to config('invoices.ai_parsing.provider')
    3. Fall back to default value

AiSettingsService::get('invoice_ai_fallback', 'provider')
    1. Check app_settings table: key = "ai.invoice_ai_fallback.provider"
    2. Fall through to invoice_parsing resolution (so the fallback feature
       defaults to the Camera Capture provider until explicitly overridden)
    3. Fall back to default value
```

API keys always come from `.env` via `AiSettingsService::getApiKey()`, never from the database.

**Config-cache-safe key resolution** (important for production): Each provider branch in `getApiKey()` reads the config repository *first*, then falls back to `env()`:

```php
'gemini'      => config('gemini.api_key')           ?: env('GEMINI_API_KEY'),
'mistral*'    => config('invoices.ai_parsing.api_key') ?: env('MISTRAL_API_KEY'),
'openai'      => config('invoices.ai_parsing.api_key') ?: env('OPENAI_API_KEY'),
```

This matters because once `php artisan config:cache` runs (standard on production deploys), Laravel unsets the Dotenv variables and `env()` returns `null` outside `config/*.php` files. Reading through `config(...)` keeps the key available because `config/invoices.php` and `config/gemini.php` baked the env values into the cached config.

### Provider Dispatch (Invoice Parsing)

```
InvoiceGeminiParsingService::parseImage($file, $featureKey = 'invoice_parsing')
    detect file type (pdf vs image)
    if pdf && provider != 'mistral-ocr':
        return structured error pointing to /tools/ai-diagnostics
    match provider:
        'gemini'      -> Gemini PHP package (generativeModel)               [image only]
        'mistral'     -> HTTP POST /chat/completions with vision             [image only]
        'mistral-ocr' -> HTTP POST /ocr with image_url or document_url
                         then HTTP POST /chat/completions (JSON structuring) [image + PDF]
        'openai'      -> HTTP POST /chat/completions with vision             [image only]
```

The same method serves both Camera Capture and the Failed-Parse Fallback -- the `$featureKey` argument selects which `app_settings` namespace to read.

### Provider Dispatch (Label Translation)

```
LabelTranslationController::upload()
LabelAreaController::uploadPhoto() / uploadPhoto2()
    if provider == 'gemini':
        Gemini PHP package
    else:
        HTTP POST /chat/completions with vision
```

## Routes

| Method | URL | Controller | Description |
|--------|-----|------------|-------------|
| GET | `/tools/ai-diagnostics` | `AiDiagnosticsController@index` | Diagnostics page |
| POST | `/tools/ai-diagnostics/test` | `AiDiagnosticsController@testConnection` | Test API connection |
| POST | `/tools/ai-diagnostics/settings` | `AiDiagnosticsController@saveSettings` | Save per-feature settings |
| POST | `/invoices/bulk-upload/camera-upload` | `InvoiceBulkUploadController@cameraUpload` | Camera invoice upload |
| POST | `/invoices/bulk-upload/{batchId}/file/{fileId}/send-to-ai` | `InvoiceBulkUploadController@sendToAi` | Send a failed / review bulk-upload file to the AI fallback parser |

## Technical Details

### Files

**Services:**
- `app/Services/AiSettingsService.php` -- Per-feature settings with DB/config fallback
- `app/Services/InvoiceGeminiParsingService.php` -- Multi-provider invoice image parsing

**Controllers:**
- `app/Http/Controllers/AiDiagnosticsController.php` -- Diagnostics page, connection tests, settings management
- `app/Http/Controllers/InvoiceBulkUploadController.php` -- Camera upload endpoint (`cameraUpload()`)
- `app/Http/Controllers/LabelTranslationController.php` -- Label translation with provider dispatch
- `app/Http/Controllers/LabelAreaController.php` -- Label area photo upload with provider dispatch

**Jobs:**
- `app/Jobs/ParseInvoiceCameraImage.php` -- Queue job for AI invoice parsing

**Views:**
- `resources/views/tools/ai-diagnostics.blade.php` -- Diagnostics UI with per-feature settings (admin-only route)
- `resources/views/invoices/bulk-upload.blade.php` -- Camera capture tab on bulk upload page
- `resources/views/components/ai-provider-badge.blade.php` -- Reusable provider indicator pill shown next to AI-feature page titles

**Config:**
- `config/invoices.php` -- `ai_parsing` section with defaults

**Database:**
- `app_settings` table -- keys prefixed `ai.{feature}.{key}`
- `invoice_upload_files.parsing_source` column -- tracks which parser processed the file (`python`, `gemini`, `mistral`, etc.)

### Dependencies

- `google-gemini-php/laravel` -- Gemini PHP package (used when provider is `gemini`)
- Laravel HTTP client -- used for Mistral/OpenAI API calls (no additional packages needed)

## Supplier Matching

AI-returned supplier names are post-processed through a 4-layer matching algorithm:

1. **Exact match** (case-insensitive)
2. **Substring containment** (e.g. "Imbibe Coffee Roasters" contains "Imbibe")
3. **Cleaned match** -- strips suffixes (Ltd, GmbH, etc.), prefixes (W.K., etc.), retries substring
4. **Word-based fuzzy match** -- extracts significant words, allows prefix matching (Fayle/Fayles) and Levenshtein distance <= 2

Unmatched suppliers reduce confidence to 60% (below auto-create threshold), forcing manual review.

## Related Features

- **[Invoice Bulk Upload](./invoice-bulk-upload-system.md)** -- The base upload system that camera capture integrates with
- **[Label Translation System](./label-translation-system.md)** -- AI-powered food label translation
- **[RTD System](./rtd-system.md)** -- Uses parsed invoice data for reverse tax determination
