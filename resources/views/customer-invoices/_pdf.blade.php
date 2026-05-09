<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number ?? '(Draft)' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; margin: 0; padding: 0; }
        .container { padding: 30px; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #222; padding-bottom: 12px; margin-bottom: 18px; }
        .business { font-size: 14px; font-weight: bold; }
        .meta { text-align: right; font-size: 11px; }
        .meta .num { font-size: 16px; font-weight: bold; color: #444; }
        .billto { margin-bottom: 16px; }
        .billto h3 { font-size: 11px; text-transform: uppercase; color: #888; margin: 0 0 4px; }
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
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="business">
            {{ config('app.business.name', config('app.name', 'Our Shop')) }}
            @if (config('app.business.address'))
                <div style="font-weight: normal; font-size: 10px; color: #666;">{{ config('app.business.address') }}</div>
            @endif
            @if (config('app.business.vat'))
                <div style="font-weight: normal; font-size: 10px; color: #666;">VAT: {{ config('app.business.vat') }}</div>
            @endif
        </div>
        <div class="meta">
            <div>INVOICE</div>
            <div class="num">{{ $invoice->invoice_number ?? '(Draft)' }}</div>
            <div>Issue date: {{ $invoice->issue_date->format('d M Y') }}</div>
            @if ($invoice->due_date)
                <div>Due date: {{ $invoice->due_date->format('d M Y') }}</div>
            @endif
            @if ($invoice->isVoid())
                <div class="status-void">VOIDED</div>
            @endif
        </div>
    </div>

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
        <tr><td>Subtotal (net):</td><td class="num">€{{ number_format($invoice->subtotal, 2) }}</td></tr>
        <tr><td>VAT:</td><td class="num">€{{ number_format($invoice->vat_total, 2) }}</td></tr>
        <tr class="grand"><td>Total:</td><td class="num">€{{ number_format($invoice->total, 2) }}</td></tr>
    </table>

    @if ($invoice->notes)
        <div class="notes">{{ $invoice->notes }}</div>
    @endif
</div>
</body>
</html>
