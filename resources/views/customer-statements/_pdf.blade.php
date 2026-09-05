
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Statement — {{ $customer->name }}</title>
    <style>
        body { font-family: Helvetica, sans-serif; font-size: 11px; color: #222; margin: 0; padding: 0; }
        .container { padding: 30px 30px 110px; }
        .header { width: 100%; border-bottom: 2px solid #222; padding-bottom: 12px; margin-bottom: 18px; }
        .header td { vertical-align: top; padding: 0; border: 0; }
        .business-name { font-size: 15px; font-weight: bold; }
        .business-line { font-size: 10px; color: #555; line-height: 1.45; }
        .meta { text-align: right; font-size: 11px; }
        .meta .label { font-size: 10px; letter-spacing: 0.08em; text-transform: uppercase; color: #888; }
        .meta .big { font-size: 14px; font-weight: bold; color: #444; margin: 2px 0 4px; }
        .billto { margin-bottom: 16px; }
        .billto h3 { font-size: 10px; text-transform: uppercase; letter-spacing: 0.08em; color: #888; margin: 0 0 4px; font-weight: 600; }
        h4.section { font-size: 10px; text-transform: uppercase; letter-spacing: 0.08em; color: #888; margin: 16px 0 6px; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th, td { padding: 5px 6px; border-bottom: 1px solid #ddd; font-size: 10.5px; }
        th { background: #f4f4f4; text-align: left; }
        thead { display: table-header-group; }
        /* Ledger rows are now 2-3 lines tall; without this Dompdf splits one
           across a page boundary. Mirrors print.blade.php. */
        tr { page-break-inside: avoid; }
        .num { text-align: right; }
        /* Allocation detail under a ledger description. */
        .alloc { font-size: 9.5px; color: #666; margin-top: 1px; }
        .oncredit { color: #060; }
        .opening { background: #fafafa; font-style: italic; color: #555; }
        .overdue { color: #b00; }
        .muted { color: #888; }
        .aging td, .aging th { text-align: right; }
        .aging th:first-child, .aging td:first-child { text-align: left; }
        .aging .bucket-total { font-weight: bold; }
        .summary { width: 50%; margin-left: 50%; font-size: 11px; }
        .summary td { border: none; padding: 3px 6px; }
        .summary .grand { font-size: 14px; font-weight: bold; border-top: 2px solid #222; padding-top: 6px; }
        .credit-note { color: #060; font-weight: bold; }

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
                @if ($businessLine2)
                    <div class="business-line">{{ $businessLine2 }}</div>
                @endif
                @if (! empty($contactParts))
                    <div class="business-line">{{ implode(' · ', $contactParts) }}</div>
                @endif
                @if (! empty($business['vat']))
                    <div class="business-line">VAT: {{ $business['vat'] }}</div>
                @endif
            </td>
            <td class="meta">
                <div class="label">Statement</div>
                <div class="big">{{ $to->format('d M Y') }}</div>
                @if ($from)
                    <div>From {{ $from->format('d M Y') }}</div>
                @else
                    <div>All activity</div>
                @endif
                <div>To {{ $to->format('d M Y') }}</div>
            </td>
        </tr>
    </table>

    <div class="billto">
        <h3>Account Holder</h3>
        <strong>{{ $customer->name }}</strong>
        @php
            $addr = array_filter([$customer->address_line1, $customer->address_line2, $customer->city, $customer->postcode, $customer->country]);
        @endphp
        @if (count($addr))
            <div>{!! nl2br(e(implode("\n", $addr))) !!}</div>
        @endif
        @if ($customer->vat_number)
            <div>VAT: {{ $customer->vat_number }}</div>
        @endif
    </div>

    @include('customer-statements._body')
</div>

<div class="pdf-footer">
    <table>
        <tr>
            <td style="width: 70%;">
                <strong>{{ $business['name'] ?? '' }}</strong>
                @if ($footerAddress) · {{ $footerAddress }} @endif
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
