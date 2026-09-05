@php
    $business = config('app.business', []);
    $overdue = ($ctx_aging = $aging ?? [])
        ? round(($aging['d1_30'] ?? 0) + ($aging['d31_60'] ?? 0) + ($aging['d61_90'] ?? 0) + ($aging['d90_plus'] ?? 0), 2)
        : 0.0;
@endphp
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0; padding:0; background:#f4f4f5; font-family: Helvetica, Arial, sans-serif; color:#222;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5; padding:24px 12px;">
    <tr><td align="center">
        <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="max-width:640px; width:100%; background:#ffffff; border-radius:6px; overflow:hidden;">

            <tr><td style="padding:20px 24px; border-bottom:2px solid #222;">
                <div style="font-size:16px; font-weight:bold;">{{ $business['name'] ?? config('app.name') }}</div>
                <div style="font-size:12px; color:#666; margin-top:2px;">Statement of account</div>
            </td></tr>

            <tr><td style="padding:24px;">
                <p style="margin:0 0 14px; font-size:14px;">Hello {{ $customer->name }},</p>
                <p style="margin:0 0 18px; font-size:14px; line-height:1.5;">
                    Please find attached your statement of account
                    @if ($from)
                        for {{ $from->format('j M Y') }} to {{ $to->format('j M Y') }}.
                    @else
                        up to {{ $to->format('j M Y') }}.
                    @endif
                </p>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e5e5; border-radius:4px; margin-bottom:18px;">
                    <tr>
                        <td style="padding:14px 16px; font-size:13px; color:#555;">Balance outstanding</td>
                        <td style="padding:14px 16px; font-size:20px; font-weight:bold; text-align:right; color:{{ $closing_balance > 0.005 ? '#b00' : '#060' }};">
                            @if ($closing_balance < -0.005)
                                €{{ number_format(abs($closing_balance), 2) }} credit
                            @else
                                €{{ number_format($closing_balance, 2) }}
                            @endif
                        </td>
                    </tr>
                    @if ($overdue > 0.005)
                        <tr>
                            <td style="padding:10px 16px; border-top:1px solid #eee; font-size:13px; color:#b00;">Of which overdue</td>
                            <td style="padding:10px 16px; border-top:1px solid #eee; font-size:14px; font-weight:bold; text-align:right; color:#b00;">
                                €{{ number_format($overdue, 2) }}
                            </td>
                        </tr>
                    @endif
                </table>

                @if (! empty($open_invoices))
                    <div style="font-size:12px; text-transform:uppercase; letter-spacing:0.06em; color:#888; margin-bottom:6px;">Outstanding invoices</div>
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; font-size:13px; margin-bottom:18px;">
                        <tr style="background:#f7f7f7;">
                            <th align="left"  style="padding:6px 8px; border-bottom:1px solid #ddd; font-size:12px;">Invoice</th>
                            <th align="left"  style="padding:6px 8px; border-bottom:1px solid #ddd; font-size:12px;">Due</th>
                            <th align="right" style="padding:6px 8px; border-bottom:1px solid #ddd; font-size:12px;">Outstanding</th>
                        </tr>
                        @foreach ($open_invoices as $row)
                            <tr>
                                <td style="padding:6px 8px; border-bottom:1px solid #f0f0f0;">{{ $row['invoice_number'] }}</td>
                                <td style="padding:6px 8px; border-bottom:1px solid #f0f0f0; color:{{ $row['days_overdue'] > 0 ? '#b00' : '#555' }};">
                                    {{ $row['due_date']->format('j M Y') }}
                                    @if ($row['days_overdue'] > 0)
                                        <span style="font-size:11px;">({{ $row['days_overdue'] }}d overdue)</span>
                                    @endif
                                </td>
                                <td align="right" style="padding:6px 8px; border-bottom:1px solid #f0f0f0;">€{{ number_format($row['outstanding'], 2) }}</td>
                            </tr>
                        @endforeach
                    </table>
                @endif

                <p style="margin:0; font-size:13px; color:#555; line-height:1.5;">
                    The full statement is attached as a PDF. If anything looks wrong, just reply to this email
                    and we'll take a look.
                </p>
            </td></tr>

            <tr><td style="padding:16px 24px; background:#fafafa; border-top:1px solid #eee; font-size:11px; color:#777; line-height:1.5;">
                <strong>{{ $business['name'] ?? '' }}</strong><br>
                {{ implode(', ', array_filter([$business['address_line1'] ?? null, $business['address_line2'] ?? null, $business['county'] ?? null])) }}
                @if (! empty($business['postcode'])). {{ $business['postcode'] }}@endif
                <br>
                {{ implode(' · ', array_filter([
                    ! empty($business['phone']) ? 'Tel: ' . $business['phone'] : null,
                    $business['email'] ?? null,
                    ! empty($business['vat']) ? 'VAT: ' . $business['vat'] : null,
                ])) }}
            </td></tr>

        </table>
    </td></tr>
</table>
</body>
</html>
