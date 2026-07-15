<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Order Management') }}
            </h2>
            <a href="{{ route('orders.create') }}" 
               class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Generate New Order
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Quick Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm font-medium text-gray-500 uppercase tracking-wide">Draft Orders</div>
                    <div class="mt-1 text-3xl font-semibold text-gray-900">
                        {{ $orders->where('status', 'draft')->count() }}
                    </div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm font-medium text-gray-500 uppercase tracking-wide">This Week</div>
                    <div class="mt-1 text-3xl font-semibold text-gray-900">
                        {{ $orders->where('created_at', '>=', now()->startOfWeek())->count() }}
                    </div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm font-medium text-gray-500 uppercase tracking-wide">Completed</div>
                    <div class="mt-1 text-3xl font-semibold text-gray-900">
                        {{ $orders->where('status', 'completed')->count() }}
                    </div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm font-medium text-gray-500 uppercase tracking-wide">Total Value</div>
                    <div class="mt-1 text-3xl font-semibold text-gray-900">
                        €{{ number_format($orders->sum('total_value'), 2) }}
                    </div>
                </div>
            </div>

            <!-- Orders Table -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 gap-3">
                        <h3 class="text-lg font-semibold text-gray-900">Recent Orders</h3>

                        <form method="GET" action="{{ route('orders.index') }}" class="flex items-center gap-2">
                            <label for="supplier_id" class="text-sm text-gray-600">Filter by supplier:</label>
                            <select name="supplier_id"
                                    id="supplier_id"
                                    onchange="this.form.submit()"
                                    class="border-gray-300 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 text-sm">
                                <option value="">All suppliers</option>
                                @foreach($availableSuppliers as $supplier)
                                    <option value="{{ $supplier->SupplierID }}"
                                            @selected((string) $selectedSupplierId === (string) $supplier->SupplierID)>
                                        {{ $supplier->Supplier }}
                                    </option>
                                @endforeach
                            </select>

                            @if($selectedSupplierId)
                                <a href="{{ route('orders.index') }}"
                                   class="text-sm text-gray-500 hover:text-gray-700 underline">
                                    Clear
                                </a>
                            @endif
                        </form>
                    </div>
                    
                    @if($orders->count() > 0)
                    <div x-data="{
                            selected: [],
                            toggle(id) {
                                const i = this.selected.indexOf(id);
                                if (i > -1) { this.selected.splice(i, 1); return; }
                                if (this.selected.length === 2) { this.selected.shift(); }
                                this.selected.push(id);
                            }
                         }">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-12">
                                            <span class="sr-only">Select for comparison</span>
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Order Details
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Supplier
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Coverage
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Status
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Items
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Total Value
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($orders as $order)
                                        <tr class="hover:bg-gray-50"
                                            :class="selected.includes('{{ $order->id }}') && 'bg-indigo-50'">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <input type="checkbox"
                                                       :checked="selected.includes('{{ $order->id }}')"
                                                       @change="toggle('{{ $order->id }}')"
                                                       aria-label="Select order #{{ $order->id }} for comparison"
                                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">
                                                    Order #{{ $order->id }}
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    Created {{ $order->created_at->format('M j, Y') }}
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    Delivery: {{ $order->order_date ? $order->order_date->format('M j, Y') : 'Not set' }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">
                                                    {{ $order->supplier->Supplier ?? 'Unknown Supplier' }}
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    ID: {{ $order->supplier_id }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                @if($order->coverage_days)
                                                    <div class="text-sm font-medium text-gray-900">
                                                        {{ $order->coverage_days }} {{ \Illuminate\Support\Str::plural('day', $order->coverage_days) }}
                                                    </div>
                                                    @if($order->coverage_ends_on)
                                                        <div class="text-sm text-gray-500">
                                                            until {{ $order->coverage_ends_on->format('M j, Y') }}
                                                        </div>
                                                    @endif
                                                @else
                                                    <span class="text-sm text-gray-400">&mdash;</span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                                    @if($order->status === 'completed') bg-green-100 text-green-800
                                                    @elseif($order->status === 'draft') bg-yellow-100 text-yellow-800
                                                    @elseif($order->status === 'submitted') bg-blue-100 text-blue-800
                                                    @else bg-gray-100 text-gray-800
                                                    @endif">
                                                    {{ ucfirst($order->status) }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $order->total_items }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                €{{ number_format($order->total_value, 2) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <div class="flex space-x-2">
                                                    <a href="{{ route('orders.show', $order) }}" 
                                                       class="text-indigo-600 hover:text-indigo-900">
                                                        View
                                                    </a>
                                                    
                                                    @if($order->status === 'completed')
                                                        <a href="{{ route('orders.export', $order) }}" 
                                                           class="text-green-600 hover:text-green-900">
                                                            Export
                                                        </a>
                                                        
                                                        <form method="POST" action="{{ route('orders.duplicate', $order) }}" class="inline">
                                                            @csrf
                                                            <button type="submit" class="text-blue-600 hover:text-blue-900">
                                                                Duplicate
                                                            </button>
                                                        </form>
                                                    @endif
                                                    
                                                    @if($order->isEditable())
                                                        <form method="POST" action="{{ route('orders.destroy', $order) }}" 
                                                              class="inline" 
                                                              onsubmit="return confirm('Are you sure you want to delete this order?')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="text-red-600 hover:text-red-900">
                                                                Delete
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
                        
                        <!-- Pagination -->
                        <div class="mt-4">
                            {{ $orders->links() }}
                        </div>

                        <!-- Compare selection bar -->
                        <div x-show="selected.length > 0"
                             x-cloak
                             class="sticky bottom-0 mt-4 flex items-center justify-between border-t border-gray-200 bg-white px-6 py-3 shadow-lg">
                            <span class="text-sm text-gray-600">
                                <span class="font-medium" x-text="selected.length"></span> of 2 orders selected
                                <span x-show="selected.length === 1" class="text-gray-400">&mdash; pick one more to compare</span>
                            </span>
                            <div class="flex items-center gap-3">
                                <button type="button"
                                        @click="selected = []"
                                        class="text-sm text-gray-500 underline hover:text-gray-700">
                                    Clear
                                </button>
                                <a x-show="selected.length === 2"
                                   :href="'{{ route('orders.compare') }}?a=' + selected[0] + '&b=' + selected[1]"
                                   class="inline-flex items-center rounded bg-blue-500 px-4 py-2 text-sm font-bold text-white hover:bg-blue-700">
                                    Compare
                                </a>
                            </div>
                        </div>
                    </div>
                    @else
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No orders</h3>
                            <p class="mt-1 text-sm text-gray-500">Get started by creating your first order.</p>
                            <div class="mt-6">
                                <a href="{{ route('orders.create') }}" 
                                   class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                    <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                    </svg>
                                    Generate New Order
                                </a>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>