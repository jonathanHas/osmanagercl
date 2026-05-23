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

            <!-- Legend -->
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-6 text-sm text-gray-700 dark:text-gray-200">
                <strong>Primary</strong> products trigger a KDS entry when sold.
                <strong>Companion</strong> products only appear on the KDS when sold on the same ticket as a primary product.
                Bakery items are typically companions to coffee.
            </div>

            <!-- Add a single product (search) -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-semibold mb-2">Add a product (search POS)</h3>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <input id="search-input" type="text"
                               placeholder="Search by name, code or reference (min 2 chars)…"
                               class="flex-1 px-3 py-2 border rounded dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                               autocomplete="off">
                        <select id="search-mode" class="px-3 py-2 border rounded dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100">
                            <option value="primary">Mode: Primary</option>
                            <option value="companion">Mode: Companion</option>
                        </select>
                    </div>
                    <div id="search-status" class="text-xs text-gray-500 mt-1 h-4"></div>
                    <div id="search-results" class="mt-3 space-y-1"></div>
                </div>
            </div>

            <!-- Bulk add by category -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-semibold mb-2">Bulk add by category</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                        Adds every POS product in a category (skipping any already in the list).
                    </p>
                    <div class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-center">
                        <select id="bulk-category" class="flex-1 px-3 py-2 border rounded dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100">
                            <option value="">Loading categories…</option>
                        </select>
                        <select id="bulk-mode" class="px-3 py-2 border rounded dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100">
                            <option value="primary">Add as Primary</option>
                            <option value="companion" selected>Add as Companion</option>
                        </select>
                        <button id="bulk-add-btn"
                                onclick="bulkAdd()"
                                class="px-4 py-2 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700">
                            Add all
                        </button>
                    </div>
                    <div id="bulk-status" class="text-xs text-gray-500 mt-2 h-4"></div>
                </div>
            </div>

            <!-- Primary section -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold">
                            Primary products
                            <span class="text-sm font-normal text-gray-500">({{ $primaryProducts->count() }})</span>
                        </h3>
                        <span class="text-xs text-gray-500">Trigger a KDS entry when sold</span>
                    </div>
                    @include('kds._products_table', ['rows' => $primaryProducts])
                </div>
            </div>

            <!-- Companion section -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold">
                            Companion products
                            <span class="text-sm font-normal text-gray-500">({{ $companionProducts->count() }})</span>
                        </h3>
                        <span class="text-xs text-gray-500">Only appear on a KDS entry alongside a primary</span>
                    </div>
                    @include('kds._products_table', ['rows' => $companionProducts])
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        // --- Per-product search ---
        const searchInput = document.getElementById('search-input');
        const searchResults = document.getElementById('search-results');
        const searchStatus = document.getElementById('search-status');
        const searchMode = document.getElementById('search-mode');
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
            if (!results.length) { searchResults.innerHTML = ''; return; }
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
                    body: JSON.stringify({ product_id: productId, trigger_mode: searchMode.value }),
                });
                const data = await response.json();
                if (!response.ok || !data.success) {
                    alert(data.message || 'Failed to add product');
                    buttonEl.disabled = false;
                    buttonEl.textContent = 'Add';
                    return;
                }
                location.reload();
            } catch (e) {
                console.error(e);
                alert('Failed to add product');
                buttonEl.disabled = false;
                buttonEl.textContent = 'Add';
            }
        }

        // --- Bulk add by category ---
        const bulkCategory = document.getElementById('bulk-category');
        const bulkMode = document.getElementById('bulk-mode');
        const bulkBtn = document.getElementById('bulk-add-btn');
        const bulkStatus = document.getElementById('bulk-status');

        (async function loadCategories() {
            try {
                const response = await fetch('/kds/products/pos-categories', {
                    headers: { 'Accept': 'application/json' },
                });
                const data = await response.json();
                bulkCategory.innerHTML = '<option value="">Select a category…</option>'
                    + (data.categories || []).map(c =>
                        `<option value="${escapeAttr(c.id)}">${escapeHtml(c.name)} (${escapeHtml(c.id)})</option>`
                    ).join('');
            } catch (e) {
                console.error(e);
                bulkCategory.innerHTML = '<option value="">Failed to load</option>';
            }
        })();

        async function bulkAdd() {
            const categoryId = bulkCategory.value;
            if (!categoryId) { alert('Pick a category first'); return; }
            const mode = bulkMode.value;
            if (!confirm(`Add all products from this category as "${mode}"?`)) return;

            bulkBtn.disabled = true;
            bulkBtn.textContent = 'Adding…';
            bulkStatus.textContent = '';
            try {
                const response = await fetch('/kds/products/bulk-add', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ category_id: categoryId, trigger_mode: mode }),
                });
                const data = await response.json();
                if (!response.ok || !data.success) {
                    bulkStatus.textContent = 'Failed';
                    bulkBtn.disabled = false;
                    bulkBtn.textContent = 'Add all';
                    return;
                }
                bulkStatus.textContent = `Added ${data.added} product(s). Reloading…`;
                setTimeout(() => location.reload(), 600);
            } catch (e) {
                console.error(e);
                bulkStatus.textContent = 'Failed';
                bulkBtn.disabled = false;
                bulkBtn.textContent = 'Add all';
            }
        }

        // --- Per-row actions (defined on window for inline handlers in partial) ---
        window.toggleActive = async function (id) {
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
        };

        window.changeMode = async function (id) {
            const select = document.getElementById(`mode-${id}`);
            const newMode = select.value;
            try {
                const response = await fetch(`/kds/products/${id}`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ trigger_mode: newMode }),
                });
                const data = await response.json();
                if (!response.ok || !data.success) {
                    alert('Failed to change mode');
                    return;
                }
                // Reload so the row moves to the correct section
                location.reload();
            } catch (e) {
                console.error(e);
                alert('Failed to change mode');
            }
        };

        window.removeProduct = async function (id) {
            if (!confirm('Remove this product from the KDS allow-list?')) return;
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
        };

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
