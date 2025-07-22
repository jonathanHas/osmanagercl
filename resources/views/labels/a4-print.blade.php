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
        
        .labels-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            grid-template-rows: repeat(8, 1fr);
            gap: 2mm;
            width: 190mm;
            height: 277mm;
        }
        
        .label {
            width: 58mm;
            height: 32mm;
            border: 1px dashed #ccc;
            padding: 2mm;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            font-size: 8pt;
            line-height: 1.2;
            page-break-inside: avoid;
            box-sizing: border-box;
        }
        
        .label-name {
            font-weight: bold;
            font-size: 9pt;
            height: 2.4em;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            margin-bottom: 2mm;
        }
        
        .label-barcode {
            text-align: center;
            margin: 2mm 0;
        }
        
        .barcode-visual {
            display: flex;
            justify-content: center;
            height: 15mm;
            margin: 1mm 0;
        }
        
        .barcode-bars {
            display: flex;
            align-items: end;
            height: 100%;
        }
        
        .bar {
            background: black;
            margin: 0 0.5px;
        }
        
        .barcode-number {
            font-family: monospace;
            font-size: 7pt;
            letter-spacing: 1px;
            margin-top: 1mm;
        }
        
        .label-price-section {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-top: auto;
        }
        
        .label-price {
            font-size: 12pt;
            font-weight: bold;
        }
        
        .label-price-vat {
            font-size: 6pt;
            color: #666;
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
        @for($i = 0; $i < 24; $i++)
            @if(isset($products[$i]))
                @php $product = $products[$i]; @endphp
                <div class="label">
                    <div class="label-name">{{ $product->NAME }}</div>
                    
                    <div class="label-barcode">
                        <div class="barcode-visual">
                            <div class="barcode-bars">
                                @php
                                    // Simple barcode pattern generator
                                    $barcode = $product->CODE;
                                    $barcodeArray = str_split($barcode);
                                @endphp
                                @foreach($barcodeArray as $digit)
                                    @php
                                        $width = (intval($digit) % 3) + 1;
                                        $height = 70 + (intval($digit) % 4) * 5; // Varying heights
                                    @endphp
                                    <div class="bar" style="width: {{ $width }}px; height: {{ $height }}%;"></div>
                                    @if(intval($digit) % 2 == 0)
                                        <div style="width: 1px;"></div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                        <div class="barcode-number">{{ $product->CODE }}</div>
                    </div>
                    
                    <div class="label-price-section">
                        <div class="label-price">€{{ number_format($product->PRICESELL, 2) }}</div>
                        <div class="label-price-vat">{{ $product->getFormattedPriceWithVatAttribute() }} incl. VAT</div>
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