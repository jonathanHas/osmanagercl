<x-admin-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        {{-- Header --}}
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-100">Invoices</h2>
            <div class="flex space-x-2">
                <a href="{{ route('vat-rates.index') }}" 
                   class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                    VAT Rates
                </a>
                <div class="flex space-x-1">
                    <a href="{{ route('invoices.create-simple') }}" 
                       class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        New Invoice
                    </a>
                    <a href="{{ route('invoices.create') }}" 
                       class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-1 px-3 rounded text-sm inline-flex items-center">
                        Detailed
                    </a>
                </div>
            </div>
        </div>

        {{-- Statistics Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-gray-800 rounded-lg p-4">
                <div class="text-gray-400 text-sm font-medium">Total Unpaid</div>
                <div class="text-2xl font-bold text-yellow-400">€{{ number_format($stats['total_unpaid'], 2) }}</div>
                <div class="text-gray-500 text-xs">{{ $stats['count_unpaid'] }} invoices</div>
            </div>
            <div class="bg-gray-800 rounded-lg p-4">
                <div class="text-gray-400 text-sm font-medium">Overdue</div>
                <div class="text-2xl font-bold text-red-400">€{{ number_format($stats['total_overdue'], 2) }}</div>
                <div class="text-gray-500 text-xs">{{ $stats['count_overdue'] }} invoices</div>
            </div>
            <div class="bg-gray-800 rounded-lg p-4">
                <div class="text-gray-400 text-sm font-medium">This Month</div>
                <div class="text-2xl font-bold text-green-400">
                    €{{ number_format(\App\Models\Invoice::whereMonth('invoice_date', now()->month)->whereYear('invoice_date', now()->year)->sum('total_amount'), 2) }}
                </div>
            </div>
            <div class="bg-gray-800 rounded-lg p-4">
                <div class="text-gray-400 text-sm font-medium">Last Month</div>
                <div class="text-2xl font-bold text-gray-300">
                    €{{ number_format(\App\Models\Invoice::whereMonth('invoice_date', now()->subMonth()->month)->whereYear('invoice_date', now()->subMonth()->year)->sum('total_amount'), 2) }}
                </div>
            </div>
        </div>

        {{-- Filters --}}
        <div class="bg-gray-800 rounded-lg p-4 mb-6">
            <form method="GET" action="{{ route('invoices.index') }}" class="grid grid-cols-1 md:grid-cols-6 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1">Supplier</label>
                    <select name="supplier_id" class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md">
                        <option value="">All Suppliers</option>
                        @foreach($suppliers as $id => $name)
                            <option value="{{ $id }}" {{ request('supplier_id') == $id ? 'selected' : '' }}>
                                {{ $name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1">Status</label>
                    <select name="payment_status" class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md">
                        <option value="">All Status</option>
                        <option value="pending" {{ request('payment_status') == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="overdue" {{ request('payment_status') == 'overdue' ? 'selected' : '' }}>Overdue</option>
                        <option value="paid" {{ request('payment_status') == 'paid' ? 'selected' : '' }}>Paid</option>
                        <option value="partial" {{ request('payment_status') == 'partial' ? 'selected' : '' }}>Partial</option>
                        <option value="cancelled" {{ request('payment_status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1">From Date</label>
                    <input type="date" name="from_date" value="{{ request('from_date') }}" 
                           class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1">To Date</label>
                    <input type="date" name="to_date" value="{{ request('to_date') }}" 
                           class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" 
                           placeholder="Invoice #, supplier..." 
                           class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md">
                </div>
                <div class="flex items-end space-x-2">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        Filter
                    </button>
                    <a href="{{ route('invoices.index') }}" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                        Clear
                    </a>
                </div>
            </form>
        </div>

        {{-- Invoices Table --}}
        <div class="bg-gray-800 rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-700">
                <thead class="bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                            <a href="{{ route('invoices.index', array_merge(request()->all(), ['sort' => 'invoice_number', 'direction' => $sortField === 'invoice_number' && $sortDirection === 'asc' ? 'desc' : 'asc'])) }}" 
                               class="flex items-center space-x-1 hover:text-gray-200">
                                <span>Invoice #</span>
                                @if($sortField === 'invoice_number')
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        @if($sortDirection === 'asc')
                                            <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" />
                                        @else
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        @endif
                                    </svg>
                                @endif
                            </a>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                            <a href="{{ route('invoices.index', array_merge(request()->all(), ['sort' => 'supplier_name', 'direction' => $sortField === 'supplier_name' && $sortDirection === 'asc' ? 'desc' : 'asc'])) }}" 
                               class="flex items-center space-x-1 hover:text-gray-200">
                                <span>Supplier</span>
                                @if($sortField === 'supplier_name')
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        @if($sortDirection === 'asc')
                                            <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" />
                                        @else
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        @endif
                                    </svg>
                                @endif
                            </a>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                            <a href="{{ route('invoices.index', array_merge(request()->all(), ['sort' => 'invoice_date', 'direction' => $sortField === 'invoice_date' && $sortDirection === 'asc' ? 'desc' : 'asc'])) }}" 
                               class="flex items-center space-x-1 hover:text-gray-200">
                                <span>Date</span>
                                @if($sortField === 'invoice_date')
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        @if($sortDirection === 'asc')
                                            <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" />
                                        @else
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        @endif
                                    </svg>
                                @endif
                            </a>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                            <a href="{{ route('invoices.index', array_merge(request()->all(), ['sort' => 'payment_status', 'direction' => $sortField === 'payment_status' && $sortDirection === 'asc' ? 'desc' : 'asc'])) }}" 
                               class="flex items-center space-x-1 hover:text-gray-200">
                                <span>Status</span>
                                @if($sortField === 'payment_status')
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        @if($sortDirection === 'asc')
                                            <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" />
                                        @else
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        @endif
                                    </svg>
                                @endif
                            </a>
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider">
                            <a href="{{ route('invoices.index', array_merge(request()->all(), ['sort' => 'subtotal', 'direction' => $sortField === 'subtotal' && $sortDirection === 'asc' ? 'desc' : 'asc'])) }}" 
                               class="flex items-center justify-end space-x-1 hover:text-gray-200">
                                <span>Net</span>
                                @if($sortField === 'subtotal')
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        @if($sortDirection === 'asc')
                                            <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" />
                                        @else
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        @endif
                                    </svg>
                                @endif
                            </a>
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider">
                            <a href="{{ route('invoices.index', array_merge(request()->all(), ['sort' => 'vat_amount', 'direction' => $sortField === 'vat_amount' && $sortDirection === 'asc' ? 'desc' : 'asc'])) }}" 
                               class="flex items-center justify-end space-x-1 hover:text-gray-200">
                                <span>VAT</span>
                                @if($sortField === 'vat_amount')
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        @if($sortDirection === 'asc')
                                            <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" />
                                        @else
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        @endif
                                    </svg>
                                @endif
                            </a>
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider">
                            <a href="{{ route('invoices.index', array_merge(request()->all(), ['sort' => 'total_amount', 'direction' => $sortField === 'total_amount' && $sortDirection === 'asc' ? 'desc' : 'asc'])) }}" 
                               class="flex items-center justify-end space-x-1 hover:text-gray-200">
                                <span>Total</span>
                                @if($sortField === 'total_amount')
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        @if($sortDirection === 'asc')
                                            <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" />
                                        @else
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        @endif
                                    </svg>
                                @endif
                            </a>
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-gray-800 divide-y divide-gray-700">
                    @forelse($invoices as $invoice)
                        <tr class="hover:bg-gray-750">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <a href="{{ route('invoices.show', $invoice) }}" class="text-blue-400 hover:text-blue-300">
                                    {{ $invoice->invoice_number }}
                                    @if($invoice->hasAttachments())
                                        <span class="inline-flex items-center ml-2 px-2 py-1 rounded-full text-xs bg-blue-600 text-blue-100" title="{{ $invoice->attachment_count }} attachment(s)">
                                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z" />
                                            </svg>
                                            {{ $invoice->attachment_count }}
                                        </span>
                                    @endif
                                </a>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-gray-300">
                                {{ $invoice->supplier_name }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-gray-300">
                                {{ $invoice->invoice_date->format('d/m/Y') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @php
                                    $statusColors = [
                                        'pending' => 'bg-yellow-900 text-yellow-300',
                                        'overdue' => 'bg-red-900 text-red-300',
                                        'paid' => 'bg-green-900 text-green-300',
                                        'partial' => 'bg-orange-900 text-orange-300',
                                        'cancelled' => 'bg-gray-700 text-gray-400',
                                    ];
                                    $statusColor = $statusColors[$invoice->payment_status] ?? 'bg-gray-700 text-gray-400';
                                @endphp
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $statusColor }}">
                                    {{ ucfirst($invoice->payment_status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-gray-300">
                                €{{ number_format($invoice->subtotal, 2) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-gray-300">
                                €{{ number_format($invoice->vat_amount, 2) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right font-semibold text-white">
                                €{{ number_format($invoice->total_amount, 2) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex justify-end space-x-2">
                                    <a href="{{ route('invoices.show', $invoice) }}" 
                                       class="text-gray-400 hover:text-gray-300" title="View">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </a>
                                    <a href="{{ route('invoices.edit', $invoice) }}" 
                                       class="text-blue-400 hover:text-blue-300" title="Edit">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                                No invoices found. <a href="{{ route('invoices.create') }}" class="text-blue-400 hover:text-blue-300">Create your first invoice</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($invoices->hasPages())
            <div class="mt-4">
                {{ $invoices->withQueryString()->links() }}
            </div>
        @endif
    </div>
</x-admin-layout>