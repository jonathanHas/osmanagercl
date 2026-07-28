{{-- Per-day breakdown of the verified bags that make up a lodgement --}}
{{-- Expects: $bagVerifications (Collection of CashBagVerification), $lodgement (CashLodgement) --}}
@php
    $countedTotal = $bagVerifications->sum(fn ($v) => (float) $v->counted_total);
    $expectedTotal = $bagVerifications->sum(fn ($v) => (float) $v->expected_total);
    $totalVariance = $countedTotal - $expectedTotal;
    $absTotalVariance = abs($totalVariance);
    $totalVarianceClass = $absTotalVariance < 1
        ? 'text-green-600 dark:text-green-400'
        : ($absTotalVariance > 20 ? 'text-red-600 dark:text-red-400' : 'text-amber-600 dark:text-amber-400');
@endphp

<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
        <div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Days Included in This Lodgement</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">Each verified cash bag that was banked together</p>
        </div>
        <span class="text-sm text-gray-600 dark:text-gray-400">
            {{ $bagVerifications->count() }} {{ Str::plural('bag', $bagVerifications->count()) }} &middot;
            <span class="font-bold text-green-600 dark:text-green-400">€{{ number_format($countedTotal, 2) }}</span>
        </span>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-900/50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Date</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Till</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Counted</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Expected</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Variance</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Verified By</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($bagVerifications as $v)
                @php
                    $absVar = abs($v->variance);
                    $varClass = $absVar < 1 ? 'text-green-600 dark:text-green-400' : ($absVar > 20 ? 'text-red-600 dark:text-red-400' : 'text-amber-600 dark:text-amber-400');
                    $reconciliation = $v->reconciliation;
                @endphp
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white">
                        @if($reconciliation)
                            <a href="{{ route('cash-reconciliation.index', ['date' => $reconciliation->date->format('Y-m-d'), 'till_id' => $reconciliation->till_id]) }}"
                               class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                {{ $reconciliation->date->format('D, M j, Y') }}
                            </a>
                        @else
                            <span class="text-gray-500 dark:text-gray-400">Unknown</span>
                        @endif
                    </td>
                    <td class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">{{ $reconciliation->till_name ?? '-' }}</td>
                    <td class="px-4 py-2 text-sm text-right font-medium text-gray-900 dark:text-white">€{{ number_format($v->counted_total, 2) }}</td>
                    <td class="px-4 py-2 text-sm text-right text-gray-500 dark:text-gray-400">€{{ number_format($v->expected_total, 2) }}</td>
                    <td class="px-4 py-2 text-sm text-right">
                        <span class="{{ $varClass }} font-medium">
                            @if($absVar < 0.01) &check; @else €{{ number_format($absVar, 2) }} {{ $v->variance > 0 ? '↑' : '↓' }} @endif
                        </span>
                    </td>
                    <td class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">{{ $v->verifier->name ?? '-' }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-gray-50 dark:bg-gray-900/50 border-t-2 border-gray-200 dark:border-gray-700">
                <tr>
                    <td class="px-4 py-2 text-sm font-bold text-gray-900 dark:text-white" colspan="2">TOTAL</td>
                    <td class="px-4 py-2 text-sm text-right font-bold text-gray-900 dark:text-white">€{{ number_format($countedTotal, 2) }}</td>
                    <td class="px-4 py-2 text-sm text-right font-medium text-gray-500 dark:text-gray-400">€{{ number_format($expectedTotal, 2) }}</td>
                    <td class="px-4 py-2 text-sm text-right">
                        <span class="{{ $totalVarianceClass }} font-bold">
                            @if($absTotalVariance < 0.01) &check; @else €{{ number_format($absTotalVariance, 2) }} {{ $totalVariance > 0 ? '↑' : '↓' }} @endif
                        </span>
                    </td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>

    @if(abs($countedTotal - (float) $lodgement->cash_amount) >= 0.01)
        <div class="px-6 py-3 border-t border-gray-200 dark:border-gray-700 text-sm text-amber-600 dark:text-amber-400">
            Bags total €{{ number_format($countedTotal, 2) }} but the lodgement records
            €{{ number_format($lodgement->cash_amount, 2) }} in cash — a difference of
            €{{ number_format(abs($countedTotal - (float) $lodgement->cash_amount), 2) }}.
        </div>
    @endif
</div>
