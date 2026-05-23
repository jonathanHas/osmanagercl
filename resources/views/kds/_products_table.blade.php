@if($rows->isEmpty())
    <p class="text-sm text-gray-500 dark:text-gray-400">No products in this section.</p>
@else
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Product</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Category</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Mode</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Active</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($rows as $product)
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
                            <select id="mode-{{ $product->id }}"
                                    onchange="changeMode({{ $product->id }})"
                                    class="text-sm px-2 py-1 border rounded dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100">
                                <option value="primary" {{ $product->trigger_mode === 'primary' ? 'selected' : '' }}>Primary</option>
                                <option value="companion" {{ $product->trigger_mode === 'companion' ? 'selected' : '' }}>Companion</option>
                            </select>
                        </td>
                        <td class="px-4 py-2">
                            <input type="checkbox"
                                   id="active-{{ $product->id }}"
                                   {{ $product->is_active ? 'checked' : '' }}
                                   onchange="toggleActive({{ $product->id }})"
                                   class="rounded dark:bg-gray-700 dark:border-gray-600">
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
