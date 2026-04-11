<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Invoice Upload Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for bulk invoice upload functionality including file
    | limits, allowed types, and processing options.
    |
    */

    'bulk_upload' => [
        // Maximum number of files per batch upload
        'max_files_per_batch' => env('INVOICE_MAX_FILES_PER_BATCH', 50),

        // Maximum file size in MB
        'max_file_size_mb' => env('INVOICE_MAX_FILE_SIZE_MB', 25),

        // Maximum total upload size per batch in MB
        'max_total_size_mb' => env('INVOICE_MAX_TOTAL_SIZE_MB', 500),

        // Allowed file extensions
        'allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'tiff', 'tif', 'doc', 'docx', 'xls', 'xlsx', 'ods'],

        // Allowed MIME types
        'allowed_mime_types' => [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/tiff',
            // Microsoft Word formats
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            // Microsoft Excel formats
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            // LibreOffice/OpenDocument formats
            'application/vnd.oasis.opendocument.spreadsheet',
        ],

        // Temporary storage path (relative to storage/app/)
        'temp_path' => 'temp/invoices',

        // How long to keep temporary files (in hours)
        'temp_file_lifetime' => 24,

        // Enable chunk uploading for large files
        'enable_chunked_upload' => true,

        // Chunk size in MB
        'chunk_size_mb' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Invoice Parsing Configuration
    |--------------------------------------------------------------------------
    */

    'parsing' => [
        // Python parser script path
        'python_parser_script' => env('INVOICE_PARSER_SCRIPT', base_path('scripts/invoice-parser/invoice_parser_laravel.py')),

        // Python executable path
        'python_executable' => env('PYTHON_EXECUTABLE', '/usr/bin/python3'),

        // Python virtual environment path
        'python_venv_path' => env('PYTHON_VENV_PATH', base_path('scripts/invoice-parser/venv')),

        // Parser directory
        'python_parser_dir' => env('PYTHON_PARSER_DIR', base_path('scripts/invoice-parser')),

        // Maximum parsing time per file (seconds)
        'max_parse_time' => env('INVOICE_PARSER_TIMEOUT', 60),

        // Enable OCR for scanned documents
        'enable_ocr' => env('INVOICE_PARSER_ENABLE_OCR', true),

        // Confidence threshold for OCR (0-100)
        'ocr_confidence_threshold' => 70,

        // Queue configuration
        'queue_name' => env('INVOICE_PARSING_QUEUE', 'default'),

        // Maximum retries for parsing
        'max_retries' => env('INVOICE_PARSER_MAX_RETRIES', 3),

        // Auto-create threshold (0-100) - invoices with confidence above this will be auto-created
        'auto_create_threshold' => env('INVOICE_AUTO_CREATE_THRESHOLD', 80.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | PDF Repair Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for automatic PDF repair functionality to handle
    | corrupted PDFs from suppliers like Klee Paper
    |
    */

    /*
    |--------------------------------------------------------------------------
    | AI Vision Parsing Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for AI-powered invoice parsing from camera images.
    | Supports any OpenAI-compatible vision API (Mistral, OpenAI, etc).
    |
    */

    'ai_parsing' => [
        'enabled' => env('INVOICE_AI_PARSING_ENABLED', true),

        // Provider: 'gemini', 'mistral', 'mistral-ocr', 'openai'
        'provider' => env('INVOICE_AI_PROVIDER', 'mistral-ocr'),

        // API credentials (used by mistral, mistral-ocr, openai providers)
        'api_key' => env('MISTRAL_API_KEY'),
        'base_url' => env('INVOICE_AI_BASE_URL', 'https://api.mistral.ai/v1'),

        // Vision/chat model (used by mistral, openai providers)
        'model' => env('INVOICE_AI_MODEL', 'mistral-small-latest'),

        // Chat model for structuring OCR text (used by mistral-ocr provider)
        'ocr_chat_model' => env('INVOICE_AI_OCR_CHAT_MODEL', 'mistral-small-latest'),

        'timeout' => env('INVOICE_AI_TIMEOUT', 120),
        'max_image_dimension' => 1200,
        'jpeg_quality' => 80,
    ],

    'pdf_repair' => [
        // Enable automatic PDF repair during upload
        'enabled' => env('INVOICE_PDF_REPAIR_ENABLED', true),

        // Maximum file size to attempt repair (in MB)
        'max_file_size_mb' => env('INVOICE_PDF_REPAIR_MAX_SIZE', 50),

        // Log all repair attempts for debugging
        'log_repairs' => env('INVOICE_PDF_REPAIR_LOG', true),

        // Known problematic suppliers (for tracking)
        'problematic_suppliers' => [
            'Klee Paper',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Invoice Storage Configuration
    |--------------------------------------------------------------------------
    */

    'storage' => [
        // Permanent storage disk
        'disk' => 'private',

        // Path pattern for storing invoices (supports placeholders)
        'path_pattern' => 'invoices/{year}/{month}/{invoice_id}',

        // Generate thumbnails for image files
        'generate_thumbnails' => true,

        // Thumbnail dimensions
        'thumbnail_width' => 200,
        'thumbnail_height' => 200,
    ],
];
