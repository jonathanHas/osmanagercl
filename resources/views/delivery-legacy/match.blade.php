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
            <a href="{{ route('delivery-legacy.index') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                Back to Selection
            </a>
        </div>
    </x-slot>

    <div class="py-6" x-data="{
        filter: 'all',
        showDetails: false,
        sectionsOpen: {
            critical: true,
            warnings: true,
            verified: false,
            pending: true,
            extra: true,
            missing: true
        }
    }">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            @if($isUdea)
                <div class="mb-4 bg-yellow-100 border border-yellow-400 text-yellow-800 px-4 py-3 rounded">
                    <strong>Note:</strong> Profit and Margin adjusted for UDEA delivery charge (15%)
                </div>
            @endif

            <!-- Financial Dashboard -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
                <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
                    <div class="text-xs text-gray-500 uppercase tracking-wide">Invoice Total</div>
                    <div class="text-xl font-bold text-gray-900">&euro;{{ number_format($financials['invoiceTotal'], 2) }}</div>
                </div>
                <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
                    <div class="text-xs text-gray-500 uppercase tracking-wide">Scanned Total</div>
                    <div class="text-xl font-bold text-gray-900">&euro;{{ number_format($financials['scannedTotal'], 2) }}</div>
                </div>
                <div class="bg-white rounded-lg shadow p-4 border-l-4 {{ $financials['discrepancy'] > 0 ? 'border-red-500' : 'border-gray-300' }}">
                    <div class="text-xs text-gray-500 uppercase tracking-wide">Discrepancy</div>
                    <div class="text-xl font-bold {{ $financials['discrepancy'] > 0 ? 'text-red-600' : 'text-gray-900' }}">&euro;{{ number_format($financials['discrepancy'], 2) }}</div>
                </div>
                <div class="bg-white rounded-lg shadow p-4 border-l-4 {{ $financials['missingValue'] > 0 ? 'border-red-500' : 'border-gray-300' }}">
                    <div class="text-xs text-gray-500 uppercase tracking-wide">Missing Value</div>
                    <div class="text-xl font-bold {{ $financials['missingValue'] > 0 ? 'text-red-600' : 'text-gray-900' }}">&euro;{{ number_format($financials['missingValue'], 2) }}</div>
                </div>
                <div class="bg-white rounded-lg shadow p-4 border-l-4 {{ $financials['extraValue'] > 0 ? 'border-orange-500' : 'border-gray-300' }}">
                    <div class="text-xs text-gray-500 uppercase tracking-wide">Extra Items Value</div>
                    <div class="text-xl font-bold {{ $financials['extraValue'] > 0 ? 'text-orange-600' : 'text-gray-900' }}">&euro;{{ number_format($financials['extraValue'], 2) }}</div>
                </div>
                <div class="bg-white rounded-lg shadow p-4 border-l-4 {{ $financials['marginAlerts'] > 0 ? 'border-yellow-500' : 'border-gray-300' }}">
                    <div class="text-xs text-gray-500 uppercase tracking-wide">Margin Alerts</div>
                    <div class="text-xl font-bold {{ $financials['marginAlerts'] > 0 ? 'text-yellow-600' : 'text-gray-900' }}">{{ $financials['marginAlerts'] }}</div>
                </div>
            </div>

            <!-- Progress Bar -->
            <div class="bg-white rounded-lg shadow p-4 mb-6">
                <div class="flex justify-between text-sm mb-2">
                    <span class="font-medium text-gray-700">Verification Progress</span>
                    <span class="text-gray-600">
                        <span class="text-green-600 font-medium">{{ $financials['verifiedCount'] }}</span> verified
                        <span class="text-gray-400 mx-1">|</span>
                        <span class="text-red-600 font-medium">{{ $financials['mismatchCount'] }}</span> mismatched
                        <span class="text-gray-400 mx-1">|</span>
                        <span class="text-gray-500 font-medium">{{ $financials['pendingCount'] }}</span> pending
                        <span class="text-gray-400 mx-1">|</span>
                        {{ $financials['totalItems'] }} total
                    </span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                    @php
                        $verifiedPct = $financials['totalItems'] > 0 ? ($financials['verifiedCount'] / $financials['totalItems']) * 100 : 0;
                        $mismatchPct = $financials['totalItems'] > 0 ? ($financials['mismatchCount'] / $financials['totalItems']) * 100 : 0;
                    @endphp
                    <div class="h-3 flex">
                        <div class="bg-green-500 h-3" style="width: {{ $verifiedPct }}%"></div>
                        <div class="bg-red-500 h-3" style="width: {{ $mismatchPct }}%"></div>
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
                    $unitsDelivered = (fmod($myOrder, 1) == 0.0) ? $caseUnits * $myOrder : round($caseUnits * $myOrder);
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
                    $unitsDelivered = (fmod($myOrder, 1) == 0.0) ? $caseUnits * $myOrder : round($caseUnits * $myOrder);
                    $isNotCritical = $item->scanned === null || floatval($item->scanned) == $unitsDelivered;
                    return $isNotCritical && ($hasMarginIssue || $hasCaseUnitChange);
                });

                $verifiedItems = collect($matchedItems)->filter(function($item) {
                    $caseUnits = $item->invoiceCaseUnits ?? 1;
                    $myOrder = $item->myOrder ?? 0;
                    $unitsDelivered = (fmod($myOrder, 1) == 0.0) ? $caseUnits * $myOrder : round($caseUnits * $myOrder);
                    return $item->scanned !== null && floatval($item->scanned) == $unitsDelivered;
                });

                $pendingItems = collect($matchedItems)->filter(fn($item) => $item->scanned === null);
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
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Expected</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Scanned</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Diff</th>
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
                                    @foreach($criticalItems as $item)
                                        @php
                                            $cost = $item->cost ?? 0;
                                            $vat = ($item->RATE ?? 0) * 100;
                                            $sell = ($item->PRICESELL ?? 0) * (1 + ($item->RATE ?? 0));
                                            $profit = $isUdea ? (($item->PRICESELL ?? 0) - ($cost * 1.15)) : (($item->PRICESELL ?? 0) - $cost);
                                            $margin = ($item->PRICESELL ?? 0) > 0 ? ($profit / $item->PRICESELL) * 100 : 0;
                                            $caseUnits = $item->invoiceCaseUnits ?? 1;
                                            $myOrder = $item->myOrder ?? 0;
                                            $unitsDelivered = (fmod($myOrder, 1) == 0.0) ? $caseUnits * $myOrder : round($caseUnits * $myOrder);
                                            $diff = ($item->scanned ?? 0) - $unitsDelivered;
                                            $impact = $diff * $cost;
                                        @endphp
                                        <tr class="bg-red-50">
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
                                            <td class="px-3 py-2 text-center font-medium text-blue-600">{{ $item->scanned }}</td>
                                            <td class="px-3 py-2 text-center font-bold {{ $diff > 0 ? 'text-green-600' : 'text-red-600' }}">
                                                {{ $diff > 0 ? '+' : '' }}{{ $diff }}
                                            </td>
                                            <td class="px-3 py-2 text-right font-medium {{ $impact > 0 ? 'text-green-600' : 'text-red-600' }}">
                                                {{ $impact > 0 ? '+' : '' }}&euro;{{ number_format($impact, 2) }}
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
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Expected</th>
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
                                            $unitsDelivered = (fmod($myOrder, 1) == 0.0) ? $caseUnits * $myOrder : round($caseUnits * $myOrder);
                                            $hasMarginIssue = $profit < 0 || $margin < 15;
                                            $hasCaseUnitChange = $item->invoiceCaseUnits != $item->CaseUnits;
                                            $issues = [];
                                            if ($hasMarginIssue) $issues[] = 'Low margin';
                                            if ($hasCaseUnitChange) $issues[] = 'Case: ' . ($item->invoiceCaseUnits ?? 1) . ' → ' . ($item->CaseUnits ?? '?');
                                        @endphp
                                        <tr class="bg-yellow-50">
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
                                            <td class="px-3 py-2 text-center font-medium {{ $item->scanned !== null ? 'text-blue-600' : 'text-gray-400' }}">
                                                {{ $item->scanned ?? '-' }}
                                            </td>
                                            <td class="px-3 py-2 text-center">
                                                @foreach($issues as $issue)
                                                    <span class="inline-block text-xs px-2 py-0.5 bg-yellow-200 text-yellow-800 rounded mb-0.5">{{ $issue }}</span>
                                                @endforeach
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
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Expected</th>
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
                                            $unitsDelivered = (fmod($myOrder, 1) == 0.0) ? $caseUnits * $myOrder : round($caseUnits * $myOrder);
                                            $value = $cost * $unitsDelivered;
                                        @endphp
                                        <tr class="hover:bg-green-50">
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
                                            <td class="px-3 py-2 text-center font-medium text-green-600">{{ $item->scanned }}</td>
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
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Expected</th>
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
                                    @foreach($pendingItems as $item)
                                        @php
                                            $cost = $item->cost ?? 0;
                                            $vat = ($item->RATE ?? 0) * 100;
                                            $sell = ($item->PRICESELL ?? 0) * (1 + ($item->RATE ?? 0));
                                            $profit = $isUdea ? (($item->PRICESELL ?? 0) - ($cost * 1.15)) : (($item->PRICESELL ?? 0) - $cost);
                                            $margin = ($item->PRICESELL ?? 0) > 0 ? ($profit / $item->PRICESELL) * 100 : 0;
                                            $caseUnits = $item->invoiceCaseUnits ?? 1;
                                            $myOrder = $item->myOrder ?? 0;
                                            $unitsDelivered = (fmod($myOrder, 1) == 0.0) ? $caseUnits * $myOrder : round($caseUnits * $myOrder);
                                            $value = $cost * $unitsDelivered;
                                        @endphp
                                        <tr class="hover:bg-gray-50">
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
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Barcode</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Scanned Qty</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Sell Price</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">VAT</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($scannedNotOnInvoice as $item)
                                        <tr class="bg-orange-50">
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
                                            <td class="px-3 py-2 text-center font-medium text-orange-600">{{ $item->scanned }}</td>
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
                                            $totalUnits = round($caseUnits * $myOrder);
                                            $value = ($item->cost ?? 0) * $totalUnits;
                                        @endphp
                                        <tr class="bg-red-50">
                                            <td class="px-3 py-2 font-medium text-gray-900">{{ $item->prodName }}</td>
                                            <td class="px-3 py-2 text-gray-500">{{ $item->supCode }}</td>
                                            <td class="px-3 py-2 text-center">{{ $myOrder }}</td>
                                            <td class="px-3 py-2 text-center">{{ $caseUnits }}</td>
                                            <td class="px-3 py-2 text-center font-medium text-red-600">{{ $totalUnits }}</td>
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
</x-admin-layout>
