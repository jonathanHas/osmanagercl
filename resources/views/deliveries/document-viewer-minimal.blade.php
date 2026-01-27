<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $document->original_filename }}</title>
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
        .viewer-container {
            width: 100vw;
            height: 100vh;
            position: relative;
        }
        .fallback {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #1f2937;
            text-align: center;
        }
        .fallback-content {
            padding: 2rem;
        }
        .fallback svg {
            width: 4rem;
            height: 4rem;
            margin: 0 auto 1rem;
            color: #6b7280;
            fill: currentColor;
        }
        .fallback h3 {
            font-size: 1.125rem;
            font-weight: 600;
            color: #d1d5db;
            margin-bottom: 0.5rem;
        }
        .fallback p {
            color: #9ca3af;
            margin-bottom: 1rem;
        }
        .fallback a {
            background: #3b82f6;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            text-decoration: none;
            font-weight: 600;
            margin: 0 0.25rem;
            display: inline-block;
        }
        .fallback a:hover {
            background: #2563eb;
        }
        embed, iframe, img {
            width: 100%;
            height: 100vh;
            border: none;
            display: block;
        }
        img {
            object-fit: contain;
            max-height: 100vh;
        }
    </style>
</head>
<body>
    <div class="viewer-container">
        @php
            $extension = strtolower(pathinfo($document->original_filename, PATHINFO_EXTENSION));
        @endphp

        @if($extension === 'pdf')
            {{-- PDF Viewer --}}
            <embed src="{{ $viewUrl }}"
                   type="application/pdf"
                   title="{{ $document->original_filename }}"
                   id="pdf-embed">

            {{-- Fallback if embed doesn't work --}}
            <div class="fallback" id="pdf-fallback" style="display: none;">
                <div class="fallback-content">
                    <svg viewBox="0 0 24 24">
                        <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z" />
                    </svg>
                    <h3>PDF Preview Unavailable</h3>
                    <p>Your browser cannot display this PDF inline.</p>
                    <a href="{{ $viewUrl }}" target="_blank">Open in New Tab</a>
                    <a href="{{ $downloadUrl }}">Download</a>
                </div>
            </div>

            <script>
                // Check if PDF loaded successfully
                setTimeout(function() {
                    const embed = document.getElementById('pdf-embed');
                    if (!embed || embed.offsetHeight === 0) {
                        document.getElementById('pdf-fallback').style.display = 'flex';
                        if (embed) embed.style.display = 'none';
                    }
                }, 3000);
            </script>

        @elseif(in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']))
            {{-- Image Viewer --}}
            <img src="{{ $viewUrl }}"
                 alt="{{ $document->original_filename }}"
                 onerror="this.nextElementSibling.style.display='flex'; this.style.display='none';">

            <div class="fallback" style="display: none;">
                <div class="fallback-content">
                    <svg viewBox="0 0 24 24">
                        <path d="M8.5,13.5L11,16.5L14.5,12L19,18H5M21,19V5C21,3.89 20.1,3 19,3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19Z" />
                    </svg>
                    <h3>Image Cannot Be Displayed</h3>
                    <p>There was an error loading this image.</p>
                    <a href="{{ $downloadUrl }}">Download Original</a>
                </div>
            </div>

        @elseif(in_array($extension, ['txt', 'csv']))
            {{-- Text/CSV File Viewer --}}
            <iframe src="{{ $viewUrl }}"
                    title="{{ $document->original_filename }}"
                    style="background: white;">
            </iframe>

        @else
            {{-- Unsupported File Type --}}
            <div class="fallback" style="display: flex;">
                <div class="fallback-content">
                    <svg viewBox="0 0 24 24">
                        <path d="M13,9V3.5L18.5,9M6,2C4.89,2 4,2.89 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2H6Z" />
                    </svg>
                    <h3>Preview Not Available</h3>
                    <p>This file type cannot be previewed in the browser.</p>
                    <a href="{{ $downloadUrl }}">Download File</a>
                </div>
            </div>
        @endif
    </div>
</body>
</html>
