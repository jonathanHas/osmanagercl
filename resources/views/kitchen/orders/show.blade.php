<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Kitchen Order #{{ $order->id }} &mdash; {{ $order->supplier_name }}
            </h2>
            <div class="flex items-center space-x-4">
                <a href="{{ route('kitchen.orders.csv', $order) }}"
                   class="inline-flex items-center px-4 py-2 bg-orange-600 text-white font-semibold rounded-md hover:bg-orange-700">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Download CSV
                </a>
                <a href="{{ route('kitchen.orders.index') }}" class="text-gray-600 hover:text-gray-900">
                    &larr; Back to Kitchen Orders
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    {{ session('success') }}
                </div>
            @endif
            @if (session('error'))
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    {{ session('error') }}
                </div>
            @endif

            {{-- Meta --}}
            <div class="mb-6 bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <div class="text-xs font-medium text-gray-500 uppercase tracking-wide">Ordered</div>
                        <div class="mt-1 text-gray-900">{{ $order->created_at->format('D d M Y') }}</div>
                        <div class="text-sm text-gray-500">{{ $order->created_at->format('H:i') }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-500 uppercase tracking-wide">Ordered by</div>
                        <div class="mt-1 text-gray-900">{{ $order->user->name ?? 'Unknown' }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-500 uppercase tracking-wide">Lines</div>
                        <div class="mt-1 text-2xl font-bold text-gray-900">{{ $order->line_count }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-500 uppercase tracking-wide">Total cases</div>
                        <div class="mt-1 text-2xl font-bold text-indigo-700">{{ $order->total_cases }}</div>
                    </div>
                    @if($order->notes)
                        <div class="col-span-2 md:col-span-4">
                            <div class="text-xs font-medium text-gray-500 uppercase tracking-wide">Notes</div>
                            <div class="mt-1 text-gray-900 whitespace-pre-line">{{ $order->notes }}</div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Lines (same columns as the CSV) --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider w-24">Qty</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier code</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Case size</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($order->items->sortBy(fn ($i) => mb_strtolower($i->product_name)) as $item)
                                <tr>
                                    <td class="px-6 py-3 text-right text-lg font-bold text-gray-900">{{ $item->quantity }}</td>
                                    <td class="px-6 py-3 font-mono text-sm text-slate-700">{{ $item->supplier_code ?? '—' }}</td>
                                    <td class="px-6 py-3 text-gray-900">{{ $item->product_name }}</td>
                                    <td class="px-6 py-3 text-sm text-gray-700">{{ $item->case_units }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-6 flex items-center gap-4">
                <a href="{{ route('kitchen.orders.create', ['supplier' => $order->supplier_id]) }}"
                   class="text-indigo-600 hover:text-indigo-900">
                    Order again from {{ $order->supplier_name }} &rarr;
                </a>
            </div>
        </div>
    </div>
</x-admin-layout>
