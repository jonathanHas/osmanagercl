<x-admin-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        {{-- Header --}}
        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-100">Amazon Pending Payments</h2>
                <p class="text-gray-400 text-sm mt-1">Amazon invoices awaiting EUR payment entry</p>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('invoices.bulk-upload.index') }}" 
                   class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>
                    Upload Invoices
                </a>
                <a href="{{ route('invoices.index') }}" 
                   class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    Back to Invoices
                </a>
            </div>
        </div>

        {{-- Summary Cards --}}
        @if($summary['total_count'] > 0)
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-yellow-900/30 border border-yellow-600 rounded-lg p-4">
                <div class="flex items-center">
                    <svg class="w-8 h-8 text-yellow-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                    <div>
                        <p class="text-2xl font-bold text-yellow-300">{{ $summary['total_count'] }}</p>
                        <p class="text-yellow-400 text-sm">Pending Invoices</p>
                    </div>
                </div>
            </div>

            <div class="bg-gray-800 rounded-lg p-4">
                <div class="flex items-center">
                    <svg class="w-8 h-8 text-gray-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zM14 6a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2V8a2 2 0 012-2h6zM4 14a2 2 0 002 2h8a2 2 0 002-2v-2H4v2z"/>
                    </svg>
                    <div>
                        <p class="text-2xl font-bold text-gray-100">£{{ number_format($summary['total_gbp_amount'], 2) }}</p>
                        <p class="text-gray-400 text-sm">Total GBP Amount</p>
                    </div>
                </div>
            </div>

            <div class="bg-gray-800 rounded-lg p-4">
                <div class="flex items-center">
                    <svg class="w-8 h-8 text-blue-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                    </svg>
                    <div>
                        <p class="text-2xl font-bold text-gray-100">{{ $summary['oldest_days'] }}</p>
                        <p class="text-gray-400 text-sm">Days (Oldest)</p>
                    </div>
                </div>
            </div>

            <div class="bg-gray-800 rounded-lg p-4">
                <div class="flex items-center">
                    <svg class="w-8 h-8 text-green-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                    <div>
                        <p class="text-2xl font-bold text-gray-100">{{ $summary['recent_count'] }}</p>
                        <p class="text-gray-400 text-sm">This Week</p>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- Main Content --}}
        @if($pendingInvoices->count() > 0)
        <div class="bg-gray-800 rounded-lg">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-100">
                        Pending Amazon Invoices
                        <span class="ml-2 px-2 py-1 text-xs rounded-full bg-yellow-900 text-yellow-300">
                            {{ $pendingInvoices->count() }} awaiting payment
                        </span>
                    </h3>
                    
                    @php $readyToProcess = $pendingInvoices->filter(fn($p) => $p->canBeProcessed())->count(); @endphp
                    @if($readyToProcess > 0)
                    <form action="{{ route('amazon-pending.bulk-process') }}" method="POST" class="inline">
                        @csrf
                        <button type="button" onclick="selectReadyAndSubmit()" 
                                class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                            <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Process All Ready ({{ $readyToProcess }})
                        </button>
                    </form>
                    @endif
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="border-b border-gray-700">
                                <th class="text-left py-3 px-4 text-xs font-medium text-gray-400 uppercase tracking-wider">
                                    Invoice Details
                                </th>
                                <th class="text-left py-3 px-4 text-xs font-medium text-gray-400 uppercase tracking-wider">
                                    Amounts
                                </th>
                                <th class="text-left py-3 px-4 text-xs font-medium text-gray-400 uppercase tracking-wider">
                                    Status
                                </th>
                                <th class="text-left py-3 px-4 text-xs font-medium text-gray-400 uppercase tracking-wider">
                                    Days Pending
                                </th>
                                <th class="text-left py-3 px-4 text-xs font-medium text-gray-400 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            @foreach($pendingInvoices as $pending)
                            <tr class="hover:bg-gray-700/50">
                                <td class="py-3 px-4">
                                    <div>
                                        <p class="text-gray-200 font-medium">{{ $pending->uploadFile->original_filename }}</p>
                                        @if($pending->invoice_date)
                                            <p class="text-gray-400 text-sm">{{ $pending->invoice_date->format('d/m/Y') }}</p>
                                        @endif
                                        @if($pending->invoice_number)
                                            <p class="text-gray-500 text-xs">{{ $pending->invoice_number }}</p>
                                        @endif
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <div>
                                        <p class="text-gray-200">{{ $pending->formatted_gbp_amount }}</p>
                                        @if($pending->hasPaymentEntered())
                                            <p class="text-green-400 text-sm font-medium">{{ $pending->formatted_eur_amount }}</p>
                                            @if($pending->exchange_rate)
                                                <p class="text-gray-500 text-xs">Rate: {{ $pending->exchange_rate }}</p>
                                            @endif
                                        @else
                                            <p class="text-yellow-400 text-sm">EUR payment needed</p>
                                        @endif
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="px-2 py-1 text-xs rounded-full bg-{{ $pending->status_color }}-900 text-{{ $pending->status_color }}-300">
                                        {{ $pending->status_label }}
                                    </span>
                                    @if($pending->hasPaymentEntered())
                                        <p class="text-gray-400 text-xs mt-1">
                                            by {{ $pending->paymentEnteredBy->name ?? 'Unknown' }}
                                        </p>
                                    @endif
                                </td>
                                <td class="py-3 px-4">
                                    <span class="text-gray-300">{{ $pending->days_pending }}</span>
                                    <span class="text-gray-500 text-sm">
                                        {{ $pending->days_pending === 1 ? 'day' : 'days' }}
                                    </span>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex space-x-2">
                                        <a href="{{ route('amazon-pending.show', $pending) }}" 
                                           class="text-blue-400 hover:text-blue-300 text-sm font-medium">
                                            @if($pending->hasPaymentEntered())
                                                Process
                                            @else
                                                Enter Payment
                                            @endif
                                        </a>
                                        
                                        <a href="{{ route('amazon-pending.viewer', $pending) }}" 
                                           class="text-gray-400 hover:text-gray-300 text-sm" 
                                           title="View invoice PDF" 
                                           onclick="event.preventDefault(); window.open(this.href, 'invoice-viewer', 'width=1200,height=800,scrollbars=yes,resizable=yes'); return false;">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                        </a>
                                        
                                        @if(!$pending->hasPaymentEntered())
                                        <form action="{{ route('amazon-pending.cancel', $pending) }}" 
                                              method="POST" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" 
                                                    onclick="return confirm('Cancel this pending invoice? It will be moved back to review status.')"
                                                    class="text-red-400 hover:text-red-300 text-sm">
                                                Cancel
                                            </button>
                                        </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @else
        {{-- Empty State --}}
        <div class="bg-gray-800 rounded-lg p-12 text-center">
            <svg class="w-16 h-16 text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <h3 class="text-xl font-medium text-gray-300 mb-2">No Pending Amazon Invoices</h3>
            <p class="text-gray-400 mb-4">All Amazon invoices have been processed or no Amazon invoices have been uploaded recently.</p>
            <a href="{{ route('invoices.bulk-upload.index') }}" 
               class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                </svg>
                Upload Invoices
            </a>
        </div>
        @endif
    </div>

    @push('scripts')
    <script>
        function selectReadyAndSubmit() {
            const form = event.target.closest('form');
            
            // Find all pending invoices that can be processed
            const readyRows = document.querySelectorAll('tbody tr');
            const readyIds = [];
            
            readyRows.forEach(row => {
                const processLink = row.querySelector('a[href*="amazon-pending"]');
                if (processLink && processLink.textContent.trim() === 'Process') {
                    // Extract ID from the href
                    const href = processLink.href;
                    const id = href.split('/').pop();
                    readyIds.push(id);
                }
            });
            
            if (readyIds.length === 0) {
                alert('No invoices are ready for processing.');
                return;
            }
            
            if (confirm(`Process ${readyIds.length} invoice(s)? This will create actual invoices from the pending payments.`)) {
                // Add hidden inputs for each ID
                readyIds.forEach(id => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'pending_ids[]';
                    input.value = id;
                    form.appendChild(input);
                });
                
                form.submit();
            }
        }
    </script>
    @endpush
</x-admin-layout>