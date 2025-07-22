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

    <script>
        window.onload = function() {
            // Auto-print when page loads
            setTimeout(function() {
                window.print();
            }, 500);
        }
    </script>
</body>
</html>