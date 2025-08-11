<x-admin-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        {{-- Header --}}
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-100">VAT Rates Management</h2>
            <a href="{{ route('invoices.index') }}" 
               class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                Back to Invoices
            </a>
        </div>

        @if(session('success'))
            <div class="bg-green-900 border border-green-700 text-green-300 px-4 py-3 rounded mb-6">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-900 border border-red-700 text-red-300 px-4 py-3 rounded mb-6">
                {{ session('error') }}
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Current VAT Rates --}}
            <div class="lg:col-span-2">
                <div class="bg-gray-800 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-100 mb-4">Current VAT Rates</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr class="border-b border-gray-700">
                                    <th class="text-left text-xs font-medium text-gray-400 uppercase pb-2">Code</th>
                                    <th class="text-left text-xs font-medium text-gray-400 uppercase pb-2">Name</th>
                                    <th class="text-center text-xs font-medium text-gray-400 uppercase pb-2">Rate</th>
                                    <th class="text-center text-xs font-medium text-gray-400 uppercase pb-2">Effective From</th>
                                    <th class="text-center text-xs font-medium text-gray-400 uppercase pb-2">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($currentRates as $rate)
                                    <tr class="border-b border-gray-700">
                                        <td class="py-3 text-gray-300 font-semibold">{{ $rate->code }}</td>
                                        <td class="py-3 text-gray-300">{{ $rate->name }}</td>
                                        <td class="py-3 text-center text-white font-semibold">{{ $rate->formatted_rate }}</td>
                                        <td class="py-3 text-center text-gray-300">{{ $rate->effective_from->format('d/m/Y') }}</td>
                                        <td class="py-3 text-center">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-900 text-green-300">
                                                Active
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- VAT Rate History --}}
                <div class="bg-gray-800 rounded-lg p-6 mt-6">
                    <h3 class="text-lg font-semibold text-gray-100 mb-4">All VAT Rates (Including Future & Historical)</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr class="border-b border-gray-700">
                                    <th class="text-left text-xs font-medium text-gray-400 uppercase pb-2">Code</th>
                                    <th class="text-left text-xs font-medium text-gray-400 uppercase pb-2">Name</th>
                                    <th class="text-center text-xs font-medium text-gray-400 uppercase pb-2">Rate</th>
                                    <th class="text-center text-xs font-medium text-gray-400 uppercase pb-2">Effective From</th>
                                    <th class="text-center text-xs font-medium text-gray-400 uppercase pb-2">Effective To</th>
                                    <th class="text-center text-xs font-medium text-gray-400 uppercase pb-2">Status</th>
                                    <th class="text-center text-xs font-medium text-gray-400 uppercase pb-2">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($vatRates as $rate)
                                    @php
                                        $isActive = $rate->effective_from <= now() && (!$rate->effective_to || $rate->effective_to >= now());
                                        $isFuture = $rate->effective_from > now();
                                        $isPast = $rate->effective_to && $rate->effective_to < now();
                                    @endphp
                                    <tr class="border-b border-gray-700 {{ $isPast ? 'opacity-50' : '' }}">
                                        <td class="py-3 text-gray-300 font-semibold">{{ $rate->code }}</td>
                                        <td class="py-3 text-gray-300">{{ $rate->name }}</td>
                                        <td class="py-3 text-center text-white font-semibold">{{ $rate->formatted_rate }}</td>
                                        <td class="py-3 text-center text-gray-300">{{ $rate->effective_from->format('d/m/Y') }}</td>
                                        <td class="py-3 text-center text-gray-300">
                                            {{ $rate->effective_to ? $rate->effective_to->format('d/m/Y') : '-' }}
                                        </td>
                                        <td class="py-3 text-center">
                                            @if($isActive)
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-900 text-green-300">
                                                    Active
                                                </span>
                                            @elseif($isFuture)
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-900 text-blue-300">
                                                    Future
                                                </span>
                                            @else
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-700 text-gray-400">
                                                    Historical
                                                </span>
                                            @endif
                                        </td>
                                        <td class="py-3 text-center">
                                            @if($isFuture)
                                                <form action="{{ route('vat-rates.destroy', $rate) }}" method="POST" 
                                                      class="inline" onsubmit="return confirm('Delete this future VAT rate?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-red-400 hover:text-red-300">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </button>
                                                </form>
                                            @else
                                                <span class="text-gray-600">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Schedule New VAT Rate --}}
            <div class="lg:col-span-1">
                <div class="bg-gray-800 rounded-lg p-6 sticky top-6">
                    <h3 class="text-lg font-semibold text-gray-100 mb-4">Schedule VAT Rate Change</h3>
                    <p class="text-sm text-gray-400 mb-4">
                        Schedule a future VAT rate change. The new rate will automatically take effect on the specified date.
                    </p>
                    
                    <form action="{{ route('vat-rates.store') }}" method="POST" class="space-y-4">
                        @csrf
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">VAT Code *</label>
                            <select name="code" required class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md">
                                <option value="">Select VAT Code</option>
                                <option value="STANDARD">STANDARD - Standard Rate (23%)</option>
                                <option value="REDUCED">REDUCED - Reduced Rate (13.5%)</option>
                                <option value="SECOND_REDUCED">SECOND_REDUCED - Second Reduced Rate (9%)</option>
                                <option value="ZERO">ZERO - Zero Rate (0%)</option>
                            </select>
                            @error('code')
                                <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">Name *</label>
                            <input type="text" name="name" required
                                   class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md"
                                   placeholder="e.g. Standard Rate">
                            @error('name')
                                <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">Rate (%) *</label>
                            <input type="number" name="rate" required step="0.01" min="0" max="100"
                                   class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md"
                                   placeholder="e.g. 20 for 20%"
                                   oninput="this.value = this.value / 100">
                            <p class="text-xs text-gray-500 mt-1">Enter percentage (e.g., 20 for 20%)</p>
                            @error('rate')
                                <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">Effective From *</label>
                            <input type="date" name="effective_from" required
                                   min="{{ now()->addDay()->format('Y-m-d') }}"
                                   class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md">
                            <p class="text-xs text-gray-500 mt-1">Must be a future date</p>
                            @error('effective_from')
                                <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <button type="submit" 
                                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Schedule VAT Rate Change
                        </button>
                    </form>
                    
                    <div class="mt-6 p-4 bg-gray-900 rounded">
                        <h4 class="text-sm font-semibold text-yellow-400 mb-2">⚠️ Important Notes:</h4>
                        <ul class="text-xs text-gray-400 space-y-1">
                            <li>• Historical rates cannot be edited or deleted</li>
                            <li>• Future rates can be deleted before they take effect</li>
                            <li>• Rate changes affect new invoices from the effective date</li>
                            <li>• Existing invoices retain their original VAT rates</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        // Fix the rate input to convert percentage to decimal
        document.addEventListener('DOMContentLoaded', function() {
            const rateInput = document.querySelector('input[name="rate"]');
            if (rateInput) {
                // Override the oninput to properly handle the conversion
                rateInput.removeAttribute('oninput');
                
                // Store the display value
                let displayValue = '';
                
                rateInput.addEventListener('input', function(e) {
                    displayValue = e.target.value;
                });
                
                // Convert to decimal on form submit
                const form = rateInput.closest('form');
                form.addEventListener('submit', function(e) {
                    if (displayValue) {
                        rateInput.value = parseFloat(displayValue) / 100;
                    }
                });
            }
        });
    </script>
    @endpush
</x-admin-layout>