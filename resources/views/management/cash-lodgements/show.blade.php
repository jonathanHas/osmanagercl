<x-admin-layout>
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <a href="{{ route('management.cash-lodgements.index') }}" 
                   class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </a>
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Cash Lodgement Details</h1>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        Lodged on {{ $lodgement->lodgement_date->format('F j, Y') }}
                        @if($lodgement->imported_from_legacy)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300 ml-2">
                                Imported from Legacy
                            </span>
                        @endif
                    </p>
                </div>
            </div>
            <div class="flex space-x-3">
                @if($lodgement->bankTransaction)
                    <a href="#" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        View Bank Match
                    </a>
                @endif
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Lodgement Details -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Basic Information -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Lodgement Information</h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Lodgement Date</label>
                            <p class="mt-1 text-lg text-gray-900 dark:text-white">{{ $lodgement->lodgement_date->format('F j, Y') }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Till Name</label>
                            <p class="mt-1 text-lg text-gray-900 dark:text-white">{{ $lodgement->till_name ?: 'Unknown' }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Cash Amount</label>
                            <p class="mt-1 text-2xl font-bold text-green-600 dark:text-green-400">
                                €{{ number_format($lodgement->cash_amount, 2) }}
                            </p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Cheque Amount</label>
                            <p class="mt-1 text-2xl font-bold text-blue-600 dark:text-blue-400">
                                €{{ number_format($lodgement->cheque_amount, 2) }}
                            </p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Total Amount</label>
                            <p class="mt-1 text-3xl font-bold text-gray-900 dark:text-white">
                                €{{ number_format($lodgement->total_amount, 2) }}
                            </p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Lodgement Type</label>
                            <p class="mt-1">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                                    @if($lodgement->lodgement_type === 'cash_only') bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-300
                                    @elseif($lodgement->lodgement_type === 'cheque_only') bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-300
                                    @else bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-300 @endif">
                                    {{ ucfirst(str_replace('_', ' ', $lodgement->lodgement_type)) }}
                                </span>
                            </p>
                        </div>
                    </div>

                    @if($lodgement->notes)
                        <div class="mt-6">
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Notes</label>
                            <p class="mt-1 text-gray-900 dark:text-white">{{ $lodgement->notes }}</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Match Status -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Match Status</h3>
                </div>
                <div class="p-6">
                    <div class="flex items-center space-x-4 mb-4">
                        @if($lodgement->is_matched)
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center">
                                    <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                                <span class="ml-3 text-lg font-medium text-green-600 dark:text-green-400">Matched</span>
                            </div>
                        @else
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-yellow-100 dark:bg-yellow-900 rounded-full flex items-center justify-center">
                                    <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                                <span class="ml-3 text-lg font-medium text-yellow-600 dark:text-yellow-400">Unmatched</span>
                            </div>
                        @endif
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Total Matched</label>
                            <p class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">
                                €{{ number_format($lodgement->total_matched_amount, 2) }}
                            </p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Remaining</label>
                            <p class="mt-1 text-xl font-semibold {{ $lodgement->remaining_amount > 0 ? 'text-orange-600 dark:text-orange-400' : 'text-green-600 dark:text-green-400' }}">
                                €{{ number_format($lodgement->remaining_amount, 2) }}
                            </p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Match Progress</label>
                            <div class="mt-1">
                                @php
                                    $percentage = $lodgement->total_amount > 0 ? ($lodgement->total_matched_amount / $lodgement->total_amount) * 100 : 0;
                                @endphp
                                <div class="flex items-center">
                                    <div class="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                        <div class="bg-green-600 dark:bg-green-400 h-2 rounded-full" style="width: {{ min(100, $percentage) }}%"></div>
                                    </div>
                                    <span class="ml-2 text-sm font-medium text-gray-900 dark:text-white">{{ number_format($percentage, 1) }}%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Matches Details -->
            @if($lodgement->matches->count() > 0)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Reconciliation Matches</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">POS Date</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Matched Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Till</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Created</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($lodgement->matches as $match)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                            {{ $match->pos_date->format('M j, Y') }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white text-right">
                                            €{{ number_format($match->matched_amount, 2) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                            {{ $match->cashReconciliation->till_name ?? 'Unknown' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ $match->created_at->format('M j, Y g:i A') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <!-- Potential Matches (if unmatched) -->
            @if(!$lodgement->is_matched && $potentialMatches->count() > 0)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Potential Matches</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            Reconciliations within 7 days with similar amounts
                        </p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">POS Date</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Available to Lodge</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Variance</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Confidence</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Till</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($potentialMatches as $potential)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                            {{ $potential['reconciliation']->date->format('M j, Y') }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white text-right">
                                            €{{ number_format($potential['available_to_lodge'], 2) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                            <span class="text-{{ $potential['variance'] < 10 ? 'green' : ($potential['variance'] < 50 ? 'yellow' : 'red') }}-600 dark:text-{{ $potential['variance'] < 10 ? 'green' : ($potential['variance'] < 50 ? 'yellow' : 'red') }}-400">
                                                €{{ number_format($potential['variance'], 2) }}
                                                ({{ $potential['variance_percent'] }}%)
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <div class="flex items-center">
                                                <div class="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-2 mr-2">
                                                    <div class="bg-{{ $potential['confidence'] > 80 ? 'green' : ($potential['confidence'] > 60 ? 'yellow' : 'red') }}-600 h-2 rounded-full" 
                                                         style="width: {{ $potential['confidence'] }}%"></div>
                                                </div>
                                                <span class="text-gray-900 dark:text-white text-xs">{{ $potential['confidence'] }}%</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                            {{ $potential['reconciliation']->till_name ?? 'Unknown' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- System Information -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">System Information</h3>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Money ID</label>
                        <p class="mt-1 text-sm font-mono text-gray-900 dark:text-white">{{ $lodgement->money_id }}</p>
                    </div>
                    
                    @if($lodgement->original_lodge_date)
                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Original Lodge Date</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $lodgement->original_lodge_date->format('M j, Y g:i A') }}</p>
                        </div>
                    @endif

                    <div>
                        <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Created</label>
                        <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $lodgement->created_at->format('M j, Y g:i A') }}</p>
                        @if($lodgement->creator)
                            <p class="text-xs text-gray-600 dark:text-gray-400">by {{ $lodgement->creator->name }}</p>
                        @endif
                    </div>

                    @if($lodgement->updated_at != $lodgement->created_at)
                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Last Updated</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $lodgement->updated_at->format('M j, Y g:i A') }}</p>
                            @if($lodgement->updater)
                                <p class="text-xs text-gray-600 dark:text-gray-400">by {{ $lodgement->updater->name }}</p>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            <!-- Bank Transaction (if matched) -->
            @if($lodgement->bankTransaction)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Bank Transaction</h3>
                    </div>
                    <div class="p-6 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Transaction Date</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $lodgement->bankTransaction->transaction_date->format('M j, Y') }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Amount</label>
                            <p class="mt-1 text-lg font-semibold text-green-600 dark:text-green-400">€{{ number_format($lodgement->bankTransaction->amount, 2) }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Description</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $lodgement->bankTransaction->description }}</p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Reconciliation Comparison -->
            @if($relatedReconciliations->count() > 0)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Reconciliation Comparison</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            Side-by-side: what was counted vs what was lodged
                        </p>
                    </div>
                    <div class="p-4 space-y-3">
                        @foreach($relatedReconciliations as $reconciliation)
                            @include('management.cash-lodgements.partials.reconciliation-comparison', [
                                'reconciliation' => $reconciliation,
                                'lodgement' => $lodgement,
                            ])
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
</x-admin-layout>