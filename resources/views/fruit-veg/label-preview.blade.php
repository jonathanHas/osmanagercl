<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fruit & Veg Labels - Preview</title>
    <style>
        @page {
            size: A4;
            margin: 5mm;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', 'Roboto', 'Ubuntu', 'Helvetica Neue', sans-serif;
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
            gap: 0.5mm;
            padding: 2mm;
        }

        .label {
            border: 1px solid #ccc;
            padding: 2mm;
            height: 32mm;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
            background: white;
        }


        .product-name {
            font-weight: 600;
            margin-bottom: 1mm;
            line-height: 1.1;
            max-height: 12mm;
            overflow: hidden;
            text-align: left;
            word-wrap: break-word;
            hyphens: auto;
            font-size: 12pt;
            letter-spacing: -0.02em;
        }

        .price-section {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0.5mm 0;
        }

        .price {
            font-size: 30pt;
            font-weight: 700;
            text-align: center;
            color: #333;
            letter-spacing: -0.03em;
        }

        .price-unit {
            font-size: 10pt;
            color: #666;
            font-weight: 400;
            letter-spacing: normal;
        }

        .info-section {
            border-top: 1px solid #ddd;
            padding-top: 1mm;
            margin-top: auto;
        }

        .origin-class-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 8pt;
            font-weight: 500;
        }

        .origin-info {
            text-align: left;
            color: #666;
            letter-spacing: 0.01em;
        }

        .class-info {
            text-align: right;
            color: #666;
            letter-spacing: 0.01em;
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
        $chunks = $products->chunk(16); // 16 labels per page (4x4 grid)
    @endphp

    @foreach($chunks as $chunk)
    <div class="page">
        <div class="labels-grid">
            @foreach($chunk as $product)
            <div class="label">
                <div class="product-name" data-text="{{ $product->NAME }}">
                    {{ $product->NAME }}
                </div>

                <div class="price-section">
                    <div class="price">€{{ number_format($product->current_price, 2) }} <span class="price-unit">per {{ $product->vegDetails->unit_name ?? 'kg' }}</span></div>
                </div>

                <div class="info-section">
                    <div class="origin-class-row">
                        @if($product->vegDetails && $product->vegDetails->country)
                        <span class="origin-info">Origin: {{ $product->vegDetails->country->name }}</span>
                        @endif

                        @if($product->vegDetails && $product->vegDetails->class_name)
                        <span class="class-info">Class: {{ $product->vegDetails->class_name }}</span>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach

            @if($chunk->count() < 16)
                @for($i = $chunk->count(); $i < 16; $i++)
                <div class="label" style="visibility: hidden;"></div>
                @endfor
            @endif
        </div>
    </div>
    @endforeach

    <script>
        // Auto-scale product names to fit available space
        function scaleProductNames() {
            document.querySelectorAll('.product-name').forEach(function(element) {
                const maxHeight = 12; // max height in mm (matches CSS)
                const maxHeightPx = maxHeight * 3.78; // Convert mm to pixels (approximate)

                // Start with the CSS defined font size
                let fontSize = 12;
                element.style.fontSize = fontSize + 'pt';
                element.style.lineHeight = '1.1';

                // Increase font size until we hit constraints
                while (fontSize < 40 && element.scrollHeight <= maxHeightPx) {
                    fontSize += 0.5;
                    element.style.fontSize = fontSize + 'pt';
                }

                // Decrease if we went too far
                while (element.scrollHeight > maxHeightPx && fontSize > 8) {
                    fontSize -= 0.5;
                    element.style.fontSize = fontSize + 'pt';
                }

                // Final adjustment - ensure text fits well
                if (element.scrollHeight > maxHeightPx) {
                    element.style.fontSize = '8pt';
                }
            });
        }

        // Auto-print on load
        window.onload = function() {
            scaleProductNames();
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
