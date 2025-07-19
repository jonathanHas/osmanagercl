<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Product Details') }}
            </h2>
            <a href="{{ route('products.index') }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-500 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-25 transition ease-in-out duration-150">
                Back to Products
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif
            
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Basic Information -->
                        <div>
                            <h3 class="text-lg font-semibold mb-4">Basic Information</h3>
                            <dl class="space-y-3">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Product ID</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $product->ID }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Name</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $product->NAME }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Code</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $product->CODE }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Reference</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $product->REFERENCE }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Category ID</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $product->CATEGORY }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Tax Category</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $product->tax_category_badge_class }} mr-2">
                                            {{ $product->formatted_vat_rate }}
                                        </span>
                                        {{ $product->tax_category_name }} ({{ $product->TAXCAT }})
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Pricing and Stock -->
                        <div>
                            <h3 class="text-lg font-semibold mb-4">Pricing & Stock</h3>
                            <dl class="space-y-3">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Buy Price</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">€{{ number_format($product->PRICEBUY, 2) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Net Sell Price</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">€{{ $product->formatted_price }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Gross Sell Price (incl. VAT)</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100 font-semibold text-lg">{{ $product->formatted_price_with_vat }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">VAT Amount</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">€{{ number_format($product->getVatAmount(), 2) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Stock Cost</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">€{{ number_format($product->STOCKCOST, 2) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Stock Units</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                        @if($product->isService())
                                            <span class="text-gray-500">N/A (Service)</span>
                                        @else
                                            {{ number_format($product->STOCKUNITS, 2) }}
                                        @endif
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Stock Volume</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ number_format($product->STOCKVOLUME, 2) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Warranty</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $product->WARRANTY }} days</dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <!-- Product Attributes -->
                    <div class="mt-8">
                        <h3 class="text-lg font-semibold mb-4">Product Attributes</h3>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="flex items-center">
                                <input type="checkbox" {{ $product->ISCOM ? 'checked' : '' }} disabled class="rounded border-gray-300 text-indigo-600">
                                <label class="ml-2 text-sm text-gray-600 dark:text-gray-400">Is Commission</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" {{ $product->ISSCALE ? 'checked' : '' }} disabled class="rounded border-gray-300 text-indigo-600">
                                <label class="ml-2 text-sm text-gray-600 dark:text-gray-400">Sold by Weight</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" {{ $product->ISKITCHEN ? 'checked' : '' }} disabled class="rounded border-gray-300 text-indigo-600">
                                <label class="ml-2 text-sm text-gray-600 dark:text-gray-400">Kitchen Item</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" {{ $product->PRINTKB ? 'checked' : '' }} disabled class="rounded border-gray-300 text-indigo-600">
                                <label class="ml-2 text-sm text-gray-600 dark:text-gray-400">Print to Kitchen</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" {{ $product->SENDSTATUS ? 'checked' : '' }} disabled class="rounded border-gray-300 text-indigo-600">
                                <label class="ml-2 text-sm text-gray-600 dark:text-gray-400">Send Status</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" {{ $product->ISSERVICE ? 'checked' : '' }} disabled class="rounded border-gray-300 text-indigo-600">
                                <label class="ml-2 text-sm text-gray-600 dark:text-gray-400">Is Service</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" {{ $product->ISVPRICE ? 'checked' : '' }} disabled class="rounded border-gray-300 text-indigo-600">
                                <label class="ml-2 text-sm text-gray-600 dark:text-gray-400">Variable Price</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" {{ $product->ISVERPATRIB ? 'checked' : '' }} disabled class="rounded border-gray-300 text-indigo-600">
                                <label class="ml-2 text-sm text-gray-600 dark:text-gray-400">Variable Attributes</label>
                            </div>
                        </div>
                    </div>

                    <!-- Tax Assignment Edit -->
                    <div class="mt-8">
                        <h3 class="text-lg font-semibold mb-4">Tax Assignment</h3>
                        <form method="POST" action="{{ route('products.update-tax', $product->ID) }}" class="space-y-4">
                            @csrf
                            @method('PATCH')
                            <div>
                                <label for="tax_category" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tax Category</label>
                                <select name="tax_category" id="tax_category" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600">
                                    @foreach($taxCategories as $category)
                                        <option value="{{ $category->ID }}" {{ $product->TAXCAT == $category->ID ? 'selected' : '' }}>
                                            {{ $category->NAME }} ({{ $category->primaryTax?->formatted_rate ?? '0%' }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:bg-gray-700 dark:focus:bg-white active:bg-gray-900 dark:active:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                                    Update Tax Category
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Additional Information -->
                    @if($product->TEXTTIP || $product->DISPLAY)
                        <div class="mt-8">
                            <h3 class="text-lg font-semibold mb-4">Additional Information</h3>
                            <dl class="space-y-3">
                                @if($product->TEXTTIP)
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Text Tip</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $product->TEXTTIP }}</dd>
                                    </div>
                                @endif
                                @if($product->DISPLAY)
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Display</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $product->DISPLAY }}</dd>
                                    </div>
                                @endif
                            </dl>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>