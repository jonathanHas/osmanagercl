<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Coffee KDS - Product Metadata
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Coffee Types Section -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-semibold mb-4">Coffee Types (Main Drinks) - {{ $coffeeTypes->count() }} items</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                        Product Name
                                    </th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                        Type
                                    </th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                        Short Name (KDS Display)
                                    </th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                        Display Order
                                    </th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                        Active
                                    </th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($coffeeTypes as $coffee)
                                <tr class="bg-green-50 dark:bg-green-900/20">
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                        {{ $coffee->product_name }}
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        <select id="type_{{ $coffee->id }}" 
                                            class="text-sm px-2 py-1 border rounded w-24 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                            onchange="handleTypeChange({{ $coffee->id }})">
                                            <option value="coffee" {{ $coffee->type === 'coffee' ? 'selected' : '' }}>Coffee</option>
                                            <option value="option" {{ $coffee->type === 'option' ? 'selected' : '' }}>Option</option>
                                        </select>
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        <input type="text" 
                                            id="short_name_{{ $coffee->id }}"
                                            value="{{ $coffee->short_name }}" 
                                            class="text-sm px-2 py-1 border rounded w-32 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                            maxlength="20">
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        <input type="number" 
                                            id="display_order_{{ $coffee->id }}"
                                            value="{{ $coffee->display_order }}" 
                                            class="text-sm px-2 py-1 border rounded w-20 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                            min="0">
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        <input type="checkbox" 
                                            id="is_active_{{ $coffee->id }}"
                                            {{ $coffee->is_active ? 'checked' : '' }}
                                            class="rounded dark:bg-gray-700 dark:border-gray-600">
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        <div class="flex gap-2">
                                            <button onclick="updateMetadata({{ $coffee->id }})" 
                                                class="px-3 py-1 bg-blue-500 text-white text-sm rounded hover:bg-blue-600">
                                                Update
                                            </button>
                                            <button onclick="deleteMetadata({{ $coffee->id }})" 
                                                class="px-3 py-1 bg-red-500 text-white text-sm rounded hover:bg-red-600">
                                                Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Options Section -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-semibold mb-4">Coffee Options (Modifiers)</h3>
                    @foreach($optionsGrouped as $groupName => $options)
                    <div class="mb-6">
                        <h4 class="text-md font-medium mb-2 text-gray-700 dark:text-gray-300">{{ $groupName }}</h4>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                            Product Name
                                        </th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                            Type
                                        </th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                            Short Name
                                        </th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                            Group
                                        </th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                            Order
                                        </th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                            Active
                                        </th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($options as $option)
                                    <tr class="bg-blue-50 dark:bg-blue-900/20">
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                            {{ $option->product_name }}
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap">
                                            <select id="type_{{ $option->id }}" 
                                                class="text-sm px-2 py-1 border rounded w-24 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                                onchange="handleTypeChange({{ $option->id }})">
                                                <option value="coffee" {{ $option->type === 'coffee' ? 'selected' : '' }}>Coffee</option>
                                                <option value="option" {{ $option->type === 'option' ? 'selected' : '' }}>Option</option>
                                            </select>
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap">
                                            <input type="text" 
                                                id="short_name_{{ $option->id }}"
                                                value="{{ $option->short_name }}" 
                                                class="text-sm px-2 py-1 border rounded w-32 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                                maxlength="20">
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap">
                                            <input type="text" 
                                                id="group_name_{{ $option->id }}"
                                                value="{{ $option->group_name }}" 
                                                class="text-sm px-2 py-1 border rounded w-24 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                                maxlength="50">
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap">
                                            <input type="number" 
                                                id="display_order_{{ $option->id }}"
                                                value="{{ $option->display_order }}" 
                                                class="text-sm px-2 py-1 border rounded w-20 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                                min="0">
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap">
                                            <input type="checkbox" 
                                                id="is_active_{{ $option->id }}"
                                                {{ $option->is_active ? 'checked' : '' }}
                                                class="rounded dark:bg-gray-700 dark:border-gray-600">
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap">
                                            <div class="flex gap-2">
                                                <button onclick="updateMetadata({{ $option->id }})" 
                                                    class="px-3 py-1 bg-blue-500 text-white text-sm rounded hover:bg-blue-600">
                                                    Update
                                                </button>
                                                <button onclick="deleteMetadata({{ $option->id }})" 
                                                    class="px-3 py-1 bg-red-500 text-white text-sm rounded hover:bg-red-600">
                                                    Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            <!-- Missing Metadata Section -->
            @if($missingMetadata->count() > 0)
            <div class="bg-yellow-50 dark:bg-yellow-900/20 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-semibold mb-4 text-yellow-800 dark:text-yellow-200">
                        Products Missing Metadata ({{ $missingMetadata->count() }})
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($missingMetadata as $product)
                        <div class="bg-white dark:bg-gray-800 p-4 rounded border">
                            <p class="font-medium">{{ $product->NAME }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">ID: {{ $product->ID }}</p>
                            <button onclick="createMetadata('{{ $product->ID }}', '{{ addslashes($product->NAME) }}')" 
                                class="mt-2 px-3 py-1 bg-green-500 text-white text-sm rounded hover:bg-green-600">
                                Add Metadata
                            </button>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
        async function updateMetadata(id) {
            const shortName = document.getElementById(`short_name_${id}`).value;
            const displayOrder = document.getElementById(`display_order_${id}`).value;
            const isActive = document.getElementById(`is_active_${id}`).checked;
            const type = document.getElementById(`type_${id}`).value;
            
            let groupName = null;
            if (type === 'option') {
                const groupElement = document.getElementById(`group_name_${id}`);
                if (groupElement) {
                    groupName = groupElement.value;
                }
            }

            try {
                const response = await fetch(`/coffee/metadata/${id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        short_name: shortName,
                        type: type,
                        group_name: groupName,
                        display_order: parseInt(displayOrder),
                        is_active: isActive
                    })
                });

                const result = await response.json();
                if (result.success) {
                    // Show success feedback
                    const button = event.target;
                    const originalText = button.textContent;
                    button.textContent = '✓ Updated';
                    button.className = button.className.replace('bg-blue-500 hover:bg-blue-600', 'bg-green-500 hover:bg-green-600');
                    
                    setTimeout(() => {
                        button.textContent = originalText;
                        button.className = button.className.replace('bg-green-500 hover:bg-green-600', 'bg-blue-500 hover:bg-blue-600');
                    }, 2000);
                } else {
                    alert('Failed to update metadata');
                }
            } catch (error) {
                console.error('Error updating metadata:', error);
                alert('Error updating metadata');
            }
        }

        function handleTypeChange(id) {
            const type = document.getElementById(`type_${id}`).value;
            const row = document.querySelector(`tr:has(#type_${id})`);
            
            if (type === 'coffee') {
                row.className = 'bg-green-50 dark:bg-green-900/20';
            } else {
                row.className = 'bg-blue-50 dark:bg-blue-900/20';
            }
        }

        async function deleteMetadata(id) {
            if (!confirm('Are you sure you want to delete this metadata? This cannot be undone.')) {
                return;
            }

            try {
                const response = await fetch(`/coffee/metadata/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });

                const result = await response.json();
                if (result.success) {
                    // Remove the row from the table
                    const row = document.querySelector(`tr:has(#type_${id})`);
                    if (row) {
                        row.style.transition = 'opacity 0.5s';
                        row.style.opacity = '0';
                        setTimeout(() => row.remove(), 500);
                    }
                } else {
                    alert('Failed to delete metadata');
                }
            } catch (error) {
                console.error('Error deleting metadata:', error);
                alert('Error deleting metadata');
            }
        }

        async function createMetadata(productId, productName) {
            const shortName = prompt(`Enter short name for "${productName}":`, productName.substring(0, 12));
            if (!shortName) return;

            const type = confirm('Is this a coffee type (main drink)?\nOK = Coffee Type, Cancel = Option/Modifier') ? 'coffee' : 'option';
            
            let groupName = null;
            if (type === 'option') {
                groupName = prompt('Enter group name (e.g., Syrups, Milk, Service):', 'Other');
            }

            try {
                const response = await fetch('/coffee/metadata', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        product_id: productId,
                        product_name: productName,
                        short_name: shortName,
                        type: type,
                        group_name: groupName,
                        display_order: 999
                    })
                });

                const result = await response.json();
                if (result.success) {
                    alert('Metadata created successfully');
                    location.reload();
                } else {
                    alert('Failed to create metadata');
                }
            } catch (error) {
                console.error('Error creating metadata:', error);
                alert('Error creating metadata');
            }
        }

        async function addSpecificSyrups() {
            try {
                const response = await fetch('/coffee/metadata/add-syrups', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });

                const result = await response.json();
                if (result.success) {
                    alert(result.message);
                    if (result.created > 0) {
                        location.reload();
                    }
                } else {
                    alert('Failed to add syrups');
                }
            } catch (error) {
                console.error('Error adding syrups:', error);
                alert('Error adding syrups');
            }
        }
    </script>
    @endpush
</x-admin-layout>