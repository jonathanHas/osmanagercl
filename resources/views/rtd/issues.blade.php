<x-admin-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        {{-- Header --}}
        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-100">RTD Issues Work Queue</h2>
                <p class="text-gray-400 text-sm mt-1">Invoices with unresolved article codes that need attention</p>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('rtd-fallbacks.unresolved') }}"
                   class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                    </svg>
                    Manage Fallbacks
                </a>
                <a href="{{ route('rtd.index') }}"
                   class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back to RTD
                </a>
            </div>
        </div>

        {{-- Summary Stats --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-gray-800 rounded-lg p-4">
                <div class="text-3xl font-bold text-red-400">{{ $invoices->total() }}</div>
                <div class="text-sm text-gray-400">Invoices with Issues</div>
            </div>
            <div class="bg-gray-800 rounded-lg p-4">
                <div class="text-3xl font-bold text-yellow-400">{{ $totalUnresolvedCount }}</div>
                <div class="text-sm text-gray-400">Total Unresolved Items</div>
            </div>
            <div class="bg-gray-800 rounded-lg p-4">
                <div class="text-3xl font-bold text-orange-400">{{ number_format($totalUnresolvedValue, 2) }}</div>
                <div class="text-sm text-gray-400">Unresolved Value</div>
            </div>
        </div>

        {{-- Workflow Tips --}}
        <div class="bg-blue-900/30 border border-blue-600 rounded-lg p-4 mb-6">
            <div class="flex items-start">
                <svg class="w-5 h-5 text-blue-400 mr-3 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div class="text-sm text-blue-200">
                    <strong class="text-blue-300">Workflow:</strong>
                    To resolve issues, use "Manage Fallbacks" to assign VAT rates to unrecognized article codes.
                    After adding fallbacks, click "Recompute" on each invoice to update RTD.
                    Once all items are resolved, click "Freeze" to lock the RTD data.
                </div>
            </div>
        </div>

        {{-- Issues Table --}}
        <div class="bg-gray-800 rounded-lg overflow-hidden">
            @if($invoices->isEmpty())
                <div class="p-12 text-center">
                    <svg class="w-16 h-16 mx-auto mb-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <h3 class="text-xl font-semibold text-gray-200 mb-2">All Clear!</h3>
                    <p class="text-gray-400">No invoices have unresolved RTD items.</p>
                    <a href="{{ route('rtd.index') }}" class="inline-block mt-4 text-blue-400 hover:text-blue-300">
                        Return to RTD Management
                    </a>
                </div>
            @else
                <table class="min-w-full divide-y divide-gray-700">
                    <thead class="bg-gray-900">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Invoice</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Date</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase">Invoice Total</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-400 uppercase">Unresolved</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase">Unresolved Value</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Top Issues</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        @foreach($invoices as $invoice)
                            @php
                                $unresolvedCount = $invoice->rtd_breakdown['unresolved']['count'] ?? 0;
                                $unresolvedValue = $invoice->rtd_breakdown['unresolved']['net_total'] ?? 0;
                                $topIssues = array_slice($invoice->rtd_resolution_issues ?? [], 0, 3);
                            @endphp
                            <tr class="hover:bg-gray-750">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-white">#{{ $invoice->invoice_number }}</div>
                                    <div class="text-xs text-gray-400">{{ $invoice->supplier_name }}</div>
                                </td>
                                <td class="px-4 py-3 text-gray-300">
                                    {{ $invoice->invoice_date->format('d/m/Y') }}
                                </td>
                                <td class="px-4 py-3 text-right text-gray-300 font-mono">
                                    {{ number_format($invoice->total_amount, 2) }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="px-2 py-1 text-sm font-semibold rounded-full bg-red-900 text-red-300">
                                        {{ $unresolvedCount }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right text-red-400 font-mono">
                                    {{ number_format($unresolvedValue, 2) }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        @foreach($topIssues as $issue)
                                            <span class="px-1.5 py-0.5 text-xs rounded bg-gray-700 text-gray-300 font-mono"
                                                  title="{{ $issue['description'] ?? '' }}">
                                                {{ $issue['article_code'] }}
                                            </span>
                                        @endforeach
                                        @if(count($invoice->rtd_resolution_issues ?? []) > 3)
                                            <span class="px-1.5 py-0.5 text-xs rounded bg-gray-600 text-gray-400">
                                                +{{ count($invoice->rtd_resolution_issues) - 3 }} more
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex justify-end space-x-2">
                                        <a href="{{ route('rtd-fallbacks.unresolved', ['invoice_id' => $invoice->id]) }}"
                                           class="bg-yellow-600 hover:bg-yellow-700 text-white text-xs px-3 py-1 rounded"
                                           title="Resolve unresolved items">
                                            Resolve
                                        </a>
                                        <form action="{{ route('rtd.compute', $invoice) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-xs px-3 py-1 rounded"
                                                    title="Recompute RTD">
                                                Recompute
                                            </button>
                                        </form>
                                        <a href="{{ route('invoices.show', $invoice) }}"
                                           class="bg-gray-600 hover:bg-gray-500 text-white text-xs px-3 py-1 rounded"
                                           title="View Invoice">
                                            View
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                {{-- Pagination --}}
                @if($invoices->hasPages())
                    <div class="bg-gray-900 px-4 py-3 border-t border-gray-700">
                        {{ $invoices->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-admin-layout>
