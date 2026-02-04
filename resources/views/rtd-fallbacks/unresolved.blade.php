<x-admin-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        {{-- Header --}}
        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-100">
                    RTD Unresolved Items
                    @if($filteredInvoice ?? null)
                        <span class="text-lg font-normal text-gray-400">· Invoice #{{ $filteredInvoice->invoice_number }}</span>
                    @endif
                </h2>
                <p class="text-gray-400 text-sm mt-1">
                    @if($filteredInvoice ?? null)
                        Showing unresolved items from invoice #{{ $filteredInvoice->invoice_number }}
                        · <a href="{{ route('rtd-fallbacks.unresolved') }}" class="text-blue-400 hover:text-blue-300">View All Unresolved</a>
                    @else
                        Assign VAT rates to article codes that don't have product links
                    @endif
                    @if($supplier ?? null)
                        · <span class="text-blue-400">{{ $supplier->name }}</span>
                    @endif
                </p>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('rtd-fallbacks.index') }}"
                   class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    View All Fallbacks
                </a>
                <form action="{{ route('rtd-fallbacks.recompute-affected') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"
                            onclick="return confirm('Recompute RTD for all invoices with unresolved items?')">
                        Recompute All Affected
                    </button>
                </form>
            </div>
        </div>

        {{-- Flash Messages --}}
        @if(session('success'))
            <div class="bg-green-600 text-white px-4 py-3 rounded mb-6">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-600 text-white px-4 py-3 rounded mb-6">
                {{ session('error') }}
            </div>
        @endif

        @if($unresolvedItems->isEmpty())
            <div class="bg-green-900/30 border border-green-700 rounded-lg p-8 text-center">
                <svg class="w-16 h-16 mx-auto text-green-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <h3 class="text-xl font-semibold text-green-400 mb-2">All Items Resolved</h3>
                <p class="text-gray-400">There are no unresolved article codes across your invoices.</p>
            </div>
        @else
            {{-- Bulk Assignment Form --}}
            <form action="{{ route('rtd-fallbacks.bulk-assign') }}" method="POST" id="bulkAssignForm">
                @csrf
                <input type="hidden" name="supplier_id" value="{{ $supplierId }}">

                {{-- Bulk Actions Bar --}}
                <div class="bg-gray-800 rounded-lg p-4 mb-4 flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <label class="flex items-center text-gray-300">
                            <input type="checkbox" id="selectAll" class="mr-2 rounded bg-gray-700 border-gray-600">
                            Select All
                        </label>
                        <span id="selectedCount" class="text-gray-400 text-sm">0 selected</span>
                    </div>

                    <div class="flex items-center space-x-4">
                        <label class="text-gray-300">
                            Assign VAT Rate:
                            <select name="vat_rate" required class="ml-2 px-3 py-1.5 bg-gray-700 border border-gray-600 rounded text-white">
                                <option value="">-- Select --</option>
                                <option value="0">0% (Zero rated)</option>
                                <option value="9">9% (Reduced)</option>
                                <option value="13.5">13.5% (Second reduced)</option>
                                <option value="23">23% (Standard)</option>
                            </select>
                        </label>

                        <button type="submit"
                                class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded disabled:opacity-50"
                                id="assignBtn" disabled>
                            Assign Selected
                        </button>
                    </div>
                </div>

                {{-- Items Table --}}
                <div class="bg-gray-800 rounded-lg overflow-hidden">
                    <table class="w-full">
                        <thead class="bg-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase w-12"></th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase">Article Code</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase">Description</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-300 uppercase">Total Value</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-300 uppercase">Occurrences</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-300 uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            @foreach($unresolvedItems as $item)
                                @php
                                    $hasExistingFallback = isset($existingFallbacks[$item['article_code']]);
                                    $existingRate = $hasExistingFallback ? $existingFallbacks[$item['article_code']] : null;
                                @endphp
                                <tr class="hover:bg-gray-700/50 {{ $hasExistingFallback ? 'opacity-60' : '' }}">
                                    <td class="px-4 py-3">
                                        <input type="checkbox"
                                               name="article_codes[]"
                                               value="{{ $item['article_code'] }}"
                                               class="item-checkbox rounded bg-gray-700 border-gray-600"
                                               {{ $hasExistingFallback ? 'disabled' : '' }}>
                                        <input type="hidden"
                                               name="descriptions[{{ $item['article_code'] }}]"
                                               value="{{ $item['description'] }}">
                                    </td>
                                    <td class="px-4 py-3 font-mono text-gray-200">
                                        {{ $item['article_code'] }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-300 text-sm">
                                        {{ $item['description'] ?: '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-200">
                                        {{ number_format($item['total_value'], 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-center text-gray-400">
                                        {{ $item['occurrence_count'] }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        @if($hasExistingFallback)
                                            <span class="px-2 py-1 bg-green-600 text-white text-xs rounded">
                                                {{ $existingRate }}% assigned
                                            </span>
                                        @else
                                            <span class="px-2 py-1 bg-red-600 text-white text-xs rounded">
                                                Unassigned
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Summary --}}
                <div class="mt-4 bg-gray-800 rounded-lg p-4">
                    <div class="flex justify-between text-sm text-gray-400">
                        <span>{{ $unresolvedItems->count() }} unique article codes</span>
                        <span>{{ number_format($unresolvedItems->sum('total_value'), 2) }} total unresolved value</span>
                        <span>{{ $unresolvedItems->sum('occurrence_count') }} total occurrences</span>
                    </div>
                </div>
            </form>
        @endif
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.item-checkbox:not(:disabled)');
            const selectedCount = document.getElementById('selectedCount');
            const assignBtn = document.getElementById('assignBtn');

            function updateSelectedCount() {
                const checked = document.querySelectorAll('.item-checkbox:checked').length;
                selectedCount.textContent = checked + ' selected';
                assignBtn.disabled = checked === 0;
            }

            selectAll?.addEventListener('change', function() {
                checkboxes.forEach(cb => cb.checked = this.checked);
                updateSelectedCount();
            });

            checkboxes.forEach(cb => {
                cb.addEventListener('change', updateSelectedCount);
            });

            // Form validation
            document.getElementById('bulkAssignForm')?.addEventListener('submit', function(e) {
                const checked = document.querySelectorAll('.item-checkbox:checked').length;
                const vatRate = document.querySelector('select[name="vat_rate"]').value;

                if (checked === 0) {
                    e.preventDefault();
                    alert('Please select at least one article code.');
                    return;
                }

                if (!vatRate) {
                    e.preventDefault();
                    alert('Please select a VAT rate.');
                    return;
                }
            });
        });
    </script>
    @endpush
</x-admin-layout>
