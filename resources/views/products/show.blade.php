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
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $product->TAXCAT }}</dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Pricing and Stock -->
                        <div>
                            <h3 class="text-lg font-semibold mb-4">Pricing & Stock</h3>
                            <dl class="space-y-3">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Buy Price</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">${{ number_format($product->PRICEBUY, 2) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Sell Price</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100 font-semibold text-lg">${{ $product->formatted_price }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Stock Cost</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">${{ number_format($product->STOCKCOST, 2) }}</dd>
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