<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Product-Supplier Link Debug
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Product to Supplier Link Check</h3>
                    <p class="mb-4 text-gray-600">Checking if products have matching barcodes in supplier_link table:</p>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product Code</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Has Supplier Link?</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Supplier ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Supplier Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stocked</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($results as $result)
                                <tr class="{{ $result['has_supplier_link'] === 'YES' ? 'bg-green-50' : '' }}">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $result['product_id'] }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-mono">{{ $result['product_code'] }}</td>
                                    <td class="px-6 py-4 text-sm">{{ $result['product_name'] }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="{{ $result['has_supplier_link'] === 'YES' ? 'text-green-600 font-semibold' : 'text-gray-400' }}">
                                            {{ $result['has_supplier_link'] }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $result['supplier_id'] ?? '-' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $result['supplier_name'] ?? '-' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="{{ $result['is_stocked'] === 'YES' ? 'text-green-600 font-semibold' : 'text-gray-400' }}">
                                            {{ $result['is_stocked'] }}
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mt-6 p-4 bg-blue-50 rounded">
                        <p class="text-sm text-blue-800">
                            <strong>Note:</strong> Products highlighted in green have supplier links. 
                            The supplier_link table uses the product's CODE field as the Barcode to match.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>