<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-lg text-gray-800 leading-tight py-1">
            Destock Review
        </h2>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto px-2 sm:px-4 lg:px-6">
            <!-- Tab Navigation -->
            <div class="border-b border-gray-200 mb-4">
                <nav class="-mb-px flex space-x-8">
                    <a href="{{ route('destock-review.index') }}"
                       class="border-b-2 border-indigo-500 text-indigo-600 py-3 px-1 text-sm font-medium">
                        Audit Log
                    </a>
                    <a href="{{ route('destock-review.suggestions') }}"
                       class="border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 py-3 px-1 text-sm font-medium">
                        Restock Suggestions
                    </a>
                    <a href="{{ route('supplier-code-lookup.index') }}"
                       class="border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 py-3 px-1 text-sm font-medium">
                        Supplier Code Lookup
                    </a>
                </nav>
            </div>

            <!-- Filters -->
            <div class="bg-white shadow-sm sm:rounded-lg p-4 mb-4">
                <form method="GET" action="{{ route('destock-review.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
                    <div>
                        <label for="date_from" class="block text-xs font-medium text-gray-500 uppercase">From</label>
                        <input type="date" name="date_from" id="date_from" value="{{ request('date_from') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label for="date_to" class="block text-xs font-medium text-gray-500 uppercase">To</label>
                        <input type="date" name="date_to" id="date_to" value="{{ request('date_to') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label for="action" class="block text-xs font-medium text-gray-500 uppercase">Action</label>
                        <select name="action" id="action"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">All</option>
                            <option value="destock" {{ request('action') === 'destock' ? 'selected' : '' }}>Destock</option>
                            <option value="restock" {{ request('action') === 'restock' ? 'selected' : '' }}>Restock</option>
                        </select>
                    </div>
                    <div>
                        <label for="user_id" class="block text-xs font-medium text-gray-500 uppercase">User</label>
                        <select name="user_id" id="user_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">All Users</option>
                            @foreach($users as $id => $name)
                                <option value="{{ $id }}" {{ request('user_id') == $id ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            Filter
                        </button>
                        <a href="{{ route('destock-review.index') }}"
                           class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-300">
                            Clear
                        </a>
                    </div>
                </form>
            </div>

            <!-- Results -->
            @if($audits->isEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg p-8 text-center text-gray-500">
                    <p class="text-lg font-medium">No destock records yet</p>
                    <p class="text-sm mt-1">Audit records will appear here when products are destocked or restocked.</p>
                </div>
            @else
                <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date/Time</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Barcode</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Action</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Source</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach($audits as $audit)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $audit->created_at->format('d M Y H:i') }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700 font-mono">{{ $audit->barcode }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $audit->product_name }}</td>
                                    <td class="px-4 py-3 text-center">
                                        @if($audit->action === 'destock')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                Destocked
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                Restocked
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $audit->user?->name ?? 'Unknown' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">
                                        @switch($audit->source)
                                            @case('order_review')
                                                Order Review
                                                @break
                                            @case('order_review_christmas')
                                                Order Review (Christmas)
                                                @break
                                            @case('product_show')
                                                Product Page
                                                @break
                                            @case('destock_review')
                                                Destock Review
                                                @break
                                            @default
                                                {{ $audit->source ?? '—' }}
                                        @endswitch
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $audits->withQueryString()->links() }}
                </div>
            @endif
        </div>
    </div>
</x-admin-layout>
