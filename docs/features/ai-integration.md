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

Settings are stored in the `app_settings` table with keys like `ai.invoice_parsing.provider`. Changes automatically restart queue workers.

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
```

API keys always come from `.env` via `AiSettingsService::getApiKey()`, never from the database.

### Provider Dispatch (Invoice Parsing)

```
InvoiceGeminiParsingService::parseImage()
    match provider:
        'gemini'      -> Gemini PHP package (generativeModel)
        'mistral'     -> HTTP POST /chat/completions with vision
        'mistral-ocr' -> HTTP POST /ocr (text extraction)
                         then HTTP POST /chat/completions (JSON structuring)
        'openai'      -> HTTP POST /chat/completions with vision
```

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
- `resources/views/tools/ai-diagnostics.blade.php` -- Diagnostics UI with per-feature settings
- `resources/views/invoices/bulk-upload.blade.php` -- Camera capture tab on bulk upload page

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
