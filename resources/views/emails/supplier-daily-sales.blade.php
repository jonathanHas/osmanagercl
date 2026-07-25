@php
    use Carbon\Carbon;

    $supplier = $report['supplier'];
    $date = $report['date'] instanceof Carbon ? $report['date'] : Carbon::parse($report['date']);
    $items = $report['items'];
    $totals = $report['totals'];
    $showValues = $report['show_values'] ?? true;
    $attachCsv = $report['attach_csv'] ?? true;

    $money = fn ($v) => '&euro;' . number_format((float) $v, 2);
    $units = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
    $trendArrow = ['up' => '&#9650;', 'down' => '&#9660;', 'stable' => '&ndash;'];
    $trendColour = ['up' => '#16a34a', 'down' => '#dc2626', 'stable' => '#6b7280'];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily product sales</title>
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6; font-family:Arial, Helvetica, sans-serif; color:#111827;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f4f6; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="width:640px; max-width:100%; background-color:#ffffff; border-radius:8px; overflow:hidden; border:1px solid #e5e7eb;">
                    <!-- Header -->
                    <tr>
                        <td style="background-color:#111827; padding:24px 28px;">
                            <div style="color:#ffffff; font-size:20px; font-weight:bold;">Daily Product Sales</div>
                            <div style="color:#9ca3af; font-size:14px; margin-top:4px;">{{ $date->format('l, j F Y') }}</div>
                        </td>
                    </tr>

                    <!-- Greeting -->
                    <tr>
                        <td style="padding:24px 28px 8px 28px; font-size:14px; line-height:1.5; color:#374151;">
                            <p style="margin:0 0 12px 0;">Hi {{ $supplier->contact_person ?: $supplier->name }},</p>
                            <p style="margin:0;">Here is how your products sold with us today.@if ($attachCsv) A CSV of the same figures is attached.@endif</p>
                        </td>
                    </tr>

                    <!-- Summary tiles -->
                    <tr>
                        <td style="padding:16px 28px;">
                            @php($tileWidth = $showValues ? '33%' : '50%')
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    @if ($showValues)
                                        <td width="{{ $tileWidth }}" style="padding:12px; background-color:#f9fafb; border:1px solid #e5e7eb; border-radius:6px; text-align:center;">
                                            <div style="font-size:22px; font-weight:bold; color:#111827;">{!! $money($totals['revenue']) !!}</div>
                                            <div style="font-size:12px; color:#6b7280; margin-top:2px;">Sales (ex. VAT)</div>
                                        </td>
                                        <td width="10">&nbsp;</td>
                                    @endif
                                    <td width="{{ $tileWidth }}" style="padding:12px; background-color:#f9fafb; border:1px solid #e5e7eb; border-radius:6px; text-align:center;">
                                        <div style="font-size:22px; font-weight:bold; color:#111827;">{{ $units($totals['units']) }}</div>
                                        <div style="font-size:12px; color:#6b7280; margin-top:2px;">Units sold</div>
                                    </td>
                                    <td width="10">&nbsp;</td>
                                    <td width="{{ $tileWidth }}" style="padding:12px; background-color:#f9fafb; border:1px solid #e5e7eb; border-radius:6px; text-align:center;">
                                        <div style="font-size:22px; font-weight:bold; color:#111827;">{{ $totals['lines'] }}</div>
                                        <div style="font-size:12px; color:#6b7280; margin-top:2px;">Products sold</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Detail table -->
                    <tr>
                        <td style="padding:8px 28px 24px 28px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; font-size:13px;">
                                <thead>
                                    <tr style="background-color:#f3f4f6;">
                                        <th align="left" style="padding:8px 6px; border-bottom:2px solid #e5e7eb; color:#374151;">Product</th>
                                        <th align="right" style="padding:8px 6px; border-bottom:2px solid #e5e7eb; color:#374151;">Units</th>
                                        @if ($showValues)
                                            <th align="right" style="padding:8px 6px; border-bottom:2px solid #e5e7eb; color:#374151;">Sales</th>
                                        @endif
                                        <th align="right" style="padding:8px 6px; border-bottom:2px solid #e5e7eb; color:#374151;">Week</th>
                                        <th align="right" style="padding:8px 6px; border-bottom:2px solid #e5e7eb; color:#374151;">Month</th>
                                        <th align="right" style="padding:8px 6px; border-bottom:2px solid #e5e7eb; color:#374151;">Avg/mo</th>
                                        <th align="center" style="padding:8px 6px; border-bottom:2px solid #e5e7eb; color:#374151;">Trend</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($items as $item)
                                        <tr>
                                            <td style="padding:8px 6px; border-bottom:1px solid #f3f4f6; color:#111827;">
                                                {{ $item['name'] }}
                                                <div style="font-size:11px; color:#9ca3af;">{{ $item['barcode'] }}</div>
                                            </td>
                                            <td align="right" style="padding:8px 6px; border-bottom:1px solid #f3f4f6; color:#111827; font-weight:bold;">{{ $units($item['units']) }}</td>
                                            @if ($showValues)
                                                <td align="right" style="padding:8px 6px; border-bottom:1px solid #f3f4f6; color:#111827;">{!! $money($item['revenue']) !!}</td>
                                            @endif
                                            <td align="right" style="padding:8px 6px; border-bottom:1px solid #f3f4f6; color:#6b7280;">{{ $units($item['week_units']) }}</td>
                                            <td align="right" style="padding:8px 6px; border-bottom:1px solid #f3f4f6; color:#6b7280;">{{ $units($item['month_units']) }}</td>
                                            <td align="right" style="padding:8px 6px; border-bottom:1px solid #f3f4f6; color:#6b7280;">{{ $units($item['avg_monthly_units']) }}</td>
                                            <td align="center" style="padding:8px 6px; border-bottom:1px solid #f3f4f6; color:{{ $trendColour[$item['trend']] ?? '#6b7280' }};">{!! $trendArrow[$item['trend']] ?? '&ndash;' !!}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr style="background-color:#f9fafb;">
                                        <td style="padding:10px 6px; font-weight:bold; color:#111827;">Total</td>
                                        <td align="right" style="padding:10px 6px; font-weight:bold; color:#111827;">{{ $units($totals['units']) }}</td>
                                        @if ($showValues)
                                            <td align="right" style="padding:10px 6px; font-weight:bold; color:#111827;">{!! $money($totals['revenue']) !!}</td>
                                        @endif
                                        <td colspan="4"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding:16px 28px 28px 28px; border-top:1px solid #e5e7eb;">
                            <p style="margin:0; font-size:12px; color:#9ca3af; line-height:1.5;">
                                This lists every product of yours sold so far this month; the "Units"@if ($showValues) and "Sales"@endif column@if ($showValues)s show@else shows@endif {{ $date->format('j M') }}'s figures (0 if none sold that day).
                            </p>
                            <p style="margin:8px 0 0 0; font-size:12px; color:#9ca3af; line-height:1.5;">
                                @if ($showValues)Sales figures are retail values excluding VAT. @endif"Week" and "Month" show your units sold so far this week and this calendar month; "Avg/mo" is the average monthly units over the last 12 months.
                            </p>
                            <p style="margin:12px 0 0 0; font-size:12px; color:#9ca3af;">
                                Sent by {{ config('app.name') }}.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
