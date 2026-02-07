<x-admin-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        {{-- Header --}}
        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-100">RTD Supplier Classification</h2>
                <p class="text-gray-400 text-sm mt-1">Classify suppliers for RTD tracking - T1 (Goods for Resale) or T2 (Service/Overhead)</p>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('rtd.index') }}"
                   class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back to RTD
                </a>
            </div>
        </div>

        {{-- Stats Cards --}}
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
            <a href="{{ route('rtd.suppliers', ['classification' => 'all']) }}"
               class="bg-gray-800 rounded-lg p-4 hover:bg-gray-700 transition {{ $filterClassification === 'all' ? 'ring-2 ring-blue-500' : '' }}">
                <div class="text-3xl font-bold text-white">{{ $stats['total'] }}</div>
                <div class="text-sm text-gray-400">Total Suppliers</div>
            </a>
            <a href="{{ route('rtd.suppliers', ['classification' => 'goods_parser']) }}"
               class="bg-gray-800 rounded-lg p-4 hover:bg-gray-700 transition {{ $filterClassification === 'goods_parser' ? 'ring-2 ring-blue-500' : '' }}">
                <div class="text-3xl font-bold text-blue-400">{{ $stats['goods_parser'] }}</div>
                <div class="text-sm text-gray-400">Parser (T1)</div>
            </a>
            <a href="{{ route('rtd.suppliers', ['classification' => 'goods_simple']) }}"
               class="bg-gray-800 rounded-lg p-4 hover:bg-gray-700 transition {{ $filterClassification === 'goods_simple' ? 'ring-2 ring-purple-500' : '' }}">
                <div class="text-3xl font-bold text-purple-400">{{ $stats['goods_simple'] }}</div>
                <div class="text-sm text-gray-400">Simple VAT (T1)</div>
            </a>
            <a href="{{ route('rtd.suppliers', ['classification' => 'service_overhead']) }}"
               class="bg-gray-800 rounded-lg p-4 hover:bg-gray-700 transition {{ $filterClassification === 'service_overhead' ? 'ring-2 ring-yellow-500' : '' }}">
                <div class="text-3xl font-bold text-yellow-400">{{ $stats['service_overhead'] }}</div>
                <div class="text-sm text-gray-400">Service (T2)</div>
            </a>
            <a href="{{ route('rtd.suppliers', ['classification' => 'not_applicable']) }}"
               class="bg-gray-800 rounded-lg p-4 hover:bg-gray-700 transition {{ $filterClassification === 'not_applicable' ? 'ring-2 ring-gray-500' : '' }}">
                <div class="text-3xl font-bold text-gray-400">{{ $stats['not_applicable'] }}</div>
                <div class="text-sm text-gray-400">Not Tracked</div>
            </a>
        </div>

        {{-- Legend --}}
        <div class="bg-gray-800 rounded-lg p-4 mb-6">
            <h3 class="text-sm font-semibold text-gray-300 mb-2">Classification Guide</h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
                <div class="flex items-start">
                    <span class="px-2 py-1 rounded bg-blue-900 text-blue-300 text-xs font-semibold mr-2">Parser</span>
                    <span class="text-gray-400">T1 - Has dedicated invoice parser (Udea, Dynamis, IIH)</span>
                </div>
                <div class="flex items-start">
                    <span class="px-2 py-1 rounded bg-purple-900 text-purple-300 text-xs font-semibold mr-2">Simple</span>
                    <span class="text-gray-400">T1 - Uses invoice VAT breakdown fields directly</span>
                </div>
                <div class="flex items-start">
                    <span class="px-2 py-1 rounded bg-yellow-900 text-yellow-300 text-xs font-semibold mr-2">Service</span>
                    <span class="text-gray-400">T2 - Service/overhead supplier, tracked separately</span>
                </div>
                <div class="flex items-start">
                    <span class="px-2 py-1 rounded bg-gray-700 text-gray-300 text-xs font-semibold mr-2">N/A</span>
                    <span class="text-gray-400">Not tracked in RTD dashboard</span>
                </div>
            </div>
        </div>

        {{-- Filters --}}
        <div class="bg-gray-800 rounded-lg p-4 mb-4">
            <form action="{{ route('rtd.suppliers') }}" method="GET" class="flex flex-wrap items-center gap-4">
                <div class="relative flex-1 min-w-64">
                    <input type="text" name="search" value="{{ $search }}" placeholder="Search supplier name or code..."
                           class="w-full bg-gray-700 text-white rounded-lg pl-10 pr-4 py-2 focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                <select name="classification" class="bg-gray-700 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    <option value="all" {{ $filterClassification === 'all' ? 'selected' : '' }}>All Classifications</option>
                    @foreach($rtdClassifications as $value => $label)
                        <option value="{{ $value }}" {{ $filterClassification === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="type" class="bg-gray-700 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    <option value="all" {{ $filterType === 'all' ? 'selected' : '' }}>All Types</option>
                    @foreach($supplierTypes as $type)
                        <option value="{{ $type }}" {{ $filterType === $type ? 'selected' : '' }}>{{ ucfirst($type) }}</option>
                    @endforeach
                </select>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                    Filter
                </button>
                @if($search || $filterClassification !== 'all' || $filterType !== 'all')
                    <a href="{{ route('rtd.suppliers') }}" class="text-gray-400 hover:text-white">Clear</a>
                @endif
            </form>
        </div>

        {{-- Toast notification container --}}
        <div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>

        {{-- Suppliers Table --}}
        <div class="bg-gray-800 rounded-lg overflow-hidden">
            @if($suppliers->isEmpty())
                <div class="p-8 text-center text-gray-400">
                    <svg class="w-12 h-12 mx-auto mb-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                    <p>No suppliers found{{ $search ? ' matching your search' : '' }}.</p>
                </div>
            @else
                <table class="min-w-full divide-y divide-gray-700">
                    <thead class="bg-gray-900">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Supplier</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Type</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-400 uppercase tracking-wider">Invoices</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">VAT Treatment</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider w-64">RTD Classification</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        @foreach($suppliers as $supplier)
                            <tr class="hover:bg-gray-750" id="supplier-row-{{ $supplier->id }}">
                                <td class="px-4 py-3">
                                    <div>
                                        <a href="{{ route('suppliers.show', $supplier) }}" class="text-white font-medium hover:text-blue-400">
                                            {{ $supplier->name }}
                                        </a>
                                        <div class="text-xs text-gray-500">{{ $supplier->code }}</div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    @if($supplier->supplier_type)
                                        <span class="px-2 py-1 text-xs rounded bg-gray-700 text-gray-300">
                                            {{ ucfirst($supplier->supplier_type) }}
                                        </span>
                                    @else
                                        <span class="text-gray-500">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="text-gray-300">{{ $supplier->invoices_count }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    @if($supplier->vat_treatment)
                                        <span class="text-xs text-gray-400">
                                            {{ \App\Models\AccountingSupplier::VAT_TREATMENTS[$supplier->vat_treatment] ?? $supplier->vat_treatment }}
                                        </span>
                                    @else
                                        <span class="text-gray-500">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <select
                                            id="classification-{{ $supplier->id }}"
                                            data-supplier-id="{{ $supplier->id }}"
                                            data-supplier-name="{{ $supplier->name }}"
                                            onchange="updateClassification(this)"
                                            class="classification-select bg-gray-700 text-white text-sm rounded-lg px-3 py-1.5 border-0 focus:ring-2 focus:ring-blue-500 w-full
                                                {{ $supplier->rtd_classification === 'goods_parser' ? 'ring-1 ring-blue-500' : '' }}
                                                {{ $supplier->rtd_classification === 'goods_simple' ? 'ring-1 ring-purple-500' : '' }}
                                                {{ $supplier->rtd_classification === 'service_overhead' ? 'ring-1 ring-yellow-500' : '' }}">
                                            @foreach($rtdClassifications as $value => $label)
                                                <option value="{{ $value }}" {{ $supplier->rtd_classification === $value ? 'selected' : '' }}>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <span id="status-{{ $supplier->id }}" class="hidden">
                                            <svg class="animate-spin h-4 w-4 text-blue-400" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                        </span>
                                        <span id="success-{{ $supplier->id }}" class="hidden text-green-400">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                            </svg>
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                {{-- Count --}}
                <div class="bg-gray-900 px-4 py-3 text-sm text-gray-400">
                    Showing {{ $suppliers->count() }} supplier(s)
                </div>
            @endif
        </div>
    </div>

    <script>
        function updateClassification(selectElement) {
            const supplierId = selectElement.dataset.supplierId;
            const supplierName = selectElement.dataset.supplierName;
            const newValue = selectElement.value;
            const statusSpinner = document.getElementById('status-' + supplierId);
            const successIcon = document.getElementById('success-' + supplierId);

            // Show loading spinner
            statusSpinner.classList.remove('hidden');
            successIcon.classList.add('hidden');
            selectElement.disabled = true;

            fetch(`/rtd/suppliers/${supplierId}/classify`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    rtd_classification: newValue
                })
            })
            .then(response => response.json())
            .then(data => {
                // Hide spinner
                statusSpinner.classList.add('hidden');
                selectElement.disabled = false;

                if (data.success) {
                    // Show success icon briefly
                    successIcon.classList.remove('hidden');
                    setTimeout(() => {
                        successIcon.classList.add('hidden');
                    }, 2000);

                    // Update select ring color
                    updateSelectStyle(selectElement, newValue);

                    // Show toast
                    showToast(data.message, 'success');
                } else {
                    showToast(data.message || 'Failed to update', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                statusSpinner.classList.add('hidden');
                selectElement.disabled = false;
                showToast('An error occurred', 'error');
            });
        }

        function updateSelectStyle(selectElement, value) {
            // Remove all ring classes
            selectElement.classList.remove('ring-1', 'ring-blue-500', 'ring-purple-500', 'ring-yellow-500');

            // Add appropriate ring based on value
            if (value === 'goods_parser') {
                selectElement.classList.add('ring-1', 'ring-blue-500');
            } else if (value === 'goods_simple') {
                selectElement.classList.add('ring-1', 'ring-purple-500');
            } else if (value === 'service_overhead') {
                selectElement.classList.add('ring-1', 'ring-yellow-500');
            }
        }

        function showToast(message, type) {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `px-4 py-3 rounded-lg shadow-lg max-w-sm transform transition-all duration-300 ease-out translate-x-full ${
                type === 'success' ? 'bg-green-900 border border-green-500 text-green-300' : 'bg-red-900 border border-red-500 text-red-300'
            }`;
            toast.innerHTML = `
                <div class="flex justify-between items-center">
                    <span class="text-sm">${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-lg leading-none">&times;</button>
                </div>
            `;
            container.appendChild(toast);

            // Animate in
            requestAnimationFrame(() => {
                toast.classList.remove('translate-x-full');
            });

            // Auto-remove after 3 seconds
            setTimeout(() => {
                toast.classList.add('translate-x-full');
                setTimeout(() => {
                    if (toast.parentElement) {
                        toast.remove();
                    }
                }, 300);
            }, 3000);
        }
    </script>
</x-admin-layout>
