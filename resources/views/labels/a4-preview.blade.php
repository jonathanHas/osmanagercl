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
        @endphp
        
        body {
            margin: 0;
            padding: 20px;
            font-family: Arial, sans-serif;
            background: #f5f5f5;
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
            grid-template-columns: repeat({{ $labelsPerRow }}, 1fr);
            gap: 0;
            width: 190mm;
            min-height: 277mm;
            border: 1px solid #ddd;
            padding: 10mm;
            background: white;
        }
        
        .label {
            width: {{ $css['width'] }};
            height: {{ $css['height'] }};
            border: 1px dashed #ddd;
            padding: {{ $css['margin'] }};
            display: flex;
            flex-direction: column;
            font-size: 8pt;
            line-height: 1.2;
            box-sizing: border-box;
            overflow: hidden;
        }
        
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