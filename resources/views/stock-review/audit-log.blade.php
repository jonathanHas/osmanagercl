<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-lg text-gray-800 leading-tight py-1">
                Stock Zero Audit Log
            </h2>
            <a href="{{ route('stock-review.index') }}" class="text-sm text-blue-600 hover:text-blue-800">
                &larr; Back to Stock Review
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto px-2 sm:px-4 lg:px-6">
            @if($audits->isEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg p-8 text-center text-gray-500">
                    <p class="text-lg font-medium">No audit records yet</p>
                    <p class="text-sm mt-1">Audit records will appear here after using the "Set to Zero" action.</p>
                </div>
            @else
                <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Products Zeroed</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Value Zeroed</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Details</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200" x-data="{ expanded: null }">
                            @foreach($audits as $audit)
                                <tr class="hover:bg-gray-50 cursor-pointer" @click="expanded = expanded === {{ $audit->id }} ? null : {{ $audit->id }}">
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $audit->created_at->format('d M Y H:i') }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700">{{ $audit->category_name }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $audit->reference_date->format('d M Y') }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $audit->user?->name ?? 'Unknown' }}</td>
                                    <td class="px-4 py-3 text-sm text-right font-medium text-gray-900">{{ $audit->products_zeroed }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-red-600 font-medium">&euro;{{ number_format($audit->total_stock_value_zeroed, 2) }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <svg class="w-4 h-4 mx-auto text-gray-400 transition-transform" :class="expanded === {{ $audit->id }} ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </td>
                                </tr>
                                {{-- Expandable detail row --}}
                                <tr x-show="expanded === {{ $audit->id }}" x-transition style="display: none;">
                                    <td colspan="7" class="px-4 py-3 bg-gray-50">
                                        @if($audit->product_details && count($audit->product_details) > 0)
                                            <table class="w-full text-sm">
                                                <thead>
                                                    <tr class="text-xs text-gray-500">
                                                        <th class="text-left py-1 pr-4">Product</th>
                                                        <th class="text-left py-1 pr-4">Barcode</th>
                                                        <th class="text-right py-1 pr-4">Old Stock</th>
                                                        <th class="text-right py-1">Cost Value</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($audit->product_details as $detail)
                                                        <tr class="border-t border-gray-100">
                                                            <td class="py-1 pr-4 text-gray-700">{{ $detail['name'] }}</td>
                                                            <td class="py-1 pr-4 text-gray-500 font-mono">{{ $detail['barcode'] }}</td>
                                                            <td class="py-1 pr-4 text-right text-gray-700">{{ number_format($detail['old_stock'], 1) }}</td>
                                                            <td class="py-1 text-right text-red-600">&euro;{{ number_format($detail['cost_value'], 2) }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        @else
                                            <p class="text-gray-400 text-sm">No product details available.</p>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="px-4 py-3 border-t border-gray-200">
                        {{ $audits->links() }}
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-admin-layout>
