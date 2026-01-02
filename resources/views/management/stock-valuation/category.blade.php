<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ $category->category_name }}
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    {{ $snapshot->name }} | {{ $category->product_count }} products
                    @if($category->has_override)
                        <span class="ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                            Override Applied
                        </span>
                    @endif
                </p>
            </div>
            <a href="{{ route('management.stock-valuation.show', $snapshot) }}"
               class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <svg class="-ml-1 mr-2 h-5 w-5 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Back to Snapshot
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Value Summary -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="text-sm font-medium text-gray-500">Calculated Value</div>
                        <div class="mt-2 text-2xl font-bold {{ $category->has_override ? 'text-gray-400 line-through' : 'text-gray-900' }}">
                            {{ number_format($category->calculated_value, 2) }}
                        </div>
                    </div>
                </div>
                @if($category->has_override)
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border-l-4 border-blue-500">
                        <div class="p-6">
                            <div class="text-sm font-medium text-gray-500">Override Value</div>
                            <div class="mt-2 text-2xl font-bold text-blue-600">
                                {{ number_format($category->override_value, 2) }}
                            </div>
                            @if($category->override_reason)
                                <div class="text-xs text-blue-600 mt-1">
                                    Reason: {{ $category->override_reason }}
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg {{ $category->has_override ? 'border-l-4 border-green-500' : '' }}">
                    <div class="p-6">
                        <div class="text-sm font-medium text-gray-500">Final Value</div>
                        <div class="mt-2 text-2xl font-bold {{ $category->has_override ? 'text-green-600' : 'text-gray-900' }}">
                            {{ number_format($category->final_value, 2) }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Products Table -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Products</h3>

                    @if($category->items->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Code
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Product Name
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Unit Cost
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Stock Units
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Line Value
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($category->items as $item)
                                        <tr class="hover:bg-gray-50 {{ $item->stock_units <= 0 ? 'bg-red-50' : '' }}">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-mono text-gray-900">
                                                    {{ $item->product_code }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">
                                                    {{ $item->product_name }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                                <div class="text-sm text-gray-900">
                                                    {{ number_format($item->unit_cost, 4) }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                                <div class="text-sm {{ $item->stock_units <= 0 ? 'text-red-600 font-medium' : 'text-gray-900' }}">
                                                    {{ number_format($item->stock_units, 2) }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                                <div class="text-sm font-medium text-gray-900">
                                                    {{ number_format($item->line_value, 2) }}
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="bg-gray-50">
                                    <tr>
                                        <td colspan="4" class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900 text-right">
                                            Category Total:
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-bold text-gray-900">
                                            {{ number_format($category->calculated_value, 2) }}
                                        </td>
                                    </tr>
                                    @if($category->has_override)
                                        <tr>
                                            <td colspan="4" class="px-6 py-4 whitespace-nowrap text-sm font-bold text-blue-600 text-right">
                                                Override Value:
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-bold text-blue-600">
                                                {{ number_format($category->override_value, 2) }}
                                            </td>
                                        </tr>
                                    @endif
                                </tfoot>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-8 text-gray-500">
                            No products found in this category.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
