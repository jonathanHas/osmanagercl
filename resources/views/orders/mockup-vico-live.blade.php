<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Order Review – {{ $orderSession->supplier->Supplier ?? 'Supplier' }} (Real Data)
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-8">
            @php
                $reviewCount = $orderSession->items->where('review_priority', 'review')->count();
                $standardCount = $orderSession->items->where('review_priority', 'standard')->count();
                $safeCount = $orderSession->items->where('review_priority', 'safe')->count();
                $displayItems = $orderSession->items;
                $limitNotice = false;
            @endphp

            <!-- Order Header -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 rounded-lg shadow-lg p-6 mb-6 text-white">
                <div class="flex justify-between items-center">
                    <div>
                        <h3 class="text-2xl font-bold">{{ $orderSession->supplier->Supplier ?? 'Order Mockup' }}</h3>
                        <p class="text-blue-100 mt-1">
                            Delivery: {{ optional($orderSession->order_date)->format('l, M d, Y') ?? 'TBC' }}
                            • {{ $orderSession->items->count() }} items
                            • €{{ number_format($orderSession->total_value, 2) }}
                        </p>
                    </div>
                    <div class="flex space-x-6">
                        <div class="text-center">
                            <p class="text-4xl font-bold">{{ $reviewCount }}</p>
                            <p class="text-blue-100 text-sm">Review</p>
                        </div>
                        <div class="text-center">
                            <p class="text-4xl font-bold">{{ $standardCount }}</p>
                            <p class="text-blue-100 text-sm">Standard</p>
                        </div>
                        <div class="text-center">
                            <p class="text-4xl font-bold">{{ $safeCount }}</p>
                            <p class="text-blue-100 text-sm">Safe</p>
                        </div>
                    </div>
                </div>
            </div>

            @include('orders.partials.review-table', [
                'orderSession' => $orderSession,
                'displayItems' => $displayItems,
                'limitNotice' => $limitNotice,
                'backLink' => route('orders.mockups'),
                'primaryActions' => [
                    '<button class="px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700" type="button">Export CSV</button>',
                ],
            ])
        </div>
    </div>
</x-admin-layout>
