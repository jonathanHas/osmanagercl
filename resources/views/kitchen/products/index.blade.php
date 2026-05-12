<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Kitchen Products') }}
            </h2>
            <div class="flex items-center space-x-4">
                <a href="{{ route('kitchen.profiles.index') }}" class="text-indigo-600 hover:text-indigo-900">
                    View Ingredient Profiles
                </a>
                <a href="{{ route('kitchen.index') }}" class="text-gray-600 hover:text-gray-900">
                    &larr; Back to Recipes
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

            <!-- Statistics Cards -->
            @include('kitchen.products.partials.stats')

            <!-- Info Box -->
            <div class="mb-6 bg-orange-50 border border-orange-200 rounded-lg p-4">
                <h3 class="font-medium text-orange-800 mb-2">What are Kitchen Products?</h3>
                <p class="text-sm text-orange-700">
                    Products flagged here are ones that regularly go to the kitchen. This helps with ordering ingredients
                    and speeds up creating ingredient profiles for accurate recipe costing. Flag products from the
                    <a href="{{ route('orders.index') }}" class="underline font-medium">Orders</a> page using the "Kitchen" button.
                </p>
            </div>

            <!-- Add Product Search -->
            <div class="mb-6 bg-white overflow-visible shadow-sm sm:rounded-lg" x-data="productSearch()">
                <div class="p-6">
                    <h3 class="text-sm font-medium text-gray-700 mb-3">Add Product to Kitchen List</h3>
                    <div class="relative">
                        <div class="flex gap-2">
                            <div class="flex-1 relative">
                                <input type="text"
                                       x-model="query"
                                       @input.debounce.300ms="search()"
                                       @focus="showResults = results.length > 0"
                                       @keydown.escape="showResults = false"
                                       placeholder="Search by product name, barcode, or supplier code..."
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <div x-show="loading" class="absolute right-3 top-2.5">
                                    <svg class="animate-spin h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- Search Results Dropdown -->
                        <div x-show="showResults && results.length > 0"
                             x-cloak
                             @click.outside="showResults = false"
                             class="absolute z-20 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-80 overflow-y-auto">
                            <template x-for="product in results" :key="product.id">
                                <div class="px-4 py-3 hover:bg-gray-50 border-b border-gray-100 last:border-0 flex items-center justify-between">
                                    <div class="flex-1 min-w-0">
                                        <div class="font-medium text-gray-900 truncate" x-text="product.name"></div>
                                        <div class="text-xs text-gray-500 flex flex-wrap gap-x-3">
                                            <span x-show="product.code">Barcode: <span x-text="product.code" class="font-mono"></span></span>
                                            <span x-show="product.supplier_code">Supplier Code: <span x-text="product.supplier_code" class="font-mono"></span></span>
                                            <span x-show="product.supplier" class="text-indigo-600" x-text="product.supplier"></span>
                                        </div>
                                    </div>
                                    <button type="button"
                                            @click="addProduct(product)"
                                            :disabled="adding === product.id"
                                            class="ml-3 px-3 py-1.5 text-sm font-medium text-white bg-orange-600 hover:bg-orange-700 rounded-md disabled:opacity-50 disabled:cursor-wait">
                                        <span x-show="adding !== product.id">Add</span>
                                        <span x-show="adding === product.id">Adding...</span>
                                    </button>
                                </div>
                            </template>
                        </div>

                        <!-- No Results Message -->
                        <div x-show="showResults && results.length === 0 && query.length >= 2 && !loading"
                             x-cloak
                             class="absolute z-20 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg p-4 text-center text-gray-500">
                            No products found matching "<span x-text="query"></span>"
                        </div>

                        <!-- Success Message -->
                        <div x-show="successMessage"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:leave="transition ease-in duration-150"
                             class="mt-2 p-2 bg-green-100 border border-green-300 text-green-700 rounded-md text-sm">
                            <span x-text="successMessage"></span>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                function productSearch() {
                    return {
                        query: '',
                        results: [],
                        showResults: false,
                        loading: false,
                        adding: null,
                        successMessage: '',

                        async search() {
                            if (this.query.length < 2) {
                                this.results = [];
                                this.showResults = false;
                                return;
                            }

                            this.loading = true;
                            try {
                                const response = await fetch(`{{ route('kitchen.products.search') }}?q=${encodeURIComponent(this.query)}`);
                                this.results = await response.json();
                                this.showResults = true;
                            } catch (error) {
                                console.error('Search failed:', error);
                            } finally {
                                this.loading = false;
                            }
                        },

                        async addProduct(product) {
                            this.adding = product.id;
                            try {
                                const response = await fetch('{{ route('kitchen.products.toggle') }}', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                        'Accept': 'application/json'
                                    },
                                    body: JSON.stringify({ product_id: product.id })
                                });

                                const data = await response.json();

                                if (data.success && data.is_kitchen) {
                                    // Remove from results
                                    this.results = this.results.filter(p => p.id !== product.id);
                                    this.successMessage = `"${product.name}" added to kitchen products`;

                                    // Clear success message after 3 seconds
                                    setTimeout(() => {
                                        this.successMessage = '';
                                    }, 3000);

                                    // Reload page to show the new product
                                    setTimeout(() => {
                                        window.location.reload();
                                    }, 1000);
                                }
                            } catch (error) {
                                console.error('Add failed:', error);
                                alert('Failed to add product. Please try again.');
                            } finally {
                                this.adding = null;
                            }
                        }
                    };
                }

                function copyToClipboard(text, button) {
                    // Store original content
                    const originalHtml = button.innerHTML;

                    // Try modern clipboard API first, fallback to execCommand
                    const copyText = function() {
                        if (navigator.clipboard && window.isSecureContext) {
                            return navigator.clipboard.writeText(text);
                        } else {
                            // Fallback for non-HTTPS
                            const textArea = document.createElement('textarea');
                            textArea.value = text;
                            textArea.style.position = 'fixed';
                            textArea.style.left = '-9999px';
                            document.body.appendChild(textArea);
                            textArea.select();
                            document.execCommand('copy');
                            document.body.removeChild(textArea);
                            return Promise.resolve();
                        }
                    };

                    copyText().then(function() {
                        // Show copied feedback
                        button.innerHTML = '<span class="flex items-center gap-1.5 text-green-600"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg><span class="text-xs font-medium">Copied!</span></span>';

                        // Restore original after 1 second
                        setTimeout(function() {
                            button.innerHTML = originalHtml;
                        }, 1000);
                    }).catch(function(err) {
                        console.error('Failed to copy:', err);
                        alert('Failed to copy: ' + text);
                    });
                }
            </script>

            <!-- Filters -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <form id="kitchen-filter-form" method="GET" action="{{ route('kitchen.products.index') }}"
                          class="flex flex-wrap items-center gap-4">
                        <div class="flex-1 min-w-[200px] relative">
                            <input type="text" name="search" id="kitchen-filter-search" value="{{ $search }}"
                                   placeholder="Search by product name or code..."
                                   autocomplete="off"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 pr-10">
                            <div id="kitchen-filter-spinner" class="absolute right-3 top-2.5 pointer-events-none hidden">
                                <svg class="animate-spin h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                        </div>

                        <!-- Supplier dropdown -->
                        <div class="w-48">
                            <select name="supplier" id="kitchen-filter-supplier"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">All Suppliers</option>
                                @foreach($availableSuppliers as $supplier)
                                    <option value="{{ $supplier }}" {{ $selectedSupplier === $supplier ? 'selected' : '' }}>
                                        {{ $supplier }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Group by Category checkbox -->
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="group_by_category" id="kitchen-filter-group" value="1"
                                   {{ $groupByCategory ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="ml-2 text-sm text-gray-700">Group by Category</span>
                        </label>

                        <a href="{{ route('kitchen.products.index') }}"
                           id="kitchen-filter-clear"
                           class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 {{ ($search || $selectedSupplier || $groupByCategory) ? '' : 'hidden' }}">
                            Clear
                        </a>

                        <noscript>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                                Filter
                            </button>
                        </noscript>
                    </form>
                </div>
            </div>

            <script>
                (function () {
                    function init() {
                        const form = document.getElementById('kitchen-filter-form');
                        if (!form) return;

                        const searchInput = document.getElementById('kitchen-filter-search');
                        const supplierSel = document.getElementById('kitchen-filter-supplier');
                        const groupChk = document.getElementById('kitchen-filter-group');
                        const spinner = document.getElementById('kitchen-filter-spinner');
                        const clearLink = document.getElementById('kitchen-filter-clear');

                        let abortCtl = null;
                        let debounceTimer = null;

                        function updateClearVisibility() {
                            const data = new FormData(form);
                            const any = !!(data.get('search') || data.get('supplier') || data.get('group_by_category'));
                            clearLink.classList.toggle('hidden', !any);
                        }

                        async function refresh() {
                            if (abortCtl) abortCtl.abort();
                            abortCtl = new AbortController();
                            const params = new URLSearchParams(new FormData(form)).toString();
                            const url = form.action + (params ? '?' + params : '');
                            spinner.classList.remove('hidden');
                            try {
                                const res = await fetch(url, {
                                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
                                    signal: abortCtl.signal,
                                });
                                if (!res.ok) throw new Error('HTTP ' + res.status);
                                const html = await res.text();
                                const doc = new DOMParser().parseFromString(html, 'text/html');
                                ['kitchen-stats-content', 'kitchen-table-content'].forEach(function (id) {
                                    const fresh = doc.getElementById(id);
                                    const current = document.getElementById(id);
                                    if (fresh && current) current.innerHTML = fresh.innerHTML;
                                });
                                window.history.replaceState({}, '', url);
                                updateClearVisibility();
                            } catch (err) {
                                if (err.name !== 'AbortError') console.error('Filter refresh failed:', err);
                            } finally {
                                spinner.classList.add('hidden');
                            }
                        }

                        function debouncedRefresh() {
                            clearTimeout(debounceTimer);
                            debounceTimer = setTimeout(refresh, 400);
                        }

                        searchInput.addEventListener('input', debouncedRefresh);
                        supplierSel.addEventListener('change', function () { clearTimeout(debounceTimer); refresh(); });
                        groupChk.addEventListener('change', function () { clearTimeout(debounceTimer); refresh(); });
                        form.addEventListener('submit', function (e) {
                            e.preventDefault();
                            clearTimeout(debounceTimer);
                            refresh();
                        });

                        if (searchInput.value) {
                            searchInput.focus();
                            searchInput.setSelectionRange(searchInput.value.length, searchInput.value.length);
                        }
                    }

                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', init);
                    } else {
                        init();
                    }
                })();
            </script>

            <!-- Products Table -->
            @include('kitchen.products.partials.table')
        </div>
    </div>
</x-admin-layout>
