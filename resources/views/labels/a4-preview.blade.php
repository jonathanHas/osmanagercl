<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview Labels - {{ $template->name }}</title>
    <style>
        @php
            $labelsPerA4 = $template->labels_per_a4;
            $usableWidth = 190; // A4 usable width in mm
            $usableHeight = 277; // A4 usable height in mm
            $labelsPerRow = floor($usableWidth / $template->width_mm);
            $labelsPerColumn = floor($usableHeight / $template->height_mm);
            $css = $template->css_dimensions;
            $isGrid4x9 = isset($template->layout_config['type']) && $template->layout_config['type'] === 'grid_4x9';
        @endphp
        
        /* CSS Reset for cross-environment consistency */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            margin: 0;
            padding: 20px;
            font-family: Arial, Helvetica, sans-serif;
            font-weight: 400;
            font-style: normal;
            font-size: 16px;
            line-height: 1.2;
            background: #f5f5f5;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-rendering: optimizeLegibility;
        }
        
        .preview-header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .template-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .template-name {
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }
        
        .template-details {
            font-size: 14px;
            color: #666;
        }
        
        .preview-stats {
            text-align: right;
            font-size: 14px;
            color: #666;
        }
        
        .preview-controls {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-primary {
            background: #4F46E5;
            color: white;
        }
        
        .btn-primary:hover {
            background: #4338CA;
        }
        
        .btn-secondary {
            background: #6B7280;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #4B5563;
        }
        
        .sheets-container {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }
        
        .sheet {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
            page-break-after: always;
        }
        
        .sheet-header {
            background: #F3F4F6;
            padding: 15px 20px;
            border-bottom: 1px solid #E5E7EB;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .sheet-title {
            font-weight: bold;
            color: #374151;
        }
        
        .sheet-content {
            padding: 20px;
            display: flex;
            justify-content: center;
        }
        
        .labels-container {
            display: grid;
            grid-template-columns: repeat({{ $labelsPerRow }}, {{ $css['width'] }});
            gap: 0;
            width: 190mm;
            min-height: 277mm;
            border: 1px solid #ddd;
            padding: 10mm;
            background: white;
            box-sizing: border-box;
            justify-content: center;
        }
        
        .label {
            width: {{ $css['width'] }};
            height: {{ $css['height'] }};
            border: 1px dashed #ddd;
            padding: {{ $css['margin'] }};
            display: flex;
            flex-direction: column;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8pt;
            line-height: 1.2;
            box-sizing: border-box;
            overflow: hidden;
            position: relative;
        }
        
        @if($isGrid4x9)
        .label-4x9 {
            position: relative;
            display: flex;
            flex-direction: column;
            height: {{ $css['height'] }};
            overflow: hidden;
            justify-content: space-between;
        }
        
        .label-name-4x9 {
            font-weight: 600;
            font-size: 12pt;
            line-height: 1.15;
            overflow: hidden;
            word-break: break-word;
            hyphens: manual; /* Better control over hyphenation */
            -webkit-hyphens: manual;
            -moz-hyphens: manual;
            display: -webkit-box;
            -webkit-line-clamp: 4;
            -webkit-box-orient: vertical;
            flex: 1 1 auto;
            margin-bottom: 1px;
            text-align: left;
            height: calc(100% - 33px); /* Optimized for three rows: 22px middle + 10px bottom + 1px margins */
            padding-right: 2px;
        }
        
        /* Custom Grid 4x9 improved sizing classes */
        .label-name-4x9[data-length="custom-tiny"] {
            font-size: 22pt;
            -webkit-line-clamp: 2;
            line-height: 1.1;
        }
        
        .label-name-4x9[data-length="custom-extra-short"] {
            font-size: 18pt;
            -webkit-line-clamp: 2;
            line-height: 1.15;
        }
        
        .label-name-4x9[data-length="custom-short"] {
            font-size: 15pt;
            -webkit-line-clamp: 3;
            line-height: 1.15;
        }
        
        .label-name-4x9[data-length="custom-medium"] {
            font-size: 13pt;
            -webkit-line-clamp: 3;
            line-height: 1.15;
        }
        
        .label-name-4x9[data-length="custom-long"] {
            font-size: 11pt;
            -webkit-line-clamp: 4;
            line-height: 1.15;
        }
        
        .label-name-4x9[data-length="custom-extra-long"] {
            font-size: 9pt;
            -webkit-line-clamp: 5;
            line-height: 1.12;
        }
        
        /* Custom Grid 4x9 price improvements */
        .label-4x9 .label-middle-row-4x9 .label-barcode-4x9 {
            flex: 0 0 35%; /* Reduced from 48% to give more space to price */
        }
        
        .label-4x9 .label-middle-row-4x9 .label-price-4x9[data-price-length^="custom"] {
            flex: 0 0 65%; /* Increased from 48% to prevent cropping */
            overflow: visible !important; /* Allow text to show even if it overflows */
            white-space: nowrap;
            min-width: 0; /* Allow flex item to shrink */
        }
        
        /* Custom price size classes with better sizing */
        .label-price-4x9[data-price-length="custom-normal"] {
            font-size: 24pt !important; /* Slightly smaller than 26pt for better fit */
        }
        
        .label-price-4x9[data-price-length="custom-long"] {
            font-size: 22pt !important; /* For 6-char prices like €32.95 */
        }
        
        .label-price-4x9[data-price-length="custom-extra-long"] {
            font-size: 20pt !important; /* For 7+ char prices */
        }

        /* Original responsive font sizes for standard Grid 4x9 */
        .label-name-4x9[data-length="extra-short"] {
            font-size: 22pt; /* Very large for short names */
            -webkit-line-clamp: 2;
            line-height: 1.1;
        }
        
        .label-name-4x9[data-length="short"] {
            font-size: 18pt;
            -webkit-line-clamp: 3;
            line-height: 1.12;
        }
        
        .label-name-4x9[data-length="medium"] {
            font-size: 14pt;
            -webkit-line-clamp: 3;
            line-height: 1.15;
        }
        
        .label-name-4x9[data-length="long"] {
            font-size: 11pt;
            -webkit-line-clamp: 4;
            line-height: 1.15;
        }
        
        .label-name-4x9[data-length="extra-long"] {
            font-size: 9pt;
            -webkit-line-clamp: 5; /* Allow more lines for very long text */
            line-height: 1.12;
        }
        
        /* Prevent single word on last line (orphans) */
        .label-name-4x9 {
            text-align-last: left;
        }
        
        /* Better text rendering for small sizes */
        .label-name-4x9[data-length="long"],
        .label-name-4x9[data-length="extra-long"] {
            letter-spacing: -0.02em; /* Slightly tighter spacing for long text */
        }
        
        .label-middle-row-4x9 {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex: 0 0 auto;
            height: 22px;
            margin-top: 2px;
        }
        
        .label-barcode-number-row-4x9 {
            display: flex;
            justify-content: center;
            align-items: center;
            flex: 0 0 auto;
            height: 10px;
            margin-top: 1px;
        }
        
        .label-barcode-4x9 {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            flex: 0 0 48%; /* Increased space for barcode */
            padding-right: 2px;
            overflow: hidden;
        }
        
        .barcode-visual-4x9 {
            height: 18px; /* Larger barcode */
            width: 100%;
            display: flex;
            justify-content: center;
        }
        
        .barcode-visual-4x9 svg {
            width: auto;
            height: 100%;
            max-width: 100%;
        }
        
        .barcode-number-4x9 {
            font-family: monospace;
            font-size: 7pt; /* Larger, more legible */
            letter-spacing: 0.5px;
            color: #444;
            line-height: 1;
            text-align: center;
        }
        
        .label-price-4x9 {
            font-size: 26pt !important; /* Maximum 26pt as requested */
            font-weight: 900 !important;
            color: #000 !important;
            text-align: right;
            line-height: 0.9 !important;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            flex: 0 0 48%; /* Equal space with barcode */
            height: auto !important;
            max-height: none !important;
            overflow: visible !important; /* Allow € symbol to show */
            font-family: Arial, sans-serif !important;
            white-space: nowrap;
            padding-left: 2px;
            padding-right: 0; /* No right padding to maximize space */
        }
        
        /* Responsive sizing for longer prices */
        .label-price-4x9[data-price-length="long"] {
            font-size: 22pt !important;
            padding-left: 4px; /* Extra padding for smaller text */
        }
        
        .label-price-4x9[data-price-length="extra-long"] {
            font-size: 18pt !important;
            padding-left: 4px; /* Extra padding for smaller text */
        }
        
        /* Debug: Add border to see element bounds */
        .debug-mode .label-price-4x9 {
            border: 1px solid red;
        }
        .debug-mode .label-name-4x9 {
            border: 1px solid blue;
        }
        .debug-mode .label-barcode-4x9 {
            border: 1px solid green;
        }
        .debug-mode .label-middle-row-4x9 {
            border: 1px solid purple;
        }
        .debug-mode .label-barcode-number-row-4x9 {
            border: 1px solid orange;
        }
        .debug-mode .label {
            background: rgba(255, 255, 0, 0.05);
        }
        @endif
        
        .label-name {
            font-weight: 600;
            font-size: {{ $css['font_size_name'] }};
            line-height: 1.3;
            flex-grow: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 4;
            -webkit-box-orient: vertical;
            word-break: break-word;
            hyphens: auto;
            margin: 0 0 3px 0;
        }
        
        .label-price-section {
            text-align: center;
            flex-shrink: 0;
            margin: 2px 0;
        }
        
        .label-price {
            font-size: {{ $css['font_size_price'] }};
            font-weight: bold;
            color: #000;
            margin: 0;
        }
        
        .label-barcode {
            text-align: center;
            flex-shrink: 0;
            margin-top: auto;
        }
        
        .barcode-visual {
            display: flex;
            justify-content: center;
            height: calc({{ $css['barcode_height'] }} * 0.6);
            margin: 1px 0;
        }
        
        .barcode-visual svg {
            width: auto;
            max-width: 90%;
            height: 100%;
        }
        
        .barcode-number {
            font-family: monospace;
            font-size: calc({{ $css['font_size_barcode'] }} * 0.7);
            letter-spacing: 0.5px;
            color: #555;
            margin: 1px 0;
        }
        
        .empty-label {
            border: 1px dashed #eee;
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .preview-header, .no-print {
                display: none !important;
            }
            
            .sheet {
                box-shadow: none;
                border-radius: 0;
                margin: 0;
                page-break-after: always;
            }
            
            .sheet-header {
                display: none;
            }
            
            .sheet-content {
                padding: 0;
            }
            
            .labels-container {
                border: none;
                padding: 10mm;
                margin: 0;
            }
        }
        
        .zoom-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .zoom-level {
            font-size: 14px;
            color: #666;
            min-width: 50px;
            text-align: center;
        }
        
        .zoom-btn {
            width: 32px;
            height: 32px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        
        .zoom-btn:hover {
            background: #f5f5f5;
        }
        
        .scalable-content {
            transform-origin: top center;
            transition: transform 0.2s ease;
        }
    </style>
</head>
<body>
    <div class="preview-header no-print">
        <div class="template-info">
            <div class="template-name">{{ $template->name }}</div>
            <div class="template-details">
                {{ $template->description }} • {{ $template->width_mm }}×{{ $template->height_mm }}mm • 
                {{ $template->labels_per_a4 }} labels per A4
            </div>
        </div>
        
        <div class="preview-stats">
            <div><strong>{{ $products->count() }} labels</strong> across <strong>{{ $totalSheets }} sheet{{ $totalSheets !== 1 ? 's' : '' }}</strong></div>
        </div>
        
        <div class="preview-controls">
            <div class="zoom-controls">
                <button class="zoom-btn" onclick="zoomOut()">−</button>
                <div class="zoom-level" id="zoom-level">100%</div>
                <button class="zoom-btn" onclick="zoomIn()">+</button>
            </div>
            
            <button onclick="toggleDebug()" class="btn btn-secondary">Debug</button>
            
            <button onclick="showMeasurements()" class="btn btn-secondary">Measure</button>
            
            <button onclick="window.print()" class="btn btn-primary">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                Print All
            </button>
            
            <button onclick="window.close()" class="btn btn-secondary">Close</button>
        </div>
    </div>

    <div class="scalable-content" id="scalable-content">
        <div class="sheets-container">
            @foreach($sheets as $sheet)
                <div class="sheet">
                    <div class="sheet-header no-print">
                        <div class="sheet-title">Sheet {{ $sheet['number'] }} of {{ $totalSheets }}</div>
                        <div>{{ $sheet['labels_count'] }} labels</div>
                    </div>
                    
                    <div class="sheet-content">
                        <div class="labels-container">
                            @for($i = 0; $i < $labelsPerA4; $i++)
                                @if(isset($sheet['products'][$i]))
                                    @php 
                                        $product = $sheet['products'][$i]; 
                                        $labelService = app(\App\Services\LabelService::class);
                                        $barcodeImage = $labelService->generateBarcode($product->CODE);
                                    @endphp
                                    @if($isGrid4x9)
                                        @php
                                            // Check if this is the custom template with improved sizing
                                            $isCustomGrid = $template->name === 'Grid 4x9 Custom (47x31mm)';
                                            
                                            if ($isCustomGrid) {
                                                // Improved sizing algorithm for Grid 4x9 Custom
                                                $nameLength = mb_strlen($product->NAME);
                                                $wordCount = str_word_count($product->NAME);
                                                
                                                // Find the largest font that fits the content
                                                if ($nameLength <= 10) {
                                                    $lengthClass = 'custom-tiny';      // 22pt, 1-2 lines
                                                } elseif ($nameLength <= 20) {
                                                    $lengthClass = 'custom-extra-short'; // 18pt, 2 lines max
                                                } elseif ($nameLength <= 30) {
                                                    $lengthClass = 'custom-short';      // 15pt, 2-3 lines
                                                } elseif ($nameLength <= 45 && $wordCount >= 4) {
                                                    // For longer names with multiple words, use medium size
                                                    $lengthClass = 'custom-medium';     // 13pt, 3-4 lines
                                                } elseif ($nameLength <= 60) {
                                                    $lengthClass = 'custom-long';       // 11pt, 4 lines
                                                } else {
                                                    $lengthClass = 'custom-extra-long'; // 9pt, 5 lines
                                                }
                                            } else {
                                                // Original logic for standard Grid 4x9
                                                $nameLength = mb_strlen($product->NAME);
                                                // More granular thresholds for better space utilization
                                                if ($nameLength <= 12) {
                                                    $lengthClass = 'extra-short';
                                                } elseif ($nameLength <= 20) {
                                                    $lengthClass = 'short';
                                                } elseif ($nameLength <= 30) {
                                                    $lengthClass = 'medium';
                                                } elseif ($nameLength <= 45) {
                                                    $lengthClass = 'long';
                                                } else {
                                                    $lengthClass = 'extra-long';
                                                }
                                            }
                                            
                                            $priceText = $product->getFormattedPriceWithVatAttribute();
                                            if ($isCustomGrid) {
                                                // Use mb_strlen for accurate character count (€ = 1 char, not 3 bytes)
                                                $priceLength = mb_strlen($priceText);
                                                // Better price classification: €9.99 = 5 chars, €32.95 = 6 chars
                                                $priceLengthClass = $priceLength <= 5 ? 'custom-normal' : ($priceLength <= 6 ? 'custom-long' : 'custom-extra-long');
                                            } else {
                                                // Use mb_strlen for accurate character count with UTF-8 (€ symbol)
                                                $priceLength = mb_strlen($priceText);
                                                // Adjusted thresholds: €9.99 = 5 chars, €15.95 = 6 chars
                                                $priceLengthClass = $priceLength <= 5 ? 'normal' : ($priceLength <= 6 ? 'long' : 'extra-long');
                                            }
                                        @endphp
                                        <div class="label label-4x9">
                                            <div class="label-name-4x9" data-length="{{ $lengthClass }}">{{ $product->NAME }}</div>
                                            
                                            <div class="label-middle-row-4x9">
                                                <div class="label-barcode-4x9">
                                                    <div class="barcode-visual-4x9">
                                                        {!! $barcodeImage !!}
                                                    </div>
                                                </div>
                                                
                                                <div class="label-price-4x9" data-price-length="{{ $priceLengthClass }}">{{ $priceText }}</div>
                                            </div>
                                            
                                            <div class="label-barcode-number-row-4x9">
                                                <div class="barcode-number-4x9">{{ $product->CODE }}</div>
                                            </div>
                                        </div>
                                    @else
                                        <div class="label">
                                            <div class="label-name">{{ $product->NAME }}</div>
                                            
                                            <div class="label-price-section">
                                                <div class="label-price">{{ $product->getFormattedPriceWithVatAttribute() }}</div>
                                            </div>
                                            
                                            <div class="label-barcode">
                                                <div class="barcode-visual">
                                                    {!! $barcodeImage !!}
                                                </div>
                                                <div class="barcode-number">{{ $product->CODE }}</div>
                                            </div>
                                        </div>
                                    @endif
                                @else
                                    <div class="empty-label"></div>
                                @endif
                            @endfor
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <script>
        let currentZoom = 100;
        
        function toggleDebug() {
            document.body.classList.toggle('debug-mode');
            console.log('Debug mode:', document.body.classList.contains('debug-mode') ? 'ON' : 'OFF');
        }
        
        function showMeasurements() {
            console.log('=== MEASUREMENTS ===');
            
            // Template values
            console.log('Template:', {
                labelWidth: '{{ $css['width'] ?? 'unknown' }}',
                labelHeight: '{{ $css['height'] ?? 'unknown' }}',
                margin: '{{ $css['margin'] ?? 'unknown' }}',
                labelsPerRow: {{ $labelsPerRow ?? 'null' }}
            });
            
            // Container actual size
            const container = document.querySelector('.labels-container');
            if (container) {
                const rect = container.getBoundingClientRect();
                console.log('Container actual:', {
                    width: rect.width + 'px',
                    cssWidth: window.getComputedStyle(container).width,
                    padding: window.getComputedStyle(container).padding
                });
            }
            
            // First label analysis
            const firstLabel = document.querySelector('.label');
            if (firstLabel) {
                const rect = firstLabel.getBoundingClientRect();
                const style = window.getComputedStyle(firstLabel);
                console.log('First label:', {
                    actualWidth: rect.width + 'px',
                    cssWidth: style.width,
                    padding: style.padding,
                    boxSizing: style.boxSizing
                });
                
                // Calculate if 4 labels fit
                const containerWidth = container ? container.getBoundingClientRect().width : 0;
                const totalLabelWidth = rect.width * {{ $labelsPerRow ?? 4 }};
                console.log('Width calculation:', {
                    containerWidth: containerWidth + 'px',
                    singleLabelWidth: rect.width + 'px',
                    totalFor4Labels: totalLabelWidth + 'px',
                    overflow: totalLabelWidth > containerWidth ? 'YES by ' + (totalLabelWidth - containerWidth) + 'px' : 'NO'
                });
            }
        }
        
        function updateZoom() {
            const content = document.getElementById('scalable-content');
            const zoomLevel = document.getElementById('zoom-level');
            content.style.transform = `scale(${currentZoom / 100})`;
            zoomLevel.textContent = currentZoom + '%';
        }
        
        function zoomIn() {
            if (currentZoom < 200) {
                currentZoom += 25;
                updateZoom();
            }
        }
        
        function zoomOut() {
            if (currentZoom > 50) {
                currentZoom -= 25;
                updateZoom();
            }
        }
        
        // Debug: Log label information
        document.addEventListener('DOMContentLoaded', function() {
            console.log('=== Label Debugging Info ===');
            
            // Debug container and label dimensions
            const labelsContainer = document.querySelector('.labels-container');
            if (labelsContainer) {
                const containerRect = labelsContainer.getBoundingClientRect();
                console.log('Labels Container:', {
                    width: containerRect.width + 'px',
                    computedWidth: window.getComputedStyle(labelsContainer).width,
                    gridColumns: window.getComputedStyle(labelsContainer).gridTemplateColumns,
                    gap: window.getComputedStyle(labelsContainer).gap
                });
            }
            
            // Debug individual labels
            document.querySelectorAll('.label').forEach((label, idx) => {
                const labelRect = label.getBoundingClientRect();
                const computedStyle = window.getComputedStyle(label);
                console.log(`Label ${idx + 1}:`, {
                    width: labelRect.width + 'px',
                    cssWidth: computedStyle.width,
                    padding: computedStyle.padding,
                    boxSizing: computedStyle.boxSizing,
                    overflow: computedStyle.overflow
                });
                
                // Check if label is overflowing container
                if (labelsContainer) {
                    const containerRect = labelsContainer.getBoundingClientRect();
                    if (labelRect.right > containerRect.right) {
                        console.error(`⚠️ Label ${idx + 1} is overflowing container by ${labelRect.right - containerRect.right}px`);
                    }
                }
            });
            
            // Debug middle row elements
            document.querySelectorAll('.label-middle-row-4x9').forEach((row, idx) => {
                const barcodeEl = row.querySelector('.label-barcode-4x9');
                const priceEl = row.querySelector('.label-price-4x9');
                
                if (barcodeEl && priceEl) {
                    const rowRect = row.getBoundingClientRect();
                    const barcodeRect = barcodeEl.getBoundingClientRect();
                    const priceRect = priceEl.getBoundingClientRect();
                    
                    console.log(`Middle Row ${idx + 1}:`, {
                        rowWidth: rowRect.width + 'px',
                        barcodeWidth: barcodeRect.width + 'px',
                        priceWidth: priceRect.width + 'px',
                        totalWidth: (barcodeRect.width + priceRect.width) + 'px',
                        overflow: (barcodeRect.width + priceRect.width) > rowRect.width ? 'YES' : 'NO',
                        barcodeFlex: window.getComputedStyle(barcodeEl).flex,
                        priceFlex: window.getComputedStyle(priceEl).flex
                    });
                    
                    // Check for overflow (console only, no visual indicator)
                    if ((barcodeRect.width + priceRect.width) > rowRect.width) {
                        console.error(`⚠️ Middle row ${idx + 1} content exceeds row width`);
                    }
                }
            });
            
            // Focus on name debugging only
            
            // Check name elements
            document.querySelectorAll('.label-name-4x9').forEach((el, idx) => {
                const computedStyle = window.getComputedStyle(el);
                const dataLength = el.getAttribute('data-length');
                const text = el.textContent.trim();
                console.log(`Name ${idx + 1}:`, {
                    dataLength: dataLength,
                    fontSize: computedStyle.fontSize,
                    whiteSpace: computedStyle.whiteSpace,
                    text: text.substring(0, 30) + (text.length > 30 ? '...' : ''),
                    textLength: text.length
                });
            });
            
            // Check template values
            console.log('Template info:', {
                name: '{{ $template->name ?? 'null' }}',
                isGrid4x9: {{ $isGrid4x9 ? 'true' : 'false' }},
                @if(isset($css))
                font_size_price: '{{ $css['font_size_price'] }}',
                font_size_name: '{{ $css['font_size_name'] }}',
                raw_font_size_price: {{ $template->font_size_price ?? 'null' }},
                raw_font_size_name: {{ $template->font_size_name ?? 'null' }},
                @endif
            });
            
            // Environment info
            console.log('Environment:', {
                app_env: '{{ config('app.env') }}',
                php_version: '{{ PHP_VERSION }}',
                charset: document.characterSet,
                userAgent: navigator.userAgent
            });
            
            // Visual test for name scaling
            setTimeout(() => {
                document.querySelectorAll('.label-name-4x9').forEach(el => {
                    const dataLength = el.getAttribute('data-length');
                    const rect = el.getBoundingClientRect();
                    const parentRect = el.parentElement.getBoundingClientRect();
                    const isOverflowing = el.scrollWidth > el.clientWidth;
                    
                    const computedStyle = window.getComputedStyle(el);
                    console.log(`Name analysis:`, {
                        text: el.textContent.trim().substring(0, 40) + '...',
                        category: dataLength,
                        fontSize: computedStyle.fontSize,
                        lineHeight: computedStyle.lineHeight,
                        isOverflowing: isOverflowing,
                        textWidth: el.scrollWidth,
                        availableWidth: el.clientWidth,
                        parentHeight: parentRect.height,
                        nameHeight: rect.height,
                        unusedHeight: parentRect.height - rect.height
                    });
                    
                    // Log overflowing text (console only, no visual indicator)
                    if (isOverflowing) {
                        console.warn('Text overflowing:', el.textContent.trim().substring(0, 40) + '...');
                    }
                    
                    // Check if text is being truncated with ellipsis
                    const hasEllipsis = el.scrollHeight > el.clientHeight;
                    if (hasEllipsis) {
                        console.warn('Text truncated:', el.textContent.trim());
                    }
                });
            }, 500);
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey || e.metaKey) {
                if (e.key === '=' || e.key === '+') {
                    e.preventDefault();
                    zoomIn();
                } else if (e.key === '-') {
                    e.preventDefault();
                    zoomOut();
                } else if (e.key === 'p') {
                    e.preventDefault();
                    window.print();
                }
            }
        });
    </script>
</body>
</html>