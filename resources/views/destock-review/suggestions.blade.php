<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-lg text-gray-800 leading-tight py-1">
            Destock Review - Restock Suggestions
        </h2>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto px-2 sm:px-4 lg:px-6">
            <!-- Tab Navigation -->
            <div class="border-b border-gray-200 mb-4">
                <nav class="-mb-px flex space-x-8">
                    <a href="{{ route('destock-review.index') }}"
                       class="border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 py-3 px-1 text-sm font-medium">
                        Audit Log
                    </a>
                    <a href="{{ route('destock-review.suggestions') }}"
                       class="border-b-2 border-indigo-500 text-indigo-600 py-3 px-1 text-sm font-medium">
                        Restock Suggestions
                    </a>
                </nav>
            </div>

            <!-- Filters -->
            <div class="bg-white shadow-sm sm:rounded-lg p-4 mb-4">
                <form method="GET" action="{{ route('destock-review.suggestions') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
                    <div>
                        <label for="days" class="block text-xs font-medium text-gray-500 uppercase">Sales Period</label>
                        <select name="days" id="days"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach([7 => 'Last 7 days', 14 => 'Last 14 days', 30 => 'Last 30 days', 60 => 'Last 60 days', 90 => 'Last 90 days', 180 => 'Last 6 months', 365 => 'Last year'] as $value => $label)
                                <option value="{{ $value }}" {{ $days == $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="min_units" class="block text-xs font-medium text-gray-500 uppercase">Min Units Sold</label>
                        <input type="number" name="min_units" id="min_units" value="{{ $minUnits }}" min="1" step="1"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div class="flex gap-2">
                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            Filter
                        </button>
                        <a href="{{ route('destock-review.suggestions') }}"
                           class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-300">
                            Reset
                        </a>
                    </div>
                </form>
            </div>

            <!-- Info banner -->
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4">
                <p class="text-sm text-amber-800">
                    <span class="font-medium">These products are not currently stocked but have recorded sales in the last {{ $days }} days.</span>
                    Consider restocking products with significant sales activity.
                </p>
            </div>

            <!-- Results -->
            @if($suggestions->isEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg p-8 text-center text-gray-500">
                    <p class="text-lg font-medium">No suggestions found</p>
                    <p class="text-sm mt-1">No destocked products with sales activity in the selected period.</p>
                </div>
            @else
                <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Barcode</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Units Sold</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Revenue</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Days with Sales</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Sale</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach($suggestions as $product)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm text-gray-700 font-mono">{{ $product->product_code }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $product->product_name }}</td>
                                    <td class="px-4 py-3 text-sm text-right font-medium text-gray-900">{{ number_format($product->total_units_sold, 1) }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-700">&euro;{{ number_format($product->total_revenue, 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-500">{{ $product->days_with_sales }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ \Carbon\Carbon::parse($product->last_sale)->format('d M Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $suggestions->withQueryString()->links() }}
                </div>
            @endif
        </div>
    </div>
</x-admin-layout>
