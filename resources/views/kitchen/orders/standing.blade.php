<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Standing Weekly Order') }}
            </h2>
            <div class="flex items-center space-x-4">
                <a href="{{ route('kitchen.orders.create') }}"
                   class="inline-flex items-center px-4 py-2 bg-orange-600 text-white font-semibold rounded-md hover:bg-orange-700">
                    Create Order
                </a>
                <a href="{{ route('kitchen.orders.index') }}" class="text-indigo-600 hover:text-indigo-900">
                    Order History
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

            <div class="mb-6 bg-indigo-50 border border-indigo-200 rounded-lg p-4">
                <h3 class="font-medium text-indigo-800 mb-1">How the standing order works</h3>
                <p class="text-sm text-indigo-700">
                    These quantities (in <strong>cases</strong>) are pre-filled every time you create an order.
                    Nothing is sent or logged automatically &mdash; you still review and confirm each order.
                </p>
            </div>

            @if($groups->isEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6 text-center text-gray-500">
                        <p>No kitchen products yet.</p>
                        <a href="{{ route('kitchen.products.index') }}" class="mt-4 inline-flex items-center px-4 py-2 bg-orange-600 text-white rounded-md hover:bg-orange-700">
                            Go to Kitchen Products
                        </a>
                    </div>
                </div>
            @else
                <form method="POST" action="{{ route('kitchen.standing-order.update') }}" id="standing-order-form">
                    @csrf
                    @method('PUT')

                    @foreach($groups as $supplierName => $rows)
                        @php $orderableGroup = $rows->first()['orderable'] ?? false; @endphp
                        <div class="mb-6 bg-white shadow-sm sm:rounded-lg overflow-hidden {{ $orderableGroup ? '' : 'opacity-75' }}">
                            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                                <h3 class="text-lg font-semibold text-gray-800">
                                    {{ $supplierName }}
                                    <span class="text-sm font-normal text-gray-500">({{ $rows->count() }} {{ \Illuminate\Support\Str::plural('product', $rows->count()) }})</span>
                                    @unless($orderableGroup)
                                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-gray-200 text-gray-700">cannot be ordered</span>
                                    @endunless
                                </h3>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-20">Image</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier code</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Case size</th>
                                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-44">Weekly qty (cases)</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach($rows as $row)
                                            @include('kitchen.orders.partials.product-row', [
                                                'row' => $row,
                                                'standing' => $standing,
                                                'lastOrders' => [],
                                                'showHistory' => false,
                                                'imageSize' => 'lg',
                                            ])
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach

                    {{-- Sticky footer --}}
                    <div class="sticky bottom-0 mt-4 bg-white border-t border-gray-200 shadow-lg sm:rounded-lg">
                        <div class="px-6 py-4 flex flex-wrap items-center justify-between gap-4">
                            <div class="text-gray-700">
                                <span class="text-2xl font-bold text-indigo-700" id="total-lines">0</span> products with a standing quantity
                                <span class="text-gray-400">&middot; <span id="total-cases">0</span> cases/week</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <button type="button" id="clear-all-qty"
                                        class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200">
                                    Clear all
                                </button>
                                <button type="submit"
                                        class="px-6 py-2 bg-indigo-600 text-white font-semibold rounded-md hover:bg-indigo-700">
                                    Save Standing Order
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
