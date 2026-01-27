<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Delivery Verification
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    Supplier: <span class="font-medium">{{ $supplier->Supplier ?? 'Unknown' }}</span>
                    | Scan Session: <span class="font-medium">#{{ $deliveryId }}</span>
                </p>
            </div>
            <div class="flex items-center gap-3">
                @if(!$isCompleted)
                    <form method="POST" action="{{ route('delivery-legacy.complete') }}"
                          onsubmit="return confirm('This will update stock levels for all scanned items and mark this delivery as complete. This action cannot be undone. Continue?')">
                        @csrf
                        <input type="hidden" name="delID" value="{{ $deliveryId }}">
                        <input type="hidden" name="supplierID" value="{{ $supplierId }}">
                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Update Stock & Complete
                        </button>
                    </form>
                @endif
                <a href="{{ route('delivery-legacy.index') }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                    Back to Selection
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6" x-data="deliveryMatch()" x-ref="deliveryMatchRoot">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 p-4 bg-green-100 border border-green-300 rounded-lg text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            @if($isCompleted)
                <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                    <div class="flex items-center gap-2 mb-2">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="font-medium text-green-800">Delivery Complete - Stock Updated</span>
                    </div>
                    @if(session('updateResults'))
                        @php $results = session('updateResults'); @endphp
                        <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm max-w-xs ml-7">
                            <div class="text-gray-600">Products updated:</div>
                            <div class="font-medium text-gray-800">{{ $results['productsUpdated'] }}</div>
                            <div class="text-gray-600">Units added:</div>
                            <div class="font-medium text-gray-800">{{ number_format($results['unitsAdded'], 2) }}</div>
                            @if($results['productsSkipped'] > 0)
                                <div class="text-orange-600">Products skipped:</div>
                                <div class="font-medium text-orange-600">{{ $results['productsSkipped'] }} (no stock record)</div>
                            @endif
                        </div>
                    @endif
                </div>
            @endif

            @if($isUdea)
                <div class="mb-4 bg-yellow-100 border border-yellow-400 text-yellow-800 px-4 py-3 rounded">
                    <strong>Note:</strong> Profit and Margin adjusted for UDEA delivery charge (15%)
                </div>
            @endif

            @if(!$isCompleted && $stockPreview['productsToUpdate'] > 0)
                <div class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <h4 class="font-medium text-blue-800 mb-2 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                        Stock Update Preview
                    </h4>
                    <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm max-w-xs">
                        <div class="text-gray-600">Products to update:</div>
                        <div class="font-medium text-gray-800">{{ $stockPreview['productsToUpdate'] }}</div>
                        <div class="text-gray-600">Total units to add:</div>
                        <div class="font-medium text-gray-800">{{ number_format($stockPreview['totalUnitsToAdd'], 2) }}</div>
                        <div class="text-gray-600">Current stock total:</div>
                        <div class="font-medium text-gray-800">{{ number_format($stockPreview['currentStockTotal'], 2) }}</div>
                        <div class="text-gray-600">Expected after update:</div>
                        <div class="font-medium text-green-600">{{ number_format($stockPreview['expectedStockTotal'], 2) }}</div>
                    </div>
                </div>
            @endif

            <!-- Financial Dashboard -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
                <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
                    <div class="text-xs text-gray-500 uppercase tracking-wide">Invoice Total</div>
                    <div class="text-xl font-bold text-gray-900">&euro;<span x-text="financials.invoiceTotal.toFixed(2)"></span></div>
                </div>
                <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
                    <div class="text-xs text-gray-500 uppercase tracking-wide">Scanned Total</div>
                    <div class="text-xl font-bold text-gray-900">&euro;<span x-text="financials.scannedTotal.toFixed(2)"></span></div>
                </div>
                <div class="bg-white rounded-lg shadow p-4 border-l-4" :class="financials.discrepancy > 0 ? 'border-red-500' : 'border-gray-300'">
                    <div class="text-xs text-gray-500 uppercase tracking-wide">Discrepancy</div>
                    <div class="text-xl font-bold" :class="financials.discrepancy > 0 ? 'text-red-600' : 'text-gray-900'">&euro;<span x-text="financials.discrepancy.toFixed(2)"></span></div>
                </div>
                <div class="bg-white rounded-lg shadow p-4 border-l-4" :class="financials.missingValue > 0 ? 'border-red-500' : 'border-gray-300'">
                    <div class="text-xs text-gray-500 uppercase tracking-wide">Missing Value</div>
                    <div class="text-xl font-bold" :class="financials.missingValue > 0 ? 'text-red-600' : 'text-gray-900'">&euro;<span x-text="financials.missingValue.toFixed(2)"></span></div>
                </div>
                <div class="bg-white rounded-lg shadow p-4 border-l-4" :class="financials.extraValue > 0 ? 'border-orange-500' : 'border-gray-300'">
                    <div class="text-xs text-gray-500 uppercase tracking-wide">Extra Items Value</div>
                    <div class="text-xl font-bold" :class="financials.extraValue > 0 ? 'text-orange-600' : 'text-gray-900'">&euro;<span x-text="financials.extraValue.toFixed(2)"></span></div>
                </div>
                <div class="bg-white rounded-lg shadow p-4 border-l-4" :class="financials.marginAlerts > 0 ? 'border-yellow-500' : 'border-gray-300'">
                    <div class="text-xs text-gray-500 uppercase tracking-wide">Margin Alerts</div>
                    <div class="text-xl font-bold" :class="financials.marginAlerts > 0 ? 'text-yellow-600' : 'text-gray-900'" x-text="financials.marginAlerts"></div>
                </div>
            </div>

            @php
                // Calculate OOS count early for progress bar display
                $oosCount = collect($matchedItems)->filter(fn($item) => ($item->myOrder ?? 0) == 0)->count();
            @endphp

            <!-- Progress Bar -->
            <div class="bg-white rounded-lg shadow p-4 mb-6">
                <div class="flex justify-between text-sm mb-2">
                    <span class="font-medium text-gray-700">Verification Progress</span>
                    <span class="text-gray-600">
                        <span class="text-green-600 font-medium" x-text="financials.verifiedCount"></span> verified
                        <span class="text-gray-400 mx-1">|</span>
                        <span class="text-red-600 font-medium" x-text="financials.mismatchCount"></span> mismatched
                        <span class="text-gray-400 mx-1">|</span>
                        <span class="text-gray-500 font-medium" x-text="financials.pendingCount"></span> pending
                        @if($oosCount > 0)
                        <span class="text-gray-400 mx-1">|</span>
                        <span class="text-orange-600 font-medium">{{ $oosCount }}</span> OOS
                        @endif
                        <span class="text-gray-400 mx-1">|</span>
                        <span x-text="financials.totalItems"></span> total
                    </span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                    <div class="h-3 flex">
                        <div class="bg-green-500 h-3 transition-all duration-300" :style="'width: ' + (financials.totalItems > 0 ? (financials.verifiedCount / financials.totalItems) * 100 : 0) + '%'"></div>
                        <div class="bg-red-500 h-3 transition-all duration-300" :style="'width: ' + (financials.totalItems > 0 ? (financials.mismatchCount / financials.totalItems) * 100 : 0) + '%'"></div>
                    </div>
                </div>
            </div>

            <!-- Quick Filters -->
            <div class="bg-white rounded-lg shadow p-4 mb-6">
                <div class="flex flex-wrap items-center gap-4">
                    <span class="text-sm font-medium text-gray-700">Filter:</span>
                    <div class="flex gap-2">
                        <button @click="filter = 'all'"
                                :class="filter === 'all' ? 'bg-gray-800 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'"
                                class="px-3 py-1.5 rounded-md text-sm font-medium transition-colors">
                            All Items
                        </button>
                        <button @click="filter = 'problems'"
                                :class="filter === 'problems' ? 'bg-red-600 text-white' : 'bg-red-100 text-red-700 hover:bg-red-200'"
                                class="px-3 py-1.5 rounded-md text-sm font-medium transition-colors">
                            Problems Only ({{ $financials['mismatchCount'] + count($scannedNotOnInvoice) + count($onInvoiceNotScanned) }})
                        </button>
                        <button @click="filter = 'verified'"
                                :class="filter === 'verified' ? 'bg-green-600 text-white' : 'bg-green-100 text-green-700 hover:bg-green-200'"
                                class="px-3 py-1.5 rounded-md text-sm font-medium transition-colors">
                            Verified Only ({{ $financials['verifiedCount'] }})
                        </button>
                    </div>
                    <label class="ml-auto flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" x-model="showDetails" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm text-gray-700">Show Details</span>
                    </label>
                </div>
            </div>

            @php
                // Pre-categorize items for the sections
                $criticalItems = collect($matchedItems)->filter(function($item) {
                    $caseUnits = $item->invoiceCaseUnits ?? 1;
                    $myOrder = $item->myOrder ?? 0;
                    $unitsDelivered = (fmod($myOrder, 1) != 0.0) ? $myOrder : $caseUnits * $myOrder;
                    return $item->scanned !== null && floatval($item->scanned) != $unitsDelivered;
                });

                $warningItems = collect($matchedItems)->filter(function($item) use ($isUdea) {
                    $cost = $item->cost ?? 0;
                    $profit = $isUdea ? (($item->PRICESELL ?? 0) - ($cost * 1.15)) : (($item->PRICESELL ?? 0) - $cost);
                    $hasMarginIssue = $profit < 0 || (($item->PRICESELL ?? 0) > 0 && ($profit / $item->PRICESELL) < 0.15);
                    $hasCaseUnitChange = $item->invoiceCaseUnits != $item->CaseUnits;
                    // Only include if not already in critical
                    $caseUnits = $item->invoiceCaseUnits ?? 1;
                    $myOrder = $item->myOrder ?? 0;
                    $unitsDelivered = (fmod($myOrder, 1) != 0.0) ? $myOrder : $caseUnits * $myOrder;
                    $isNotCritical = $item->scanned === null || floatval($item->scanned) == $unitsDelivered;
                    return $isNotCritical && ($hasMarginIssue || $hasCaseUnitChange);
                });

                // OOS items (myOrder = 0 means supplier didn't deliver)
                $oosItems = collect($matchedItems)->filter(function($item) {
                    return ($item->myOrder ?? 0) == 0;
                });

                $verifiedItems = collect($matchedItems)->filter(function($item) {
                    $caseUnits = $item->invoiceCaseUnits ?? 1;
                    $myOrder = $item->myOrder ?? 0;
                    if ($myOrder == 0) return false; // Exclude OOS items
                    $unitsDelivered = (fmod($myOrder, 1) != 0.0) ? $myOrder : $caseUnits * $myOrder;
                    return $item->scanned !== null && floatval($item->scanned) == $unitsDelivered;
                });

                $pendingItems = collect($matchedItems)->filter(fn($item) => $item->scanned === null && ($item->myOrder ?? 0) > 0);
            @endphp

            <!-- Critical Issues Section -->
            <div class="mb-4" x-show="filter === 'all' || filter === 'problems'">
                <button @click="sectionsOpen.critical = !sectionsOpen.critical"
                        class="w-full flex justify-between items-center p-4 bg-red-100 hover:bg-red-200 rounded-t-lg transition-colors"
                        :class="sectionsOpen.critical ? 'rounded-t-lg' : 'rounded-lg'">
                    <span class="font-medium text-red-800">
                        <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        Critical Issues - Quantity Mismatches ({{ $criticalItems->count() }})
                    </span>
                    <svg :class="sectionsOpen.critical ? 'rotate-180' : ''" class="w-5 h-5 text-red-600 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="sectionsOpen.critical" x-collapse class="bg-white border border-red-200 border-t-0 rounded-b-lg overflow-hidden">
                    @if($criticalItems->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-red-50">
                                    <tr>
                                        <th class="px-2 py-2 w-12"></th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Expected</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Inv Case</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">DB Case</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Scanned</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Diff</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Impact</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Issue</th>
                                        <template x-if="showDetails">
                                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">VAT</th>
                                        </template>
                                        <template x-if="showDetails">
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Barcode</th>
                                        </template>
                                        <template x-if="showDetails">
                                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Cost</th>
                                        </template>
                                        <template x-if="showDetails">
                                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Sell</th>
                                        </template>
                                        <template x-if="showDetails">
                                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Margin</th>
                                        </template>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Stock</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($criticalItems as $item)
                                        @php
                                            $cost = $item->cost ?? 0;
                                            $vat = ($item->RATE ?? 0) * 100;
                                            $sell = ($item->PRICESELL ?? 0) * (1 + ($item->RATE ?? 0));
                                            $profit = $isUdea ? (($item->PRICESELL ?? 0) - ($cost * 1.15)) : (($item->PRICESELL ?? 0) - $cost);
                                            $margin = ($item->PRICESELL ?? 0) > 0 ? ($profit / $item->PRICESELL) * 100 : 0;
                                            $caseUnits = $item->invoiceCaseUnits ?? 1;
                                            $myOrder = $item->myOrder ?? 0;
                                            $unitsDelivered = (fmod($myOrder, 1) != 0.0) ? $myOrder : $caseUnits * $myOrder;
                                            $diff = ($item->scanned ?? 0) - $unitsDelivered;
                                            $impact = $diff * $cost;
                                            $hasCaseUnitChange = $item->invoiceCaseUnits != $item->CaseUnits;
                                        @endphp
                                        <tr class="bg-red-50">
                                            <td class="px-2 py-2">
                                                @php
                                                    $tempProduct = (object)[
                                                        'barcode' => $item->Barcode,
                                                        'supplier' => (object)['SupplierID' => $supplierId],
                                                    ];
                                                @endphp
                                                <x-product-image
                                                    :product="$tempProduct"
                                                    :supplier-service="$supplierService"
                                                    size="sm"
                                                    :hover="true" />
                                            </td>
                                            <td class="px-3 py-2">
                                                @if($item->productID)
                                                    <a href="{{ route('products.edit', $item->productID) }}" target="_blank" class="text-indigo-600 hover:text-indigo-900 font-medium">
                                                        {{ $item->prodName }}
                                                    </a>
                                                @else
                                                    <span class="font-medium text-gray-900">{{ $item->prodName }}</span>
                                                @endif
                                                <span class="text-xs text-gray-500 block">{{ $item->supCode }}</span>
                                            </td>
                                            <td class="px-3 py-2 text-center font-medium">{{ $unitsDelivered }}</td>
                                            <td class="px-3 py-2 text-center text-gray-600">{{ $item->invoiceCaseUnits ?? '-' }}</td>
                                            <td class="px-3 py-2 text-center"
                                                x-data="{ editing: false, caseQty: {{ $item->CaseUnits ?? 1 }}, originalCaseQty: {{ $item->CaseUnits ?? 1 }}, saving: false, canEdit: {{ $isCompleted ? 'false' : 'true' }} }">
                                                <template x-if="!editing">
                                                    <span @click="canEdit && (editing = true, $nextTick(() => $refs.caseInput.select()))"
                                                          :class="canEdit ? 'cursor-pointer hover:bg-blue-100' : ''"
                                                          class="px-2 py-1 rounded inline-flex items-center gap-1 {{ $hasCaseUnitChange ? 'text-orange-600 font-bold' : 'text-gray-600' }}"
                                                          :title="canEdit ? 'Click to edit DB case units' : ''">
                                                        <span x-text="caseQty"></span>
                                                        <svg x-show="canEdit" class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                                        </svg>
                                                    </span>
                                                </template>
                                                <template x-if="editing">
                                                    <form @submit.prevent="saving = true; window.deliveryMatchInstance.saveCaseUnits('{{ $item->Barcode }}', caseQty, (newQty) => { originalCaseQty = newQty; editing = false; saving = false; }).catch(() => saving = false)"
                                                          class="flex items-center justify-center gap-1">
                                                        <input type="number" x-model="caseQty" x-ref="caseInput" min="1" step="1"
                                                               @keydown.escape="caseQty = originalCaseQty; editing = false"
                                                               class="w-16 text-center border border-gray-300 rounded px-1 py-0.5 text-sm focus:ring-blue-500 focus:border-blue-500">
                                                        <button type="submit" :disabled="saving" class="text-green-600 hover:text-green-800 disabled:opacity-50">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                            </svg>
                                                        </button>
                                                        <button type="button" @click="caseQty = originalCaseQty; editing = false" class="text-gray-400 hover:text-gray-600">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                            </svg>
                                                        </button>
                                                    </form>
                                                </template>
                                            </td>
                                            <td class="px-3 py-2 text-center font-medium text-blue-600"
                                                x-data="{ editing: false, qty: {{ $item->scanned ?? 0 }}, originalQty: {{ $item->scanned ?? 0 }}, saving: false, canEdit: {{ $isCompleted ? 'false' : 'true' }} }">
                                                <template x-if="!editing">
                                                    <span @click="canEdit && (editing = true, $nextTick(() => $refs.qtyInput.select()))"
                                                          :class="canEdit ? 'cursor-pointer hover:bg-blue-100' : ''"
                                                          class="px-2 py-1 rounded inline-flex items-center gap-1"
                                                          :title="canEdit ? 'Click to edit' : ''">
                                                        <span x-text="qty"></span>
                                                        <svg x-show="canEdit" class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                                        </svg>
                                                    </span>
                                                </template>
                                                <template x-if="editing">
                                                    <form @submit.prevent="saving = true; window.deliveryMatchInstance.saveScannedQty('{{ $item->Barcode }}', qty, (newQty) => { originalQty = newQty; editing = false; saving = false; }).catch(() => saving = false)"
                                                          class="flex items-center justify-center gap-1">
                                                        <input type="number" x-model="qty" x-ref="qtyInput" min="0" step="0.001"
                                                               @keydown.escape="qty = originalQty; editing = false"
                                                               class="w-16 text-center border border-gray-300 rounded px-1 py-0.5 text-sm focus:ring-blue-500 focus:border-blue-500">
                                                        <button type="submit" :disabled="saving" class="text-green-600 hover:text-green-800 disabled:opacity-50">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                            </svg>
                                                        </button>
                                                        <button type="button" @click="qty = originalQty; editing = false" class="text-gray-400 hover:text-gray-600">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                            </svg>
                                                        </button>
                                                    </form>
                                                </template>
                                            </td>
                                            <td class="px-3 py-2 text-center font-bold {{ $diff > 0 ? 'text-green-600' : 'text-red-600' }}">
                                                {{ $diff > 0 ? '+' : '' }}{{ $diff }}
                                            </td>
                                            <td class="px-3 py-2 text-right font-medium {{ $impact > 0 ? 'text-green-600' : 'text-red-600' }}">
                                                {{ $impact > 0 ? '+' : '' }}&euro;{{ number_format($impact, 2) }}
                                            </td>
                                            <td class="px-3 py-2 text-center">
                                                @if($hasCaseUnitChange)
                                                    <button type="button"
                                                            onclick="window.deliveryMatchInstance.saveCaseUnits('{{ $item->Barcode }}', {{ $item->invoiceCaseUnits ?? 1 }}, () => location.reload())"
                                                            class="inline-block text-xs px-2 py-0.5 bg-orange-200 text-orange-800 rounded hover:bg-orange-300 cursor-pointer transition-colors"
                                                            title="Click to update DB case units to {{ $item->invoiceCaseUnits ?? 1 }}">
                                                        Case: {{ $item->invoiceCaseUnits ?? 1 }} &rarr; {{ $item->CaseUnits ?? '?' }}
                                                    </button>
                                                @endif
                                            </td>
                                            <template x-if="showDetails">
                                                <td class="px-3 py-2 text-center text-gray-500">{{ number_format($vat, 0) }}%</td>
                                            </template>
                                            <template x-if="showDetails">
                                                <td class="px-3 py-2 text-xs text-gray-500">{{ $item->Barcode }}</td>
                                            </template>
                                            <template x-if="showDetails">
                                                <td class="px-3 py-2 text-right text-gray-500">&euro;{{ number_format($cost, 2) }}</td>
                                            </template>
                                            <template x-if="showDetails">
                                                <td class="px-3 py-2 text-right text-gray-500">&euro;{{ number_format($sell, 2) }}</td>
                                            </template>
                                            <template x-if="showDetails">
                                                <td class="px-3 py-2 text-center {{ $margin < 15 ? 'text-red-600 font-bold' : 'text-gray-500' }}">{{ number_format($margin, 0) }}%</td>
                                            </template>
                                            <td class="px-3 py-2 text-center text-gray-500">{{ floatval($item->UNITS ?? 0) }}</td>
                                            <td class="px-3 py-2 text-center">
                                                <button class="text-xs px-2 py-1 bg-green-100 text-green-700 rounded hover:bg-green-200 transition-colors">Verify</button>
                                                <button class="text-xs px-2 py-1 bg-yellow-100 text-yellow-700 rounded hover:bg-yellow-200 ml-1 transition-colors">Flag</button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="p-4 text-gray-500">No quantity mismatches found.</p>
                    @endif
                </div>
            </div>

            <!-- Warnings Section -->
            <div class="mb-4" x-show="filter === 'all' || filter === 'problems'">
                <button @click="sectionsOpen.warnings = !sectionsOpen.warnings"
                        class="w-full flex justify-between items-center p-4 bg-yellow-100 hover:bg-yellow-200 rounded-t-lg transition-colors"
                        :class="sectionsOpen.warnings ? 'rounded-t-lg' : 'rounded-lg'">
                    <span class="font-medium text-yellow-800">
                        <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Warnings - Margin/Case Unit Issues ({{ $warningItems->count() }})
                    </span>
                    <svg :class="sectionsOpen.warnings ? 'rotate-180' : ''" class="w-5 h-5 text-yellow-600 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="sectionsOpen.warnings" x-collapse class="bg-white border border-yellow-200 border-t-0 rounded-b-lg overflow-hidden">
                    @if($warningItems->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-yellow-50">
                                    <tr>
                                        <th class="px-2 py-2 w-12"></th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Expected</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Inv Case</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">DB Case</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Scanned</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Issue</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Impact</th>
                                        <template x-if="showDetails">
                                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">VAT</th>
                                        </template>
                                        <template x-if="showDetails">
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Barcode</th>
                                        </template>
                                        <template x-if="showDetails">
                                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Cost</th>
                                        </template>
                                        <template x-if="showDetails">
                                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Sell</th>
                                        </template>
                                        <template x-if="showDetails">
                                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Margin</th>
                                        </template>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Stock</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($warningItems as $item)
                                        @php
                                            $cost = $item->cost ?? 0;
                                            $vat = ($item->RATE ?? 0) * 100;
                                            $sell = ($item->PRICESELL ?? 0) * (1 + ($item->RATE ?? 0));
                                            $profit = $isUdea ? (($item->PRICESELL ?? 0) - ($cost * 1.15)) : (($item->PRICESELL ?? 0) - $cost);
                                            $margin = ($item->PRICESELL ?? 0) > 0 ? ($profit / $item->PRICESELL) * 100 : 0;
                                            $caseUnits = $item->invoiceCaseUnits ?? 1;
                                            $myOrder = $item->myOrder ?? 0;
                                            $unitsDelivered = (fmod($myOrder, 1) != 0.0) ? $myOrder : $caseUnits * $myOrder;
                                            $hasMarginIssue = $profit < 0 || $margin < 15;
                                            $hasCaseUnitChange = $item->invoiceCaseUnits != $item->CaseUnits;
                                            $issues = [];
                                            if ($hasMarginIssue) $issues[] = 'Low margin';
                                        @endphp
                                        <tr class="bg-yellow-50">
                                            <td class="px-2 py-2">
                                                @php
                                                    $tempProduct = (object)[
                                                        'barcode' => $item->Barcode,
                                                        'supplier' => (object)['SupplierID' => $supplierId],
                                                    ];
                                                @endphp
                                                <x-product-image
                                                    :product="$tempProduct"
                                                    :supplier-service="$supplierService"
                                                    size="sm"
                                                    :hover="true" />
                                            </td>
                                            <td class="px-3 py-2">
                                                @if($item->productID)
                                                    <a href="{{ route('products.edit', $item->productID) }}" target="_blank" class="text-indigo-600 hover:text-indigo-900 font-medium">
                                                        {{ $item->prodName }}
                                                    </a>
                                                @else
                                                    <span class="font-medium text-gray-900">{{ $item->prodName }}</span>
                                                @endif
                                                <span class="text-xs text-gray-500 block">{{ $item->supCode }}</span>
                                            </td>
                                            <td class="px-3 py-2 text-center font-medium">{{ $unitsDelivered }}</td>
                                            <td class="px-3 py-2 text-center text-gray-600">{{ $item->invoiceCaseUnits ?? '-' }}</td>
                                            <td class="px-3 py-2 text-center"
                                                x-data="{ editing: false, caseQty: {{ $item->CaseUnits ?? 1 }}, originalCaseQty: {{ $item->CaseUnits ?? 1 }}, saving: false, canEdit: {{ $isCompleted ? 'false' : 'true' }} }">
                                                <template x-if="!editing">
                                                    <span @click="canEdit && (editing = true, $nextTick(() => $refs.caseInput.select()))"
                                                          :class="canEdit ? 'cursor-pointer hover:bg-blue-100' : ''"
                                                          class="px-2 py-1 rounded inline-flex items-center gap-1 {{ $hasCaseUnitChange ? 'text-orange-600 font-bold' : 'text-gray-600' }}"
                                                          :title="canEdit ? 'Click to edit DB case units' : ''">
                                                        <span x-text="caseQty"></span>
                                                        <svg x-show="canEdit" class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                                        </svg>
                                                    </span>
                                                </template>
                                                <template x-if="editing">
                                                    <form @submit.prevent="saving = true; window.deliveryMatchInstance.saveCaseUnits('{{ $item->Barcode }}', caseQty, (newQty) => { originalCaseQty = newQty; editing = false; saving = false; }).catch(() => saving = false)"
                                                          class="flex items-center justify-center gap-1">
                                                        <input type="number" x-model="caseQty" x-ref="caseInput" min="1" step="1"
                                                               @keydown.escape="caseQty = originalCaseQty; editing = false"
                                                               class="w-16 text-center border border-gray-300 rounded px-1 py-0.5 text-sm focus:ring-blue-500 focus:border-blue-500">
                                                        <button type="submit" :disabled="saving" class="text-green-600 hover:text-green-800 disabled:opacity-50">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                            </svg>
                                                        </button>
                                                        <button type="button" @click="caseQty = originalCaseQty; editing = false" class="text-gray-400 hover:text-gray-600">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                            </svg>
                                                        </button>
                                                    </form>
                                                </template>
                                            </td>
                                            <td class="px-3 py-2 text-center font-medium"
                                                :class="qty !== null ? 'text-blue-600' : 'text-gray-400'"
                                                x-data="{ editing: false, qty: {{ $item->scanned !== null ? $item->scanned : 'null' }}, originalQty: {{ $item->scanned !== null ? $item->scanned : 'null' }}, saving: false, canEdit: {{ $isCompleted ? 'false' : 'true' }} }">
                                                <template x-if="!editing">
                                                    <span @click="canEdit && (editing = true, $nextTick(() => $refs.qtyInput.select()))"
                                                          :class="canEdit ? 'cursor-pointer hover:bg-blue-100' : ''"
                                                          class="px-2 py-1 rounded inline-flex items-center gap-1"
                                                          :title="canEdit ? 'Click to edit' : ''">
                                                        <span x-text="qty !== null ? qty : '-'"></span>
                                                        <svg x-show="canEdit" class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                                        </svg>
                                                    </span>
                                                </template>
                                                <template x-if="editing">
                                                    <form @submit.prevent="saving = true; window.deliveryMatchInstance.saveScannedQty('{{ $item->Barcode }}', qty || 0, (newQty) => { qty = newQty; originalQty = newQty; editing = false; saving = false; }).catch(() => saving = false)"
                                                          class="flex items-center justify-center gap-1">
                                                        <input type="number" x-model="qty" x-ref="qtyInput" min="0" step="0.001"
                                                               @keydown.escape="qty = originalQty; editing = false"
                                                               class="w-16 text-center border border-gray-300 rounded px-1 py-0.5 text-sm focus:ring-blue-500 focus:border-blue-500">
                                                        <button type="submit" :disabled="saving" class="text-green-600 hover:text-green-800 disabled:opacity-50">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                            </svg>
                                                        </button>
                                                        <button type="button" @click="qty = originalQty; editing = false" class="text-gray-400 hover:text-gray-600">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                            </svg>
                                                        </button>
                                                    </form>
                                                </template>
                                            </td>
                                            <td class="px-3 py-2 text-center">
                                                @foreach($issues as $issue)
                                                    <span class="inline-block text-xs px-2 py-0.5 bg-yellow-200 text-yellow-800 rounded mb-0.5">{{ $issue }}</span>
                                                @endforeach
                                                @if($hasCaseUnitChange)
                                                    <button type="button"
                                                            onclick="window.deliveryMatchInstance.saveCaseUnits('{{ $item->Barcode }}', {{ $item->invoiceCaseUnits ?? 1 }}, () => location.reload())"
                                                            class="inline-block text-xs px-2 py-0.5 bg-orange-200 text-orange-800 rounded hover:bg-orange-300 cursor-pointer transition-colors mb-0.5"
                                                            title="Click to update DB case units to {{ $item->invoiceCaseUnits ?? 1 }}">
                                                        Case: {{ $item->invoiceCaseUnits ?? 1 }} &rarr; {{ $item->CaseUnits ?? '?' }}
                                                    </button>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-right font-medium {{ $margin < 15 ? 'text-red-600' : 'text-gray-500' }}">
                                                {{ number_format($margin, 0) }}% margin
                                            </td>
                                            <template x-if="showDetails">
                                                <td class="px-3 py-2 text-center text-gray-500">{{ number_format($vat, 0) }}%</td>
                                            </template>
                                            <template x-if="showDetails">
                                                <td class="px-3 py-2 text-xs text-gray-500">{{ $item->Barcode }}</td>
                                            </template>
                                            <template x-if="showDetails">
                                                <td class="px-3 py-2 text-right text-gray-500">&euro;{{ number_format($cost, 2) }}</td>
                                            </template>
                                            <template x-if="showDetails">
                                                <td class="px-3 py-2 text-right text-gray-500">&euro;{{ number_format($sell, 2) }}</td>
                                            </template>
                                            <template x-if="showDetails">
                                                <td class="px-3 py-2 text-center {{ $margin < 15 ? 'text-red-600 font-bold' : 'text-gray-500' }}">{{ number_format($margin, 0) }}%</td>
                                            </template>
                                            <td class="px-3 py-2 text-center text-gray-500">{{ floatval($item->UNITS ?? 0) }}</td>
                                            <td class="px-3 py-2 text-center">
                                                <button class="text-xs px-2 py-1 bg-green-100 text-green-700 rounded hover:bg-green-200 transition-colors">Verify</button>
                                                <button class="text-xs px-2 py-1 bg-yellow-100 text-yellow-700 rounded hover:bg-yellow-200 ml-1 transition-colors">Flag</button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="p-4 text-gray-500">No margin or case unit warnings found.</p>
                    @endif
                </div>
            </div>

            <!-- Verified Section -->
            <div class="mb-4" x-show="filter === 'all' || filter === 'verified'">
                <button @click="sectionsOpen.verified = !sectionsOpen.verified"
                        class="w-full flex justify-between items-center p-4 bg-green-100 hover:bg-green-200 rounded-t-lg transition-colors"
                        :class="sectionsOpen.verified ? 'rounded-t-lg' : 'rounded-lg'">
                    <span class="font-medium text-green-800">
                        <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Verified Items - Quantities Match ({{ $verifiedItems->count() }})
                    </span>
                    <svg :class="sectionsOpen.verified ? 'rotate-180' : ''" class="w-5 h-5 text-green-600 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="sectionsOpen.verified" x-collapse class="bg-white border border-green-200 border-t-0 rounded-b-lg overflow-hidden">
                    @if($verifiedItems->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-green-50">
                                    <tr>
                                        <th class="px-2 py-2 w-12"></th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Expected</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Inv Case</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">DB Case</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Scanned</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Value</th>
                                        <template x-if="showDetails">
                                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">VAT</th>
                                        </template>
                                        <template x-if="showDetails">
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Barcode</th>
                                        </template>
                                        <template x-if="showDetails">
                                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Cost</th>
                                        </template>
                                        <template x-if="showDetails">
                                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Sell</th>
                                        </template>
                                        <template x-if="showDetails">
                                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Margin</th>
                                        </template>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Stock</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($verifiedItems as $item)
                                        @php
                                            $cost = $item->cost ?? 0;
                                            $vat = ($item->RATE ?? 0) * 100;
                                            $sell = ($item->PRICESELL ?? 0) * (1 + ($item->RATE ?? 0));
                                            $profit = $isUdea ? (($item->PRICESELL ?? 0) - ($cost * 1.15)) : (($item->PRICESELL ?? 0) - $cost);
                                            $margin = ($item->PRICESELL ?? 0) > 0 ? ($profit / $item->PRICESELL) * 100 : 0;
                                            $caseUnits = $item->invoiceCaseUnits ?? 1;
                                            $myOrder = $item->myOrder ?? 0;
                                            $unitsDelivered = (fmod($myOrder, 1) != 0.0) ? $myOrder : $caseUnits * $myOrder;
                                            $value = $cost * $unitsDelivered;
                                            $hasCaseUnitChange = $item->invoiceCaseUnits != $item->CaseUnits;
                                        @endphp
                                        <tr class="hover:bg-green-50">
                                            <td class="px-2 py-2">
                                                @php
                                                    $tempProduct = (object)[
                                                        'barcode' => $item->Barcode,
                                                        'supplier' => (object)['SupplierID' => $supplierId],
                                                    ];
                                                @endphp
                                                <x-product-image
                                                    :product="$tempProduct"
                                                    :supplier-service="$supplierService"
                                                    size="sm"
                                                    :hover="true" />
                                            </td>
                                            <td class="px-3 py-2">
                                                @if($item->productID)
                                                    <a href="{{ route('products.edit', $item->productID) }}" target="_blank" class="text-indigo-600 hover:text-indigo-900 font-medium">
                                                        {{ $item->prodName }}
                                                    </a>
                                                @else
                                                    <span class="font-medium text-gray-900">{{ $item->prodName }}</span>
                                                @endif
                                                <span class="text-xs text-gray-500 block">{{ $item->supCode }}</span>
                                            </td>
                                            <td class="px-3 py-2 text-center font-medium text-green-600">{{ $unitsDelivered }}</td>
                                            <td class="px-3 py-2 text-center text-gray-600">{{ $item->invoiceCaseUnits ?? '-' }}</td>
                                            <td class="px-3 py-2 text-center"
                                                x-data="{ editing: false, caseQty: {{ $item->CaseUnits ?? 1 }}, originalCaseQty: {{ $item->CaseUnits ?? 1 }}, saving: false, canEdit: {{ $isCompleted ? 'false' : 'true' }} }">
                                                <template x-if="!editing">
                                                    <span @click="canEdit && (editing = true, $nextTick(() => $refs.caseInput.select()))"
                                                          :class="canEdit ? 'cursor-pointer hover:bg-blue-100' : ''"
                                                          class="px-2 py-1 rounded inline-flex items-center gap-1 {{ $hasCaseUnitChange ? 'text-orange-600 font-bold' : 'text-gray-600' }}"
                                                          :title="canEdit ? 'Click to edit DB case units' : ''">
                                                        <span x-text="caseQty"></span>
                                                        <svg x-show="canEdit" class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                                        </svg>
                                                    </span>
                                                </template>
                                                <template x-if="editing">
                                                    <form @submit.prevent="saving = true; window.deliveryMatchInstance.saveCaseUnits('{{ $item->Barcode }}', caseQty, (newQty) => { originalCaseQty = newQty; editing = false; saving = false; }).catch(() => saving = false)"
                                                          class="flex items-center justify-center gap-1">
                                                        <input type="number" x-model="caseQty" x-ref="caseInput" min="1" step="1"
                                                               @keydown.escape="caseQty = originalCaseQty; editing = false"
                                                               class="w-16 text-center border border-gray-300 rounded px-1 py-0.5 text-sm focus:ring-blue-500 focus:border-blue-500">
                                                        <button type="submit" :disabled="saving" class="text-green-600 hover:text-green-800 disabled:opacity-50">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                            </svg>
                                                        </button>
                                                        <button type="button" @click="caseQty = originalCaseQty; editing = false" class="text-gray-400 hover:text-gray-600">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                            </svg>
                                                        </button>
                                                    </form>
                                                </template>
                                            </td>
                                            <td class="px-3 py-2 text-center font-medium text-green-600"
                                                x-data="{ editing: false, qty: {{ $item->scanned ?? 0 }}, originalQty: {{ $item->scanned ?? 0 }}, saving: false, canEdit: {{ $isCompleted ? 'false' : 'true' }} }">
                                                <template x-if="!editing">
                                                    <span @click="canEdit && (editing = true, $nextTick(() => $refs.qtyInput.select()))"
                                                          :class="canEdit ? 'cursor-pointer hover:bg-green-100' : ''"
                                                          class="px-2 py-1 rounded inline-flex items-center gap-1"
                                                          :title="canEdit ? 'Click to edit' : ''">
                                                        <span x-text="qty"></span>
                                                        <svg x-show="canEdit" class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                                        </svg>
                                                    </span>
                                                </template>
                                                <template x-if="editing">
                                                    <form @submit.prevent="saving = true; window.deliveryMatchInstance.saveScannedQty('{{ $item->Barcode }}', qty, (newQty) => { originalQty = newQty; editing = false; saving = false; }).catch(() => saving = false)"
                                                          class="flex items-center justify-center gap-1">
                                                        <input type="number" x-model="qty" x-ref="qtyInput" min="0" step="0.001"
                                                               @keydown.escape="qty = originalQty; editing = false"
                                                               class="w-16 text-center border border-gray-300 rounded px-1 py-0.5 text-sm focus:ring-blue-500 focus:border-blue-500">
                                                        <button type="submit" :disabled="saving" class="text-green-600 hover:text-green-800 disabled:opacity-50">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                            </svg>
                                                        </button>
                                                        <button type="button" @click="qty = originalQty; editing = false" class="text-gray-400 hover:text-gray-600">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                            </svg>
                                                        </button>
                                                    </form>
                                                </template>
                                            </td>
                                            <td class="px-3 py-2 text-right text-gray-500">&euro;{{ number_format($value, 2) }}</td>
                                            <template x-if="showDetails">
                                                <td class="px-3 py-2 text-center text-gray-500">{{ number_format($vat, 0) }}%</td>
                                            </template>
                                            <template x-if="showDetails">
                                                <td class="px-3 py-2 text-xs text-gray-500">{{ $item->Barcode }}</td>
                                            </template>
                                            <template x-if="showDetails">
                                                <td class="px-3 py-2 text-right text-gray-500">&euro;{{ number_format($cost, 2) }}</td>
                                            </template>
                                            <template x-if="showDetails">
                                                <td class="px-3 py-2 text-right text-gray-500">&euro;{{ number_format($sell, 2) }}</td>
                                            </template>
                                            <template x-if="showDetails">
                                                <td class="px-3 py-2 text-center text-gray-500">{{ number_format($margin, 0) }}%</td>
                                            </template>
                                            <td class="px-3 py-2 text-center text-gray-500">{{ floatval($item->UNITS ?? 0) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="p-4 text-gray-500">No verified items yet.</p>
                    @endif
                </div>
            </div>

            <!-- Out of Stock Section -->
            @if($oosItems->count() > 0)
            <div class="mb-4" x-show="filter === 'all'">
                <button @click="sectionsOpen.oos = !sectionsOpen.oos"
                        class="w-full flex justify-between items-center p-4 bg-orange-100 hover:bg-orange-200 rounded-t-lg transition-colors"
                        :class="sectionsOpen.oos ? 'rounded-t-lg' : 'rounded-lg'">
                    <span class="font-medium text-orange-800">
                        <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                        </svg>
                        Out of Stock - Supplier Did Not Deliver ({{ $oosItems->count() }})
                    </span>
                    <svg :class="sectionsOpen.oos ? 'rotate-180' : ''" class="w-5 h-5 text-orange-600 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="sectionsOpen.oos" x-collapse class="bg-white border border-orange-200 border-t-0 rounded-b-lg overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-orange-50">
                                <tr>
                                    <th class="px-2 py-2 w-12"></th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                    <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Inv Case</th>
                                    <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">DB Case</th>
                                    <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Stock</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($oosItems as $item)
                                    @php
                                        $hasCaseUnitChange = $item->invoiceCaseUnits != $item->CaseUnits;
                                    @endphp
                                    <tr class="hover:bg-orange-50">
                                        <td class="px-2 py-2">
                                            @php
                                                $tempProduct = (object)[
                                                    'barcode' => $item->Barcode,
                                                    'supplier' => (object)['SupplierID' => $supplierId],
                                                ];
                                            @endphp
                                            <x-product-image
                                                :product="$tempProduct"
                                                :supplier-service="$supplierService"
                                                size="sm"
                                                :hover="true" />
                                        </td>
                                        <td class="px-3 py-2">
                                            @if($item->productID)
                                                <a href="{{ route('products.edit', $item->productID) }}" target="_blank" class="text-indigo-600 hover:text-indigo-900 font-medium">
                                                    {{ $item->prodName }}
                                                </a>
                                            @else
                                                <span class="font-medium text-gray-900">{{ $item->prodName }}</span>
                                            @endif
                                            <span class="text-xs text-gray-500 block">{{ $item->supCode }}</span>
                                        </td>
                                        <td class="px-3 py-2 text-center {{ $hasCaseUnitChange ? 'bg-orange-100' : '' }}">
                                            {{ $item->invoiceCaseUnits ?? 1 }}
                                        </td>
                                        <td class="px-3 py-2 text-center {{ $hasCaseUnitChange ? 'bg-orange-100' : '' }}">
                                            {{ $item->CaseUnits ?? '-' }}
                                        </td>
                                        <td class="px-3 py-2 text-center text-gray-500">{{ floatval($item->UNITS ?? 0) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            <!-- Pending Section -->
            <div class="mb-4" x-show="filter === 'all'">
                <button @click="sectionsOpen.pending = !sectionsOpen.pending"
                        class="w-full flex justify-between items-center p-4 bg-gray-100 hover:bg-gray-200 rounded-t-lg transition-colors"
                        :class="sectionsOpen.pending ? 'rounded-t-lg' : 'rounded-lg'">
                    <span class="font-medium text-gray-700">
                        <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Pending - Not Yet Scanned ({{ $pendingItems->count() }})
                    </span>
                    <svg :class="sectionsOpen.pending ? 'rotate-180' : ''" class="w-5 h-5 text-gray-500 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="sectionsOpen.pending" x-collapse class="bg-white border border-gray-200 border-t-0 rounded-b-lg overflow-hidden">
                    @if($pendingItems->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-2 py-2 w-12"></th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Barcode</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Expected</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase w-8"></th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Delivered</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Value</th>
                                        <template x-if="showDetails">
                                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">VAT</th>
                                        </template>
                                        <template x-if="showDetails">
                                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Cost</th>
                                        </template>
                                        <template x-if="showDetails">
                                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Sell</th>
                                        </template>
                                        <template x-if="showDetails">
                                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Margin</th>
                                        </template>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Stock</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($pendingItems as $item)
                                        @php
                                            $cost = $item->cost ?? 0;
                                            $vat = ($item->RATE ?? 0) * 100;
                                            $sell = ($item->PRICESELL ?? 0) * (1 + ($item->RATE ?? 0));
                                            $profit = $isUdea ? (($item->PRICESELL ?? 0) - ($cost * 1.15)) : (($item->PRICESELL ?? 0) - $cost);
                                            $margin = ($item->PRICESELL ?? 0) > 0 ? ($profit / $item->PRICESELL) * 100 : 0;
                                            $caseUnits = $item->invoiceCaseUnits ?? 1;
                                            $myOrder = $item->myOrder ?? 0;
                                            // If myOrder has decimal, it's weight-based - don't multiply by caseUnits and don't round
                                            $isWeightBased = fmod($myOrder, 1) != 0.0;
                                            $unitsDelivered = $isWeightBased ? $myOrder : $caseUnits * $myOrder;
                                            $value = $cost * $unitsDelivered;
                                        @endphp
                                        <tr class="hover:bg-gray-50"
                                            x-data="{ editing: false, qty: null, originalQty: null, saving: false, canEdit: {{ $isCompleted ? 'false' : 'true' }} }">
                                            <td class="px-2 py-2">
                                                @php
                                                    $tempProduct = (object)[
                                                        'barcode' => $item->Barcode,
                                                        'supplier' => (object)['SupplierID' => $supplierId],
                                                    ];
                                                @endphp
                                                <x-product-image
                                                    :product="$tempProduct"
                                                    :supplier-service="$supplierService"
                                                    size="sm"
                                                    :hover="true" />
                                            </td>
                                            <td class="px-3 py-2">
                                                @if($item->productID)
                                                    <a href="{{ route('products.edit', $item->productID) }}" target="_blank" class="text-indigo-600 hover:text-indigo-900 font-medium">
                                                        {{ $item->prodName }}
                                                    </a>
                                                @else
                                                    <span class="font-medium text-gray-900">{{ $item->prodName }}</span>
                                                @endif
                                                <span class="text-xs text-gray-500 block">{{ $item->supCode }}</span>
                                            </td>
                                            <td class="px-3 py-2 text-sm text-gray-600 font-mono">{{ $item->Barcode }}</td>
                                            <td class="px-3 py-2 text-center font-medium">
                                                @if($isWeightBased)
                                                    {{ number_format($unitsDelivered, 3) }}
                                                    <span class="text-xs text-purple-600 block">kg</span>
                                                @else
                                                    {{ $unitsDelivered }}
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-center">
                                                <button x-show="canEdit"
                                                        @click="qty = {{ $unitsDelivered }}; editing = true; $nextTick(() => $refs.qtyInput?.focus())"
                                                        class="text-gray-400 hover:text-blue-600 transition-colors"
                                                        title="Copy expected to delivered">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                                    </svg>
                                                </button>
                                            </td>
                                            <td class="px-3 py-2 text-center">
                                                <template x-if="!editing">
                                                    <span @click="canEdit && (editing = true, $nextTick(() => $refs.qtyInput?.select()))"
                                                          :class="canEdit ? 'cursor-pointer hover:bg-blue-100' : ''"
                                                          class="px-2 py-1 rounded inline-flex items-center gap-1 text-gray-400"
                                                          :title="canEdit ? 'Click to enter delivered quantity' : ''">
                                                        <span x-text="qty !== null ? qty : '-'"></span>
                                                        <svg x-show="canEdit" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                                        </svg>
                                                    </span>
                                                </template>
                                                <template x-if="editing">
                                                    <form @submit.prevent="saving = true; window.deliveryMatchInstance.saveScannedQty('{{ $item->Barcode }}', qty || 0, (newQty) => { qty = newQty; originalQty = newQty; editing = false; saving = false; location.reload(); }).catch(() => saving = false)"
                                                          class="flex items-center justify-center gap-1">
                                                        <input type="number" x-model="qty" x-ref="qtyInput" min="0" step="0.001"
                                                               @keydown.escape="qty = originalQty; editing = false"
                                                               class="w-20 text-center border border-gray-300 rounded px-1 py-0.5 text-sm focus:ring-blue-500 focus:border-blue-500">
                                                        <button type="submit" :disabled="saving" class="text-green-600 hover:text-green-800 disabled:opacity-50">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                            </svg>
                                                        </button>
                                                        <button type="button" @click="qty = originalQty; editing = false" class="text-gray-400 hover:text-gray-600">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                            </svg>
                                                        </button>
                                                    </form>
                                                </template>
                                            </td>
                                            <td class="px-3 py-2 text-right text-gray-500">&euro;{{ number_format($value, 2) }}</td>
                                            <template x-if="showDetails">
                                                <td class="px-3 py-2 text-center text-gray-500">{{ number_format($vat, 0) }}%</td>
                                            </template>
                                            <template x-if="showDetails">
                                                <td class="px-3 py-2 text-right text-gray-500">&euro;{{ number_format($cost, 2) }}</td>
                                            </template>
                                            <template x-if="showDetails">
                                                <td class="px-3 py-2 text-right text-gray-500">&euro;{{ number_format($sell, 2) }}</td>
                                            </template>
                                            <template x-if="showDetails">
                                                <td class="px-3 py-2 text-center text-gray-500">{{ number_format($margin, 0) }}%</td>
                                            </template>
                                            <td class="px-3 py-2 text-center text-gray-500">{{ floatval($item->UNITS ?? 0) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="p-4 text-gray-500">All items have been scanned.</p>
                    @endif
                </div>
            </div>

            <!-- Extra Items Section (Scanned but NOT on Invoice) -->
            <div class="mb-4" x-show="filter === 'all' || filter === 'problems'">
                <button @click="sectionsOpen.extra = !sectionsOpen.extra"
                        class="w-full flex justify-between items-center p-4 bg-orange-100 hover:bg-orange-200 rounded-t-lg transition-colors"
                        :class="sectionsOpen.extra ? 'rounded-t-lg' : 'rounded-lg'">
                    <span class="font-medium text-orange-800">
                        <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Extra Items - Scanned but NOT on Invoice ({{ count($scannedNotOnInvoice) }})
                    </span>
                    <svg :class="sectionsOpen.extra ? 'rotate-180' : ''" class="w-5 h-5 text-orange-600 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="sectionsOpen.extra" x-collapse class="bg-white border border-orange-200 border-t-0 rounded-b-lg overflow-hidden">
                    @if(count($scannedNotOnInvoice) > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-orange-50">
                                    <tr>
                                        <th class="px-2 py-2 w-12"></th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Barcode</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Case Units</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Scanned Qty</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Stock</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Sell Price</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">VAT</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($scannedNotOnInvoice as $item)
                                        <tr class="bg-orange-50">
                                            <td class="px-2 py-2">
                                                @php
                                                    $tempProduct = (object)[
                                                        'barcode' => $item->Barcode,
                                                        'supplier' => (object)['SupplierID' => $supplierId],
                                                    ];
                                                @endphp
                                                <x-product-image
                                                    :product="$tempProduct"
                                                    :supplier-service="$supplierService"
                                                    size="sm"
                                                    :hover="true" />
                                            </td>
                                            <td class="px-3 py-2">
                                                <span class="font-medium text-gray-900">{{ $item->NAME ?? 'Unknown Product' }}</span>
                                                @if($item->SupplierCode)
                                                    <span class="text-xs text-gray-500 block">{{ $item->SupplierCode }}</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2">
                                                @if($item->productID)
                                                    <a href="{{ route('products.edit', $item->productID) }}" target="_blank" class="text-indigo-600 hover:text-indigo-900 text-sm">
                                                        {{ $item->Barcode }}
                                                    </a>
                                                @else
                                                    <span class="text-gray-500">-</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-center text-gray-600">{{ $item->CaseUnits ?? '-' }}</td>
                                            <td class="px-3 py-2 text-center font-medium text-orange-600"
                                                x-data="{ editing: false, qty: {{ $item->scanned ?? 0 }}, originalQty: {{ $item->scanned ?? 0 }}, saving: false, canEdit: {{ $isCompleted ? 'false' : 'true' }} }">
                                                <template x-if="!editing">
                                                    <span @click="canEdit && (editing = true, $nextTick(() => $refs.qtyInput.select()))"
                                                          :class="canEdit ? 'cursor-pointer hover:bg-orange-100' : ''"
                                                          class="px-2 py-1 rounded inline-flex items-center gap-1"
                                                          :title="canEdit ? 'Click to edit' : ''">
                                                        <span x-text="qty"></span>
                                                        <svg x-show="canEdit" class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                                        </svg>
                                                    </span>
                                                </template>
                                                <template x-if="editing">
                                                    <form @submit.prevent="saving = true; window.deliveryMatchInstance.saveScannedQty('{{ $item->Barcode }}', qty, (newQty) => { originalQty = newQty; editing = false; saving = false; }).catch(() => saving = false)"
                                                          class="flex items-center justify-center gap-1">
                                                        <input type="number" x-model="qty" x-ref="qtyInput" min="0" step="0.001"
                                                               @keydown.escape="qty = originalQty; editing = false"
                                                               class="w-16 text-center border border-gray-300 rounded px-1 py-0.5 text-sm focus:ring-blue-500 focus:border-blue-500">
                                                        <button type="submit" :disabled="saving" class="text-green-600 hover:text-green-800 disabled:opacity-50">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                            </svg>
                                                        </button>
                                                        <button type="button" @click="qty = originalQty; editing = false" class="text-gray-400 hover:text-gray-600">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                            </svg>
                                                        </button>
                                                    </form>
                                                </template>
                                            </td>
                                            <td class="px-3 py-2 text-center text-gray-500">{{ floatval($item->UNITS ?? 0) }}</td>
                                            <td class="px-3 py-2 text-right text-gray-500">
                                                @if($item->PRICESELL)
                                                    &euro;{{ number_format($item->PRICESELL * (1 + ($item->RATE ?? 0)), 2) }}
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-center text-gray-500">
                                                {{ number_format(($item->RATE ?? 0) * 100, 0) }}%
                                            </td>
                                            <td class="px-3 py-2 text-center">
                                                <button class="text-xs px-2 py-1 bg-green-100 text-green-700 rounded hover:bg-green-200 transition-colors">Verify</button>
                                                <button class="text-xs px-2 py-1 bg-yellow-100 text-yellow-700 rounded hover:bg-yellow-200 ml-1 transition-colors">Flag</button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="p-4 text-gray-500">No extra items scanned.</p>
                    @endif
                </div>
            </div>

            <!-- Missing Items Section (On Invoice but NOT Scanned) -->
            <div class="mb-4" x-show="filter === 'all' || filter === 'problems'">
                <button @click="sectionsOpen.missing = !sectionsOpen.missing"
                        class="w-full flex justify-between items-center p-4 bg-red-100 hover:bg-red-200 rounded-t-lg transition-colors"
                        :class="sectionsOpen.missing ? 'rounded-t-lg' : 'rounded-lg'">
                    <span class="font-medium text-red-800">
                        <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/>
                        </svg>
                        Missing Items - On Invoice but NOT Scanned ({{ count($onInvoiceNotScanned) }})
                    </span>
                    <svg :class="sectionsOpen.missing ? 'rotate-180' : ''" class="w-5 h-5 text-red-600 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="sectionsOpen.missing" x-collapse class="bg-white border border-red-200 border-t-0 rounded-b-lg overflow-hidden">
                    @if(count($onInvoiceNotScanned) > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-red-50">
                                    <tr>
                                        <th class="px-2 py-2 w-12"></th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Supplier Code</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Cases</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Units/Case</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Total Units</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Value</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($onInvoiceNotScanned as $item)
                                        @php
                                            $caseUnits = $item->caseUnits ?? 1;
                                            $myOrder = $item->myOrder ?? 0;
                                            // If myOrder has decimal, it's weight-based - don't multiply by caseUnits
                                            $isWeightBased = fmod($myOrder, 1) != 0.0;
                                            $totalUnits = $isWeightBased ? $myOrder : $caseUnits * $myOrder;
                                            $value = ($item->cost ?? 0) * $totalUnits;
                                        @endphp
                                        <tr class="bg-red-50">
                                            <td class="px-2 py-2">
                                                {{-- Missing items only have supplier code, no barcode - show fallback --}}
                                                <x-product-image
                                                    :product="null"
                                                    size="sm" />
                                            </td>
                                            <td class="px-3 py-2 font-medium text-gray-900">{{ $item->prodName }}</td>
                                            <td class="px-3 py-2 text-gray-500">{{ $item->supCode }}</td>
                                            <td class="px-3 py-2 text-center">
                                                @if($isWeightBased)
                                                    {{ number_format($myOrder, 3) }} <span class="text-xs text-purple-600">kg</span>
                                                @else
                                                    {{ $myOrder }}
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-center">{{ $isWeightBased ? '-' : $caseUnits }}</td>
                                            <td class="px-3 py-2 text-center font-medium text-red-600">
                                                @if($isWeightBased)
                                                    {{ number_format($totalUnits, 3) }} <span class="text-xs text-purple-600">kg</span>
                                                @else
                                                    {{ $totalUnits }}
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-right font-medium text-red-600">&euro;{{ number_format($value, 2) }}</td>
                                            <td class="px-3 py-2 text-center">
                                                <button class="text-xs px-2 py-1 bg-green-100 text-green-700 rounded hover:bg-green-200 transition-colors">Verify</button>
                                                <button class="text-xs px-2 py-1 bg-yellow-100 text-yellow-700 rounded hover:bg-yellow-200 ml-1 transition-colors">Flag</button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="p-4 text-gray-500">All invoice items have been scanned.</p>
                    @endif
                </div>
            </div>

            <!-- Legend -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4">
                    <h4 class="text-sm font-medium text-gray-700 mb-3">Legend</h4>
                    <div class="flex flex-wrap gap-4 text-sm">
                        <div class="flex items-center gap-2">
                            <span class="inline-block w-4 h-4 bg-red-100 border border-red-300 rounded"></span>
                            <span>Critical - Quantity mismatch</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="inline-block w-4 h-4 bg-yellow-100 border border-yellow-300 rounded"></span>
                            <span>Warning - Margin or case unit issue</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="inline-block w-4 h-4 bg-green-100 border border-green-300 rounded"></span>
                            <span>Verified - Quantities match</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="inline-block w-4 h-4 bg-orange-100 border border-orange-300 rounded"></span>
                            <span>Extra - Scanned but not on invoice</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="inline-block w-4 h-4 bg-gray-100 border border-gray-300 rounded"></span>
                            <span>Pending - Not yet scanned</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Global reference to store the Alpine component instance
        window.deliveryMatchInstance = null;

        function deliveryMatch() {
            return {
                filter: 'all',
                showDetails: false,
                isCompleted: {{ $isCompleted ? 'true' : 'false' }},
                sectionsOpen: {
                    critical: true,
                    warnings: true,
                    verified: false,
                    pending: true,
                    extra: true,
                    missing: true,
                    oos: true
                },
                financials: @js($financials),
                deliveryId: '{{ $deliveryId }}',
                supplierID: '{{ $supplierId }}',
                init() {
                    // Store reference to this instance for child components
                    window.deliveryMatchInstance = this;
                },
                async saveScannedQty(barcode, newQty, onSuccess) {
                    try {
                        const response = await fetch('{{ route('delivery-legacy.update-quantity') }}', {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                delID: this.deliveryId,
                                barcode: barcode,
                                quantity: newQty,
                                supplierID: this.supplierID
                            })
                        });
                        const data = await response.json();
                        if (data.success) {
                            this.financials = data.financials;
                            if (onSuccess) onSuccess(data.quantity);
                        }
                        return data;
                    } catch (error) {
                        console.error('Error saving scanned qty:', error);
                        return { success: false };
                    }
                },
                async saveCaseUnits(barcode, newCaseUnits, onSuccess) {
                    try {
                        const response = await fetch('{{ route('delivery-legacy.update-case-units') }}', {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                barcode: barcode,
                                caseUnits: newCaseUnits,
                                supplierID: this.supplierID
                            })
                        });
                        const data = await response.json();
                        if (data.success) {
                            if (onSuccess) onSuccess(data.caseUnits);
                            // Reload page to recalculate expected quantities
                            location.reload();
                        }
                        return data;
                    } catch (error) {
                        console.error('Error saving case units:', error);
                        return { success: false };
                    }
                }
            };
        }
    </script>
</x-admin-layout>
