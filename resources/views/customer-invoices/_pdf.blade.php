@php
    $business = config('app.business', []);
    $certPath = ! empty($business['organic_cert_image'])
        ? public_path($business['organic_cert_image'])
        : null;
    $certData = ($certPath && is_file($certPath))
        ? 'data:image/png;base64,' . base64_encode(file_get_contents($certPath))
        : null;

    // Compose a single address line for the footer
    $addressBits = array_filter([
        $business['address_line1'] ?? null,
        $business['address_line2'] ?? null,
        $business['county'] ?? null,
    ]);
    $footerAddress = implode(', ', $addressBits);
    if (! empty($business['postcode'])) {
        $footerAddress .= '. ' . $business['postcode'];
    }

    // Footer contact row
    $contactBits = array_filter([
        ! empty($business['phone']) ? 'Tel: ' . $business['phone'] : null,
        $business['email'] ?? null,
        ! empty($business['vat']) ? 'VAT: ' . $business['vat'] : null,
    ]);
    $contactLine = implode(' · ', $contactBits);
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number ?? '(Draft)' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; margin: 0; padding: 0; }
        .container { padding: 30px 30px 110px; }
        .header { width: 100%; border-bottom: 2px solid #222; padding-bottom: 12px; margin-bottom: 18px; }
        .header td { vertical-align: top; padding: 0; border: 0; }
        .business-name { font-size: 15px; font-weight: bold; }
        .business-line { font-size: 10px; color: #555; line-height: 1.45; }
        .meta { text-align: right; font-size: 11px; }
        .meta .label { font-size: 10px; letter-spacing: 0.08em; text-transform: uppercase; color: #888; }
        .meta .num { font-size: 16px; font-weight: bold; color: #444; margin: 2px 0 4px; }
        .billto { margin-bottom: 16px; }
        .billto h3 { font-size: 10px; text-transform: uppercase; letter-spacing: 0.08em; color: #888; margin: 0 0 4px; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th, td { padding: 5px 6px; border-bottom: 1px solid #ddd; font-size: 10.5px; }
        th { background: #f4f4f4; text-align: left; }
        .num { text-align: right; }
        .totals { width: 50%; margin-left: 50%; font-size: 11px; }
        .totals td { border: none; padding: 3px 6px; }
        .totals .grand { font-size: 14px; font-weight: bold; border-top: 2px solid #222; padding-top: 6px; }
        .vat-breakdown { width: 60%; margin-bottom: 12px; }
        .notes { font-size: 10px; color: #666; margin-top: 18px; border-top: 1px solid #ddd; padding-top: 8px; white-space: pre-line; }
        .status-void { color: #c00; font-weight: bold; font-size: 14px; }

        /* Footer with org cert + contact line — fixed at bottom of every page */
        .pdf-footer {
            position: fixed;
            bottom: 22px; left: 30px; right: 30px;
            border-top: 1px solid #ccc;
            padding-top: 8px;
            font-size: 9.5px;
            color: #666;
        }
        .pdf-footer table { width: 100%; border-collapse: collapse; }
        .pdf-footer td { border: 0; padding: 0; vertical-align: middle; }
        .pdf-footer .contact { line-height: 1.5; }
        .pdf-footer .cert { text-align: right; }
        .pdf-footer .cert img { height: 64px; }
    </style>
</head>
<body>
<div class="container">
    <table class="header">
        <tr>
            <td style="width: 60%;">
                <div class="business-name">{{ $business['name'] ?? config('app.name', 'Our Shop') }}</div>
                @if (! empty($business['address_line1']))
                    <div class="business-line">{{ $business['address_line1'] }}</div>
                @endif
                @if (! empty($business['address_line2']))
                    @php
                        $line2Bits = array_filter([
                            $business['address_line2'] ?? null,
                            $business['county'] ?? null,
                        ]);
                        $line2 = implode(', ', $line2Bits);
                        if (! empty($business['postcode'])) {
                            $line2 .= '. ' . $business['postcode'];
                        }
                    @endphp
                    <div class="business-line">{{ $line2 }}</div>
                @endif
                @php
                    $contactParts = array_filter([
                        ! empty($business['phone']) ? 'Phone: ' . $business['phone'] : null,
                        ! empty($business['email']) ? 'Email: ' . $business['email'] : null,
                    ]);
                @endphp
                @if (! empty($contactParts))
                    <div class="business-line">{{ implode(' · ', $contactParts) }}</div>
                @endif
                @if (! empty($business['vat']))
                    <div class="business-line">VAT: {{ $business['vat'] }}</div>
                @endif
            </td>
            <td class="meta">
                <div class="label">Invoice</div>
                <div class="num">{{ $invoice->invoice_number ?? '(Draft)' }}</div>
                <div>Issue date: {{ $invoice->issue_date->format('d M Y') }}</div>
                @if ($invoice->due_date)
                    <div>Due date: {{ $invoice->due_date->format('d M Y') }}</div>
                @endif
                @if ($invoice->isVoid())
                    <div class="status-void">VOIDED</div>
                @endif
            </td>
        </tr>
    </table>

    <div class="billto">
        <h3>Bill To</h3>
        <strong>{{ $invoice->customer_name }}</strong>
        @if ($invoice->customer_address)
            <div>{!! nl2br(e($invoice->customer_address)) !!}</div>
        @endif
        @if ($invoice->customer_email)
            <div>{{ $invoice->customer_email }}</div>
        @endif
        @if ($invoice->customer_vat_number)
            <div>VAT: {{ $invoice->customer_vat_number }}</div>
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th class="num">Qty</th>
                <th class="num">Unit Net</th>
                <th class="num">VAT %</th>
                <th class="num">Net</th>
                <th class="num">VAT</th>
                <th class="num">Gross</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td>
                        {{ $item->description }}
                        @if ($item->pos_product_code)
                            <span style="color:#999; font-size:9px;">({{ $item->pos_product_code }})</span>
                        @endif
                    </td>
                    <td class="num">{{ rtrim(rtrim(number_format($item->quantity, 3), '0'), '.') }}</td>
                    <td class="num">€{{ number_format($item->unit_price, 4) }}</td>
                    <td class="num">{{ number_format($item->vat_rate * 100, 1) }}%</td>
                    <td class="num">€{{ number_format($item->net_amount, 2) }}</td>
                    <td class="num">€{{ number_format($item->vat_amount, 2) }}</td>
                    <td class="num">€{{ number_format($item->gross_amount, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="vat-breakdown">
        <thead>
            <tr>
                <th>VAT Rate</th>
                <th class="num">Net</th>
                <th class="num">VAT</th>
                <th class="num">Gross</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($invoice->getVatBreakdown() as $band)
                <tr>
                    <td>{{ $band['code'] }} ({{ number_format($band['rate'] * 100, 1) }}%)</td>
                    <td class="num">€{{ number_format($band['net_amount'], 2) }}</td>
                    <td class="num">€{{ number_format($band['vat_amount'], 2) }}</td>
                    <td class="num">€{{ number_format($band['gross_amount'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" style="text-align:center; color:#999;">No VAT lines</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals">
        @if ($invoice->hasDiscount())
            <tr><td>Subtotal (before discount):</td><td class="num">€{{ number_format($invoice->getPreDiscountNet(), 2) }}</td></tr>
            <tr style="color:#a14;">
                <td>Discount ({{ rtrim(rtrim(number_format($invoice->discount_percent, 2), '0'), '.') }}%):</td>
                <td class="num">−€{{ number_format($invoice->getDiscountAmount(), 2) }}</td>
            </tr>
        @endif
        <tr><td>Subtotal (net):</td><td class="num">€{{ number_format($invoice->subtotal, 2) }}</td></tr>
        <tr><td>VAT:</td><td class="num">€{{ number_format($invoice->vat_total, 2) }}</td></tr>
        <tr class="grand"><td>Total:</td><td class="num">€{{ number_format($invoice->total, 2) }}</td></tr>
    </table>

    @if ($invoice->notes)
        <div class="notes">{{ $invoice->notes }}</div>
    @endif
</div>

<div class="pdf-footer">
    <table>
        <tr>
            <td class="contact" style="width: 70%;">
                <strong>{{ $business['name'] ?? '' }}</strong>
                @if ($footerAddress)
                    · {{ $footerAddress }}
                @endif
                <br>
                {{ $contactLine }}
            </td>
            <td class="cert">
                @if ($certData)
                    <img src="{{ $certData }}" alt="Certified Organic IE-ORG-03 Licence No. 1139">
                @endif
            </td>
        </tr>
    </table>
</div>
</body>
</html>
