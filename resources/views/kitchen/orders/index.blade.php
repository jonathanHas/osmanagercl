<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Kitchen Orders') }}
            </h2>
            <div class="flex items-center space-x-4">
                <a href="{{ route('kitchen.orders.create') }}"
                   class="inline-flex items-center px-4 py-2 bg-orange-600 text-white font-semibold rounded-md hover:bg-orange-700">
                    Create Order
                </a>
                <a href="{{ route('kitchen.standing-order.edit') }}" class="text-indigo-600 hover:text-indigo-900">
                    Standing Order
                </a>
                <a href="{{ route('kitchen.products.index') }}" class="text-gray-600 hover:text-gray-900">
                    &larr; Back to Kitchen Products
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

            {{-- Filter --}}
            <div class="mb-6 bg-white shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="GET" action="{{ route('kitchen.orders.index') }}" class="flex flex-wrap items-end gap-4">
                        <div>
                            <label for="supplier" class="block text-sm font-medium text-gray-700 mb-1">Supplier</label>
                            <select id="supplier" name="supplier" onchange="this.form.submit()"
                                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 min-w-[16rem]">
                                <option value="">All suppliers</option>
                                @foreach($suppliers as $option)
                                    <option value="{{ $option['id'] }}" @selected($selectedSupplierId === $option['id'])>
                                        {{ $option['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <noscript>
                            <button type="submit" class="px-4 py-2 bg-gray-800 text-white rounded-md hover:bg-gray-700">Filter</button>
                        </noscript>
                        @if($selectedSupplierId)
                            <a href="{{ route('kitchen.orders.index') }}" class="text-sm text-gray-600 hover:text-gray-900 self-center">Clear</a>
                        @endif
                    </form>
                </div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                @if($orders->isEmpty())
                    <div class="p-12 text-center">
                        <h3 class="text-sm font-medium text-gray-900">No kitchen orders yet</h3>
                        <p class="mt-1 text-sm text-gray-500">Confirmed orders appear here with a CSV re-download.</p>
                        <div class="mt-6">
                            <a href="{{ route('kitchen.orders.create') }}" class="inline-flex items-center px-4 py-2 bg-orange-600 text-white rounded-md hover:bg-orange-700">
                                Create Order
                            </a>
                        </div>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Lines</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Cases</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">By</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($orders as $order)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-3 text-sm text-gray-500">{{ $order->id }}</td>
                                        <td class="px-6 py-3 text-sm text-gray-900 whitespace-nowrap">
                                            {{ $order->created_at->format('D d M Y') }}
                                            <span class="text-gray-400">{{ $order->created_at->format('H:i') }}</span>
                                        </td>
                                        <td class="px-6 py-3 text-sm font-medium text-gray-900">{{ $order->supplier_name }}</td>
                                        <td class="px-6 py-3 text-sm text-right text-gray-900">{{ $order->line_count }}</td>
                                        <td class="px-6 py-3 text-sm text-right font-semibold text-indigo-700">{{ $order->total_cases }}</td>
                                        <td class="px-6 py-3 text-sm text-gray-700">{{ $order->user->name ?? '—' }}</td>
                                        <td class="px-6 py-3 text-sm text-gray-500" title="{{ $order->notes }}">{{ \Illuminate\Support\Str::limit($order->notes, 60) }}</td>
                                        <td class="px-6 py-3 text-sm text-right whitespace-nowrap">
                                            <a href="{{ route('kitchen.orders.show', $order) }}" class="text-indigo-600 hover:text-indigo-900 mr-3">View</a>
                                            <a href="{{ route('kitchen.orders.csv', $order) }}" class="text-orange-600 hover:text-orange-800">CSV</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="px-6 py-4 border-t border-gray-200">
                        {{ $orders->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-admin-layout>
