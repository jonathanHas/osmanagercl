<x-admin-layout>
    <x-slot name="header">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-2">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Order Review – {{ $order->supplier->Supplier ?? 'Supplier' }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    Delivery: {{ optional($order->order_date)->format('l, F j, Y') ?? 'TBC' }}
                    • Created by {{ $order->user->name ?? 'System' }}
                    • Status: <span class="font-semibold">{{ ucfirst($order->status) }}</span>
                </p>
            </div>
        </div>
    </x-slot>

    @php
        $coverageDays = $order->coverage_days ?? null;
        $coverageWeeks = $coverageDays ? $coverageDays / 7 : null;
        $coverageEnds = optional($order->coverage_ends_on)?->format('l, F j, Y');
        $salesHistoryWeeks = $order->sales_history_weeks ?? 8;
        $reviewCount = $order->items->where('review_priority', 'review')->count();
        $standardCount = $order->items->where('review_priority', 'standard')->count();
        $safeCount = $order->items->where('review_priority', 'safe')->count();
        $displayItems = $order->items;
        $primaryActions = [];
        if ($order->isEditable()) {
            $primaryActions[] = '<form method="POST" action="'.route('orders.auto-approve-safe', $order).'" class="inline-block">'
                .csrf_field()
                .'<button type="submit" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium">Auto-Approve Safe</button>'
                .'</form>';
            $primaryActions[] = '<form method="POST" action="'.route('orders.complete', $order).'" class="inline-block">'
                .csrf_field()
                .'<button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium">Complete Order</button>'
                .'</form>';
        }
        $primaryActions[] = '<a href="'.route('orders.export', $order).'" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-medium">Export CSV</a>';
    @endphp

    <div class="py-6">
        <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="text-sm font-medium text-gray-500">Total Items</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-900">{{ $statistics['total_items'] }}</div>
                </div>
                <div class="bg-red-50 shadow-sm rounded-lg p-4">
                    <div class="text-sm font-medium text-red-600">Requires Review</div>
                    <div class="mt-1 text-2xl font-semibold text-red-700">{{ $reviewCount }}</div>
                </div>
                <div class="bg-yellow-50 shadow-sm rounded-lg p-4">
                    <div class="text-sm font-medium text-yellow-600">Standard</div>
                    <div class="mt-1 text-2xl font-semibold text-yellow-700">{{ $standardCount }}</div>
                </div>
                <div class="bg-green-50 shadow-sm rounded-lg p-4">
                    <div class="text-sm font-medium text-green-600">Safe Items</div>
                    <div class="mt-1 text-2xl font-semibold text-green-700">{{ $safeCount }}</div>
                </div>
                <div class="bg-blue-50 shadow-sm rounded-lg p-4 md:col-span-2">
                    <div class="text-sm font-medium text-blue-600">Coverage Window</div>
                    <div class="mt-1 text-base text-blue-900">
                        {{ $coverageEnds ? "Through {$coverageEnds}" : 'Not specified' }}
                        @if($coverageWeeks)
                            • {{ number_format($coverageWeeks, 1) }} weeks target
                        @endif
                    </div>
                    <div class="text-xs text-blue-700 mt-1">
                        Sales history window: {{ $salesHistoryWeeks }} weeks
                    </div>
                </div>
                <div class="bg-gray-50 shadow-sm rounded-lg p-4 md:col-span-2">
                    <div class="text-sm font-medium text-gray-600">Order Value</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-900">€{{ number_format($order->total_value, 2) }}</div>
                    <div class="text-xs text-gray-500 mt-1">
                        Average per item: €{{ number_format($statistics['avg_item_value'], 2) }}
                    </div>
                </div>
            </div>

            @include('orders.partials.review-table', [
                'orderSession' => $order,
                'displayItems' => $displayItems,
                'backLink' => route('orders.index'),
                'primaryActions' => $primaryActions,
            ])
        </div>
    </div>
</x-admin-layout>
