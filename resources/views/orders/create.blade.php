<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Generate New Order') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <form method="POST" action="{{ route('orders.store') }}" class="space-y-6">
                        @csrf

                        <!-- Supplier Selection -->
                        <div>
                            <label for="supplier_id" class="block text-sm font-medium text-gray-700">
                                Supplier
                            </label>
                            <select name="supplier_id" id="supplier_id" required 
                                    class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                                <option value="">Select a supplier...</option>
                                @foreach($suppliers as $supplier)
                                    <option value="{{ $supplier->SupplierID }}" 
                                            {{ old('supplier_id') == $supplier->SupplierID ? 'selected' : '' }}>
                                        {{ $supplier->Supplier }}
                                    </option>
                                @endforeach
                            </select>
                            @error('supplier_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Order Date -->
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <div>
                                <label for="order_date" class="block text-sm font-medium text-gray-700">
                                    Delivery Date
                                </label>
                                <input type="date" name="order_date" id="order_date" required
                                       value="{{ old('order_date', now()->addDays(7)->format('Y-m-d')) }}"
                                       min="{{ now()->format('Y-m-d') }}"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                @error('order_date')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-sm text-gray-500">
                                    Default picks next week. Adjust if the delivery schedule is different.
                                </p>
                            </div>

                            <div>
                                <label for="coverage_end_date" class="block text-sm font-medium text-gray-700">
                                    Cover Inventory Until
                                </label>
                                <input type="date" name="coverage_end_date" id="coverage_end_date" required
                                       value="{{ old('coverage_end_date', now()->addDays(21)->format('Y-m-d')) }}"
                                       min="{{ now()->format('Y-m-d') }}"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                @error('coverage_end_date')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-sm text-gray-500">
                                    We’ll size the order to keep shelves stocked through this date (including product safety buffers).
                                </p>
                            </div>
                        </div>

                        <!-- Sales History Window -->
                        <div>
                            <label for="sales_history_weeks" class="block text-sm font-medium text-gray-700">
                                Weeks of Sales to Analyse
                            </label>
                            <input type="number" name="sales_history_weeks" id="sales_history_weeks"
                                   value="{{ old('sales_history_weeks', 8) }}"
                                   min="1" max="26"
                                   class="mt-1 block w-32 border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            @error('sales_history_weeks')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-sm text-gray-500">
                                Defaults to the last 8 weeks. Increase for seasonal suppliers or reduce for fast-moving categories.
                            </p>
                        </div>

                        <!-- Information Panel -->
                        <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-blue-800">
                                        How Order Generation Works
                                    </h3>
                                    <div class="mt-2 text-sm text-blue-700 space-y-1">
                                        <p>Select the delivery date, how long the order should carry your inventory, and how many weeks of sales to review.</p>
                                        <ul class="list-disc pl-5 space-y-1">
                                            <li>Projects demand from the chosen sales history (defaults to 8 weeks).</li>
                                            <li>Targets coverage through the date you select, honouring each product’s safety buffer.</li>
                                            <li>Pulls live stock levels and adjusts for previous manual tweaks.</li>
                                            <li>Flags items by review priority so you know what needs attention first.</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex items-center justify-between pt-4">
                            <a href="{{ route('orders.index') }}" 
                               class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded inline-flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                                </svg>
                                Back to Orders
                            </a>
                            
                            <button type="submit" 
                                    class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded inline-flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                                Generate Order Suggestions
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Quick Actions for Frequent Suppliers -->
            @if($suppliers->where('Supplier', 'like', '%Udea%')->first())
                <div class="mt-6 bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h3>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            @php $udeaSupplier = $suppliers->where('Supplier', 'like', '%Udea%')->first(); @endphp
                            @if($udeaSupplier)
                                <form method="POST" action="{{ route('orders.store') }}" class="inline-block">
                                    @csrf
                                    <input type="hidden" name="supplier_id" value="{{ $udeaSupplier->SupplierID }}">
                                    <input type="hidden" name="order_date" value="{{ now()->addDays(7)->format('Y-m-d') }}">
                                    <input type="hidden" name="coverage_end_date" value="{{ now()->addDays(21)->format('Y-m-d') }}">
                                    <input type="hidden" name="sales_history_weeks" value="8">
                                    
                                    <button type="submit" 
                                            class="w-full bg-green-500 hover:bg-green-700 text-white font-bold py-3 px-4 rounded inline-flex items-center justify-center">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                  d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                        </svg>
                                        Quick Udea Order (Next Week)
                                    </button>
                                </form>
                            @endif
                            
                            <button onclick="alert('Feature coming soon!')" 
                                    class="w-full bg-purple-500 hover:bg-purple-700 text-white font-bold py-3 px-4 rounded inline-flex items-center justify-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                                Repeat Last Week's Order
                            </button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-admin-layout>
