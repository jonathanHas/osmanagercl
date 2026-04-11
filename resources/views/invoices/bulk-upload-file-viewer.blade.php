<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $file->original_filename }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            background: #1f2937;
            color: #f3f4f6;
            font-family: system-ui, -apple-system, sans-serif;
            overflow: hidden;
        }
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 1rem;
            background: #111827;
            border-bottom: 1px solid #374151;
        }
        .toolbar-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.875rem;
        }
        .toolbar-info .filename {
            font-weight: 600;
            color: #e5e7eb;
        }
        .toolbar-info .meta {
            color: #9ca3af;
        }
        .toolbar-actions {
            display: flex;
            gap: 0.5rem;
        }
        .toolbar-actions a {
            background: #374151;
            color: #e5e7eb;
            padding: 0.375rem 0.75rem;
            border-radius: 0.375rem;
            text-decoration: none;
            font-size: 0.8125rem;
            font-weight: 500;
        }
        .toolbar-actions a:hover {
            background: #4b5563;
        }
        .toolbar-actions a.download {
            background: #065f46;
        }
        .toolbar-actions a.download:hover {
            background: #047857;
        }
        .viewer-container {
            width: 100vw;
            height: calc(100vh - 45px);
            position: relative;
            overflow: auto;
        }
        .viewer-container img {
            max-width: 100%;
            max-height: 100%;
            width: auto;
            height: auto;
            object-fit: contain;
            display: block;
            margin: 0 auto;
        }
        .fallback {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #1f2937;
            text-align: center;
        }
        .fallback-content { padding: 2rem; }
        .fallback svg {
            width: 4rem; height: 4rem;
            margin: 0 auto 1rem;
            color: #6b7280;
            fill: currentColor;
        }
        .fallback h3 { font-size: 1.125rem; font-weight: 600; color: #d1d5db; margin-bottom: 0.5rem; }
        .fallback p { color: #9ca3af; margin-bottom: 1rem; }
        .fallback a {
            background: #3b82f6; color: white;
            padding: 0.5rem 1rem; border-radius: 0.375rem;
            text-decoration: none; font-weight: 600;
            margin: 0 0.25rem; display: inline-block;
        }
        .fallback a:hover { background: #2563eb; }
        embed, iframe {
            width: 100%;
            height: 100%;
            border: none;
            display: block;
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div class="toolbar-info">
            <span class="filename">{{ $file->original_filename }}</span>
            <span class="meta">{{ $file->formatted_file_size }}</span>
            @if($file->supplier_detected)
                <span class="meta">| {{ $file->supplier_detected }}</span>
            @endif
        </div>
        <div class="toolbar-actions">
            <a href="{{ $downloadUrl }}" class="download">Download</a>
            <a href="{{ route('invoices.bulk-upload.preview', $batch->batch_id) }}">Back to Batch</a>
        </div>
    </div>

    <div class="viewer-container">
        @if($file->isImage())
            <img src="{{ $viewUrl }}"
                 alt="{{ $file->original_filename }}"
                 id="image-viewer">
        @else
            <embed src="{{ $viewUrl }}"
                   type="application/pdf"
                   title="{{ $file->original_filename }}"
                   id="pdf-embed">
        @endif

        <div class="fallback" id="pdf-fallback" style="display: none;">
            <div class="fallback-content">
                <svg viewBox="0 0 24 24">
                    <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z" />
                </svg>
                <h3>Preview Unavailable</h3>
                <p>Your browser cannot display this file inline.</p>
                <a href="{{ $viewUrl }}" target="_blank">Open in New Tab</a>
                <a href="{{ $downloadUrl }}">Download</a>
            </div>
        </div>
    </div>

    <script>
        @if($file->isImage())
            const img = document.getElementById('image-viewer');
            img.onerror = function() {
                document.getElementById('pdf-fallback').style.display = 'flex';
                img.style.display = 'none';
            };
        @else
            setTimeout(function() {
                const embed = document.getElementById('pdf-embed');
                if (!embed || embed.offsetHeight === 0) {
                    document.getElementById('pdf-fallback').style.display = 'flex';
                    if (embed) embed.style.display = 'none';
                }
            }, 3000);
        @endif
    </script>
</body>
</html>
