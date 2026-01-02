<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ $snapshot->name }}
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    Valuation Date: {{ $snapshot->valuation_date->format('d M Y') }}
                    @if($snapshot->status === 'draft')
                        <span class="ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                            Draft
                        </span>
                    @else
                        <span class="ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                            Finalized
                        </span>
                    @endif
                </p>
            </div>
            <div class="flex space-x-3">
                <a href="{{ route('management.stock-valuation.index') }}"
                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    <svg class="-ml-1 mr-2 h-5 w-5 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back
                </a>
                <a href="{{ route('management.stock-valuation.export', $snapshot) }}"
                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    <svg class="-ml-1 mr-2 h-5 w-5 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Export CSV
                </a>
                @if($snapshot->canBeModified())
                    <form action="{{ route('management.stock-valuation.refresh', $snapshot) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
                                onclick="return confirm('This will replace all data with current stock values. Continue?')">
                            <svg class="-ml-1 mr-2 h-5 w-5 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            Refresh
                        </button>
                    </form>
                    <form action="{{ route('management.stock-valuation.finalize', $snapshot) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700"
                                onclick="return confirm('Are you sure you want to finalize this snapshot? This action cannot be undone.')">
                            <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Finalize
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif

            @if(session('error'))
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="text-sm font-medium text-gray-500">Calculated Total</div>
                        <div class="mt-2 text-2xl font-bold text-gray-900">
                            {{ number_format($snapshot->calculated_total, 2) }}
                        </div>
                    </div>
                </div>
                @if($snapshot->adjusted_total)
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border-l-4 border-blue-500">
                        <div class="p-6">
                            <div class="text-sm font-medium text-gray-500">Adjusted Total</div>
                            <div class="mt-2 text-2xl font-bold text-blue-600">
                                {{ number_format($snapshot->adjusted_total, 2) }}
                            </div>
                        </div>
                    </div>
                @endif
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg {{ $snapshot->adjusted_total ? 'border-l-4 border-green-500' : '' }}">
                    <div class="p-6">
                        <div class="text-sm font-medium text-gray-500">Final Total</div>
                        <div class="mt-2 text-2xl font-bold {{ $snapshot->adjusted_total ? 'text-green-600' : 'text-gray-900' }}">
                            {{ number_format($snapshot->final_total, 2) }}
                        </div>
                    </div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="text-sm font-medium text-gray-500">Categories</div>
                        <div class="mt-2 text-2xl font-bold text-gray-900">
                            {{ $snapshot->categories->count() }}
                        </div>
                        @if($snapshot->has_overrides)
                            <div class="text-xs text-blue-600 mt-1">
                                {{ $snapshot->categories->whereNotNull('override_value')->count() }} with overrides
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Notes -->
            @if($snapshot->notes)
                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-yellow-700">
                                <strong>Notes:</strong> {{ $snapshot->notes }}
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Categories Table -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Valuation by Category</h3>

                    @if($snapshot->categories->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Category
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Products
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Calculated
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Override
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Final Value
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($snapshot->categories as $category)
                                        <tr class="hover:bg-gray-50 {{ $category->has_override ? 'bg-blue-50' : '' }}">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">
                                                    {{ $category->category_name }}
                                                </div>
                                                @if($category->override_reason)
                                                    <div class="text-xs text-blue-600">
                                                        Override: {{ $category->override_reason }}
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                                <div class="text-sm text-gray-900">
                                                    {{ number_format($category->product_count) }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                                <div class="text-sm {{ $category->has_override ? 'text-gray-400 line-through' : 'text-gray-900' }}">
                                                    {{ number_format($category->calculated_value, 2) }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                                @if($category->has_override)
                                                    <div class="text-sm font-medium text-blue-600">
                                                        {{ number_format($category->override_value, 2) }}
                                                    </div>
                                                @else
                                                    <div class="text-sm text-gray-400">-</div>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                                <div class="text-sm font-medium {{ $category->has_override ? 'text-blue-600' : 'text-gray-900' }}">
                                                    {{ number_format($category->final_value, 2) }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <a href="{{ route('management.stock-valuation.category', [$snapshot, $category]) }}"
                                                   class="text-indigo-600 hover:text-indigo-900 mr-3">
                                                    View
                                                </a>
                                                @if($snapshot->canBeModified())
                                                    <button type="button"
                                                            onclick="openOverrideModal({{ $category->id }}, '{{ $category->category_name }}', {{ $category->calculated_value }}, {{ $category->override_value ?? 'null' }}, '{{ $category->override_reason ?? '' }}')"
                                                            class="text-blue-600 hover:text-blue-900">
                                                        Override
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="bg-gray-50">
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">
                                            Total
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-bold text-gray-900">
                                            {{ number_format($snapshot->categories->sum('product_count')) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-bold text-gray-900">
                                            {{ number_format($snapshot->calculated_total, 2) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-bold text-blue-600">
                                            @if($snapshot->adjusted_total)
                                                {{ number_format($snapshot->adjusted_total, 2) }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-bold {{ $snapshot->adjusted_total ? 'text-blue-600' : 'text-gray-900' }}">
                                            {{ number_format($snapshot->final_total, 2) }}
                                        </td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-8 text-gray-500">
                            No categories found in this snapshot.
                        </div>
                    @endif
                </div>
            </div>

            <!-- Metadata -->
            <div class="mt-6 bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Snapshot Information</h3>
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Created By</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $snapshot->creator?->name ?? 'System' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Created At</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $snapshot->created_at->format('d M Y H:i') }}</dd>
                        </div>
                        @if($snapshot->status === 'finalized')
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Finalized By</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $snapshot->finalizer?->name ?? 'System' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Finalized At</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $snapshot->finalized_at?->format('d M Y H:i') }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <!-- Override Modal -->
    @if($snapshot->canBeModified())
        <div id="overrideModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
            <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                <div class="mt-3">
                    <h3 class="text-lg font-medium text-gray-900 mb-4" id="modalTitle">Set Override</h3>
                    <form action="{{ route('management.stock-valuation.override', $snapshot) }}" method="POST">
                        @csrf
                        <input type="hidden" name="category_id" id="modalCategoryId">

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Calculated Value</label>
                            <div class="text-lg font-semibold text-gray-900" id="modalCalculatedValue">-</div>
                        </div>

                        <div class="mb-4">
                            <label for="override_value" class="block text-sm font-medium text-gray-700 mb-1">
                                Override Value
                            </label>
                            <input type="number"
                                   step="0.01"
                                   name="override_value"
                                   id="modalOverrideValue"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                   placeholder="Leave empty to clear override">
                        </div>

                        <div class="mb-4">
                            <label for="override_reason" class="block text-sm font-medium text-gray-700 mb-1">
                                Reason (optional)
                            </label>
                            <select name="override_reason"
                                    id="modalOverrideReason"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="">Select a reason...</option>
                                <option value="Stock count adjustment">Stock count adjustment</option>
                                <option value="Damaged/expired stock">Damaged/expired stock</option>
                                <option value="Price correction">Price correction</option>
                                <option value="Manual valuation">Manual valuation</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div class="flex justify-end space-x-3">
                            <button type="button"
                                    onclick="closeOverrideModal()"
                                    class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="submit"
                                    class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                                Save Override
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            function openOverrideModal(categoryId, categoryName, calculatedValue, overrideValue, overrideReason) {
                document.getElementById('modalTitle').textContent = 'Override: ' + categoryName;
                document.getElementById('modalCategoryId').value = categoryId;
                document.getElementById('modalCalculatedValue').textContent = calculatedValue.toFixed(2);
                document.getElementById('modalOverrideValue').value = overrideValue !== null ? overrideValue : '';
                document.getElementById('modalOverrideReason').value = overrideReason || '';
                document.getElementById('overrideModal').classList.remove('hidden');
            }

            function closeOverrideModal() {
                document.getElementById('overrideModal').classList.add('hidden');
            }

            // Close modal when clicking outside
            document.getElementById('overrideModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeOverrideModal();
                }
            });
        </script>
    @endif
</x-admin-layout>
