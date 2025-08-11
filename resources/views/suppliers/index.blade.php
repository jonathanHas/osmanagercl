<x-admin-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        {{-- Header Section --}}
        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-100">Suppliers & Expenses</h2>
                <p class="text-gray-400 mt-1">Manage suppliers, track spending, and control expense categories</p>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('suppliers.create') }}" 
                   class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded text-sm">
                    <i class="fas fa-plus mr-2"></i>Add Supplier
                </a>
                <a href="{{ route('invoices.index') }}" 
                   class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded text-sm">
                    <i class="fas fa-file-invoice mr-2"></i>View Invoices
                </a>
            </div>
        </div>

        {{-- Filter & Search Section --}}
        <div class="bg-gray-800 rounded-lg p-4 mb-6">
            <form method="GET" action="{{ route('suppliers.index') }}" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4" id="filter-form">
                {{-- Search --}}
                <div class="lg:col-span-2">
                    <label class="block text-sm font-medium text-gray-400 mb-1">Search</label>
                    <input type="text" name="search" value="{{ $search }}" 
                           placeholder="Name, code, email, phone..." 
                           class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md text-sm">
                </div>

                {{-- Type Filter --}}
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1">Type</label>
                    <select name="type" class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md text-sm">
                        <option value="">All Types</option>
                        @foreach($supplierTypes as $typeOption)
                            <option value="{{ $typeOption }}" {{ $type === $typeOption ? 'selected' : '' }}>
                                {{ ucfirst($typeOption) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Status Filter --}}
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1">Status</label>
                    <select name="status" class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md text-sm">
                        <option value="">All Status</option>
                        @foreach($statuses as $statusOption)
                            <option value="{{ $statusOption }}" {{ $status === $statusOption ? 'selected' : '' }}>
                                {{ ucfirst($statusOption) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- POS Linked Filter --}}
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1">Source</label>
                    <select name="pos_linked" class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md text-sm">
                        <option value="">All Sources</option>
                        <option value="1" {{ request('pos_linked') === '1' ? 'selected' : '' }}>POS Linked</option>
                        <option value="0" {{ request('pos_linked') === '0' ? 'selected' : '' }}>Manual Entry</option>
                    </select>
                </div>

                {{-- Submit --}}
                <div class="flex items-end">
                    <button type="submit" 
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded text-sm">
                        <i class="fas fa-search mr-1"></i>Filter
                    </button>
                </div>
            </form>

            {{-- Quick Stats --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4 pt-4 border-t border-gray-700">
                <div class="text-center">
                    <div class="text-2xl font-bold text-blue-400">{{ $stats['total'] }}</div>
                    <div class="text-xs text-gray-400">Total Results</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-green-400">{{ $stats['active'] }}</div>
                    <div class="text-xs text-gray-400">Active</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-purple-400">{{ $stats['pos_linked'] }}</div>
                    <div class="text-xs text-gray-400">POS Linked</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-yellow-400">€{{ number_format($stats['total_spent'], 2) }}</div>
                    <div class="text-xs text-gray-400">Total Spend</div>
                </div>
            </div>
        </div>

        {{-- Success/Error Messages --}}
        @if(session('success'))
            <div class="bg-green-800 border border-green-600 text-green-100 px-4 py-3 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-800 border border-red-600 text-red-100 px-4 py-3 rounded mb-4">
                {{ session('error') }}
            </div>
        @endif

        {{-- Suppliers Table --}}
        <div class="bg-gray-800 rounded-lg overflow-hidden">
            {{-- Table Header --}}
            <div class="p-4 border-b border-gray-700 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-100">Suppliers List</h3>
                <div class="text-sm text-gray-400">
                    Showing {{ $suppliers->firstItem() ?? 0 }} to {{ $suppliers->lastItem() ?? 0 }} 
                    of {{ $suppliers->total() }} suppliers
                </div>
            </div>

            @if($suppliers->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-700">
                        <thead class="bg-gray-900">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                                    <a href="{{ request()->fullUrlWithQuery(['sort' => 'name', 'direction' => $sortBy === 'name' && $sortDirection === 'asc' ? 'desc' : 'asc']) }}" 
                                       class="flex items-center hover:text-gray-300">
                                        Supplier
                                        @if($sortBy === 'name')
                                            <i class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }} ml-1"></i>
                                        @endif
                                    </a>
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                                    <a href="{{ request()->fullUrlWithQuery(['sort' => 'supplier_type', 'direction' => $sortBy === 'supplier_type' && $sortDirection === 'asc' ? 'desc' : 'asc']) }}" 
                                       class="flex items-center hover:text-gray-300">
                                        Type
                                        @if($sortBy === 'supplier_type')
                                            <i class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }} ml-1"></i>
                                        @endif
                                    </a>
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Contact</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                                    <a href="{{ request()->fullUrlWithQuery(['sort' => 'total_spent', 'direction' => $sortBy === 'total_spent' && $sortDirection === 'asc' ? 'desc' : 'asc']) }}" 
                                       class="flex items-center hover:text-gray-300">
                                        Spend
                                        @if($sortBy === 'total_spent')
                                            <i class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }} ml-1"></i>
                                        @endif
                                    </a>
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                                    <a href="{{ request()->fullUrlWithQuery(['sort' => 'status', 'direction' => $sortBy === 'status' && $sortDirection === 'asc' ? 'desc' : 'asc']) }}" 
                                       class="flex items-center hover:text-gray-300">
                                        Status
                                        @if($sortBy === 'status')
                                            <i class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }} ml-1"></i>
                                        @endif
                                    </a>
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-gray-800 divide-y divide-gray-700">
                            @foreach($suppliers as $supplier)
                                <tr class="hover:bg-gray-700">
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0">
                                                @if($supplier->is_pos_linked)
                                                    <div class="h-8 w-8 bg-purple-600 rounded-full flex items-center justify-center">
                                                        <i class="fas fa-link text-white text-xs"></i>
                                                    </div>
                                                @else
                                                    <div class="h-8 w-8 bg-gray-600 rounded-full flex items-center justify-center">
                                                        <i class="fas fa-building text-white text-xs"></i>
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="ml-3">
                                                <div class="text-sm font-medium text-gray-100">{{ $supplier->name }}</div>
                                                <div class="text-xs text-gray-400">{{ $supplier->code }}</div>
                                                @if($supplier->is_pos_linked)
                                                    <div class="text-xs text-purple-400">POS ID: {{ $supplier->external_pos_id }}</div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            @if($supplier->supplier_type === 'product') bg-blue-800 text-blue-100
                                            @elseif($supplier->supplier_type === 'service') bg-green-800 text-green-100
                                            @elseif($supplier->supplier_type === 'utility') bg-yellow-800 text-yellow-100
                                            @elseif($supplier->supplier_type === 'professional') bg-purple-800 text-purple-100
                                            @else bg-gray-800 text-gray-100 @endif">
                                            {{ ucfirst($supplier->supplier_type) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">
                                        @if($supplier->contact_person)
                                            <div>{{ $supplier->contact_person }}</div>
                                        @endif
                                        @if($supplier->phone)
                                            <div class="text-xs text-gray-400">{{ $supplier->phone }}</div>
                                        @endif
                                        @if($supplier->email)
                                            <div class="text-xs text-gray-400">{{ $supplier->email }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">
                                        <div class="font-medium">€{{ number_format($supplier->total_spent, 2) }}</div>
                                        @if($supplier->invoice_count > 0)
                                            <div class="text-xs text-gray-400">{{ $supplier->invoice_count }} invoices</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="flex items-center space-x-2">
                                            <form class="toggle-status-form inline" data-supplier-id="{{ $supplier->id }}">
                                                @csrf
                                                <button type="submit" 
                                                        class="status-toggle-btn text-xs px-2 py-1 rounded font-medium transition-colors
                                                               {{ $supplier->status === 'active' ? 'bg-green-600 hover:bg-green-700 text-white' : 'bg-gray-600 hover:bg-gray-700 text-white' }}" 
                                                        data-status="{{ $supplier->status }}"
                                                        title="Click to toggle status">
                                                    <i class="fas fa-toggle-{{ $supplier->status === 'active' ? 'on' : 'off' }} mr-1"></i>
                                                    <span class="status-text">{{ ucfirst($supplier->status) }}</span>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex items-center justify-end space-x-2">
                                            <a href="{{ route('suppliers.show', $supplier) }}" 
                                               class="text-blue-400 hover:text-blue-300" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="{{ route('suppliers.edit', $supplier) }}" 
                                               class="text-yellow-400 hover:text-yellow-300" title="Edit Supplier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            @if(!$supplier->is_pos_linked && $supplier->invoices_count === 0)
                                                <form method="POST" action="{{ route('suppliers.destroy', $supplier) }}" 
                                                      class="inline" onsubmit="return confirm('Are you sure you want to delete this supplier?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-red-400 hover:text-red-300" title="Delete Supplier">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                @if($suppliers->hasPages())
                    <div class="px-4 py-3 border-t border-gray-700">
                        {{ $suppliers->links() }}
                    </div>
                @endif
            @else
                <div class="p-8 text-center">
                    <div class="text-gray-400 text-lg mb-2">No suppliers found</div>
                    <div class="text-gray-500 text-sm mb-4">
                        @if($search || $type || $status || request()->has('pos_linked'))
                            Try adjusting your search criteria or 
                            <a href="{{ route('suppliers.index') }}" class="text-blue-400 hover:text-blue-300">clear all filters</a>
                        @else
                            Get started by adding your first supplier
                        @endif
                    </div>
                    <a href="{{ route('suppliers.create') }}" 
                       class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-white hover:bg-blue-700">
                        <i class="fas fa-plus mr-2"></i>Add Supplier
                    </a>
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Debug: Log current filter values on page load
            console.log('=== SUPPLIER FILTER DEBUG INFO ===');
            console.log('Current URL:', window.location.href);
            console.log('Current search params:', new URLSearchParams(window.location.search).toString());
            
            // Debug: Log form values
            const form = document.getElementById('filter-form');
            if (form) {
                console.log('Form found, current values:');
                const formData = new FormData(form);
                for (let [key, value] of formData.entries()) {
                    console.log(`  ${key}: "${value}"`);
                }
                
                // Debug: Monitor form submission
                form.addEventListener('submit', function(e) {
                    console.log('=== FORM SUBMISSION DEBUG ===');
                    console.log('Form being submitted');
                    
                    const submissionData = new FormData(this);
                    console.log('Form data being submitted:');
                    for (let [key, value] of submissionData.entries()) {
                        console.log(`  ${key}: "${value}"`);
                    }
                    
                    // Build expected URL
                    const params = new URLSearchParams();
                    for (let [key, value] of submissionData.entries()) {
                        if (value) params.append(key, value);
                    }
                    console.log('Expected redirect URL:', form.action + '?' + params.toString());
                });
            }

            // Debug: Log server-side variables from PHP
            console.log('Server-side filter values:');
            console.log('  search:', @json($search ?? 'undefined'));
            console.log('  type:', @json($type ?? 'undefined'));
            console.log('  status:', @json($status ?? 'undefined'));
            console.log('  suppliers count:', @json($suppliers->count() ?? 'undefined'));
            console.log('  suppliers total:', @json($suppliers->total() ?? 'undefined'));
            console.log('  stats:', @json($stats ?? 'undefined'));

            // Handle status toggle AJAX
            document.querySelectorAll('.toggle-status-form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const supplierId = this.dataset.supplierId;
                    const button = this.querySelector('.status-toggle-btn');
                    const statusText = this.querySelector('.status-text');
                    const icon = this.querySelector('i');
                    const originalText = statusText.textContent;
                    
                    // Show loading state
                    button.disabled = true;
                    statusText.textContent = 'Loading...';
                    
                    // Send AJAX request
                    fetch(`/suppliers/${supplierId}/toggle-status`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.querySelector('input[name="_token"]').value
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update button appearance
                            const newStatus = data.status;
                            const isActive = newStatus === 'active';
                            
                            // Update classes
                            button.className = `status-toggle-btn text-xs px-2 py-1 rounded font-medium transition-colors ${
                                isActive ? 'bg-green-600 hover:bg-green-700 text-white' : 'bg-gray-600 hover:bg-gray-700 text-white'
                            }`;
                            
                            // Update icon
                            icon.className = `fas fa-toggle-${isActive ? 'on' : 'off'} mr-1`;
                            
                            // Update text
                            statusText.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
                            
                            // Update data attribute
                            button.dataset.status = newStatus;
                            
                            // Show success message (optional)
                            showNotification(data.message, 'success');
                        } else {
                            throw new Error(data.message || 'Failed to update status');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        statusText.textContent = originalText;
                        showNotification(error.message || 'Failed to update supplier status', 'error');
                    })
                    .finally(() => {
                        button.disabled = false;
                    });
                });
            });
        });

        // Simple notification function
        function showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 p-4 rounded-md max-w-md ${
                type === 'success' ? 'bg-green-800 text-green-100' : 
                type === 'error' ? 'bg-red-800 text-red-100' : 
                'bg-blue-800 text-blue-100'
            }`;
            notification.textContent = message;
            
            // Add to page
            document.body.appendChild(notification);
            
            // Remove after 3 seconds
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }
    </script>
    @endpush
</x-admin-layout>