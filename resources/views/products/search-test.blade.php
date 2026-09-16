<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Product Search Test Page
            </h2>
            <a href="{{ route('products.index') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white font-medium rounded-md transition-colors duration-200">
                Back to Products
            </a>
        </div>
    </x-slot>

    <div class="py-6" x-data="productSearchTestPage()"
         x-on:product-search:results="onResults($event.detail)"
         x-on:product-search:selected="onSelected($event.detail)"
         x-on:product-search:cleared="selected = null">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- 1. Picker demo --}}
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">1. Picker mode</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                    <code>&lt;x-product-search mode="picker" :camera="true" name="demo_product" /&gt;</code>
                    — select a product with the mouse, ↑/↓ + Enter, or scan a barcode (Enter commits the top match).
                </p>
                <x-product-search mode="picker" :camera="true" name="demo_product" placeholder="Pick a product…" />

                <div class="mt-4">
                    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Last <code>product-search:selected</code> payload</h4>
                    <pre x-show="selected" x-cloak class="text-xs bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-200 rounded p-3 overflow-x-auto max-h-80" x-text="JSON.stringify(selected, null, 2)"></pre>
                    <p x-show="!selected" class="text-sm text-gray-400 dark:text-gray-500">Nothing selected yet.</p>
                </div>
            </div>

            {{-- 2. List demo --}}
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">2. List mode</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                    <code>&lt;x-product-search mode="list" :camera="true"&gt;</code> with a <code>row-actions</code> slot.
                    Try <em>chocolatemakers fruit</em>, <em>chocolatmakers</em>, <em>8721325594341</em>, <em>6001397</em>, <em>milk</em> (with and without "Include unstocked").
                </p>
                <x-product-search mode="list" :camera="true" :sync-url="false" autofocus>
                    <x-slot:row-actions>
                        <a :href="product.edit_url"
                           class="text-amber-600 hover:text-amber-900 dark:text-amber-400 dark:hover:text-amber-300"
                           title="Edit Product">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                        </a>
                    </x-slot:row-actions>
                </x-product-search>
            </div>

            {{-- 3. Debug panel --}}
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">3. Debug</h3>
                <div class="grid md:grid-cols-2 gap-6">
                    <div>
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Last request URL</h4>
                        <code class="block text-xs break-all bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-200 rounded p-3" x-text="lastUrl || '—'"></code>

                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mt-4 mb-1">Last <code>meta</code></h4>
                        <pre class="text-xs bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-200 rounded p-3 overflow-x-auto" x-text="lastMeta ? JSON.stringify(lastMeta, null, 2) : '—'"></pre>
                    </div>
                    <div>
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Last 10 searches (<code>took_ms</code>)</h4>
                        <table class="min-w-full text-xs">
                            <thead>
                                <tr class="text-left text-gray-500 dark:text-gray-400">
                                    <th class="py-1 pr-3">#</th>
                                    <th class="py-1 pr-3">Query</th>
                                    <th class="py-1 pr-3">Stocked</th>
                                    <th class="py-1 pr-3">Total</th>
                                    <th class="py-1 pr-3">Corrected</th>
                                    <th class="py-1 text-right">Server ms</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-800 dark:text-gray-200">
                                <template x-for="(row, i) in log" :key="row.n">
                                    <tr class="border-t border-gray-100 dark:border-gray-700">
                                        <td class="py-1 pr-3 text-gray-400" x-text="row.n"></td>
                                        <td class="py-1 pr-3 font-mono" x-text="JSON.stringify(row.query)"></td>
                                        <td class="py-1 pr-3" x-text="row.stocked ? 'yes' : 'no'"></td>
                                        <td class="py-1 pr-3" x-text="row.total"></td>
                                        <td class="py-1 pr-3" x-text="row.corrected || ''"></td>
                                        <td class="py-1 text-right font-mono" :class="row.took_ms > 100 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'" x-text="row.took_ms.toFixed(1)"></td>
                                    </tr>
                                </template>
                                <tr x-show="log.length === 0"><td colspan="6" class="py-2 text-gray-400">No searches yet.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function productSearchTestPage() {
            let n = 0;
            return {
                selected: null,
                lastUrl: null,
                lastMeta: null,
                log: [],
                onResults(detail) {
                    this.lastUrl = detail.url;
                    this.lastMeta = detail.meta;
                    if (!detail.meta) return;
                    this.log.unshift({
                        n: ++n,
                        query: detail.meta.query,
                        stocked: detail.meta.stocked,
                        total: detail.meta.total,
                        corrected: detail.meta.corrected_query,
                        took_ms: Number(detail.meta.took_ms),
                    });
                    this.log = this.log.slice(0, 10);
                },
                onSelected(product) {
                    this.selected = product;
                },
            };
        }
    </script>
    @endpush
</x-admin-layout>
