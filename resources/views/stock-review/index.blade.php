<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-lg text-gray-800 leading-tight py-1">
                Stock Check Review
            </h2>
            <a href="{{ route('stock-review.audit-log') }}" class="text-sm text-blue-600 hover:text-blue-800">
                View Audit Log
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto px-2 sm:px-4 lg:px-6">
            {{-- Success/Error Messages --}}
            @if(session('success'))
                <div class="mb-4 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Controls --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-4 mb-4">
                <form method="GET" action="{{ route('stock-review.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
                    {{-- Category --}}
                    <div>
                        <label for="category" class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                        <select name="category" id="category" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" onchange="this.form.submit()">
                            <option value="">-- Select Category --</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->ID }}" {{ $selectedCategory === $cat->ID ? 'selected' : '' }}>
                                    {{ $cat->NAME }} ({{ $cat->products_count }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Reference Date --}}
                    <div>
                        <label for="reference_date" class="block text-sm font-medium text-gray-700 mb-1">Reference Date</label>
                        <input type="date" name="reference_date" id="reference_date"
                               value="{{ $referenceDate }}"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                    </div>

                    {{-- Filter --}}
                    <div>
                        <label for="filter" class="block text-sm font-medium text-gray-700 mb-1">Show</label>
                        <select name="filter" id="filter" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                            <option value="all" {{ $filter === 'all' ? 'selected' : '' }}>All Products</option>
                            <option value="stocked" {{ $filter === 'stocked' ? 'selected' : '' }}>Stocked Only</option>
                        </select>
                    </div>

                    {{-- Sort --}}
                    <div>
                        <label for="sort" class="block text-sm font-medium text-gray-700 mb-1">Sort By</label>
                        <select name="sort" id="sort" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                            <option value="name" {{ $sortBy === 'name' ? 'selected' : '' }}>Product Name</option>
                            <option value="checked_desc" {{ $sortBy === 'checked_desc' ? 'selected' : '' }}>Last Checked (Newest)</option>
                            <option value="checked_asc" {{ $sortBy === 'checked_asc' ? 'selected' : '' }}>Last Checked (Oldest)</option>
                        </select>
                    </div>

                    {{-- Submit --}}
                    <div>
                        <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 text-sm font-medium">
                            Review
                        </button>
                    </div>
                </form>
            </div>

            @if($reviewData)
                @php
                    $summary = $reviewData['summary'];
                    $products = $reviewData['products'];
                    $dangerProducts = $products->where('status', 'danger');
                    $warningProducts = $products->where('status', 'warning');
                @endphp

                {{-- Summary Cards --}}
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
                    {{-- Total Products --}}
                    <div class="bg-white shadow-sm rounded-lg p-4">
                        <div class="text-sm text-gray-500">Total Products</div>
                        <div class="text-2xl font-bold text-gray-900">{{ $summary['total_products'] }}</div>
                    </div>

                    {{-- Checked --}}
                    <div class="bg-white shadow-sm rounded-lg p-4">
                        <div class="text-sm text-gray-500">Checked</div>
                        <div class="text-2xl font-bold text-green-600">{{ $summary['checked_count'] }}</div>
                        <div class="mt-1">
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-green-500 h-2 rounded-full" style="width: {{ $summary['progress_percentage'] }}%"></div>
                            </div>
                            <div class="text-xs text-gray-500 mt-1">{{ $summary['progress_percentage'] }}% complete</div>
                        </div>
                    </div>

                    {{-- Unchecked with Stock --}}
                    <div class="bg-white shadow-sm rounded-lg p-4 {{ $summary['unchecked_with_stock'] > 0 ? 'ring-2 ring-red-300' : '' }}">
                        <div class="text-sm text-gray-500">Unchecked with Stock</div>
                        <div class="text-2xl font-bold {{ $summary['unchecked_with_stock'] > 0 ? 'text-red-600' : 'text-gray-400' }}">
                            {{ $summary['unchecked_with_stock'] }}
                        </div>
                        @if($summary['negative_stock_count'] > 0)
                            <div class="text-xs text-yellow-600 mt-1">+ {{ $summary['negative_stock_count'] }} negative</div>
                        @endif
                    </div>

                    {{-- At Risk Value --}}
                    <div class="bg-white shadow-sm rounded-lg p-4">
                        <div class="text-sm text-gray-500">At Risk Value</div>
                        <div class="text-2xl font-bold {{ $summary['at_risk_value'] > 0 ? 'text-red-600' : 'text-gray-400' }}">
                            &euro;{{ number_format($summary['at_risk_value'], 2) }}
                        </div>
                        <div class="text-xs text-gray-500 mt-1">Total: &euro;{{ number_format($summary['total_stock_value'], 2) }}</div>
                    </div>
                </div>

                {{-- Product Table --}}
                <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden" x-data="stockReview()">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">Image</th>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">Barcode</th>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">Supplier</th>
                                    <th class="px-2 py-2 text-right text-xs font-medium text-gray-500 uppercase">Cost</th>
                                    <th class="px-2 py-2 text-right text-xs font-medium text-gray-500 uppercase">Stock</th>
                                    <th class="px-2 py-2 text-right text-xs font-medium text-gray-500 uppercase">Value</th>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">Last Checked</th>
                                    <th class="px-2 py-2 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                                    {{-- Sales columns inserted dynamically --}}
                                    <template x-if="salesLoaded">
                                        <template x-for="month in salesMonths" :key="month.key">
                                            <th class="px-2 py-2 text-right text-xs font-medium text-gray-500 uppercase" x-text="month.label"></th>
                                        </template>
                                    </template>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach($products as $index => $item)
                                    @php
                                        $rowClass = match($item->status) {
                                            'danger' => 'bg-red-50',
                                            'warning' => 'bg-yellow-50',
                                            'ok' => 'bg-green-50',
                                            default => '',
                                        };
                                    @endphp
                                    <tr class="{{ $rowClass }} hover:bg-opacity-70">
                                        <td class="px-2 py-2 text-sm text-gray-500">{{ $index + 1 }}</td>
                                        <td class="px-2 py-2">
                                            @if($item->image_url)
                                                <div class="relative w-8 h-8"
                                                     x-data="{ show: false, pos: { x: 0, y: 0 } }"
                                                     @mouseenter="
                                                        const rect = $el.getBoundingClientRect();
                                                        pos.x = rect.left;
                                                        pos.y = rect.bottom + 8;
                                                        show = true;
                                                     "
                                                     @mouseleave="show = false">
                                                    <img src="{{ $item->image_url }}"
                                                         alt="{{ $item->product->NAME }}"
                                                         class="w-8 h-8 object-cover rounded border border-gray-200"
                                                         loading="lazy"
                                                         onerror="this.style.display='none'">
                                                    <template x-teleport="body">
                                                        <div x-show="show"
                                                             x-transition:enter="transition ease-out duration-150"
                                                             x-transition:enter-start="opacity-0"
                                                             x-transition:enter-end="opacity-100"
                                                             x-transition:leave="transition ease-in duration-100"
                                                             x-transition:leave-start="opacity-100"
                                                             x-transition:leave-end="opacity-0"
                                                             class="fixed z-[99999] pointer-events-none"
                                                             :style="'left: ' + pos.x + 'px; top: ' + pos.y + 'px;'">
                                                            <img src="{{ $item->image_url }}"
                                                                 alt="{{ $item->product->NAME }}"
                                                                 class="w-48 h-auto max-h-60 object-contain rounded-lg border-2 border-white shadow-2xl bg-white">
                                                            <div class="bg-black bg-opacity-75 text-white text-xs p-1.5 rounded-b-lg truncate max-w-48">
                                                                {{ $item->product->NAME }}
                                                            </div>
                                                        </div>
                                                    </template>
                                                </div>
                                            @else
                                                <div class="w-8 h-8 bg-gray-100 rounded border border-gray-200 flex items-center justify-center">
                                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                    </svg>
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-2 py-2 text-sm font-medium text-gray-900">
                                            {{ $item->product->NAME }}
                                            @if($item->is_stocked)
                                                <span class="inline-block w-2 h-2 bg-blue-500 rounded-full ml-1" title="Stocked"></span>
                                            @endif
                                        </td>
                                        <td class="px-2 py-2 text-sm text-gray-500 font-mono">{{ $item->product->CODE }}</td>
                                        <td class="px-2 py-2 text-sm text-gray-500">{{ $item->supplier_name }}</td>
                                        <td class="px-2 py-2 text-sm text-gray-500 text-right">&euro;{{ number_format($item->product->PRICEBUY, 2) }}</td>
                                        <td class="px-2 py-2 text-sm text-right font-medium {{ $item->stock < 0 ? 'text-yellow-700' : ($item->stock > 0 ? 'text-gray-900' : 'text-gray-400') }}">
                                            {{ number_format($item->stock, 1) }}
                                        </td>
                                        <td class="px-2 py-2 text-sm text-right {{ $item->cost_value != 0 ? 'text-gray-700' : 'text-gray-400' }}">
                                            &euro;{{ number_format($item->cost_value, 2) }}
                                        </td>
                                        <td class="px-2 py-2 text-sm text-gray-500">
                                            @if($item->checked_date)
                                                {{ $item->checked_date->format('d M Y') }}
                                            @else
                                                <span class="text-gray-300">Never</span>
                                            @endif
                                        </td>
                                        <td class="px-2 py-2 text-center">
                                            @if($item->status === 'verified')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800" title="Checked after reference date">
                                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                                    OK
                                                </span>
                                            @elseif($item->status === 'danger')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800" title="Has stock but not checked">
                                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                                    Check
                                                </span>
                                            @elseif($item->status === 'warning')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800" title="Negative stock">
                                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/></svg>
                                                    Neg
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500" title="No stock, safe">
                                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                                    Clear
                                                </span>
                                            @endif
                                        </td>
                                        {{-- Sales data columns --}}
                                        <template x-if="salesLoaded">
                                            <template x-for="month in salesMonths" :key="month.key">
                                                <td class="px-2 py-2 text-sm text-right text-gray-500"
                                                    x-text="getSales('{{ $item->product->CODE }}', month.key)"></td>
                                            </template>
                                        </template>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Action Bar --}}
                    <div class="border-t border-gray-200 px-4 py-3 flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            {{-- Show Sales Toggle --}}
                            <button @click="toggleSales()"
                                    class="inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50"
                                    :class="salesLoaded ? 'bg-blue-50 border-blue-300 text-blue-700' : ''">
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                                <span x-text="salesLoading ? 'Loading...' : (salesLoaded ? 'Hide Sales' : 'Show Sales')"></span>
                            </button>

                            <span class="text-sm text-gray-500">
                                {{ $products->count() }} products | &euro;{{ number_format($summary['total_stock_value'], 2) }} total value
                            </span>
                        </div>

                        @if($summary['unchecked_with_stock'] > 0 || $summary['negative_stock_count'] > 0)
                            <button @click="showConfirmModal = true"
                                    class="inline-flex items-center px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 text-sm font-medium">
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                                Set to Zero ({{ $summary['unchecked_with_stock'] + $summary['negative_stock_count'] }} items)
                            </button>
                        @endif
                    </div>

                    {{-- Confirmation Modal --}}
                    <div x-show="showConfirmModal"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"
                         @click.self="showConfirmModal = false"
                         style="display: none;">
                        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 p-6" @click.stop>
                            <div class="flex items-center mb-4">
                                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-red-100 flex items-center justify-center mr-3">
                                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900">Confirm Set to Zero</h3>
                            </div>

                            <p class="text-sm text-gray-600 mb-3">
                                This will set stock to <strong>0</strong> for
                                <strong class="text-red-600">{{ $summary['unchecked_with_stock'] + $summary['negative_stock_count'] }}</strong> products
                                that have not been checked since <strong>{{ \Carbon\Carbon::parse($referenceDate)->format('d M Y') }}</strong>.
                            </p>

                            <p class="text-sm text-gray-600 mb-4">
                                Total stock value to be zeroed: <strong class="text-red-600">&euro;{{ number_format(abs($summary['at_risk_value']), 2) }}</strong>
                            </p>

                            {{-- List affected products --}}
                            @if($dangerProducts->count() > 0 || $warningProducts->count() > 0)
                                <div class="max-h-40 overflow-y-auto border border-gray-200 rounded-md mb-4">
                                    <ul class="divide-y divide-gray-100">
                                        @foreach($dangerProducts->merge($warningProducts)->take(15) as $item)
                                            <li class="px-3 py-1.5 text-xs flex justify-between">
                                                <span class="text-gray-700 truncate mr-2">{{ $item->product->NAME }}</span>
                                                <span class="font-mono {{ $item->stock < 0 ? 'text-yellow-600' : 'text-red-600' }} whitespace-nowrap">{{ number_format($item->stock, 1) }}</span>
                                            </li>
                                        @endforeach
                                        @if($dangerProducts->count() + $warningProducts->count() > 15)
                                            <li class="px-3 py-1.5 text-xs text-gray-400 text-center">
                                                ... and {{ $dangerProducts->count() + $warningProducts->count() - 15 }} more
                                            </li>
                                        @endif
                                    </ul>
                                </div>
                            @endif

                            <div class="flex justify-end gap-3">
                                <button @click="showConfirmModal = false"
                                        class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">
                                    Cancel
                                </button>
                                <form method="POST" action="{{ route('stock-review.set-to-zero') }}">
                                    @csrf
                                    <input type="hidden" name="category" value="{{ $selectedCategory }}">
                                    <input type="hidden" name="reference_date" value="{{ $referenceDate }}">
                                    <input type="hidden" name="filter" value="{{ $filter }}">
                                    <button type="submit"
                                            class="px-4 py-2 text-sm text-white bg-red-600 rounded-md hover:bg-red-700 font-medium">
                                        Yes, Set to Zero
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            @elseif($selectedCategory === null)
                <div class="bg-white shadow-sm sm:rounded-lg p-8 text-center text-gray-500">
                    <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                    <p class="text-lg font-medium">Select a category to review</p>
                    <p class="text-sm mt-1">Choose a category from the dropdown above to start reviewing stock checks.</p>
                </div>
            @endif
        </div>
    </div>

    @if($reviewData)
    <script>
        function stockReview() {
            return {
                showConfirmModal: false,
                salesLoaded: false,
                salesLoading: false,
                salesData: {},
                salesMonths: [],

                toggleSales() {
                    if (this.salesLoaded) {
                        this.salesLoaded = false;
                        return;
                    }
                    if (this.salesLoading) return;

                    this.salesLoading = true;
                    fetch(`{{ route('stock-review.sales-data') }}?category={{ $selectedCategory }}`)
                        .then(r => r.json())
                        .then(data => {
                            this.salesData = data.sales;
                            this.salesMonths = data.months;
                            this.salesLoaded = true;
                            this.salesLoading = false;
                        })
                        .catch(() => {
                            this.salesLoading = false;
                            alert('Failed to load sales data');
                        });
                },

                getSales(barcode, monthKey) {
                    const val = this.salesData[barcode]?.[monthKey];
                    return val ? val.toFixed(0) : '-';
                }
            };
        }
    </script>
    @endif
</x-admin-layout>
