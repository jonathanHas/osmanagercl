<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Delivery Legacy - Invoice Match
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    Supplier: <span class="font-medium">{{ $supplier->Supplier ?? 'Unknown' }}</span>
                    | Scan Session: <span class="font-medium">#{{ $deliveryId }}</span>
                </p>
            </div>
            <a href="{{ route('delivery-legacy.index') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                Back to Selection
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            @if($isUdea)
                <div class="mb-4 bg-yellow-100 border border-yellow-400 text-yellow-800 px-4 py-3 rounded">
                    <strong>Note:</strong> Profit and Margin adjusted to allow for UDEA delivery charge (15%)
                </div>
            @endif

            <!-- Summary Stats -->
            <div class="mb-6 grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="text-sm font-medium text-gray-500">Total Items on Invoice</div>
                    <div class="text-2xl font-bold text-gray-900">{{ count($matchedItems) }}</div>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="text-sm font-medium text-gray-500">Items Scanned (not on invoice)</div>
                    <div class="text-2xl font-bold text-orange-600">{{ count($scannedNotOnInvoice) }}</div>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="text-sm font-medium text-gray-500">Items Not Scanned</div>
                    <div class="text-2xl font-bold text-red-600">{{ count($onInvoiceNotScanned) }}</div>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="text-sm font-medium text-gray-500">Matched Items</div>
                    @php
                        $matchedCount = collect($matchedItems)->filter(fn($item) => $item->scanned !== null)->count();
                    @endphp
                    <div class="text-2xl font-bold text-green-600">{{ $matchedCount }}</div>
                </div>
            </div>

            <!-- Table 1: Matched Items -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">
                        Invoice Items with Scan Data
                        <span class="text-sm font-normal text-gray-500">({{ count($matchedItems) }} items)</span>
                    </h3>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">Order</th>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">Case Units</th>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">Old Case</th>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">Product Name</th>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">Sup Code</th>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">VAT</th>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">Barcode</th>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">New Cost</th>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">Cost</th>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">Sell</th>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">New Sell</th>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">Profit</th>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">Margin</th>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">Stock</th>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">Invoiced</th>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">Scanned</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($matchedItems as $item)
                                    @php
                                        // Calculate values
                                        $vat = ($item->RATE ?? 0) * 100;
                                        $sell = ($item->PRICESELL ?? 0) * (1 + ($item->RATE ?? 0));
                                        $cost = $item->cost ?? 0;

                                        // UDEA adjustment
                                        if ($isUdea) {
                                            $profit = ($item->PRICESELL ?? 0) - ($cost * 1.15);
                                        } else {
                                            $profit = ($item->PRICESELL ?? 0) - $cost;
                                        }
                                        $margin = ($item->PRICESELL ?? 0) > 0 ? ($profit / $item->PRICESELL) : 0;

                                        // Calculate invoiced units
                                        $caseUnits = $item->invoiceCaseUnits ?? 1;
                                        $myOrder = $item->myOrder ?? 0;
                                        if (fmod($myOrder, 1) == 0.0) {
                                            $unitsDelivered = $caseUnits * $myOrder;
                                        } else {
                                            $unitsDelivered = round($caseUnits * $myOrder);
                                        }

                                        $scanned = $item->scanned ?? null;
                                        $scannedFormatted = $scanned !== null ? number_format($scanned, 2) : null;

                                        // Row highlighting
                                        if ($scannedFormatted !== null && floatval($scannedFormatted) == $unitsDelivered) {
                                            $rowClass = 'bg-green-100'; // Match
                                        } elseif ($scanned === null) {
                                            $rowClass = ''; // No scan yet
                                        } else {
                                            $rowClass = 'bg-red-100'; // Mismatch
                                        }

                                        // Case units mismatch
                                        $caseClass = ($item->invoiceCaseUnits == $item->CaseUnits) ? 'text-gray-500' : 'text-red-600 font-bold';

                                        // Price warning
                                        $priceClass = (($item->PRICESELL ?? 0) < $cost) ? 'text-red-600 font-bold' : '';
                                    @endphp
                                    <tr class="{{ $rowClass }}">
                                        <td class="px-2 py-2 text-gray-500">{{ $myOrder }}</td>
                                        <td class="px-2 py-2 {{ $caseClass }}">{{ $item->invoiceCaseUnits }}</td>
                                        <td class="px-2 py-2 {{ $caseClass }}">{{ $item->CaseUnits }}</td>
                                        <td class="px-2 py-2 font-medium text-gray-900">
                                            @if($item->Barcode)
                                                <a href="{{ route('products.show', $item->Barcode) }}" class="text-indigo-600 hover:text-indigo-900">
                                                    {{ $item->prodName }}
                                                </a>
                                            @else
                                                {{ $item->prodName }}
                                            @endif
                                        </td>
                                        <td class="px-2 py-2 text-gray-500">{{ $item->supCode }}</td>
                                        <td class="px-2 py-2 text-gray-500">{{ number_format($vat, 0) }}%</td>
                                        <td class="px-2 py-2 text-gray-500 text-xs">{{ $item->Barcode }}</td>
                                        <td class="px-2 py-2 text-gray-500">{{ number_format($cost, 2) }}</td>
                                        <td class="px-2 py-2 {{ $priceClass }}">{{ number_format($item->PRICEBUY ?? 0, 2) }}</td>
                                        <td class="px-2 py-2 {{ $priceClass }}">{{ number_format($sell, 2) }}</td>
                                        <td class="px-2 py-2 text-gray-500">{{ $item->rrPrice }}</td>
                                        <td class="px-2 py-2 text-gray-500">&euro;{{ number_format($profit, 2) }}</td>
                                        <td class="px-2 py-2 text-gray-500">{{ number_format($margin * 100, 0) }}%</td>
                                        <td class="px-2 py-2 text-gray-500">{{ floatval($item->UNITS ?? 0) }}</td>
                                        <td class="px-2 py-2 font-medium text-gray-900">{{ $unitsDelivered }}</td>
                                        <td class="px-2 py-2 font-medium text-blue-600">{{ $scanned }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="16" class="px-4 py-4 text-center text-gray-500">
                                            No matched items found
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Table 2: Scanned but NOT on Invoice -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">
                        Items Scanned but NOT on Invoice
                        <span class="text-sm font-normal text-orange-600">({{ count($scannedNotOnInvoice) }} items)</span>
                    </h3>

                    @if(count($scannedNotOnInvoice) > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-orange-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Barcode</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Supplier Code</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Product Name</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Sell Price</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">VAT</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Scanned Qty</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($scannedNotOnInvoice as $item)
                                        <tr>
                                            <td class="px-4 py-2 text-gray-500">
                                                @if($item->Barcode)
                                                    <a href="{{ route('products.show', $item->Barcode) }}" class="text-indigo-600 hover:text-indigo-900">
                                                        {{ $item->Barcode }}
                                                    </a>
                                                @else
                                                    {{ $item->Barcode }}
                                                @endif
                                            </td>
                                            <td class="px-4 py-2 text-gray-500">
                                                {{ $item->SupplierCode ?? '-' }}
                                            </td>
                                            <td class="px-4 py-2 font-medium text-gray-900">{{ $item->NAME }}</td>
                                            <td class="px-4 py-2 text-gray-500">
                                                @if($item->PRICESELL)
                                                    &euro;{{ number_format($item->PRICESELL * (1 + ($item->RATE ?? 0)), 2) }}
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td class="px-4 py-2 text-gray-500">
                                                {{ number_format(($item->RATE ?? 0) * 100, 0) }}%
                                            </td>
                                            <td class="px-4 py-2 font-medium text-orange-600">{{ $item->scanned }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-gray-500">No items scanned that are missing from the invoice.</p>
                    @endif
                </div>
            </div>

            <!-- Table 3: On Invoice but NOT Scanned -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">
                        On Invoice but NOT Scanned
                        <span class="text-sm font-normal text-red-600">({{ count($onInvoiceNotScanned) }} items)</span>
                    </h3>
                    <p class="text-sm text-gray-500 mb-4">These items are on the invoice but haven't been scanned (supplier code may not match a barcode)</p>

                    @if(count($onInvoiceNotScanned) > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-red-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Supplier Code</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Product Name</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Cases Ordered</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Case Units</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Units</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Cost</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($onInvoiceNotScanned as $item)
                                        @php
                                            $caseUnits = $item->caseUnits ?? 1;
                                            $myOrder = $item->myOrder ?? 0;
                                            $totalUnits = $caseUnits * $myOrder;
                                        @endphp
                                        <tr>
                                            <td class="px-4 py-2 text-gray-500">{{ $item->supCode }}</td>
                                            <td class="px-4 py-2 font-medium text-gray-900">{{ $item->prodName }}</td>
                                            <td class="px-4 py-2 text-gray-500">{{ $myOrder }}</td>
                                            <td class="px-4 py-2 text-gray-500">{{ $caseUnits }}</td>
                                            <td class="px-4 py-2 font-medium text-red-600">{{ round($totalUnits) }}</td>
                                            <td class="px-4 py-2 text-gray-500">&euro;{{ number_format($item->cost ?? 0, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-gray-500">All invoice items have been scanned.</p>
                    @endif
                </div>
            </div>

            <!-- Legend -->
            <div class="mt-6 bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4">
                    <h4 class="text-sm font-medium text-gray-700 mb-2">Legend</h4>
                    <div class="flex flex-wrap gap-4 text-sm">
                        <div class="flex items-center gap-2">
                            <span class="inline-block w-4 h-4 bg-green-100 border border-green-300 rounded"></span>
                            <span>Scanned quantity matches invoiced</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="inline-block w-4 h-4 bg-red-100 border border-red-300 rounded"></span>
                            <span>Mismatch between scanned and invoiced</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="inline-block w-4 h-4 bg-white border border-gray-300 rounded"></span>
                            <span>Not yet scanned</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
