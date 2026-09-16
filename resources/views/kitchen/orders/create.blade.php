<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Create Kitchen Order') }}
            </h2>
            <div class="flex items-center space-x-4">
                <a href="{{ route('kitchen.orders.index') }}" class="text-indigo-600 hover:text-indigo-900">
                    Order History
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
            @if ($errors->any())
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    <ul class="list-disc list-inside text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Supplier picker --}}
            <div class="mb-6 bg-white shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="GET" action="{{ route('kitchen.orders.create') }}" class="flex flex-wrap items-end gap-4">
                        <div>
                            <label for="supplier" class="block text-sm font-medium text-gray-700 mb-1">Supplier</label>
                            <select id="supplier" name="supplier" onchange="this.form.submit()"
                                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 min-w-[16rem]">
                                @forelse($suppliers as $option)
                                    <option value="{{ $option['id'] }}" @selected($selectedSupplier && $selectedSupplier['id'] === $option['id'])>
                                        {{ $option['name'] }} ({{ $option['count'] }})
                                    </option>
                                @empty
                                    <option value="">No suppliers with kitchen products</option>
                                @endforelse
                            </select>
                        </div>
                        <noscript>
                            <button type="submit" class="px-4 py-2 bg-gray-800 text-white rounded-md hover:bg-gray-700">Change supplier</button>
                        </noscript>
                        <p class="text-sm text-gray-500 self-center">
                            Quantities are <strong>cases</strong>. Standing weekly quantities are pre-filled; nothing is sent until you confirm.
                        </p>
                    </form>
                </div>
            </div>

            @if(! $selectedSupplier)
                <div class="bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6 text-center text-gray-500">
                        <p>No kitchen products with a supplier link yet.</p>
                        <a href="{{ route('kitchen.products.index') }}" class="mt-4 inline-flex items-center px-4 py-2 bg-orange-600 text-white rounded-md hover:bg-orange-700">
                            Go to Kitchen Products
                        </a>
                    </div>
                </div>
            @else
                <form method="POST" action="{{ route('kitchen.orders.store') }}" id="kitchen-order-form">
                    @csrf
                    <input type="hidden" name="supplier_id" value="{{ $selectedSupplier['id'] }}">

                    <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-gray-800">
                                {{ $selectedSupplier['name'] }}
                                <span class="text-sm font-normal text-gray-500">({{ $rows->count() }} kitchen products)</span>
                            </h3>
                        </div>

                        @if($rows->isEmpty())
                            <div class="p-6 text-center text-gray-500">No kitchen products found for this supplier.</div>
                        @else
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-28">Image</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier code</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Case size</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last 3 orders</th>
                                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-44">Qty (cases)</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach($rows as $row)
                                            @include('kitchen.orders.partials.product-row', [
                                                'row' => $row,
                                                'standing' => $standing,
                                                'lastOrders' => $lastOrders,
                                                'showHistory' => true,
                                                'imageSize' => 'xl',
                                            ])
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        <div class="px-6 py-4 border-t border-gray-200">
                            <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes (optional)</label>
                            <textarea id="notes" name="notes" rows="2" maxlength="2000"
                                      class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                      placeholder="Anything to remember about this order">{{ old('notes') }}</textarea>
                        </div>
                    </div>

                    {{-- Sticky footer --}}
                    <div class="sticky bottom-0 mt-4 bg-white border-t border-gray-200 shadow-lg sm:rounded-lg">
                        <div class="px-6 py-4 flex flex-wrap items-center justify-between gap-4">
                            <div class="text-gray-700">
                                <span class="text-2xl font-bold text-indigo-700" id="total-lines">0</span> products
                                &middot;
                                <span class="text-2xl font-bold text-indigo-700" id="total-cases">0</span> cases
                            </div>
                            <div class="flex items-center gap-3">
                                <button type="button" id="clear-all-qty"
                                        class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200">
                                    Clear all
                                </button>
                                <button type="submit" id="confirm-order" disabled
                                        class="px-6 py-2 bg-orange-600 text-white font-semibold rounded-md hover:bg-orange-700 disabled:opacity-50 disabled:cursor-not-allowed">
                                    Confirm Order
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            @endif
        </div>
    </div>

    @include('kitchen.orders.partials.qty-scripts')
</x-admin-layout>
