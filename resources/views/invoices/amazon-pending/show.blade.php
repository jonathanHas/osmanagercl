<x-admin-layout>
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        {{-- Header --}}
        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-100">Enter EUR Payment</h2>
                <p class="text-gray-400 text-sm mt-1">Amazon Invoice: {{ $pending->uploadFile->original_filename }}</p>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('amazon-pending.viewer', $pending) }}" 
                   class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded inline-flex items-center"
                   title="View original invoice PDF" 
                   onclick="event.preventDefault(); window.open(this.href, 'invoice-viewer', 'width=1200,height=800,scrollbars=yes,resizable=yes'); return false;">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    View Invoice
                </a>
                <a href="{{ route('amazon-pending.index') }}" 
                   class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    ← Back to Pending List
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="bg-green-900/30 border border-green-700 rounded-lg p-4 mb-6">
                <div class="flex">
                    <svg class="w-5 h-5 text-green-400 mr-3 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <p class="text-green-300">{{ session('success') }}</p>
                </div>
            </div>
        @endif

        @if (session('error'))
            <div class="bg-red-900/30 border border-red-700 rounded-lg p-4 mb-6">
                <div class="flex">
                    <svg class="w-5 h-5 text-red-400 mr-3 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    <p class="text-red-300">{{ session('error') }}</p>
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Invoice Information --}}
            <div class="bg-gray-800 rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-100 mb-4">Invoice Information</h3>
                
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-gray-400">File:</span>
                        <span class="text-gray-200">{{ $pending->uploadFile->original_filename }}</span>
                    </div>
                    
                    @if($pending->invoice_date)
                    <div class="flex justify-between">
                        <span class="text-gray-400">Invoice Date:</span>
                        <span class="text-gray-200">{{ $pending->invoice_date->format('d/m/Y') }}</span>
                    </div>
                    @endif
                    
                    @if($pending->invoice_number)
                    <div class="flex justify-between">
                        <span class="text-gray-400">Invoice Number:</span>
                        <span class="text-gray-200">{{ $pending->invoice_number }}</span>
                    </div>
                    @endif
                    
                    <div class="flex justify-between">
                        <span class="text-gray-400">GBP Amount (shown on invoice):</span>
                        <span class="text-gray-200 font-medium">{{ $pending->formatted_gbp_amount }}</span>
                    </div>
                    
                    <div class="flex justify-between">
                        <span class="text-gray-400">Status:</span>
                        <span class="px-2 py-1 text-xs rounded-full bg-{{ $pending->status_color }}-900 text-{{ $pending->status_color }}-300">
                            {{ $pending->status_label }}
                        </span>
                    </div>
                    
                    <div class="flex justify-between">
                        <span class="text-gray-400">Days Pending:</span>
                        <span class="text-gray-200">{{ $pending->days_pending }} {{ $pending->days_pending === 1 ? 'day' : 'days' }}</span>
                    </div>
                    
                    {{-- EUR VAT Detection Status --}}
                    @if(isset($pending->parsed_data['EUR_VAT_Found']))
                    <div class="border-t border-gray-700 pt-3 mt-3">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-400">EUR VAT Detected:</span>
                            @if($pending->parsed_data['EUR_VAT_Found'])
                                <div class="flex items-center">
                                    <svg class="w-4 h-4 text-green-400 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    <span class="text-green-400">Yes</span>
                                </div>
                            @else
                                <div class="flex items-center">
                                    <svg class="w-4 h-4 text-yellow-400 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                    </svg>
                                    <span class="text-yellow-400">Not found</span>
                                </div>
                            @endif
                        </div>
                        
                        @if($pending->parsed_data['EUR_VAT_Found'] && isset($pending->parsed_data['EUR_VAT_Amount']))
                        <div class="flex justify-between">
                            <span class="text-gray-400">Parser Found EUR VAT:</span>
                            <span class="text-green-400 font-medium">€{{ $pending->parsed_data['EUR_VAT_Amount'] }}</span>
                        </div>
                        @endif
                        
                        @if(!$pending->parsed_data['EUR_VAT_Found'])
                        <div class="text-xs text-yellow-300 mt-1">
                            <strong>Note:</strong> Please check invoice PDF and enter the actual EUR amount paid from your bank statement.
                        </div>
                        @endif
                    </div>
                    @endif

                    @if($pending->hasPaymentEntered())
                    <div class="border-t border-gray-700 pt-3 mt-3">
                        <div class="flex justify-between">
                            <span class="text-gray-400">EUR Payment:</span>
                            <span class="text-green-400 font-medium">{{ $pending->formatted_eur_amount }}</span>
                        </div>
                        
                        @if($pending->exchange_rate)
                        <div class="flex justify-between">
                            <span class="text-gray-400">Exchange Rate:</span>
                            <span class="text-gray-200">{{ $pending->exchange_rate }}</span>
                        </div>
                        @endif
                        
                        <div class="flex justify-between">
                            <span class="text-gray-400">Entered by:</span>
                            <span class="text-gray-200">{{ $pending->paymentEnteredBy->name ?? 'Unknown' }}</span>
                        </div>
                        
                        <div class="flex justify-between">
                            <span class="text-gray-400">Entered on:</span>
                            <span class="text-gray-200">{{ $pending->payment_entered_at->format('d/m/Y H:i') }}</span>
                        </div>
                        
                        @if($pending->notes)
                        <div class="mt-2">
                            <span class="text-gray-400 block">Notes:</span>
                            <span class="text-gray-200 text-sm">{{ $pending->notes }}</span>
                        </div>
                        @endif
                    </div>
                    @endif
                </div>
            </div>

            {{-- Payment Entry Form --}}
            <div class="bg-gray-800 rounded-lg p-6">
                @if($pending->hasPaymentEntered())
                    {{-- Process Invoice Form --}}
                    <h3 class="text-lg font-semibold text-green-400 mb-4">
                        <svg class="w-5 h-5 inline mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        Ready to Process
                    </h3>
                    
                    <div class="mb-4">
                        <p class="text-gray-300 mb-2">Payment information has been entered. You can now create the actual invoice.</p>
                        
                        {{-- VAT Preview --}}
                        <div class="bg-gray-700 rounded p-4 mb-4" id="vatPreview">
                            <h4 class="text-sm font-medium text-gray-300 mb-2">VAT Calculation Preview</h4>
                            <div class="text-sm space-y-1">
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Net @ 0%:</span>
                                    <span class="text-gray-200" id="net0">€{{ number_format($pending->actual_payment_eur, 2) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-400">VAT @ 0%:</span>
                                    <span class="text-gray-200" id="vat0">€0.00</span>
                                </div>
                                <div class="flex justify-between border-t border-gray-600 pt-1">
                                    <span class="text-gray-300 font-medium">Total:</span>
                                    <span class="text-gray-200 font-medium" id="total">€{{ number_format($pending->actual_payment_eur, 2) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex space-x-3">
                        <form action="{{ route('amazon-pending.process', $pending) }}" method="POST" class="flex-1">
                            @csrf
                            <button type="submit" 
                                    onclick="return confirm('Create invoice for {{ $pending->formatted_eur_amount }}? This cannot be undone.')"
                                    class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-4 rounded">
                                <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Create Invoice ({{ $pending->formatted_eur_amount }})
                            </button>
                        </form>
                        
                        <button onclick="showEditForm()" 
                                class="bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-3 px-4 rounded">
                            Edit
                        </button>
                    </div>
                @else
                    {{-- Payment Entry Form --}}
                    <h3 class="text-lg font-semibold text-gray-100 mb-4">Enter EUR Payment</h3>
                    
                    <form action="{{ route('amazon-pending.update-payment', $pending) }}" method="POST" id="paymentForm">
                        @csrf
                        @method('PUT')
                        
                        <div class="space-y-4">
                            <div>
                                <label for="actual_payment_eur" class="block text-sm font-medium text-gray-300 mb-2">
                                    Actual EUR Amount Paid
                                    <span class="text-red-400">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute left-3 top-3 text-gray-400">€</span>
                                    <input type="number" 
                                           step="0.01" 
                                           min="0.01" 
                                           max="50000"
                                           name="actual_payment_eur" 
                                           id="actual_payment_eur"
                                           value="{{ old('actual_payment_eur') }}"
                                           placeholder="22.65"
                                           class="w-full pl-8 pr-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:border-blue-500 focus:ring-blue-500"
                                           required
                                           onchange="updateVATPreview()">
                                </div>
                                @error('actual_payment_eur')
                                    <div class="text-red-400 text-sm mt-1">
                                        @if(is_array($message))
                                            @foreach($message as $error)
                                                <div>{{ $error }}</div>
                                            @endforeach
                                        @else
                                            {{ $message }}
                                        @endif
                                    </div>
                                @enderror
                                <p class="text-gray-400 text-xs mt-1">Enter the actual amount charged to your bank account in EUR</p>
                            </div>

                            <div>
                                <label for="notes" class="block text-sm font-medium text-gray-300 mb-2">
                                    Notes (optional)
                                </label>
                                <textarea name="notes" 
                                          id="notes" 
                                          rows="3"
                                          placeholder="Exchange rate notes, discrepancies, etc..."
                                          class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:border-blue-500 focus:ring-blue-500">{{ old('notes') }}</textarea>
                            </div>

                            {{-- VAT Preview --}}
                            <div class="bg-gray-700 rounded p-4 hidden" id="vatPreview">
                                <h4 class="text-sm font-medium text-gray-300 mb-2">VAT Calculation Preview</h4>
                                <div class="text-sm space-y-1">
                                    <div class="flex justify-between">
                                        <span class="text-gray-400">Net @ 0%:</span>
                                        <span class="text-gray-200" id="net0">€0.00</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-400">VAT @ 0%:</span>
                                        <span class="text-gray-200" id="vat0">€0.00</span>
                                    </div>
                                    <div class="flex justify-between border-t border-gray-600 pt-1">
                                        <span class="text-gray-300 font-medium">Total:</span>
                                        <span class="text-gray-200 font-medium" id="total">€0.00</span>
                                    </div>
                                    <div class="flex justify-between text-xs">
                                        <span class="text-gray-500">Exchange Rate:</span>
                                        <span class="text-gray-400" id="exchangeRate">-</span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex space-x-3 pt-4">
                                <button type="submit" 
                                        class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded">
                                    Save Payment Information
                                </button>
                                
                                <a href="{{ route('amazon-pending.index') }}" 
                                   class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-3 px-4 rounded">
                                    Cancel
                                </a>
                            </div>
                        </div>
                    </form>
                @endif
            </div>
        </div>

        @if($pending->hasPaymentEntered())
        {{-- Hidden edit form --}}
        <div id="editForm" class="hidden mt-6">
            <div class="bg-gray-800 rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-100 mb-4">Edit Payment Information</h3>
                
                <form action="{{ route('amazon-pending.update-payment', $pending) }}" method="POST">
                    @csrf
                    @method('PUT')
                    
                    <div class="space-y-4">
                        <div>
                            <label for="edit_actual_payment_eur" class="block text-sm font-medium text-gray-300 mb-2">
                                Actual EUR Amount Paid
                            </label>
                            <div class="relative">
                                <span class="absolute left-3 top-3 text-gray-400">€</span>
                                <input type="number" 
                                       step="0.01" 
                                       min="0.01" 
                                       max="50000"
                                       name="actual_payment_eur" 
                                       id="edit_actual_payment_eur"
                                       value="{{ $pending->actual_payment_eur }}"
                                       class="w-full pl-8 pr-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-blue-500 focus:ring-blue-500"
                                       required>
                            </div>
                        </div>

                        <div>
                            <label for="edit_notes" class="block text-sm font-medium text-gray-300 mb-2">
                                Notes
                            </label>
                            <textarea name="notes" 
                                      id="edit_notes" 
                                      rows="3"
                                      class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-blue-500 focus:ring-blue-500">{{ $pending->notes }}</textarea>
                        </div>

                        <div class="flex space-x-3">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                Update Payment
                            </button>
                            
                            <button type="button" onclick="hideEditForm()" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                                Cancel
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        @endif
    </div>

    @push('scripts')
    <script>
        function updateVATPreview() {
            const eurAmountInput = document.getElementById('actual_payment_eur');
            const vatPreview = document.getElementById('vatPreview');
            const eurAmount = parseFloat(eurAmountInput.value);
            
            if (eurAmount && eurAmount > 0) {
                // For simplicity, Amazon invoices are typically 0% VAT
                const net0 = eurAmount;
                const vat0 = 0;
                const total = net0 + vat0;
                const gbpAmount = {{ $pending->gbp_amount ?? 0 }};
                const exchangeRate = gbpAmount > 0 ? (eurAmount / gbpAmount) : 0;
                
                document.getElementById('net0').textContent = '€' + net0.toFixed(2);
                document.getElementById('vat0').textContent = '€' + vat0.toFixed(2);
                document.getElementById('total').textContent = '€' + total.toFixed(2);
                
                if (exchangeRate > 0) {
                    document.getElementById('exchangeRate').textContent = exchangeRate.toFixed(4);
                }
                
                vatPreview.classList.remove('hidden');
            } else {
                vatPreview.classList.add('hidden');
            }
        }
        
        function showEditForm() {
            document.getElementById('editForm').classList.remove('hidden');
        }
        
        function hideEditForm() {
            document.getElementById('editForm').classList.add('hidden');
        }
        
        // Auto-focus on payment input
        @if(!$pending->hasPaymentEntered())
        document.getElementById('actual_payment_eur').focus();
        @endif
    </script>
    @endpush
</x-admin-layout>