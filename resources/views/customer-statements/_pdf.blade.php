@php
    $business = config('app.business', []);
    $certPath = ! empty($business['organic_cert_image'])
        ? public_path($business['organic_cert_image'])
        : null;
    $certData = ($certPath && is_file($certPath))
        ? 'data:image/png;base64,' . base64_encode(file_get_contents($certPath))
        : null;

    $addressBits = array_filter([
        $business['address_line1'] ?? null,
        $business['address_line2'] ?? null,
        $business['county'] ?? null,
    ]);
    $footerAddress = implode(', ', $addressBits);
    if (! empty($business['postcode'])) {
        $footerAddress .= '. ' . $business['postcode'];
    }
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
        .meta .num { font-size: 14px; font-weight: bold; color: #444; margin: 2px 0 4px; }
        .billto { margin-bottom: 16px; }
        .billto h3 { font-size: 10px; text-transform: uppercase; letter-spacing: 0.08em; color: #888; margin: 0 0 4px; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th, td { padding: 5px 6px; border-bottom: 1px solid #ddd; font-size: 10.5px; }
        th { background: #f4f4f4; text-align: left; }
        .num { text-align: right; }
        .opening { background: #fafafa; font-style: italic; color: #555; }
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
                <div class="label">Statement</div>
                <div class="num">{{ $to->format('d M Y') }}</div>
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

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Description</th>
                <th class="num">Debit</th>
                <th class="num">Credit</th>
                <th class="num">Balance</th>
            </tr>
        </thead>
        <tbody>
            @if ($from && abs($opening_balance) > 0.005)
                <tr class="opening">
                    <td>{{ $from->copy()->subDay()->format('d M Y') }}</td>
                    <td>Opening balance</td>
                    <td></td><td></td>
                    <td class="num">€{{ number_format($opening_balance, 2) }}</td>
                </tr>
            @endif
            @forelse ($events as $e)
                <tr>
                    <td>{{ $e['date']->format('d M Y') }}</td>
                    <td>{{ $e['description'] }}</td>
                    <td class="num">@if ($e['debit'] > 0)€{{ number_format($e['debit'], 2) }}@endif</td>
                    <td class="num">@if ($e['credit'] > 0)€{{ number_format($e['credit'], 2) }}@endif</td>
                    <td class="num">€{{ number_format($e['balance'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" style="text-align:center; color:#999;">No activity in this period.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="summary">
        <tr><td>Opening balance:</td><td class="num">€{{ number_format($opening_balance, 2) }}</td></tr>
        <tr><td>Invoiced in period:</td><td class="num">€{{ number_format($range_invoiced, 2) }}</td></tr>
        <tr><td>Paid in period:</td><td class="num">€{{ number_format($range_paid, 2) }}</td></tr>
        <tr class="grand">
            <td>Closing balance:</td>
            <td class="num">
                @if ($closing_balance < -0.005)
                    <span class="credit-note">€{{ number_format(abs($closing_balance), 2) }} credit</span>
                @else
                    €{{ number_format($closing_balance, 2) }}
                @endif
            </td>
        </tr>
    </table>
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
