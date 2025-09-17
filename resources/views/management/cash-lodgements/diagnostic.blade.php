<x-admin-layout>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Cash Lodgements Diagnostic</h1>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Detailed view of all cash lodgement related data for troubleshooting
                </p>
            </div>
            <div class="flex space-x-3">
                <a href="{{ route('management.cash-lodgements.diagnostic.export', request()->query()) }}"
                   class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Export CSV
                </a>
                <a href="{{ route('management.cash-lodgements.index') }}"
                   class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                    Back to Main View
                </a>
            </div>
        </div>
    </div>

    <!-- Date Filter -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 mb-6">
        <form method="GET" action="{{ route('management.cash-lodgements.diagnostic') }}" class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="start_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Start Date</label>
                    <input type="date" name="start_date" id="start_date" value="{{ request('start_date', $startDate->format('Y-m-d')) }}"
                           class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label for="end_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">End Date</label>
                    <input type="date" name="end_date" id="end_date" value="{{ request('end_date', $endDate->format('Y-m-d')) }}"
                           class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>
                <div class="flex items-end">
                    <button type="submit"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium w-full">
                        Update Date Range
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Overview Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 border border-blue-200 dark:border-blue-800">
            <h3 class="font-semibold text-blue-900 dark:text-blue-100 mb-2">Cash Lodgements</h3>
            <div class="space-y-1 text-sm">
                <div class="flex justify-between">
                    <span class="text-blue-700 dark:text-blue-300">Total:</span>
                    <span class="font-medium text-blue-900 dark:text-blue-100">{{ number_format($cashLodgementStats['total_count']) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-blue-700 dark:text-blue-300">In Range:</span>
                    <span class="font-medium text-blue-900 dark:text-blue-100">{{ number_format($cashLodgementStats['date_range_count']) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-green-700 dark:text-green-300">Matched:</span>
                    <span class="font-medium text-green-900 dark:text-green-100">{{ number_format($cashLodgementStats['matched_count']) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-red-700 dark:text-red-300">Unmatched:</span>
                    <span class="font-medium text-red-900 dark:text-red-100">{{ number_format($cashLodgementStats['unmatched_count']) }}</span>
                </div>
            </div>
        </div>

        <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4 border border-green-200 dark:border-green-800">
            <h3 class="font-semibold text-green-900 dark:text-green-100 mb-2">Matches</h3>
            <div class="space-y-1 text-sm">
                <div class="flex justify-between">
                    <span class="text-green-700 dark:text-green-300">Total:</span>
                    <span class="font-medium text-green-900 dark:text-green-100">{{ number_format($matchStats['total_matches']) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-green-700 dark:text-green-300">Manual:</span>
                    <span class="font-medium text-green-900 dark:text-green-100">{{ number_format($matchStats['manual_matches']) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-green-700 dark:text-green-300">Auto:</span>
                    <span class="font-medium text-green-900 dark:text-green-100">{{ number_format($matchStats['automatic_matches']) }}</span>
                </div>
            </div>
        </div>

        <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-4 border border-purple-200 dark:border-purple-800">
            <h3 class="font-semibold text-purple-900 dark:text-purple-100 mb-2">Reconciliations</h3>
            <div class="space-y-1 text-sm">
                <div class="flex justify-between">
                    <span class="text-purple-700 dark:text-purple-300">Total:</span>
                    <span class="font-medium text-purple-900 dark:text-purple-100">{{ number_format($reconciliationStats['total_count']) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-purple-700 dark:text-purple-300">With Payments:</span>
                    <span class="font-medium text-purple-900 dark:text-purple-100">{{ number_format($reconciliationStats['with_payments']) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-purple-700 dark:text-purple-300">Tills:</span>
                    <span class="font-medium text-purple-900 dark:text-purple-100">{{ number_format($reconciliationStats['unique_tills']) }}</span>
                </div>
            </div>
        </div>

        <div class="bg-orange-50 dark:bg-orange-900/20 rounded-lg p-4 border border-orange-200 dark:border-orange-800">
            <h3 class="font-semibold text-orange-900 dark:text-orange-100 mb-2">Potential Issues</h3>
            <div class="space-y-1 text-sm">
                <div class="flex justify-between">
                    <span class="text-orange-700 dark:text-orange-300">Unmatched Lodgements:</span>
                    <span class="font-medium text-orange-900 dark:text-orange-100">{{ number_format($relationshipStats['lodgements_without_matches']) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-orange-700 dark:text-orange-300">Orphaned POS:</span>
                    <span class="font-medium text-orange-900 dark:text-orange-100">{{ number_format($relationshipStats['pos_without_reconciliation']) }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Tables -->
    <div class="space-y-8">

        <!-- 1. Cash Lodgements -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center">
                    <div class="w-4 h-4 bg-blue-500 rounded-full mr-3"></div>
                    Cash Lodgements (Laravel Database)
                    <span class="ml-2 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 text-xs px-2 py-1 rounded">
                        {{ $cashLodgements->count() }} of {{ number_format($cashLodgementStats['date_range_count']) }}
                    </span>
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Main lodgement records from cash_lodgements table</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Money ID</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">POS Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Lodge Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Till</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Cash</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Has Recon</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Matched</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Confidence</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($cashLodgements as $lodgement)
                            @php
                                $hasReconciliation = $lodgement->matches->count() > 0;
                                $confidence = $hasReconciliation ? $lodgement->matches->first()->confidence_score : null;
                            @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 {{ $lodgement->is_matched ? 'bg-green-50 dark:bg-green-900/10' : 'bg-red-50 dark:bg-red-900/10' }}">
                                <td class="px-4 py-4 whitespace-nowrap text-xs font-mono text-blue-600 dark:text-blue-400">
                                    <span title="{{ $lodgement->money_id }}">{{ substr($lodgement->money_id, 0, 8) }}...</span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    @if($lodgement->closedCash)
                                        {{ $lodgement->closedCash->DATEEND ? $lodgement->closedCash->DATEEND->format('d/m/y') : 'N/A' }}
                                    @else
                                        <span class="text-gray-400">N/A</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    {{ $lodgement->lodgement_date->format('d/m/y') }}
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    {{ $lodgement->till_name ?? 'N/A' }}
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    €{{ number_format($lodgement->cash_amount, 2) }}
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm font-semibold text-gray-900 dark:text-white">
                                    €{{ number_format($lodgement->total_amount, 2) }}
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-center">
                                    @if($hasReconciliation)
                                        <span class="text-green-600 dark:text-green-400">✓</span>
                                    @else
                                        <span class="text-red-600 dark:text-red-400">✗</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    @if($lodgement->is_matched)
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                            Matched
                                        </span>
                                    @else
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                            Unmatched
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm">
                                    @if($confidence !== null)
                                        <div class="flex items-center">
                                            <div class="w-16 bg-gray-200 dark:bg-gray-700 rounded-full h-2 mr-2">
                                                <div class="bg-{{ $confidence >= 95 ? 'green' : ($confidence >= 80 ? 'blue' : 'yellow') }}-600 h-2 rounded-full"
                                                     style="width: {{ $confidence }}%"></div>
                                            </div>
                                            <span class="text-xs text-gray-600 dark:text-gray-400">{{ $confidence }}%</span>
                                        </div>
                                    @else
                                        <span class="text-gray-400 text-xs">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                    No cash lodgements found for the selected date range.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 2. Cash Lodgement Matches -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center">
                    <div class="w-4 h-4 bg-green-500 rounded-full mr-3"></div>
                    Cash Lodgement Matches (Junction Table)
                    <span class="ml-2 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 text-xs px-2 py-1 rounded">
                        {{ $cashLodgementMatches->count() }} matches
                    </span>
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Links between lodgements and reconciliations from cash_lodgement_matches table</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Lodgement ID</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">POS Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Matched Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Available to Lodge</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Match Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Confidence</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Matched By</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Created</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($cashLodgementMatches as $match)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-4 py-4 whitespace-nowrap text-xs font-mono text-blue-600 dark:text-blue-400">
                                    {{ substr($match->cash_lodgement_id, 0, 8) }}...
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    {{ $match->pos_date->format('Y-m-d') }}
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm font-semibold text-gray-900 dark:text-white">
                                    €{{ number_format($match->matched_amount, 2) }}
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    €{{ number_format($match->available_to_lodge, 2) }}
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                        {{ $match->match_type === 'manual' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' :
                                           ($match->match_type === 'exact' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' :
                                           'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200') }}">
                                        {{ ucfirst($match->match_type) }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm">
                                    <div class="flex items-center">
                                        <div class="w-16 bg-gray-200 dark:bg-gray-700 rounded-full h-2 mr-2">
                                            <div class="bg-{{ $match->confidence_score >= 80 ? 'green' : ($match->confidence_score >= 60 ? 'yellow' : 'red') }}-600 h-2 rounded-full"
                                                 style="width: {{ $match->confidence_score }}%"></div>
                                        </div>
                                        <span class="text-xs text-gray-600 dark:text-gray-400">{{ $match->confidence_score }}%</span>
                                    </div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    {{ $match->matcher?->name ?? 'System' }}
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">
                                    {{ $match->created_at->format('M d, H:i') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                    No matches found for the selected date range.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 3. Cash Reconciliations -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center">
                    <div class="w-4 h-4 bg-purple-500 rounded-full mr-3"></div>
                    Cash Reconciliations (Laravel Database)
                    <span class="ml-2 bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200 text-xs px-2 py-1 rounded">
                        {{ $cashReconciliations->count() }} of {{ number_format($reconciliationStats['date_range_count']) }}
                    </span>
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Till reconciliation records from cash_reconciliations table</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">ID</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Closed Cash ID</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Till</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Cash Counted</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">POS Cash</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Variance</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Float Total</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Payments</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($cashReconciliations as $reconciliation)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-4 py-4 whitespace-nowrap text-xs font-mono text-gray-500 dark:text-gray-400">
                                    {{ substr($reconciliation->id, 0, 8) }}...
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-blue-600 dark:text-blue-400">
                                    {{ $reconciliation->closed_cash_id }}
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    {{ $reconciliation->date->format('Y-m-d') }}
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    {{ $reconciliation->till_name }}
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    €{{ number_format($reconciliation->total_cash_counted, 2) }}
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    €{{ number_format($reconciliation->pos_cash_total, 2) }}
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm">
                                    <span class="font-semibold {{ $reconciliation->variance == 0 ? 'text-green-600 dark:text-green-400' : ($reconciliation->variance > 0 ? 'text-blue-600 dark:text-blue-400' : 'text-red-600 dark:text-red-400') }}">
                                        €{{ number_format($reconciliation->variance, 2) }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    €{{ number_format($reconciliation->total_float, 2) }}
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-center">
                                    @if($reconciliation->payments->count() > 0)
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                            {{ $reconciliation->payments->count() }}
                                        </span>
                                    @else
                                        <span class="text-gray-300 dark:text-gray-600">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-center">
                                    @if($reconciliation->notes->count() > 0)
                                        <span class="text-blue-600 dark:text-blue-400">{{ $reconciliation->notes->count() }}</span>
                                    @else
                                        <span class="text-gray-300 dark:text-gray-600">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                    No cash reconciliations found for the selected date range.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 4. POS Closed Cash -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center">
                    <div class="w-4 h-4 bg-red-500 rounded-full mr-3"></div>
                    POS Closed Cash Records (POS Database)
                    <span class="ml-2 bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200 text-xs px-2 py-1 rounded">
                        {{ $posClosedCash->count() }} of {{ number_format($posStats['date_range_pos']) }}
                    </span>
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">POS system records from CLOSEDCASH table (uniCenta database)</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Money ID</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date Start</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date End</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Host</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">No Sales</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Has Reconciliation</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Has Lodgement</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($posClosedCash as $closedCash)
                            @php
                                $hasReconciliation = $cashReconciliations->where('closed_cash_id', $closedCash->MONEY)->isNotEmpty();
                                $hasLodgement = $cashLodgements->where('money_id', $closedCash->MONEY)->isNotEmpty();
                            @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 {{ (!$hasReconciliation || !$hasLodgement) ? 'bg-yellow-50 dark:bg-yellow-900/10' : '' }}">
                                <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-blue-600 dark:text-blue-400">
                                    {{ $closedCash->MONEY }}
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    {{ $closedCash->DATESTART ? Carbon\Carbon::parse($closedCash->DATESTART)->format('Y-m-d H:i') : 'N/A' }}
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    {{ $closedCash->DATEEND ? Carbon\Carbon::parse($closedCash->DATEEND)->format('Y-m-d H:i') : 'N/A' }}
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    {{ $closedCash->HOST ?? 'N/A' }}
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    {{ $closedCash->NOSALES ?? 0 }}
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-center">
                                    @if($hasReconciliation)
                                        <span class="text-green-600 dark:text-green-400">✓</span>
                                    @else
                                        <span class="text-red-600 dark:text-red-400">✗</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-center">
                                    @if($hasLodgement)
                                        <span class="text-green-600 dark:text-green-400">✓</span>
                                    @else
                                        <span class="text-red-600 dark:text-red-400">✗</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                    No POS closed cash records found for the selected date range.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 5. Bank Transactions -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center">
                    <div class="w-4 h-4 bg-yellow-500 rounded-full mr-3"></div>
                    Cash-Related Bank Transactions (Laravel Database)
                    <span class="ml-2 bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200 text-xs px-2 py-1 rounded">
                        {{ $bankTransactions->count() }} transactions
                    </span>
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Bank statement records related to cash lodgements from bank_transactions table</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Description</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Debit</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Credit</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Category</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($bankTransactions as $transaction)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    {{ $transaction->transaction_date->format('Y-m-d') }}
                                </td>
                                <td class="px-4 py-4 text-sm text-gray-900 dark:text-white max-w-xs truncate">
                                    {{ $transaction->description }}
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-red-600 dark:text-red-400">
                                    @if($transaction->debit_amount > 0)
                                        €{{ number_format($transaction->debit_amount, 2) }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-green-600 dark:text-green-400">
                                    @if($transaction->credit_amount > 0)
                                        €{{ number_format($transaction->credit_amount, 2) }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                        {{ $transaction->status === 'matched' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' :
                                           ($transaction->status === 'pending' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' :
                                           'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200') }}">
                                        {{ ucfirst($transaction->status ?? 'pending') }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    {{ $transaction->credit_category_display ?? '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                    No cash-related bank transactions found for the selected date range.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 6. System Users -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center">
                    <div class="w-4 h-4 bg-indigo-500 rounded-full mr-3"></div>
                    System Users
                    <span class="ml-2 bg-indigo-100 dark:bg-indigo-900 text-indigo-800 dark:text-indigo-200 text-xs px-2 py-1 rounded">
                        {{ $systemUsers->count() }} users
                    </span>
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Users who have created or modified cash lodgement records</p>
            </div>
            <div class="px-6 py-4">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    @forelse($systemUsers as $user)
                        <div class="text-center">
                            <div class="w-12 h-12 bg-indigo-100 dark:bg-indigo-900 rounded-full flex items-center justify-center mx-auto mb-2">
                                <span class="text-indigo-600 dark:text-indigo-400 font-semibold text-lg">
                                    {{ substr($user->name, 0, 1) }}
                                </span>
                            </div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $user->name }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $user->email }}</p>
                        </div>
                    @empty
                        <p class="text-gray-500 dark:text-gray-400 col-span-4 text-center">No users found.</p>
                    @endforelse
                </div>
            </div>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add search functionality if needed
    // Add any interactive features here
});
</script>
</x-admin-layout>