<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Deliveries</h2>
            <a href="{{ route('deliveries.create') }}" 
               class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-md transition-colors duration-200">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                New Delivery
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <x-alert type="success" :message="session('success')" />

            <!-- Deliveries Table -->
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Recent Deliveries</h3>
                </div>
                
                @if($deliveries->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Delivery #
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Supplier
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Date
                                    </th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Items
                                    </th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Progress
                                    </th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Value
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($deliveries as $delivery)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-bold text-indigo-600 dark:text-indigo-400">
                                                {{ $delivery->delivery_number }}
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ $delivery->created_at->format('H:i') }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0">
                                                    @php
                                                        $supplierName = $delivery->supplier->Supplier ?? 'Unknown';
                                                        $supplierBadgeClass = match(strtolower($supplierName)) {
                                                            'udea' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
                                                            'ekoplaza' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
                                                            'bidfood' => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300',
                                                            default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                                                        };
                                                    @endphp
                                                    <span class="{{ $supplierBadgeClass }} inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                                                        {{ $supplierName }}
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                            {{ $delivery->delivery_date->format('d/m/Y') }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900 dark:text-gray-100">
                                            {{ $delivery->items->count() }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <div class="flex items-center justify-center">
                                                <div class="w-16 bg-gray-200 rounded-full h-2 mr-2">
                                                    <div class="bg-green-600 h-2 rounded-full" 
                                                         style="width: {{ $delivery->completion_percentage }}%"></div>
                                                </div>
                                                <span class="text-xs text-gray-600 dark:text-gray-400">
                                                    {{ number_format($delivery->completion_percentage, 0) }}%
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <span class="px-2 py-1 text-xs font-medium rounded-full {{ $delivery->status_badge_class }}">
                                                {{ ucfirst($delivery->status) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                            <div class="text-gray-900 dark:text-gray-100">
                                                €{{ number_format($delivery->total_expected ?? 0, 2) }}
                                            </div>
                                            @if($delivery->total_received)
                                                <div class="text-xs text-gray-500">
                                                    Received: €{{ number_format($delivery->total_received, 2) }}
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            @php
                                                $actions = [
                                                    ['type' => 'link', 'route' => 'deliveries.show', 'params' => $delivery, 'label' => 'View', 'color' => 'primary']
                                                ];
                                                
                                                if ($delivery->status === 'draft') {
                                                    $actions[] = ['type' => 'link', 'route' => 'deliveries.scan', 'params' => $delivery, 'label' => 'Start', 'color' => 'success'];
                                                } elseif ($delivery->status === 'receiving') {
                                                    $actions[] = ['type' => 'link', 'route' => 'deliveries.scan', 'params' => $delivery, 'label' => 'Continue', 'color' => 'info'];
                                                }
                                                
                                                // Add delete button for non-completed deliveries
                                                if (in_array($delivery->status, ['draft', 'cancelled'])) {
                                                    $actions[] = [
                                                        'type' => 'form', 
                                                        'method' => 'DELETE',
                                                        'route' => 'deliveries.destroy', 
                                                        'params' => $delivery, 
                                                        'label' => 'Delete', 
                                                        'color' => 'danger',
                                                        'onclick' => "return confirm('Are you sure you want to delete this delivery? This action cannot be undone.')"
                                                    ];
                                                }
                                            @endphp
                                            
                                            <x-action-buttons :actions="$actions" spacing="tight" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700">
                        {{ $deliveries->links() }}
                    </div>
                @else
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2M4 13h2m13-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 11-2 0 1 1 0 012 0z"/>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No deliveries</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by creating a new delivery import.</p>
                        <div class="mt-6">
                            <a href="{{ route('deliveries.create') }}" 
                               class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-md transition-colors duration-200">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                New Delivery
                            </a>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-admin-layout>