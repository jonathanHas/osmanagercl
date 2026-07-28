{{-- Reconciliation vs Lodgement Comparison Panel --}}
{{-- Expects: $reconciliation (CashReconciliation), $lodgement (CashLodgement) --}}
{{-- Optional: $verification (CashBagVerification) - the bag banked for THIS day. When a
     lodgement spans several days, comparing the whole lodgement total against one day's
     available-to-lodge produces a meaningless variance, so compare the day's bag instead. --}}
@php
    $verification = $verification ?? null;
    $totalCash = $reconciliation->calculateTotalCash();
    $totalFloat = $reconciliation->total_float;
    $supplierPayments = $reconciliation->total_supplier_payments;
    $availableToLodge = $reconciliation->calculateAvailableToLodge();
    $lodgedAmount = $verification ? (float) $verification->counted_total : (float) $lodgement->cash_amount;
    $variance = $lodgedAmount - $availableToLodge;
    $absVariance = abs($variance);
    $varianceClass = $absVariance < 1
        ? 'text-green-600 dark:text-green-400'
        : ($absVariance > 20
            ? 'text-red-600 dark:text-red-400'
            : ($absVariance > 5
                ? 'text-amber-600 dark:text-amber-400'
                : ($variance >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400')));
@endphp

<div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
    <div class="bg-gray-50 dark:bg-gray-900/50 px-4 py-3 flex items-center justify-between">
        <div>
            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $reconciliation->date->format('M j, Y') }}</span>
            <span class="text-xs text-gray-500 dark:text-gray-400 ml-2">{{ $reconciliation->till_name }}</span>
        </div>
        <a href="{{ route('cash-reconciliation.index', ['date' => $reconciliation->date->format('Y-m-d'), 'till_id' => $reconciliation->till_id]) }}"
           class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">Edit Reconciliation &rarr;</a>
    </div>

    <div class="p-4">
        <div class="grid grid-cols-2 gap-4">
            {{-- Reconciliation Side --}}
            <div>
                <h5 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Reconciliation</h5>
                <div class="space-y-1.5 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600 dark:text-gray-400">Total Cash Counted</span>
                        <span class="font-medium text-gray-900 dark:text-white">€{{ number_format($totalCash, 2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600 dark:text-gray-400">Notes subtotal</span>
                        <span class="text-gray-700 dark:text-gray-300">€{{ number_format($reconciliation->calculateTotalNotes(), 2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600 dark:text-gray-400">Coins subtotal</span>
                        <span class="text-gray-700 dark:text-gray-300">€{{ number_format($reconciliation->calculateTotalCoins(), 2) }}</span>
                    </div>
                    <div class="flex justify-between text-gray-500 dark:text-gray-500">
                        <span>Float retained</span>
                        <span>-€{{ number_format($totalFloat, 2) }}</span>
                    </div>
                    <div class="border-t dark:border-gray-700 pt-1.5 flex justify-between font-bold text-indigo-700 dark:text-indigo-400">
                        <span>Available to Lodge</span>
                        <span>€{{ number_format($availableToLodge, 2) }}</span>
                    </div>
                    @if($supplierPayments > 0)
                    {{-- Informational only: supplier payments are already out of the drawer,
                         so calculateAvailableToLodge() does not subtract them again. --}}
                    <div class="flex justify-between text-xs text-gray-500 dark:text-gray-500 pt-0.5">
                        <span>Supplier payments (already out of drawer)</span>
                        <span>€{{ number_format($supplierPayments, 2) }}</span>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Lodgement Side --}}
            <div>
                <h5 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">
                    {{ $verification ? 'Bag Banked This Day' : 'Lodgement' }}
                </h5>
                <div class="space-y-1.5 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600 dark:text-gray-400">Cash Lodged</span>
                        <span class="font-medium text-gray-900 dark:text-white">€{{ number_format($lodgedAmount, 2) }}</span>
                    </div>
                    @if($verification)
                    <div class="text-xs text-gray-500 dark:text-gray-500">
                        Part of a €{{ number_format($lodgement->total_amount, 2) }} lodgement
                        banked on {{ $lodgement->lodgement_date->format('M j, Y') }}
                    </div>
                    @else
                        @if($lodgement->cheque_amount > 0)
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Cheque Lodged</span>
                            <span class="text-gray-700 dark:text-gray-300">€{{ number_format($lodgement->cheque_amount, 2) }}</span>
                        </div>
                        @endif
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Total Lodged</span>
                            <span class="font-medium text-gray-900 dark:text-white">€{{ number_format($lodgement->total_amount, 2) }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Variance --}}
        <div class="mt-3 pt-3 border-t dark:border-gray-700 flex items-center justify-between">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Variance (lodged vs available)</span>
            <span class="text-lg font-bold {{ $varianceClass }}">
                @if($absVariance < 0.01)
                    Exact match
                @else
                    €{{ number_format($absVariance, 2) }}
                    {{ $variance > 0 ? '↑ over' : '↓ under' }}
                @endif
            </span>
        </div>
    </div>
</div>
