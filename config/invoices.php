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
        'allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'tiff', 'tif'],
        
        // Allowed MIME types
        'allowed_mime_types' => [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/tiff',
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