{{--
    Shared statement body for the print view and the Dompdf view. Both use the
    same plain-table CSS class names (.num, .opening, .overdue, .summary, .aging,
    .alloc, .oncredit) which each parent defines in its own <style> block.
--}}

<h4 class="section">Account activity</h4>
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
                <td>
                    {{ $e['description'] }}
                    @if ($e['allocation_summary'] !== '')
                        <div class="alloc">{{ $e['allocation_summary'] }}</div>
                    @endif
                    @if ($e['unapplied'] > 0.005)
                        <div class="alloc oncredit">€{{ number_format($e['unapplied'], 2) }} on account</div>
                    @endif
                </td>
                <td class="num">@if ($e['debit'] > 0)€{{ number_format($e['debit'], 2) }}@endif</td>
                <td class="num">@if ($e['credit'] > 0)€{{ number_format($e['credit'], 2) }}@endif</td>
                <td class="num">€{{ number_format($e['balance'], 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="5" style="text-align:center; color:#999;">No activity in this period.</td></tr>
        @endforelse
    </tbody>
</table>

@if (! empty($open_invoices))
    <h4 class="section">Outstanding invoices as at {{ $to->format('d M Y') }}</h4>
    <table>
        <thead>
            <tr>
                <th>Invoice</th>
                <th>Issued</th>
                <th>Due</th>
                <th class="num">Total</th>
                <th class="num">Paid</th>
                <th class="num">Outstanding</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($open_invoices as $row)
                <tr>
                    <td>{{ $row['invoice_number'] }}</td>
                    <td>{{ $row['issue_date']->format('d M Y') }}</td>
                    <td class="{{ $row['days_overdue'] > 0 ? 'overdue' : '' }}">
                        {{ $row['due_date']->format('d M Y') }}
                        @if ($row['days_overdue'] > 0)
                            ({{ $row['days_overdue'] }} {{ \Illuminate\Support\Str::plural('day', $row['days_overdue']) }} overdue)
                        @endif
                    </td>
                    <td class="num">€{{ number_format($row['total'], 2) }}</td>
                    <td class="num">@if ($row['paid'] > 0)€{{ number_format($row['paid'], 2) }}@endif</td>
                    <td class="num">€{{ number_format($row['outstanding'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h4 class="section">Aged balance</h4>
    <table class="aging">
        <thead>
            <tr>
                <th>Aged</th>
                @foreach ($bucket_labels as $key => $label)
                    <th>{{ $label }}</th>
                @endforeach
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Outstanding</td>
                @foreach ($bucket_labels as $key => $label)
                    <td class="{{ $key !== 'current' && $aging[$key] > 0.005 ? 'overdue' : '' }}">
                        @if ($aging[$key] > 0.005)€{{ number_format($aging[$key], 2) }}@else<span class="muted">—</span>@endif
                    </td>
                @endforeach
                <td class="bucket-total">€{{ number_format($aging['total'], 2) }}</td>
            </tr>
        </tbody>
    </table>
@endif

{{--
    Outside the open-invoices guard above: a customer can hold credit with no
    open invoices at all, and that is exactly when they most need to see it.
--}}
@if (! empty($credit_payments))
    <h4 class="section">Payments on account</h4>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Method</th>
                <th>Reference</th>
                <th class="num">Payment</th>
                <th class="num">Not yet applied</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($credit_payments as $p)
                <tr>
                    <td>{{ $p['date']->format('d M Y') }}</td>
                    <td>{{ $p['method'] }}</td>
                    <td>{{ $p['reference'] ?: '—' }}</td>
                    <td class="num">€{{ number_format($p['amount'], 2) }}</td>
                    <td class="num">€{{ number_format($p['unapplied'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="num">Total on account</td>
                <td class="num bucket-total">€{{ number_format($unallocated_credit, 2) }}</td>
            </tr>
        </tfoot>
    </table>
@endif

@if (($unallocated_credit ?? 0) > 0.005)
    {{-- The statement showing its own proof: this is the documented identity
         aged total - unallocated credit == closing balance, in plain words. --}}
    <p style="font-size:10.5px; color:#060; margin:0 0 12px;">
        €{{ number_format($aging['total'], 2) }} outstanding on invoices, less
        €{{ number_format($unallocated_credit, 2) }} received on account, leaves
        @if ($closing_balance < -0.005)
            €{{ number_format(abs($closing_balance), 2) }} in your favour.
        @else
            a balance of €{{ number_format($closing_balance, 2) }}.
        @endif
    </p>
@endif

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
