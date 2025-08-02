<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Labels - A4 Sheet</title>
    <style>
        @page {
            size: A4;
            margin: 10mm;
        }
        
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background: white;
        }
        
        @php
            $labelsPerA4 = $template->labels_per_a4;
            $usableWidth = 190; // A4 usable width in mm
            $usableHeight = 277; // A4 usable height in mm
            $labelsPerRow = floor($usableWidth / $template->width_mm);
            $labelsPerColumn = floor($usableHeight / $template->height_mm);
            $css = $template->css_dimensions;
            $isGrid4x9 = isset($template->layout_config['type']) && $template->layout_config['type'] === 'grid_4x9';
        @endphp
        
        .labels-container {
            display: grid;
            grid-template-columns: repeat({{ $labelsPerRow }}, 1fr);
            gap: 0;
            width: 190mm;
            height: 277mm;
        }
        
        .label {
            width: {{ $css['width'] }};
            height: {{ $css['height'] }};
            border: none;
            padding: {{ $css['margin'] }};
            display: flex;
            flex-direction: column;
            font-size: 8pt;
            line-height: 1.2;
            page-break-inside: avoid;
            box-sizing: border-box;
            overflow: hidden;
        }
        
        @if($isGrid4x9)
        .label-4x9 {
            position: relative;
            display: flex;
            flex-direction: column;
            height: auto !important; /* Override fixed height */
            min-height: {{ $css['height'] }}; /* Maintain minimum size */
            overflow: visible !important; /* Allow large text to show */
        }
        
        .label-name-4x9 {
            font-weight: 600;
            font-size: {{ $css['font_size_name'] }};
            line-height: 1.15;
            overflow: hidden;
            word-break: break-word;
            hyphens: auto;
            display: block;
            flex-grow: 1;
            flex-shrink: 1;
            margin-bottom: 1px;
        }
        
        .label-bottom-row-4x9 {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
            height: auto;
            min-height: 20px;
            margin-top: auto;
        }
        
        .label-barcode-4x9 {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            flex: 0 0 40%; /* Restore original barcode width */
            padding-right: 1px;
        }
        
        .barcode-visual-4x9 {
            height: 10px;
            margin-bottom: 1px;
        }
        
        .barcode-visual-4x9 svg {
            width: auto;
            height: 100%;
            max-width: 100%;
        }
        
        .barcode-number-4x9 {
            font-family: monospace;
            font-size: 5.5pt;
            letter-spacing: 0.1px;
            color: #666;
            line-height: 1;
        }
        
        .label .label-4x9 .label-price-4x9 {
            font-size: {{ $css['font_size_price'] }} !important;
            font-weight: 900 !important;
            color: #000 !important;
            text-align: right; /* Back to right alignment */
            line-height: 1.1 !important;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            flex: 0 0 60%; /* Restore original price width */
            height: auto !important;
            max-height: none !important;
            overflow: visible !important;
            font-family: Arial, sans-serif !important;
            white-space: nowrap;
            padding-left: 1px;
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
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .no-print {
                display: none !important;
            }
        }
        
        .print-info {
            position: fixed;
            top: 10px;
            right: 10px;
            background: #f0f0f0;
            padding: 10px;
            border-radius: 5px;
            font-size: 12px;
            z-index: 1000;
        }
    </style>
</head>
<body>
    <div class="print-info no-print">
        <strong>{{ count($products) }} labels</strong> ready to print
        <br>
        <button onclick="window.print()" style="margin-top: 5px; padding: 5px 10px;">Print Now</button>
        <button onclick="window.close()" style="margin-top: 5px; padding: 5px 10px; margin-left: 5px;">Close</button>
    </div>

    <div class="labels-container">
        @for($i = 0; $i < $labelsPerA4; $i++)
            @if(isset($products[$i]))
                @php 
                    $product = $products[$i]; 
                    $labelService = app(\App\Services\LabelService::class);
                    $barcodeImage = $labelService->generateBarcode($product->CODE);
                @endphp
                @if($isGrid4x9)
                    <div class="label label-4x9">
                        <div class="label-name-4x9 auto-resize-text">{{ $product->NAME }}</div>
                        
                        <div class="label-bottom-row-4x9">
                            <div class="label-barcode-4x9">
                                <div class="barcode-visual-4x9">
                                    {!! $barcodeImage !!}
                                </div>
                                <div class="barcode-number-4x9">{{ $product->CODE }}</div>
                            </div>
                            
                            <div class="label-price-4x9" style="font-size: {{ $css['font_size_price'] }} !important; font-weight: 900 !important; color: #000 !important;">{{ $product->getFormattedPriceWithVatAttribute() }}</div>
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

    <script>
        function autoResizeText() {
            @if($isGrid4x9)
            document.querySelectorAll('.auto-resize-text').forEach(function(element) {
                // Get the actual available height
                const parentElement = element.closest('.label-4x9');
                const bottomRow = parentElement.querySelector('.label-bottom-row-4x9');
                const parentHeight = parentElement.offsetHeight;
                const bottomRowHeight = bottomRow ? bottomRow.offsetHeight : 20;
                const padding = parseInt(window.getComputedStyle(parentElement).paddingTop) + 
                                parseInt(window.getComputedStyle(parentElement).paddingBottom);
                const availableHeight = parentHeight - bottomRowHeight - padding - 4; // 4px safety margin
                
                const textLength = element.textContent.trim().length;
                let fontSize;
                
                // Start with much larger font sizes
                if (textLength <= 8) {
                    fontSize = 24; // Very short text
                } else if (textLength <= 15) {
                    fontSize = 20; // Short text  
                } else if (textLength <= 25) {
                    fontSize = 16; // Medium text
                } else if (textLength <= 35) {
                    fontSize = 14; // Longer text
                } else if (textLength <= 45) {
                    fontSize = 12; // Long text
                } else {
                    fontSize = 10; // Very long text
                }
                
                element.style.fontSize = fontSize + 'pt';
                element.style.lineHeight = '1.15';
                element.style.overflow = 'hidden';
                element.style.height = 'auto';
                element.style.maxHeight = availableHeight + 'px';
                
                // Aggressively increase font size to fill space
                const maxFontSize = textLength <= 5 ? 32 : (textLength <= 10 ? 28 : (textLength <= 20 ? 24 : 20));
                let attempts = 0;
                while (fontSize < maxFontSize && element.scrollHeight < availableHeight - 2 && attempts < 20) {
                    fontSize += 1;
                    element.style.fontSize = fontSize + 'pt';
                    attempts++;
                }
                
                // Fine-tune downward if needed
                while (element.scrollHeight > availableHeight && fontSize > 8) {
                    fontSize -= 0.5;
                    element.style.fontSize = fontSize + 'pt';
                }
                
                // Final adjustment
                if (element.scrollHeight > availableHeight) {
                    element.style.fontSize = Math.max(8, fontSize - 1) + 'pt';
                }
            });
            @endif
        }

        window.onload = function() {
            // Auto-resize text for 4x9 grid
            autoResizeText();
            
            // Run again after layout settles
            setTimeout(function() {
                autoResizeText();
            }, 100);
            
            // Auto-print when page loads
            setTimeout(function() {
                window.print();
            }, 1200); // Increased delay to allow text resizing
        }
    </script>
</body>
</html>