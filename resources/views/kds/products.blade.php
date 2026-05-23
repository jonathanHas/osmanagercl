<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                KDS - Manage Products
            </h2>
            <a href="{{ route('kds.index') }}"
               class="px-3 py-1 bg-gray-600 text-white text-sm rounded hover:bg-gray-700">
                ← Back to KDS
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <!-- Add a product -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-semibold mb-2">Add a product to KDS</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                        Search the POS catalog by product name, code or reference. Any product added here will appear on the KDS the next time it is sold.
                    </p>

                    <div class="relative">
                        <input id="search-input" type="text"
                               placeholder="Search POS products (min 2 chars)…"
                               class="w-full px-3 py-2 border rounded dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                               autocomplete="off">
                        <div id="search-status" class="text-xs text-gray-500 mt-1 h-4"></div>
                    </div>

                    <div id="search-results" class="mt-3 space-y-1"></div>
                </div>
            </div>

            <!-- Current KDS products -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold">
                            Current KDS products
                        </h3>
                        <span class="text-sm text-gray-600 dark:text-gray-400">
                            {{ $activeCount }} active · {{ $inactiveCount }} inactive
                        </span>
                    </div>

                    @if($products->isEmpty())
                        <p class="text-sm text-gray-500 dark:text-gray-400">No products yet. Add one above.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Product</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Category</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Active</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="products-tbody" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($products as $product)
                                        <tr id="product-row-{{ $product->id }}"
                                            class="{{ $product->is_active ? '' : 'opacity-50' }}">
                                            <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                                                {{ $product->product_name }}
                                                <div class="text-xs text-gray-400">ID: {{ $product->product_id }}</div>
                                            </td>
                                            <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300">
                                                {{ $product->category_name ?? '—' }}
                                            </td>
                                            <td class="px-4 py-2">
                                                <label class="inline-flex items-center cursor-pointer">
                                                    <input type="checkbox"
                                                           id="active-{{ $product->id }}"
                                                           {{ $product->is_active ? 'checked' : '' }}
                                                           onchange="toggleActive({{ $product->id }})"
                                                           class="rounded dark:bg-gray-700 dark:border-gray-600">
                                                </label>
                                            </td>
                                            <td class="px-4 py-2">
                                                <button onclick="removeProduct({{ $product->id }})"
                                                        class="px-3 py-1 bg-red-500 text-white text-sm rounded hover:bg-red-600">
                                                    Remove
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const searchInput = document.getElementById('search-input');
        const searchResults = document.getElementById('search-results');
        const searchStatus = document.getElementById('search-status');

        let searchTimer = null;

        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimer);
            const q = searchInput.value.trim();

            if (q.length < 2) {
                searchResults.innerHTML = '';
                searchStatus.textContent = '';
                return;
            }

            searchStatus.textContent = 'Searching…';
            searchTimer = setTimeout(() => runSearch(q), 250);
        });

        async function runSearch(q) {
            try {
                const response = await fetch(`/kds/products/search?q=${encodeURIComponent(q)}`, {
                    headers: { 'Accept': 'application/json' },
                });
                const data = await response.json();
                renderResults(data.results || []);
                searchStatus.textContent = data.results.length
                    ? `${data.results.length} result(s)`
                    : 'No matches';
            } catch (e) {
                console.error(e);
                searchStatus.textContent = 'Search failed';
            }
        }

        function renderResults(results) {
            if (!results.length) {
                searchResults.innerHTML = '';
                return;
            }
            searchResults.innerHTML = results.map(r => `
                <div class="flex items-center justify-between px-3 py-2 border rounded dark:border-gray-600">
                    <div>
                        <div class="text-sm text-gray-900 dark:text-gray-100">${escapeHtml(r.product_name)}</div>
                        <div class="text-xs text-gray-500">
                            ${escapeHtml(r.category_name || 'No category')}${r.code ? ' · Code: ' + escapeHtml(r.code) : ''}
                        </div>
                    </div>
                    <button onclick="addProduct('${escapeAttr(r.product_id)}', this)"
                            class="px-3 py-1 bg-green-500 text-white text-sm rounded hover:bg-green-600">
                        Add
                    </button>
                </div>
            `).join('');
        }

        async function addProduct(productId, buttonEl) {
            buttonEl.disabled = true;
            buttonEl.textContent = 'Adding…';
            try {
                const response = await fetch('/kds/products', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ product_id: productId }),
                });
                const data = await response.json();
                if (!response.ok || !data.success) {
                    alert(data.message || 'Failed to add product');
                    buttonEl.disabled = false;
                    buttonEl.textContent = 'Add';
                    return;
                }
                // Reload to show the new product in the table with consistent ordering
                location.reload();
            } catch (e) {
                console.error(e);
                alert('Failed to add product');
                buttonEl.disabled = false;
                buttonEl.textContent = 'Add';
            }
        }

        async function toggleActive(id) {
            const checkbox = document.getElementById(`active-${id}`);
            const row = document.getElementById(`product-row-${id}`);
            try {
                const response = await fetch(`/kds/products/${id}`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ is_active: checkbox.checked }),
                });
                const data = await response.json();
                if (!response.ok || !data.success) {
                    alert('Failed to update');
                    checkbox.checked = !checkbox.checked;
                    return;
                }
                row.classList.toggle('opacity-50', !checkbox.checked);
            } catch (e) {
                console.error(e);
                alert('Failed to update');
                checkbox.checked = !checkbox.checked;
            }
        }

        async function removeProduct(id) {
            if (!confirm('Remove this product from the KDS allow-list?')) {
                return;
            }
            try {
                const response = await fetch(`/kds/products/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                });
                const data = await response.json();
                if (!response.ok || !data.success) {
                    alert('Failed to remove');
                    return;
                }
                const row = document.getElementById(`product-row-${id}`);
                if (row) {
                    row.style.transition = 'opacity 0.4s';
                    row.style.opacity = '0';
                    setTimeout(() => row.remove(), 400);
                }
            } catch (e) {
                console.error(e);
                alert('Failed to remove');
            }
        }

        function escapeHtml(str) {
            return String(str ?? '').replace(/[&<>"']/g, c => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
            }[c]));
        }
        function escapeAttr(str) {
            return String(str ?? '').replace(/'/g, "\\'");
        }
    </script>
    @endpush
</x-admin-layout>
