<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fruit & Veg Labels - Preview</title>
    <style>
        @page {
            size: A4;
            margin: 10mm;
        }
        
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            font-size: 12pt;
            line-height: 1.2;
        }
        
        .page {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            background: white;
            box-shadow: 0 0 5mm rgba(0,0,0,0.1);
        }
        
        @media print {
            body {
                margin: 0;
                box-shadow: none;
            }
            .page {
                margin: 0;
                box-shadow: none;
                page-break-after: always;
            }
            .no-print {
                display: none;
            }
        }
        
        .labels-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 5mm;
            padding: 10mm;
        }
        
        .label {
            border: 1px solid #ddd;
            padding: 8mm;
            height: 120mm;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
        }
        
        .organic-badge {
            position: absolute;
            top: 5mm;
            right: 5mm;
            width: 25mm;
            height: 25mm;
            background: #10b981;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 10pt;
            text-align: center;
            line-height: 1.1;
            padding: 3mm;
        }
        
        .product-name {
            font-size: 20pt;
            font-weight: bold;
            margin-bottom: 5mm;
            line-height: 1.2;
            max-height: 30mm;
            overflow: hidden;
        }
        
        .price-section {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 10mm 0;
        }
        
        .price {
            font-size: 48pt;
            font-weight: bold;
            text-align: center;
        }
        
        .price-unit {
            font-size: 18pt;
            color: #666;
            margin-top: 2mm;
            text-align: center;
        }
        
        .info-section {
            border-top: 2px solid #ddd;
            padding-top: 5mm;
            margin-top: auto;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3mm;
            font-size: 14pt;
        }
        
        .info-label {
            font-weight: bold;
            color: #666;
        }
        
        .info-value {
            text-align: right;
        }
        
        .barcode-section {
            text-align: center;
            margin-top: 5mm;
            padding-top: 5mm;
            border-top: 1px solid #eee;
        }
        
        .barcode {
            font-family: 'Courier New', monospace;
            font-size: 10pt;
            color: #666;
        }
        
        /* Print button styles */
        .print-controls {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 10px 20px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            background: #2563eb;
        }
        
        .btn-secondary {
            background: #6b7280;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
        }
    </style>
</head>
<body>
    <div class="print-controls no-print">
        <button onclick="window.print()" class="btn">Print Labels</button>
        <button onclick="markAsPrinted()" class="btn btn-secondary">Mark All as Printed</button>
        <a href="{{ route('fruit-veg.labels') }}" class="btn btn-secondary">Back to Labels</a>
    </div>

    @php
        $chunks = $products->chunk(4); // 4 labels per page (2x2 grid)
    @endphp

    @foreach($chunks as $chunk)
    <div class="page">
        <div class="labels-grid">
            @foreach($chunk as $product)
            <div class="label">
                <div class="organic-badge">
                    CERTIFIED<br>ORGANIC
                </div>
                
                <div class="product-name">
                    @if($product->DISPLAY)
                        {{ $product->DISPLAY }}
                    @else
                        {{ $product->NAME }}
                    @endif
                </div>
                
                <div class="price-section">
                    <div>
                        <div class="price">€{{ number_format($product->current_price, 2) }}</div>
                        <div class="price-unit">per {{ $product->vegDetails->unit_name ?? 'kg' }}</div>
                    </div>
                </div>
                
                <div class="info-section">
                    @if($product->vegDetails && $product->vegDetails->country)
                    <div class="info-row">
                        <span class="info-label">Origin:</span>
                        <span class="info-value">{{ $product->vegDetails->country->country }}</span>
                    </div>
                    @endif
                    
                    @if($product->vegDetails && $product->vegDetails->class_name)
                    <div class="info-row">
                        <span class="info-label">Class:</span>
                        <span class="info-value">{{ $product->vegDetails->class_name }}</span>
                    </div>
                    @endif
                    
                    @if($product->CODE)
                    <div class="barcode-section">
                        <div class="barcode">{{ $product->CODE }}</div>
                    </div>
                    @endif
                </div>
            </div>
            @endforeach
            
            @if($chunk->count() < 4)
                @for($i = $chunk->count(); $i < 4; $i++)
                <div class="label" style="visibility: hidden;"></div>
                @endfor
            @endif
        </div>
    </div>
    @endforeach

    <script>
        // Auto-print on load
        window.onload = function() {
            // Uncomment the line below to enable auto-print
            // window.print();
        }
        
        async function markAsPrinted() {
            if (!confirm('Mark all these labels as printed?')) return;
            
            try {
                const productCodes = @json($products->pluck('CODE'));
                
                const response = await fetch('{{ route('fruit-veg.labels.printed') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        products: productCodes
                    })
                });
                
                if (response.ok) {
                    alert('Labels marked as printed successfully!');
                    window.location.href = '{{ route('fruit-veg.labels') }}';
                } else {
                    alert('Failed to mark labels as printed');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred');
            }
        }
    </script>
</body>
</html>