<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Supplier Debug Information
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
            <!-- Suppliers Table Structure -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Suppliers Table Columns</h3>
                <div class="bg-gray-100 p-4 rounded">
                    @if(!empty($supplierColumns))
                        <code>{{ implode(', ', $supplierColumns) }}</code>
                    @else
                        <span class="text-red-600">No columns found or table doesn't exist</span>
                    @endif
                </div>
            </div>

            <!-- Supplier Link Table Structure -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Supplier_Link Table Columns</h3>
                <div class="bg-gray-100 p-4 rounded">
                    @if(!empty($linkColumns))
                        <code>{{ implode(', ', $linkColumns) }}</code>
                    @else
                        <span class="text-red-600">No columns found or table doesn't exist</span>
                    @endif
                </div>
            </div>

            <!-- Raw Suppliers Data -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Raw Suppliers Data (First 5 rows)</h3>
                @if($suppliers->count() > 0)
                    <div class="overflow-x-auto">
                        <pre class="bg-gray-100 p-4 rounded overflow-x-auto">{{ json_encode($suppliers, JSON_PRETTY_PRINT) }}</pre>
                    </div>
                @else
                    <span class="text-red-600">No suppliers found in table</span>
                @endif
            </div>

            <!-- Raw Supplier Links Data -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Raw Supplier_Link Data (First 5 rows)</h3>
                @if($supplierLinks->count() > 0)
                    <div class="overflow-x-auto">
                        <pre class="bg-gray-100 p-4 rounded overflow-x-auto">{{ json_encode($supplierLinks, JSON_PRETTY_PRINT) }}</pre>
                    </div>
                @else
                    <span class="text-red-600">No supplier links found in table</span>
                @endif
            </div>

            <!-- Model Test -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Model Test Results</h3>
                <div class="space-y-4">
                    <div>
                        <h4 class="font-semibold">Supplier Model First Record:</h4>
                        @if($supplierModel)
                            <pre class="bg-gray-100 p-4 rounded overflow-x-auto">{{ json_encode($supplierModel->toArray(), JSON_PRETTY_PRINT) }}</pre>
                        @else
                            <span class="text-red-600">No supplier found using model</span>
                        @endif
                    </div>
                    
                    <div>
                        <h4 class="font-semibold">SupplierLink Model First Record:</h4>
                        @if($linkModel)
                            <pre class="bg-gray-100 p-4 rounded overflow-x-auto">{{ json_encode($linkModel->toArray(), JSON_PRETTY_PRINT) }}</pre>
                        @else
                            <span class="text-red-600">No supplier link found using model</span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Test Join Query -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Test Join Query</h3>
                @php
                    try {
                        $testJoin = \DB::connection('pos')
                            ->table('supplier_link')
                            ->join('suppliers', 'supplier_link.SupplierID', '=', 'suppliers.SupplierID')
                            ->select('supplier_link.*', 'suppliers.*')
                            ->limit(3)
                            ->get();
                    } catch (\Exception $e) {
                        $testJoin = null;
                        $joinError = $e->getMessage();
                    }
                @endphp
                
                @if(isset($joinError))
                    <div class="text-red-600">Join Error: {{ $joinError }}</div>
                @elseif($testJoin && $testJoin->count() > 0)
                    <pre class="bg-gray-100 p-4 rounded overflow-x-auto">{{ json_encode($testJoin, JSON_PRETTY_PRINT) }}</pre>
                @else
                    <span class="text-red-600">No joined data found</span>
                @endif
            </div>

        </div>
    </div>
</x-admin-layout>