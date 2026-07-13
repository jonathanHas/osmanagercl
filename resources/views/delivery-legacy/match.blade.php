<x-admin-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-2">
            <div class="min-w-0">
                <h2 class="font-semibold text-lg sm:text-xl text-gray-800 leading-tight">
                    Delivery Verification
                </h2>
                <p class="text-xs sm:text-sm text-gray-600 mt-0.5">
                    <span class="font-medium">{{ $supplier->Supplier ?? 'Unknown' }}</span>
                    <span class="hidden sm:inline"> | Scan Session: <span class="font-medium">#{{ $deliveryId }}</span></span>
                </p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                @if(!$isCompleted)
                    <button type="button" onclick="document.dispatchEvent(new CustomEvent('open-scanner'))"
                            class="inline-flex items-center px-3 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 gap-1.5 touch-manipulation">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                        </svg>
                        <span class="hidden sm:inline">Scan Items</span>
                        <span class="sm:hidden">Scan</span>
                    </button>
                    <form method="POST" action="{{ route('delivery-legacy.complete') }}"
                          onsubmit="return confirm('This will update stock levels for all scanned items and mark this delivery as complete. This action cannot be undone. Continue?')">
                        @csrf
                        <input type="hidden" name="delID" value="{{ $deliveryId }}">
                        <input type="hidden" name="supplierID" value="{{ $supplierId }}">
                        <button type="submit"
                                class="inline-flex items-center px-3 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 gap-1.5 touch-manipulation">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span class="hidden sm:inline">Update Stock & Complete</span>
                            <span class="sm:hidden">Complete</span>
                        </button>
                    </form>
                @endif
                @if($isUdea)
                    <a href="{{ asset('downloads/deviation-report-template-2025.xlsx') }}" download
                       class="hidden sm:inline-flex items-center px-3 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 gap-1.5 touch-manipulation">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Deviation Report
                    </a>
                    <button type="button" onclick="generateDeviationReport()"
                            class="hidden sm:inline-flex items-center px-3 py-2 bg-purple-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-purple-700 gap-1.5 touch-manipulation">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Generate from Selected
                    </button>
                @endif
                @if($isIndependent)
                    <button type="button" onclick="generateGoodsReturnSheet()"
                            class="hidden sm:inline-flex items-center px-3 py-2 bg-purple-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-purple-700 gap-1.5 touch-manipulation">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Generate Returns Sheet
                    </button>
                @endif
                @if(($translatableCount ?? 0) > 0)
                    <button type="button" id="printTranslationsBtn" onclick="printTranslatedLabels()"
                            class="inline-flex items-center px-3 py-2 bg-teal-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-teal-700 gap-1.5 touch-manipulation disabled:opacity-50">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                        </svg>
                        <span class="hidden sm:inline">Print Translated Labels ({{ $translatableCount }})</span>
                        <span class="sm:hidden">Labels ({{ $translatableCount }})</span>
                    </button>
                @endif
                <a href="{{ route('delivery-legacy.index') }}"
                   class="inline-flex items-center px-3 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 touch-manipulation">
                    <span class="hidden sm:inline">Back to Selection</span>
                    <span class="sm:hidden">Back</span>
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-3 sm:py-6" x-data="deliveryMatch()" x-ref="deliveryMatchRoot"
         @open-scanner.document="openScanner()">

        {{-- Scanner Overlay --}}
        <div x-show="scannerOpen" x-cloak
             class="fixed inset-0 z-50 bg-gray-900 flex flex-col"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">

            {{-- Header --}}
            <div class="bg-gray-800 px-4 py-3 flex items-center justify-between flex-shrink-0">
                <div class="flex items-center gap-3">
                    <h3 class="text-white font-semibold text-lg">Delivery Scan</h3>
                    <span x-show="scanner.scanCount > 0"
                          class="bg-blue-600 text-white text-xs font-bold px-2 py-1 rounded-full"
                          x-text="scanner.scanCount + ' scanned'"></span>
                </div>
                <button @click="closeScanner()" class="text-gray-400 hover:text-white p-2 touch-manipulation">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Content --}}
            <div class="flex-1 overflow-y-auto px-4 py-3">
                <div class="max-w-lg mx-auto">

                    {{-- Step 1: Barcode Input (shown when no product identified yet) --}}
                    <div x-show="scanner.step === 'scan'">
                        <div class="flex gap-2 mb-3">
                            <input type="text"
                                   x-ref="scannerInput"
                                   x-model="scanner.barcode"
                                   @keydown.enter.prevent="lookupBarcode()"
                                   :inputmode="scanner.keyboardEnabled ? 'text' : 'none'"
                                   placeholder="Scan or type barcode..."
                                   class="flex-1 text-lg py-3 px-4 rounded-lg border-2 border-gray-600 bg-gray-800 text-white placeholder-gray-500 focus:border-blue-500 focus:ring-0">
                            <button @click="scanner.keyboardEnabled = !scanner.keyboardEnabled; $nextTick(() => $refs.scannerInput.focus())"
                                    :class="scanner.keyboardEnabled ? 'bg-blue-600 text-white' : 'bg-gray-700 text-gray-400'"
                                    class="px-3 py-3 rounded-lg touch-manipulation"
                                    title="Toggle keyboard">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/>
                                </svg>
                            </button>
                            <button @click="toggleScannerCamera()"
                                    :class="scanner.cameraActive ? 'bg-red-600 text-white' : 'bg-green-600 text-white'"
                                    class="px-3 py-3 rounded-lg touch-manipulation"
                                    :title="scanner.cameraActive ? 'Stop camera' : 'Start camera'">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                </svg>
                            </button>
                        </div>

                        {{-- Camera Viewport --}}
                        <div x-show="scanner.cameraVisible" class="mb-3">
                            <div id="delivery-scanner" class="rounded-lg overflow-hidden" style="min-height: 220px;"></div>
                            <p class="text-gray-400 text-sm text-center mt-1" x-text="scanner.cameraStatus"></p>
                        </div>

                        {{-- Prompt --}}
                        <div x-show="!scanner.cameraVisible" class="text-center py-8">
                            <svg class="w-16 h-16 text-gray-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                            </svg>
                            <p class="text-gray-400">Tap camera to start scanning<br>or type barcode above</p>
                        </div>
                    </div>

                    {{-- Step 2: Product identified — enter quantity --}}
                    <div x-show="scanner.step === 'quantity'">
                        {{-- Product info card --}}
                        <div class="rounded-lg p-4 mb-4 border-2"
                             :class="scanner.expectedQty !== null && scanner.currentScanned == scanner.expectedQty
                                 ? 'bg-green-900/30 border-green-500'
                                 : 'bg-gray-800 border-gray-700'">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-white font-semibold text-lg truncate mr-2"
                                      x-text="scanner.productInfo?.name || 'Unknown Product'"></span>
                                <button @click="resetScanner()" class="text-gray-400 hover:text-white p-1 touch-manipulation" title="Cancel">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                            <p class="text-gray-400 text-sm mb-3">
                                <span x-text="scanner.barcode"></span>
                                <template x-if="scanner.productInfo?.supplierCode">
                                    <span> | <span x-text="scanner.productInfo.supplierCode"></span></span>
                                </template>
                                <template x-if="scanner.productInfo?.categoryName">
                                    <span> | <span x-text="scanner.productInfo.categoryName"></span></span>
                                </template>
                                <template x-if="scanner.scanType === 'case'">
                                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-600 text-white">Case barcode &times; <span x-text="scanner.caseUnits"></span> units</span>
                                </template>
                            </p>
                            <div class="flex items-center justify-between text-sm">
                                <div class="flex flex-wrap gap-x-4 gap-y-1">
                                    <template x-if="scanner.expectedQty !== null">
                                        <span class="text-gray-300">Expected: <span class="text-white font-bold" x-text="scanner.expectedQty"></span></span>
                                    </template>
                                    <template x-if="scanner.currentScanned > 0">
                                        <span class="text-gray-300">Scanned: <span class="text-blue-400 font-bold" x-text="scanner.currentScanned"></span></span>
                                    </template>
                                </div>
                                <template x-if="scanner.productInfo?.currentStock !== null && scanner.productInfo?.currentStock !== undefined">
                                    <span class="text-gray-500 text-xs bg-gray-700/50 px-2 py-1 rounded">In stock: <span class="text-gray-300 font-medium" x-text="parseFloat(scanner.productInfo.currentStock || 0)"></span></span>
                                </template>
                            </div>
                            <template x-if="scanner.expectedQty !== null && scanner.currentScanned == scanner.expectedQty">
                                <p class="text-green-400 text-sm mt-2 font-medium flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    Already matches expected quantity
                                </p>
                            </template>
                        </div>

                        {{-- Quantity input + submit --}}
                        <div class="mb-4">
                            <label class="text-gray-400 text-sm mb-2 block">
                                <template x-if="scanner.scanType === 'case'">
                                    <span>Cases to add (<span x-text="scanner.caseUnits"></span> units each):</span>
                                </template>
                                <template x-if="scanner.scanType !== 'case'">
                                    <span>Quantity to add:</span>
                                </template>
                            </label>
                            <div class="flex gap-3 items-center justify-center">
                                <button @click="adjustIncrement(-1)"
                                        class="w-16 h-16 rounded-xl bg-gray-700 text-white text-3xl font-bold flex items-center justify-center touch-manipulation hover:bg-gray-600 active:bg-gray-500 flex-shrink-0">-</button>
                                <input type="number"
                                       x-ref="qtyInput"
                                       x-model.number="scanner.incrementQty"
                                       @keydown.enter.prevent="submitScan()"
                                       inputmode="numeric"
                                       min="1"
                                       class="w-24 text-center text-3xl font-bold py-3 rounded-lg border-2 border-gray-600 bg-gray-800 text-white focus:border-blue-500 focus:ring-0">
                                <button @click="adjustIncrement(1)"
                                        class="w-16 h-16 rounded-xl bg-gray-700 text-white text-3xl font-bold flex items-center justify-center touch-manipulation hover:bg-gray-600 active:bg-gray-500 flex-shrink-0">+</button>
                            </div>
                        </div>

                        <button @click="submitScan()"
                                :disabled="scanner.processing"
                                class="w-full py-4 rounded-lg text-white font-bold text-lg touch-manipulation transition-colors"
                                :class="scanner.processing ? 'bg-gray-600 cursor-wait' : 'bg-blue-600 hover:bg-blue-700 active:bg-blue-800'">
                            <span x-show="!scanner.processing">
                                <template x-if="scanner.scanType === 'case'">
                                    <span>Add <span x-text="scanner.incrementQty"></span> case<span x-show="scanner.incrementQty > 1">s</span> (<span x-text="scanner.incrementQty * scanner.caseUnits"></span> units)</span>
                                </template>
                                <template x-if="scanner.scanType !== 'case'">
                                    <span>Add <span x-text="scanner.incrementQty"></span> units</span>
                                </template>
                            </span>
                            <span x-show="scanner.processing" class="flex items-center justify-center gap-2">
                                <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                Saving...
                            </span>
                        </button>
                    </div>

                    {{-- Assign outer barcode step --}}
                    <div x-show="scanner.step === 'assign-outer'" class="mb-4">
                        <div class="bg-purple-900/30 border-2 border-purple-500 rounded-lg p-4 mb-4">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="text-purple-300 font-semibold">Assign Outer Barcode</h4>
                                <button @click="cancelOuterAssign()" class="text-gray-400 hover:text-white p-1 touch-manipulation" title="Cancel">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                            <p class="text-gray-300 text-sm mb-1">Outer barcode: <span class="text-white font-mono font-bold" x-text="scanner.outerBarcodeToAssign"></span></p>
                            <p class="text-gray-400 text-sm mb-3">Scan the <span class="text-white font-semibold">unit barcode</span> on the product to link it.</p>

                            {{-- Camera area for scanning unit barcode --}}
                            <div x-show="scanner.cameraVisible" class="mb-3">
                                <div id="outer-assign-scanner" class="w-full rounded-lg overflow-hidden" style="min-height: 200px;"></div>
                            </div>
                            <div x-show="!scanner.cameraVisible" class="text-center py-6 bg-gray-800/50 rounded-lg mb-3">
                                <svg class="w-12 h-12 text-gray-600 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                                </svg>
                                <p class="text-gray-400 text-sm">Opening camera...</p>
                            </div>

                            {{-- Manual barcode input fallback --}}
                            <div class="flex gap-2">
                                <input type="text"
                                       x-ref="outerAssignInput"
                                       x-model="scanner.outerAssignBarcode"
                                       @keydown.enter.prevent="submitOuterAssign()"
                                       placeholder="Or type unit barcode..."
                                       inputmode="numeric"
                                       class="flex-1 px-3 py-2 rounded-lg border border-gray-600 bg-gray-800 text-white text-sm placeholder-gray-500 focus:border-purple-500 focus:ring-0">
                                <button @click="submitOuterAssign()"
                                        :disabled="!scanner.outerAssignBarcode || scanner.processing"
                                        class="px-4 py-2 rounded-lg text-white font-semibold text-sm touch-manipulation transition-colors"
                                        :class="scanner.processing ? 'bg-gray-600 cursor-wait' : 'bg-purple-600 hover:bg-purple-700'">
                                    Link
                                </button>
                            </div>

                            {{-- Status messages --}}
                            <template x-if="scanner.outerAssignStatus">
                                <p class="mt-2 text-sm" :class="scanner.outerAssignError ? 'text-red-400' : 'text-green-400'" x-text="scanner.outerAssignStatus"></p>
                            </template>
                        </div>
                    </div>

                    {{-- Last submitted result (shown after submit, above history) --}}
                    <div x-show="scanner.lastResult && scanner.step === 'scan'" class="mb-4">
                        <div class="rounded-lg border-2 p-4"
                             :class="{
                                 'border-green-500 bg-green-900/30': scanner.lastResult?.matchStatus === 'verified',
                                 'border-yellow-500 bg-yellow-900/30': scanner.lastResult?.matchStatus === 'partial',
                                 'border-orange-500 bg-orange-900/30': scanner.lastResult?.matchStatus === 'over' || scanner.lastResult?.matchStatus === 'extra',
                                 'border-red-500 bg-red-900/30': scanner.lastResult?.matchStatus === 'unknown',
                                 'border-red-600 bg-red-900/40': scanner.lastResult?.error
                             }">
                            <template x-if="scanner.lastResult?.error">
                                <div>
                                    <p class="text-red-400 text-sm" x-text="scanner.lastResult.error"></p>
                                    <template x-if="scanner.lastResult?.unknownBarcode">
                                        <button @click="startOuterAssign(scanner.lastResult.unknownBarcode)"
                                                class="mt-2 text-purple-400 hover:text-purple-300 text-sm underline underline-offset-2">
                                            Assign as outer barcode &rarr;
                                        </button>
                                    </template>
                                </div>
                            </template>
                            <template x-if="!scanner.lastResult?.error">
                                <div>
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-white font-semibold text-lg truncate mr-2" x-text="scanner.lastResult?.product?.name || 'Unknown'"></span>
                                        <span class="px-2 py-1 rounded text-xs font-bold uppercase flex-shrink-0"
                                              :class="{
                                                  'bg-green-600 text-white': scanner.lastResult?.matchStatus === 'verified',
                                                  'bg-yellow-600 text-white': scanner.lastResult?.matchStatus === 'partial',
                                                  'bg-orange-600 text-white': scanner.lastResult?.matchStatus === 'over' || scanner.lastResult?.matchStatus === 'extra',
                                                  'bg-red-600 text-white': scanner.lastResult?.matchStatus === 'unknown'
                                              }"
                                              x-text="{verified:'Match',partial:'Short',over:'Over',extra:'Extra',unknown:'Unknown'}[scanner.lastResult?.matchStatus] || scanner.lastResult?.matchStatus"></span>
                                    </div>
                                    <template x-if="scanner.lastResult?.scanType === 'case'">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-600 text-white mb-2">Case scan &times; <span x-text="scanner.lastResult?.caseUnits"></span> units (<span x-text="scanner.lastResult?.casesAdded"></span> case<span x-show="scanner.lastResult?.casesAdded > 1">s</span>)</span>
                                    </template>
                                    {{-- Match comparison --}}
                                    <div class="flex items-center gap-3 text-sm">
                                        <span class="text-gray-400">Added: <span class="text-white font-bold" x-text="scanner.lastResult?.addedQty"></span></span>
                                        <span class="text-gray-600">|</span>
                                        <span class="text-gray-400">Total: <span class="text-white font-bold text-lg" x-text="scanner.lastResult?.newQuantity"></span></span>
                                        <template x-if="scanner.lastResult?.expectedQty !== null">
                                            <span class="text-gray-400">
                                                / <span class="font-bold" :class="scanner.lastResult?.matchStatus === 'verified' ? 'text-green-400' : 'text-yellow-400'" x-text="scanner.lastResult?.expectedQty"></span> expected
                                            </span>
                                        </template>
                                    </div>
                                    <template x-if="scanner.lastResult?.matchStatus === 'verified'">
                                        <p class="text-green-400 text-sm mt-1 font-medium">Quantity matches invoice</p>
                                    </template>
                                    <template x-if="scanner.lastResult?.matchStatus === 'partial'">
                                        <p class="text-yellow-400 text-sm mt-1 font-medium" x-text="(scanner.lastResult?.expectedQty - scanner.lastResult?.newQuantity) + ' more needed'"></p>
                                    </template>
                                    <template x-if="scanner.lastResult?.matchStatus === 'over'">
                                        <p class="text-orange-400 text-sm mt-1 font-medium" x-text="(scanner.lastResult?.newQuantity - scanner.lastResult?.expectedQty) + ' over expected'"></p>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Scan History --}}
                    <div x-show="scanner.history.length > 0 && scanner.step === 'scan'">
                        <h4 class="text-gray-400 text-sm font-semibold mb-2">Recent Scans</h4>
                        <div class="space-y-1 max-h-48 overflow-y-auto">
                            <template x-for="(scan, index) in scanner.history" :key="index">
                                <div class="flex items-center justify-between bg-gray-800 rounded px-3 py-2 text-sm">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span class="w-2 h-2 rounded-full flex-shrink-0"
                                              :class="{
                                                  'bg-green-500': scan.matchStatus === 'verified',
                                                  'bg-yellow-500': scan.matchStatus === 'partial',
                                                  'bg-orange-500': scan.matchStatus === 'over' || scan.matchStatus === 'extra',
                                                  'bg-red-500': scan.matchStatus === 'unknown'
                                              }"></span>
                                        <span class="text-white truncate" x-text="scan.name || scan.barcode"></span>
                                        <template x-if="scan.scanType === 'case'">
                                            <span class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-purple-600/70 text-purple-200 flex-shrink-0">CASE</span>
                                        </template>
                                    </div>
                                    <div class="flex items-center gap-2 flex-shrink-0 ml-2">
                                        <span class="text-gray-400" x-text="'+' + scan.qty"></span>
                                        <span class="text-gray-500 text-xs" x-text="scan.time"></span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <div class="max-w-full mx-auto px-2 sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 p-4 bg-green-100 border border-green-300 rounded-lg text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            <!-- Compact Progress Bar (always visible) -->
            <div class="mb-3 bg-white rounded-lg shadow-sm border border-gray-200 px-3 py-2">
                <div class="flex items-center gap-3">
                    <div class="flex-1 min-w-0">
                        <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
                            <div class="h-2 flex">
                                <div class="bg-green-500 h-2 transition-all duration-300" :style="'width: ' + (financials.totalItems > 0 ? (financials.verifiedCount / financials.totalItems) * 100 : 0) + '%'"></div>
                                <div class="bg-red-500 h-2 transition-all duration-300" :style="'width: ' + (financials.totalItems > 0 ? (financials.mismatchCount / financials.totalItems) * 100 : 0) + '%'"></div>
                                <div class="bg-orange-400 h-2 transition-all duration-300" :style="'width: ' + (financials.totalItems > 0 ? (financials.oosCount / financials.totalItems) * 100 : 0) + '%'"></div>
                            </div>
                        </div>
                    </div>
                    <span class="text-xs text-gray-500 flex-shrink-0 whitespace-nowrap">
                        <span class="text-green-600 font-medium" x-text="financials.verifiedCount"></span>/<span x-text="financials.totalItems"></span>
                    </span>
                    <button @click="dashboardOpen = !dashboardOpen"
                            class="text-gray-400 hover:text-gray-600 flex-shrink-0 p-0.5 transition-colors"
                            :title="dashboardOpen ? 'Hide details' : 'Show details'">
                        <svg :class="dashboardOpen ? 'rotate-180' : ''" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                </div>
                <div x-show="dashboardOpen" x-collapse x-cloak class="mt-2 pt-2 border-t border-gray-100">
                    <div class="text-xs text-gray-600 flex flex-wrap gap-x-2 gap-y-0.5">
                        <span><span class="text-green-600 font-medium" x-text="financials.verifiedCount"></span> verified</span>
                        <span class="text-gray-300">|</span>
                        <span><span class="text-red-600 font-medium" x-text="financials.mismatchCount"></span> mismatch</span>
                        <span class="text-gray-300">|</span>
                        <span><span class="text-gray-500 font-medium" x-text="financials.pendingCount"></span> pending</span>
                        <template x-if="financials.oosCount > 0">
                            <span>
                                <span class="text-gray-300">|</span>
                                <span class="text-orange-600 font-medium" x-text="financials.oosCount"></span> OOS
                            </span>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Collapsible Dashboard Section -->
            <div x-show="dashboardOpen" x-collapse x-cloak>

            {{-- Synced Delivery Documents --}}
            @if($syncedDelivery && $syncedDelivery->documents->count() > 0)
                <div class="mb-4 bg-blue-50 border border-blue-200 rounded-lg p-3 sm:p-4">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-3 gap-1">
                        <h3 class="text-base sm:text-lg font-medium text-blue-900 flex items-center">
                            <svg class="w-5 h-5 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Invoice Documents
                        </h3>
                        <span class="text-xs sm:text-sm text-blue-600">
                            {{ $syncedDelivery->supplier->Supplier ?? 'Unknown' }} - {{ $syncedDelivery->delivery_date->format('d/m/Y') }}
                        </span>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        @foreach($syncedDelivery->documents as $document)
                            <div class="flex items-center bg-white rounded-lg px-3 py-2 shadow-sm border border-blue-100">
                                @if($document->isPdf())
                                    <svg class="w-5 h-5 text-red-500 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
                                    </svg>
                                @else
                                    <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
                                    </svg>
                                @endif
                                <span class="text-sm text-gray-700 mr-3">{{ $document->original_filename }}</span>
                                <a href="{{ $document->viewer_minimal_url }}"
                                   onclick="window.open(this.href, 'documentViewer', 'width=900,height=700,scrollbars=yes,resizable=yes'); return false;"
                                   class="text-blue-600 hover:text-blue-800 text-sm font-medium mr-2">
                                    View
                                </a>
                                <a href="{{ $document->download_url }}"
                                   class="text-gray-500 hover:text-gray-700 text-sm">
                                    Download
                                </a>
                            </div>
                        @endforeach
                    </div>
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
                    @if(auth()->user()->can('deliveries.manage'))
                        <form method="POST" action="{{ route('delivery-legacy.undo-complete') }}" class="mt-3 ml-7"
                              onsubmit="return confirm('This will REMOVE the stock that was added and reopen this delivery so you can keep scanning. Continue?')">
                            @csrf
                            <input type="hidden" name="delID" value="{{ $deliveryId }}">
                            <input type="hidden" name="supplierID" value="{{ $supplierId }}">
                            <button type="submit"
                                    class="inline-flex items-center px-3 py-2 bg-amber-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-amber-700 gap-1.5 touch-manipulation">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a4 4 0 014 4v2m-4-6l4-4m-4 4l4 4"/>
                                </svg>
                                Undo Complete &amp; Reopen
                            </button>
                        </form>
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
            <div class="grid grid-cols-3 sm:grid-cols-3 lg:grid-cols-6 gap-2 sm:gap-4 mb-4 sm:mb-6">
                <div class="bg-white rounded-lg shadow p-2 sm:p-4 border-l-4 border-blue-500">
                    <div class="text-[10px] sm:text-xs text-gray-500 uppercase tracking-wide">Invoice</div>
                    <div class="text-sm sm:text-xl font-bold text-gray-900">&euro;<span x-text="financials.invoiceTotal.toFixed(2)"></span></div>
                </div>
                <div class="bg-white rounded-lg shadow p-2 sm:p-4 border-l-4 border-green-500">
                    <div class="text-[10px] sm:text-xs text-gray-500 uppercase tracking-wide">Scanned</div>
                    <div class="text-sm sm:text-xl font-bold text-gray-900">&euro;<span x-text="financials.scannedTotal.toFixed(2)"></span></div>
                </div>
                <div class="bg-white rounded-lg shadow p-2 sm:p-4 border-l-4" :class="financials.discrepancy > 0 ? 'border-red-500' : 'border-gray-300'">
                    <div class="text-[10px] sm:text-xs text-gray-500 uppercase tracking-wide">Diff</div>
                    <div class="text-sm sm:text-xl font-bold" :class="financials.discrepancy > 0 ? 'text-red-600' : 'text-gray-900'">&euro;<span x-text="financials.discrepancy.toFixed(2)"></span></div>
                </div>
                <div class="bg-white rounded-lg shadow p-2 sm:p-4 border-l-4" :class="financials.missingValue > 0 ? 'border-red-500' : 'border-gray-300'">
                    <div class="text-[10px] sm:text-xs text-gray-500 uppercase tracking-wide">Missing</div>
                    <div class="text-sm sm:text-xl font-bold" :class="financials.missingValue > 0 ? 'text-red-600' : 'text-gray-900'">&euro;<span x-text="financials.missingValue.toFixed(2)"></span></div>
                </div>
                <div class="bg-white rounded-lg shadow p-2 sm:p-4 border-l-4" :class="financials.extraValue > 0 ? 'border-orange-500' : 'border-gray-300'">
                    <div class="text-[10px] sm:text-xs text-gray-500 uppercase tracking-wide">Extra</div>
                    <div class="text-sm sm:text-xl font-bold" :class="financials.extraValue > 0 ? 'text-orange-600' : 'text-gray-900'">&euro;<span x-text="financials.extraValue.toFixed(2)"></span></div>
                </div>
                <div class="bg-white rounded-lg shadow p-2 sm:p-4 border-l-4" :class="financials.marginAlerts > 0 ? 'border-yellow-500' : 'border-gray-300'">
                    <div class="text-[10px] sm:text-xs text-gray-500 uppercase tracking-wide">Margin</div>
                    <div class="text-sm sm:text-xl font-bold" :class="financials.marginAlerts > 0 ? 'text-yellow-600' : 'text-gray-900'" x-text="financials.marginAlerts"></div>
                </div>
            </div>

            <!-- Quick Filters -->
            <div class="bg-white rounded-lg shadow p-3 sm:p-4 mb-4 sm:mb-6">
                <div class="flex flex-col sm:flex-row sm:flex-wrap sm:items-center gap-2 sm:gap-4">
                    <div class="flex flex-wrap gap-2 items-center">
                        <span class="text-sm font-medium text-gray-700">Filter:</span>
                        <button @click="filter = 'all'"
                                :class="filter === 'all' ? 'bg-gray-800 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'"
                                class="px-3 py-1.5 rounded-md text-xs sm:text-sm font-medium transition-colors touch-manipulation">
                            All
                        </button>
                        <button @click="filter = 'problems'"
                                :class="filter === 'problems' ? 'bg-red-600 text-white' : 'bg-red-100 text-red-700 hover:bg-red-200'"
                                class="px-3 py-1.5 rounded-md text-xs sm:text-sm font-medium transition-colors touch-manipulation">
                            Problems ({{ $financials['mismatchCount'] + count($scannedNotOnInvoice) + count($onInvoiceNotScanned) }})
                        </button>
                        <button @click="filter = 'verified'"
                                :class="filter === 'verified' ? 'bg-green-600 text-white' : 'bg-green-100 text-green-700 hover:bg-green-200'"
                                class="px-3 py-1.5 rounded-md text-xs sm:text-sm font-medium transition-colors touch-manipulation">
                            Verified ({{ $financials['verifiedCount'] }})
                        </button>
                    </div>
                    <div class="sm:ml-auto flex items-center gap-3 sm:gap-4">
                        <div class="relative flex items-center gap-2">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" x-model="showCategories" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="text-xs sm:text-sm text-gray-700">Categories</span>
                            </label>
                            <div x-show="showCategories" x-cloak class="relative" @click.outside="categoryDropdownOpen = false">
                                <button @click="categoryDropdownOpen = !categoryDropdownOpen"
                                        class="inline-flex items-center gap-1 px-2 py-1 text-xs border border-gray-300 rounded-md bg-white hover:bg-gray-50 text-gray-700">
                                    <span x-text="selectedCategories.length === allCategories.length ? 'All' : selectedCategories.length + ' selected'"></span>
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <div x-show="categoryDropdownOpen" x-cloak
                                     class="absolute right-0 mt-1 w-56 max-h-64 overflow-y-auto bg-white border border-gray-200 rounded-lg shadow-lg z-50">
                                    <div class="p-2 border-b border-gray-100 flex gap-2">
                                        <button @click="selectedCategories = [...allCategories]" class="text-xs text-indigo-600 hover:text-indigo-800">All</button>
                                        <button @click="selectedCategories = []" class="text-xs text-red-600 hover:text-red-800">None</button>
                                    </div>
                                    <template x-for="cat in allCategories" :key="cat">
                                        <label class="flex items-center gap-2 px-3 py-1.5 hover:bg-gray-50 cursor-pointer text-sm">
                                            <input type="checkbox" :value="cat" x-model="selectedCategories" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                            <span x-text="cat" class="truncate"></span>
                                        </label>
                                    </template>
                                </div>
                            </div>
                        </div>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" x-model="showDetails" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-xs sm:text-sm text-gray-700">Details</span>
                        </label>
                    </div>
                </div>
            </div>

            </div><!-- end collapsible dashboard -->

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

                // Collect all unique categories for the filter dropdown
                $allCategories = collect($matchedItems)
                    ->pluck('categoryName')
                    ->merge(collect($scannedNotOnInvoice)->pluck('categoryName'))
                    ->filter()
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();
            @endphp

            <!-- Pending Section -->
            <div class="mb-4" x-show="filter === 'all'">
                <button @click="sectionsOpen.pending = !sectionsOpen.pending"
                        class="w-full flex justify-between items-center p-3 sm:p-4 bg-gray-100 hover:bg-gray-200 rounded-t-lg transition-colors touch-manipulation"
                        :class="sectionsOpen.pending ? 'rounded-t-lg' : 'rounded-lg'">
                    <span class="font-medium text-gray-700 text-sm sm:text-base">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 inline mr-1 sm:mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Pending ({{ $pendingItems->count() }})
                    </span>
                    <svg :class="sectionsOpen.pending ? 'rotate-180' : ''" class="w-5 h-5 text-gray-500 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="sectionsOpen.pending" x-collapse class="bg-white border border-gray-200 border-t-0 rounded-b-lg overflow-hidden">
                    @if($pendingItems->count() > 0)
                        {{-- Mobile Cards --}}
                        <div class="md:hidden divide-y divide-gray-200">
                            @foreach($pendingItems as $item)
                                @php
                                    $cost = $item->cost ?? 0;
                                    $caseUnits = $item->invoiceCaseUnits ?? 1;
                                    $myOrder = $item->myOrder ?? 0;
                                    $isWeightBased = fmod($myOrder, 1) != 0.0;
                                    $unitsDelivered = $isWeightBased ? $myOrder : $caseUnits * $myOrder;
                                    $value = $cost * $unitsDelivered;
                                @endphp
                                <div class="p-3 border-l-4 border-gray-400"
                                     x-show="categoryVisible('{{ addslashes($item->categoryName ?? '') }}')"
                                     x-data="{ editing: false, qty: null, originalQty: null, saving: false, canEdit: {{ $isCompleted ? 'false' : 'true' }} }">
                                    <div class="flex items-start justify-between gap-2">
                                        @php
                                            $tempProduct = (object)['barcode' => $item->Barcode, 'supplier_code' => $item->supCode ?? ($item->SupplierCode ?? null), 'supplier' => (object)['SupplierID' => $supplierId]];
                                        @endphp
                                        <div class="flex-shrink-0">
                                            <x-product-image :product="$tempProduct" :supplier-service="$supplierService" size="lg" :hover="true" />
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            @if($item->productID)
                                                <a href="{{ route('products.edit', $item->productID) }}" target="_blank" class="text-indigo-600 hover:text-indigo-900 font-medium text-sm">{{ $item->dbProductName ?? $item->prodName }}</a>
                                            @else
                                                <span class="font-medium text-gray-900 text-sm">{{ $item->dbProductName ?? $item->prodName }}</span>
                                            @endif
                                            <span class="text-xs text-gray-500 block">{{ $item->supCode }}@if(!empty($item->orderNumber)) <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">Order #{{ $item->orderNumber }}</span>@endif</span>
                                            @if($item->Barcode)
                                                <span class="text-xs text-gray-400 font-mono block">{{ $item->Barcode }}</span>
                                            @endif
                                            <span x-show="showCategories" x-cloak class="text-xs text-indigo-500 block">{{ $item->categoryName ?? '' }}</span>
                                        </div>
                                        <div class="text-right flex-shrink-0">
                                            <span class="text-xs text-gray-500">Expected</span>
                                            <span class="font-semibold text-sm block">
                                                @if($isWeightBased)
                                                    {{ number_format($unitsDelivered, 3) }} <span class="text-xs text-purple-600">kg</span>
                                                @else
                                                    {{ $unitsDelivered }}
                                                @endif
                                            </span>
                                        </div>
                                    </div>
                                    <div class="mt-2 flex items-center gap-2">
                                        <span class="text-xs text-gray-500 w-16">Delivered:</span>
                                        <template x-if="!editing">
                                            <span @click="canEdit && (editing = true, $nextTick(() => $refs.qtyInput?.select()))"
                                                  :class="canEdit ? 'cursor-pointer hover:bg-blue-100' : ''"
                                                  class="px-2 py-1 rounded text-sm text-gray-400"
                                                  x-text="qty !== null ? qty : '-'"></span>
                                        </template>
                                        <template x-if="editing">
                                            <form @submit.prevent="saving = true; window.deliveryMatchInstance.saveScannedQty('{{ $item->Barcode }}', qty || 0, (newQty) => { qty = newQty; originalQty = newQty; editing = false; saving = false; location.reload(); }).catch(() => saving = false)"
                                                  class="flex items-center gap-1">
                                                <input type="number" x-model="qty" x-ref="qtyInput" min="0" step="0.001"
                                                       @keydown.escape="qty = originalQty; editing = false"
                                                       class="w-20 text-center border border-gray-300 rounded px-2 py-1.5 text-sm focus:ring-blue-500 focus:border-blue-500">
                                                <button type="submit" :disabled="saving" class="text-green-600 hover:text-green-800 disabled:opacity-50 p-1 touch-manipulation">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                    </svg>
                                                </button>
                                                <button type="button" @click="qty = originalQty; editing = false" class="text-gray-400 hover:text-gray-600 p-1 touch-manipulation">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                    </svg>
                                                </button>
                                            </form>
                                        </template>
                                        <span class="ml-auto text-xs text-gray-500">Stock: {{ floatval($item->UNITS ?? 0) }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        {{-- Desktop Table --}}
                        <div class="hidden md:block overflow-x-auto">
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
                                            $isWeightBased = fmod($myOrder, 1) != 0.0;
                                            $unitsDelivered = $isWeightBased ? $myOrder : $caseUnits * $myOrder;
                                            $value = $cost * $unitsDelivered;
                                        @endphp
                                        <tr class="hover:bg-gray-50"
                                            x-show="categoryVisible('{{ addslashes($item->categoryName ?? '') }}')"
                                            x-data="{ editing: false, qty: null, originalQty: null, saving: false, canEdit: {{ $isCompleted ? 'false' : 'true' }} }">
                                            <td class="px-2 py-2">
                                                @php
                                                    $tempProduct = (object)[
                                                        'barcode' => $item->Barcode,
                                                        'supplier_code' => $item->supCode ?? ($item->SupplierCode ?? null),
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
                                                        {{ $item->dbProductName ?? $item->prodName }}
                                                    </a>
                                                @else
                                                    <span class="font-medium text-gray-900">{{ $item->dbProductName ?? $item->prodName }}</span>
                                                @endif
                                                <span class="text-xs text-gray-500 block">{{ $item->supCode }}@if(!empty($item->orderNumber)) <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">Order #{{ $item->orderNumber }}</span>@endif</span>
                                                <span x-show="showCategories" x-cloak class="text-xs text-indigo-500 block">{{ $item->categoryName ?? '' }}</span>
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

            <!-- Critical Issues Section -->
            <div class="mb-4" x-show="filter === 'all' || filter === 'problems'">
                <button @click="sectionsOpen.critical = !sectionsOpen.critical"
                        class="w-full flex justify-between items-center p-3 sm:p-4 bg-red-100 hover:bg-red-200 rounded-t-lg transition-colors touch-manipulation"
                        :class="sectionsOpen.critical ? 'rounded-t-lg' : 'rounded-lg'">
                    <span class="font-medium text-red-800 text-sm sm:text-base">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 inline mr-1 sm:mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <span class="hidden sm:inline">Critical Issues - </span>Qty Mismatches ({{ $criticalItems->count() }})
                    </span>
                    <svg :class="sectionsOpen.critical ? 'rotate-180' : ''" class="w-5 h-5 text-red-600 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="sectionsOpen.critical" x-collapse class="bg-white border border-red-200 border-t-0 rounded-b-lg overflow-hidden">
                    @if($criticalItems->count() > 0)
                        {{-- Mobile Cards --}}
                        <div class="md:hidden divide-y divide-gray-200">
                            @foreach($criticalItems as $item)
                                @php
                                    $cost = $item->cost ?? 0;
                                    $caseUnits = $item->invoiceCaseUnits ?? 1;
                                    $myOrder = $item->myOrder ?? 0;
                                    $unitsDelivered = (fmod($myOrder, 1) != 0.0) ? $myOrder : $caseUnits * $myOrder;
                                    $diff = ($item->scanned ?? 0) - $unitsDelivered;
                                    $impact = $diff * $cost;
                                    $hasCaseUnitChange = $item->invoiceCaseUnits != $item->CaseUnits;
                                @endphp
                                <div class="p-3 border-l-4 border-red-400"
                                     x-show="categoryVisible('{{ addslashes($item->categoryName ?? '') }}')">
                                    <div class="flex items-start justify-between gap-2">
                                        @php
                                            $tempProduct = (object)['barcode' => $item->Barcode, 'supplier_code' => $item->supCode ?? ($item->SupplierCode ?? null), 'supplier' => (object)['SupplierID' => $supplierId]];
                                        @endphp
                                        <div class="flex-shrink-0">
                                            <x-product-image :product="$tempProduct" :supplier-service="$supplierService" size="lg" :hover="true" />
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            @if($item->productID)
                                                <a href="{{ route('products.edit', $item->productID) }}" target="_blank" class="text-indigo-600 hover:text-indigo-900 font-medium text-sm">{{ $item->dbProductName ?? $item->prodName }}</a>
                                            @else
                                                <span class="font-medium text-gray-900 text-sm">{{ $item->dbProductName ?? $item->prodName }}</span>
                                            @endif
                                            <span class="text-xs text-gray-500 block">{{ $item->supCode }}@if(!empty($item->orderNumber)) <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">Order #{{ $item->orderNumber }}</span>@endif</span>
                                            <span x-show="showCategories" x-cloak class="text-xs text-indigo-500 block">{{ $item->categoryName ?? '' }}</span>
                                        </div>
                                        <div class="text-right flex-shrink-0">
                                            <span class="font-bold text-sm {{ $diff > 0 ? 'text-green-600' : 'text-red-600' }}">{{ $diff > 0 ? '+' : '' }}{{ $diff }}</span>
                                            <span class="text-xs block {{ $impact > 0 ? 'text-green-600' : 'text-red-600' }}">{{ $impact > 0 ? '+' : '' }}&euro;{{ number_format($impact, 2) }}</span>
                                        </div>
                                    </div>
                                    <div class="mt-2 flex items-center gap-3 text-sm">
                                        <span class="text-gray-500">Exp: <span class="font-medium text-gray-900">{{ $unitsDelivered }}</span></span>
                                        <span class="text-gray-400">|</span>
                                        <div x-data="{ editing: false, qty: {{ $item->scanned ?? 0 }}, originalQty: {{ $item->scanned ?? 0 }}, saving: false, canEdit: {{ $isCompleted ? 'false' : 'true' }} }" class="flex items-center gap-1">
                                            <span class="text-gray-500">Scan:</span>
                                            <template x-if="!editing">
                                                <span @click="canEdit && (editing = true, $nextTick(() => $refs.qtyInput?.select()))"
                                                      :class="canEdit ? 'cursor-pointer hover:bg-blue-100' : ''"
                                                      class="px-1 py-0.5 rounded font-medium text-blue-600"
                                                      x-text="qty"></span>
                                            </template>
                                            <template x-if="editing">
                                                <form @submit.prevent="saving = true; window.deliveryMatchInstance.saveScannedQty('{{ $item->Barcode }}', qty, (newQty) => { originalQty = newQty; editing = false; saving = false; }).catch(() => saving = false)"
                                                      class="flex items-center gap-1">
                                                    <input type="number" x-model="qty" x-ref="qtyInput" min="0" step="0.001"
                                                           @keydown.escape="qty = originalQty; editing = false"
                                                           class="w-16 text-center border border-gray-300 rounded px-1 py-0.5 text-sm focus:ring-blue-500 focus:border-blue-500">
                                                    <button type="submit" :disabled="saving" class="text-green-600 hover:text-green-800 disabled:opacity-50 p-1 touch-manipulation">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                    </button>
                                                    <button type="button" @click="qty = originalQty; editing = false" class="text-gray-400 hover:text-gray-600 p-1 touch-manipulation">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                    </button>
                                                </form>
                                            </template>
                                        </div>
                                    </div>
                                    @if($hasCaseUnitChange)
                                        <div class="mt-1" x-data="{ editing: false, caseQty: {{ $item->CaseUnits ?? 1 }}, originalCaseQty: {{ $item->CaseUnits ?? 1 }}, saving: false, canEdit: {{ $isCompleted ? 'false' : 'true' }} }">
                                            <button type="button"
                                                    onclick="window.deliveryMatchInstance.saveCaseUnits('{{ $item->Barcode }}', {{ $item->invoiceCaseUnits ?? 1 }}, () => location.reload())"
                                                    class="text-xs px-2 py-0.5 bg-orange-200 text-orange-800 rounded hover:bg-orange-300 touch-manipulation transition-colors"
                                                    title="Click to update DB case units to {{ $item->invoiceCaseUnits ?? 1 }}">
                                                Case: {{ $item->invoiceCaseUnits ?? 1 }} &rarr; {{ $item->CaseUnits ?? '?' }}
                                            </button>
                                        </div>
                                    @endif
                                    <div class="mt-2 flex items-center gap-2">
                                        <button class="text-xs px-3 py-1.5 bg-green-100 text-green-700 rounded hover:bg-green-200 touch-manipulation transition-colors">Verify</button>
                                        <button class="text-xs px-3 py-1.5 bg-yellow-100 text-yellow-700 rounded hover:bg-yellow-200 touch-manipulation transition-colors">Flag</button>
                                        <span class="ml-auto text-xs text-gray-500">Stock: {{ floatval($item->UNITS ?? 0) }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        {{-- Desktop Table --}}
                        <div class="hidden md:block overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-red-50">
                                    <tr>
                                        @if($isUdea || $isIndependent)
                                            <th class="px-2 py-2 w-8 text-center" title="Select for returns/deviation report">
                                                <input type="checkbox" onclick="toggleDeviationGroup(this, 'mismatch')" class="rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                                            </th>
                                        @endif
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
                                        <tr class="bg-red-50" x-show="categoryVisible('{{ addslashes($item->categoryName ?? '') }}')">
                                            @if($isUdea || $isIndependent)
                                                <td class="px-2 py-2 text-center">
                                                    @if(!empty($item->Barcode))
                                                        <input type="checkbox" class="deviation-select rounded border-gray-300 text-purple-600 focus:ring-purple-500" value="mismatch:{{ $item->Barcode }}" data-description="{{ addslashes($item->dbProductName ?? $item->prodName ?? $item->supCode ?? '') }}">
                                                    @endif
                                                </td>
                                            @endif
                                            <td class="px-2 py-2">
                                                @php
                                                    $tempProduct = (object)[
                                                        'barcode' => $item->Barcode,
                                                        'supplier_code' => $item->supCode ?? ($item->SupplierCode ?? null),
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
                                                        {{ $item->dbProductName ?? $item->prodName }}
                                                    </a>
                                                @else
                                                    <span class="font-medium text-gray-900">{{ $item->dbProductName ?? $item->prodName }}</span>
                                                @endif
                                                <span class="text-xs text-gray-500 block">{{ $item->supCode }}@if(!empty($item->orderNumber)) <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">Order #{{ $item->orderNumber }}</span>@endif</span>
                                                <span x-show="showCategories" x-cloak class="text-xs text-indigo-500 block">{{ $item->categoryName ?? '' }}</span>
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
                        class="w-full flex justify-between items-center p-3 sm:p-4 bg-yellow-100 hover:bg-yellow-200 rounded-t-lg transition-colors touch-manipulation"
                        :class="sectionsOpen.warnings ? 'rounded-t-lg' : 'rounded-lg'">
                    <span class="font-medium text-yellow-800 text-sm sm:text-base">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 inline mr-1 sm:mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="hidden sm:inline">Warnings - </span>Margin/Case Issues ({{ $warningItems->count() }})
                    </span>
                    <svg :class="sectionsOpen.warnings ? 'rotate-180' : ''" class="w-5 h-5 text-yellow-600 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="sectionsOpen.warnings" x-collapse class="bg-white border border-yellow-200 border-t-0 rounded-b-lg overflow-hidden">
                    @if($warningItems->count() > 0)
                        {{-- Mobile Cards --}}
                        <div class="md:hidden divide-y divide-gray-200">
                            @foreach($warningItems as $item)
                                @php
                                    $cost = $item->cost ?? 0;
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
                                <div class="p-3 border-l-4 border-yellow-400"
                                     x-show="categoryVisible('{{ addslashes($item->categoryName ?? '') }}')">
                                    <div class="flex items-start justify-between gap-2">
                                        @php
                                            $tempProduct = (object)['barcode' => $item->Barcode, 'supplier_code' => $item->supCode ?? ($item->SupplierCode ?? null), 'supplier' => (object)['SupplierID' => $supplierId]];
                                        @endphp
                                        <div class="flex-shrink-0">
                                            <x-product-image :product="$tempProduct" :supplier-service="$supplierService" size="lg" :hover="true" />
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            @if($item->productID)
                                                <a href="{{ route('products.edit', $item->productID) }}" target="_blank" class="text-indigo-600 hover:text-indigo-900 font-medium text-sm">{{ $item->dbProductName ?? $item->prodName }}</a>
                                            @else
                                                <span class="font-medium text-gray-900 text-sm">{{ $item->dbProductName ?? $item->prodName }}</span>
                                            @endif
                                            <span class="text-xs text-gray-500 block">{{ $item->supCode }}@if(!empty($item->orderNumber)) <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">Order #{{ $item->orderNumber }}</span>@endif</span>
                                            <span x-show="showCategories" x-cloak class="text-xs text-indigo-500 block">{{ $item->categoryName ?? '' }}</span>
                                        </div>
                                        <div class="text-right flex-shrink-0">
                                            <span class="font-medium text-sm {{ $margin < 15 ? 'text-red-600' : 'text-gray-500' }}">{{ number_format($margin, 0) }}%</span>
                                            <span class="text-xs text-gray-500 block">margin</span>
                                        </div>
                                    </div>
                                    <div class="mt-2 flex items-center gap-3 text-sm">
                                        <span class="text-gray-500">Exp: <span class="font-medium text-gray-900">{{ $unitsDelivered }}</span></span>
                                        <span class="text-gray-400">|</span>
                                        <div x-data="{ editing: false, qty: {{ $item->scanned !== null ? $item->scanned : 'null' }}, originalQty: {{ $item->scanned !== null ? $item->scanned : 'null' }}, saving: false, canEdit: {{ $isCompleted ? 'false' : 'true' }} }" class="flex items-center gap-1">
                                            <span class="text-gray-500">Scan:</span>
                                            <template x-if="!editing">
                                                <span @click="canEdit && (editing = true, $nextTick(() => $refs.qtyInput?.select()))"
                                                      :class="canEdit ? 'cursor-pointer hover:bg-blue-100' : ''"
                                                      class="px-1 py-0.5 rounded font-medium"
                                                      :class="qty !== null ? 'text-blue-600' : 'text-gray-400'"
                                                      x-text="qty !== null ? qty : '-'"></span>
                                            </template>
                                            <template x-if="editing">
                                                <form @submit.prevent="saving = true; window.deliveryMatchInstance.saveScannedQty('{{ $item->Barcode }}', qty || 0, (newQty) => { qty = newQty; originalQty = newQty; editing = false; saving = false; }).catch(() => saving = false)"
                                                      class="flex items-center gap-1">
                                                    <input type="number" x-model="qty" x-ref="qtyInput" min="0" step="0.001"
                                                           @keydown.escape="qty = originalQty; editing = false"
                                                           class="w-16 text-center border border-gray-300 rounded px-1 py-0.5 text-sm focus:ring-blue-500 focus:border-blue-500">
                                                    <button type="submit" :disabled="saving" class="text-green-600 hover:text-green-800 disabled:opacity-50 p-1 touch-manipulation">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                    </button>
                                                    <button type="button" @click="qty = originalQty; editing = false" class="text-gray-400 hover:text-gray-600 p-1 touch-manipulation">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                    </button>
                                                </form>
                                            </template>
                                        </div>
                                    </div>
                                    <div class="mt-1 flex flex-wrap items-center gap-1">
                                        @foreach($issues as $issue)
                                            <span class="text-xs px-2 py-0.5 bg-yellow-200 text-yellow-800 rounded">{{ $issue }}</span>
                                        @endforeach
                                        @if($hasCaseUnitChange)
                                            <button type="button"
                                                    onclick="window.deliveryMatchInstance.saveCaseUnits('{{ $item->Barcode }}', {{ $item->invoiceCaseUnits ?? 1 }}, () => location.reload())"
                                                    class="text-xs px-2 py-0.5 bg-orange-200 text-orange-800 rounded hover:bg-orange-300 touch-manipulation transition-colors"
                                                    title="Click to update DB case units to {{ $item->invoiceCaseUnits ?? 1 }}">
                                                Case: {{ $item->invoiceCaseUnits ?? 1 }} &rarr; {{ $item->CaseUnits ?? '?' }}
                                            </button>
                                        @endif
                                    </div>
                                    <div class="mt-2 flex items-center gap-2">
                                        <button class="text-xs px-3 py-1.5 bg-green-100 text-green-700 rounded hover:bg-green-200 touch-manipulation transition-colors">Verify</button>
                                        <button class="text-xs px-3 py-1.5 bg-yellow-100 text-yellow-700 rounded hover:bg-yellow-200 touch-manipulation transition-colors">Flag</button>
                                        <span class="ml-auto text-xs text-gray-500">Stock: {{ floatval($item->UNITS ?? 0) }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        {{-- Desktop Table --}}
                        <div class="hidden md:block overflow-x-auto">
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
                                        <tr class="bg-yellow-50" x-show="categoryVisible('{{ addslashes($item->categoryName ?? '') }}')">
                                            <td class="px-2 py-2">
                                                @php
                                                    $tempProduct = (object)[
                                                        'barcode' => $item->Barcode,
                                                        'supplier_code' => $item->supCode ?? ($item->SupplierCode ?? null),
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
                                                        {{ $item->dbProductName ?? $item->prodName }}
                                                    </a>
                                                @else
                                                    <span class="font-medium text-gray-900">{{ $item->dbProductName ?? $item->prodName }}</span>
                                                @endif
                                                <span class="text-xs text-gray-500 block">{{ $item->supCode }}@if(!empty($item->orderNumber)) <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">Order #{{ $item->orderNumber }}</span>@endif</span>
                                                <span x-show="showCategories" x-cloak class="text-xs text-indigo-500 block">{{ $item->categoryName ?? '' }}</span>
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
                        class="w-full flex justify-between items-center p-3 sm:p-4 bg-green-100 hover:bg-green-200 rounded-t-lg transition-colors touch-manipulation"
                        :class="sectionsOpen.verified ? 'rounded-t-lg' : 'rounded-lg'">
                    <span class="font-medium text-green-800 text-sm sm:text-base">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 inline mr-1 sm:mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Verified ({{ $verifiedItems->count() }})
                    </span>
                    <svg :class="sectionsOpen.verified ? 'rotate-180' : ''" class="w-5 h-5 text-green-600 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="sectionsOpen.verified" x-collapse class="bg-white border border-green-200 border-t-0 rounded-b-lg overflow-hidden">
                    @if($verifiedItems->count() > 0)
                        {{-- Mobile Cards --}}
                        <div class="md:hidden divide-y divide-gray-200">
                            @foreach($verifiedItems as $item)
                                @php
                                    $cost = $item->cost ?? 0;
                                    $caseUnits = $item->invoiceCaseUnits ?? 1;
                                    $myOrder = $item->myOrder ?? 0;
                                    $unitsDelivered = (fmod($myOrder, 1) != 0.0) ? $myOrder : $caseUnits * $myOrder;
                                    $value = $cost * $unitsDelivered;
                                @endphp
                                <div class="p-3 border-l-4 border-green-400"
                                     x-show="categoryVisible('{{ addslashes($item->categoryName ?? '') }}')">
                                    <div class="flex items-start justify-between gap-2">
                                        @php
                                            $tempProduct = (object)['barcode' => $item->Barcode, 'supplier_code' => $item->supCode ?? ($item->SupplierCode ?? null), 'supplier' => (object)['SupplierID' => $supplierId]];
                                        @endphp
                                        <div class="flex-shrink-0">
                                            <x-product-image :product="$tempProduct" :supplier-service="$supplierService" size="lg" :hover="true" />
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            @if($item->productID)
                                                <a href="{{ route('products.edit', $item->productID) }}" target="_blank" class="text-indigo-600 hover:text-indigo-900 font-medium text-sm">{{ $item->dbProductName ?? $item->prodName }}</a>
                                            @else
                                                <span class="font-medium text-gray-900 text-sm">{{ $item->dbProductName ?? $item->prodName }}</span>
                                            @endif
                                            <span class="text-xs text-gray-500 block">{{ $item->supCode }}@if(!empty($item->orderNumber)) <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">Order #{{ $item->orderNumber }}</span>@endif</span>
                                            <span x-show="showCategories" x-cloak class="text-xs text-indigo-500 block">{{ $item->categoryName ?? '' }}</span>
                                        </div>
                                        <div class="text-right flex-shrink-0">
                                            <div class="flex items-center gap-1 text-green-600">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                <span class="font-semibold text-sm">{{ $unitsDelivered }}</span>
                                            </div>
                                            <span class="text-xs text-gray-400">Stock: {{ floatval($item->UNITS ?? 0) }}</span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        {{-- Desktop Table --}}
                        <div class="hidden md:block overflow-x-auto">
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
                                        <tr class="hover:bg-green-50" x-show="categoryVisible('{{ addslashes($item->categoryName ?? '') }}')">
                                            <td class="px-2 py-2">
                                                @php
                                                    $tempProduct = (object)[
                                                        'barcode' => $item->Barcode,
                                                        'supplier_code' => $item->supCode ?? ($item->SupplierCode ?? null),
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
                                                        {{ $item->dbProductName ?? $item->prodName }}
                                                    </a>
                                                @else
                                                    <span class="font-medium text-gray-900">{{ $item->dbProductName ?? $item->prodName }}</span>
                                                @endif
                                                <span class="text-xs text-gray-500 block">{{ $item->supCode }}@if(!empty($item->orderNumber)) <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">Order #{{ $item->orderNumber }}</span>@endif</span>
                                                <span x-show="showCategories" x-cloak class="text-xs text-indigo-500 block">{{ $item->categoryName ?? '' }}</span>
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
                        class="w-full flex justify-between items-center p-3 sm:p-4 bg-orange-100 hover:bg-orange-200 rounded-t-lg transition-colors touch-manipulation"
                        :class="sectionsOpen.oos ? 'rounded-t-lg' : 'rounded-lg'">
                    <span class="font-medium text-orange-800 text-sm sm:text-base">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 inline mr-1 sm:mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                        </svg>
                        OOS <span class="hidden sm:inline">- Not Delivered</span> ({{ $oosItems->count() }})
                    </span>
                    <svg :class="sectionsOpen.oos ? 'rotate-180' : ''" class="w-5 h-5 text-orange-600 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="sectionsOpen.oos" x-collapse class="bg-white border border-orange-200 border-t-0 rounded-b-lg overflow-hidden">
                    {{-- Mobile Cards --}}
                    <div class="md:hidden divide-y divide-gray-200">
                        @foreach($oosItems as $item)
                            @php
                                $hasCaseUnitChange = $item->invoiceCaseUnits != $item->CaseUnits;
                            @endphp
                            <div class="p-3 border-l-4 border-orange-400"
                                 x-show="categoryVisible('{{ addslashes($item->categoryName ?? '') }}')">
                                <div class="flex items-start justify-between gap-2">
                                    @php
                                        $tempProduct = (object)['barcode' => $item->Barcode, 'supplier_code' => $item->supCode ?? ($item->SupplierCode ?? null), 'supplier' => (object)['SupplierID' => $supplierId]];
                                    @endphp
                                    <div class="flex-shrink-0">
                                        <x-product-image :product="$tempProduct" :supplier-service="$supplierService" size="lg" :hover="true" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        @if($item->productID)
                                            <a href="{{ route('products.edit', $item->productID) }}" target="_blank" class="text-indigo-600 hover:text-indigo-900 font-medium text-sm">{{ $item->dbProductName ?? $item->prodName }}</a>
                                        @else
                                            <span class="font-medium text-gray-900 text-sm">{{ $item->dbProductName ?? $item->prodName }}</span>
                                        @endif
                                        <span class="text-xs text-gray-500 block">{{ $item->supCode }}@if(!empty($item->orderNumber)) <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">Order #{{ $item->orderNumber }}</span>@endif</span>
                                        <span x-show="showCategories" x-cloak class="text-xs text-indigo-500 block">{{ $item->categoryName ?? '' }}</span>
                                    </div>
                                    <div class="text-right flex-shrink-0">
                                        <span class="text-xs text-gray-500">Stock: <span class="font-medium">{{ floatval($item->UNITS ?? 0) }}</span></span>
                                    </div>
                                </div>
                                @if($hasCaseUnitChange)
                                    <div class="mt-1">
                                        <span class="text-xs text-orange-700">Case: {{ $item->invoiceCaseUnits ?? 1 }} (inv) vs {{ $item->CaseUnits ?? '-' }} (db)</span>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    {{-- Desktop Table --}}
                    <div class="hidden md:block overflow-x-auto">
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
                                    <tr class="hover:bg-orange-50" x-show="categoryVisible('{{ addslashes($item->categoryName ?? '') }}')">
                                        <td class="px-2 py-2">
                                            @php
                                                $tempProduct = (object)[
                                                    'barcode' => $item->Barcode,
                                                    'supplier_code' => $item->supCode ?? ($item->SupplierCode ?? null),
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
                                                    {{ $item->dbProductName ?? $item->prodName }}
                                                </a>
                                            @else
                                                <span class="font-medium text-gray-900">{{ $item->dbProductName ?? $item->prodName }}</span>
                                            @endif
                                            <span class="text-xs text-gray-500 block">{{ $item->supCode }}@if(!empty($item->orderNumber)) <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">Order #{{ $item->orderNumber }}</span>@endif</span>
                                            <span x-show="showCategories" x-cloak class="text-xs text-indigo-500 block">{{ $item->categoryName ?? '' }}</span>
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

            <!-- Extra Items Section (Scanned but NOT on Invoice) -->
            <div class="mb-4" x-show="filter === 'all' || filter === 'problems'">
                <button @click="sectionsOpen.extra = !sectionsOpen.extra"
                        class="w-full flex justify-between items-center p-3 sm:p-4 bg-orange-100 hover:bg-orange-200 rounded-t-lg transition-colors touch-manipulation"
                        :class="sectionsOpen.extra ? 'rounded-t-lg' : 'rounded-lg'">
                    <span class="font-medium text-orange-800 text-sm sm:text-base">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 inline mr-1 sm:mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Extra <span class="hidden sm:inline">- Not on Invoice</span> ({{ count($scannedNotOnInvoice) }})
                    </span>
                    <svg :class="sectionsOpen.extra ? 'rotate-180' : ''" class="w-5 h-5 text-orange-600 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="sectionsOpen.extra" x-collapse class="bg-white border border-orange-200 border-t-0 rounded-b-lg overflow-hidden">
                    @if(count($scannedNotOnInvoice) > 0)
                        {{-- Mobile Cards --}}
                        <div class="md:hidden divide-y divide-gray-200">
                            @foreach($scannedNotOnInvoice as $item)
                                <div class="p-3 border-l-4 border-orange-400"
                                     x-show="categoryVisible('{{ addslashes($item->categoryName ?? '') }}')">
                                    <div class="flex items-start justify-between gap-2">
                                        @php
                                            $tempProduct = (object)['barcode' => $item->Barcode, 'supplier_code' => $item->supCode ?? ($item->SupplierCode ?? null), 'supplier' => (object)['SupplierID' => $supplierId]];
                                        @endphp
                                        <div class="flex-shrink-0">
                                            <x-product-image :product="$tempProduct" :supplier-service="$supplierService" size="lg" :hover="true" />
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <span class="font-medium text-gray-900 text-sm">{{ $item->NAME ?? 'Unknown Product' }}</span>
                                            @if($item->productID)
                                                <a href="{{ route('products.edit', $item->productID) }}" target="_blank" class="text-xs text-indigo-600 hover:text-indigo-900 block">{{ $item->Barcode }}</a>
                                            @else
                                                <span class="text-xs text-gray-500 block">{{ $item->Barcode }}</span>
                                            @endif
                                            @if($item->SupplierCode)
                                                <span class="text-xs text-gray-500 block">{{ $item->SupplierCode }}</span>
                                            @endif
                                            <span x-show="showCategories" x-cloak class="text-xs text-indigo-500 block">{{ $item->categoryName ?? '' }}</span>
                                        </div>
                                        <div class="text-right flex-shrink-0 text-xs text-gray-500">
                                            <span>Case: {{ $item->CaseUnits ?? '-' }}</span>
                                            <span class="block">Stock: {{ floatval($item->UNITS ?? 0) }}</span>
                                        </div>
                                    </div>
                                    <div class="mt-2 flex items-center gap-3 text-sm"
                                         x-data="{ editing: false, qty: {{ $item->scanned ?? 0 }}, originalQty: {{ $item->scanned ?? 0 }}, saving: false, canEdit: {{ $isCompleted ? 'false' : 'true' }} }">
                                        <span class="text-gray-500">Scanned:</span>
                                        <template x-if="!editing">
                                            <span @click="canEdit && (editing = true, $nextTick(() => $refs.qtyInput?.select()))"
                                                  :class="canEdit ? 'cursor-pointer hover:bg-orange-100' : ''"
                                                  class="px-1 py-0.5 rounded font-medium text-orange-600"
                                                  x-text="qty"></span>
                                        </template>
                                        <template x-if="editing">
                                            <form @submit.prevent="saving = true; window.deliveryMatchInstance.saveScannedQty('{{ $item->Barcode }}', qty, (newQty) => { originalQty = newQty; editing = false; saving = false; }).catch(() => saving = false)"
                                                  class="flex items-center gap-1">
                                                <input type="number" x-model="qty" x-ref="qtyInput" min="0" step="0.001"
                                                       @keydown.escape="qty = originalQty; editing = false"
                                                       class="w-16 text-center border border-gray-300 rounded px-1 py-0.5 text-sm focus:ring-blue-500 focus:border-blue-500">
                                                <button type="submit" :disabled="saving" class="text-green-600 hover:text-green-800 disabled:opacity-50 p-1 touch-manipulation">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                </button>
                                                <button type="button" @click="qty = originalQty; editing = false" class="text-gray-400 hover:text-gray-600 p-1 touch-manipulation">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                </button>
                                            </form>
                                        </template>
                                    </div>
                                    <div class="mt-2 flex items-center gap-2">
                                        <button class="text-xs px-3 py-1.5 bg-green-100 text-green-700 rounded hover:bg-green-200 touch-manipulation transition-colors">Verify</button>
                                        <button class="text-xs px-3 py-1.5 bg-yellow-100 text-yellow-700 rounded hover:bg-yellow-200 touch-manipulation transition-colors">Flag</button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        {{-- Desktop Table --}}
                        <div class="hidden md:block overflow-x-auto">
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
                                        <tr class="bg-orange-50" x-show="categoryVisible('{{ addslashes($item->categoryName ?? '') }}')">
                                            <td class="px-2 py-2">
                                                @php
                                                    $tempProduct = (object)[
                                                        'barcode' => $item->Barcode,
                                                        'supplier_code' => $item->supCode ?? ($item->SupplierCode ?? null),
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
                                                <span x-show="showCategories" x-cloak class="text-xs text-indigo-500 block">{{ $item->categoryName ?? '' }}</span>
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
                        class="w-full flex justify-between items-center p-3 sm:p-4 bg-red-100 hover:bg-red-200 rounded-t-lg transition-colors touch-manipulation"
                        :class="sectionsOpen.missing ? 'rounded-t-lg' : 'rounded-lg'">
                    <span class="font-medium text-red-800 text-sm sm:text-base">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 inline mr-1 sm:mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/>
                        </svg>
                        Missing <span class="hidden sm:inline">- Not Scanned</span> ({{ count($onInvoiceNotScanned) }})
                    </span>
                    <svg :class="sectionsOpen.missing ? 'rotate-180' : ''" class="w-5 h-5 text-red-600 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="sectionsOpen.missing" x-collapse class="bg-white border border-red-200 border-t-0 rounded-b-lg overflow-hidden">
                    @if(count($onInvoiceNotScanned) > 0)
                        {{-- Mobile Cards --}}
                        <div class="md:hidden divide-y divide-gray-200">
                            @foreach($onInvoiceNotScanned as $item)
                                @php
                                    $caseUnits = $item->caseUnits ?? 1;
                                    $myOrder = $item->myOrder ?? 0;
                                    $isWeightBased = fmod($myOrder, 1) != 0.0;
                                    $totalUnits = $isWeightBased ? $myOrder : $caseUnits * $myOrder;
                                    $value = ($item->cost ?? 0) * $totalUnits;
                                @endphp
                                <div class="p-3 border-l-4 border-red-400">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="flex-shrink-0">
                                            @php
                                                $tempProduct = $item->Barcode ? (object)[
                                                    'barcode' => $item->Barcode,
                                                    'supplier_code' => $item->supCode,
                                                    'supplier' => (object)['SupplierID' => $supplierId],
                                                ] : null;
                                            @endphp
                                            <x-product-image :product="$tempProduct" :supplier-service="$supplierService" size="lg" :hover="true" />
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            @if($item->productID)
                                                <a href="{{ route('products.edit', $item->productID) }}" target="_blank" class="text-indigo-600 hover:text-indigo-900 font-medium text-sm">{{ $item->dbProductName ?? $item->prodName }}</a>
                                            @else
                                                <span class="font-medium text-gray-900 text-sm">{{ $item->dbProductName ?? $item->prodName }}</span>
                                            @endif
                                            <span class="text-xs text-gray-500 block">{{ $item->supCode }}@if(!empty($item->orderNumber)) <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">Order #{{ $item->orderNumber }}</span>@endif</span>
                                            @if($item->Barcode)
                                                <span class="text-xs text-gray-400 font-mono block">{{ $item->Barcode }}</span>
                                            @endif
                                        </div>
                                        <div class="text-right flex-shrink-0">
                                            <span class="font-medium text-red-600 text-sm">&euro;{{ number_format($value, 2) }}</span>
                                        </div>
                                    </div>
                                    <div class="mt-1 text-sm text-gray-600">
                                        @if($isWeightBased)
                                            {{ number_format($myOrder, 3) }} <span class="text-xs text-purple-600">kg</span>
                                            = <span class="font-medium text-red-600">{{ number_format($totalUnits, 3) }} <span class="text-xs text-purple-600">kg</span></span>
                                        @else
                                            {{ $myOrder }} x {{ $caseUnits }}/case
                                            = <span class="font-medium text-red-600">{{ $totalUnits }} units</span>
                                        @endif
                                    </div>
                                    <div class="mt-2 flex items-center gap-2">
                                        <button class="text-xs px-3 py-1.5 bg-green-100 text-green-700 rounded hover:bg-green-200 touch-manipulation transition-colors">Verify</button>
                                        <button class="text-xs px-3 py-1.5 bg-yellow-100 text-yellow-700 rounded hover:bg-yellow-200 touch-manipulation transition-colors">Flag</button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        {{-- Desktop Table --}}
                        <div class="hidden md:block overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-red-50">
                                    <tr>
                                        @if($isUdea || $isIndependent)
                                            <th class="px-2 py-2 w-8 text-center" title="Select for returns/deviation report">
                                                <input type="checkbox" onclick="toggleDeviationGroup(this, 'pending')" class="rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                                            </th>
                                        @endif
                                        <th class="px-2 py-2 w-12"></th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Barcode</th>
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
                                            @if($isUdea || $isIndependent)
                                                <td class="px-2 py-2 text-center">
                                                    @if(!empty($item->Barcode))
                                                        <input type="checkbox" class="deviation-select rounded border-gray-300 text-purple-600 focus:ring-purple-500" value="pending:{{ $item->Barcode }}" data-description="{{ addslashes($item->dbProductName ?? $item->prodName ?? $item->supCode ?? '') }}">
                                                    @endif
                                                </td>
                                            @endif
                                            <td class="px-2 py-2">
                                                @php
                                                    $tempProduct = $item->Barcode ? (object)[
                                                        'barcode' => $item->Barcode,
                                                        'supplier_code' => $item->supCode,
                                                        'supplier' => (object)['SupplierID' => $supplierId],
                                                    ] : null;
                                                @endphp
                                                <x-product-image
                                                    :product="$tempProduct"
                                                    :supplier-service="$supplierService"
                                                    size="sm"
                                                    :hover="true" />
                                            </td>
                                            <td class="px-3 py-2 font-medium text-gray-900">
                                                @if($item->productID)
                                                    <a href="{{ route('products.edit', $item->productID) }}" target="_blank" class="text-indigo-600 hover:text-indigo-900">{{ $item->dbProductName ?? $item->prodName }}</a>
                                                @else
                                                    {{ $item->dbProductName ?? $item->prodName }}
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-sm text-gray-600 font-mono">{{ $item->Barcode ?? '-' }}</td>
                                            <td class="px-3 py-2 text-gray-500">{{ $item->supCode }}@if(!empty($item->orderNumber)) <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">Order #{{ $item->orderNumber }}</span>@endif</td>
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

    {{-- IIHF Goods Return Sheet — per-item reason selection modal --}}
    <div id="returnReasonModal" class="hidden fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <div class="flex min-h-screen items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-900/50" onclick="closeReturnReasonModal()"></div>
            <div class="relative w-full max-w-2xl rounded-lg bg-white shadow-xl">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-900">Select Return Reason</h3>
                    <p class="mt-1 text-sm text-gray-500">Choose a reason code for each item before generating the returns sheet.</p>
                </div>
                <div id="returnReasonList" class="max-h-96 overflow-y-auto px-6 py-3"></div>
                <div class="flex justify-end gap-3 border-t border-gray-200 px-6 py-4">
                    <button type="button" onclick="closeReturnReasonModal()" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="button" onclick="submitGoodsReturnSheet()" class="rounded-md bg-purple-600 px-4 py-2 text-sm font-medium text-white hover:bg-purple-700">Download Returns Sheet</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Review modal: lists scanned products that have a translated label before printing. --}}
    <div id="translationPrintModal" class="hidden fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <div class="flex min-h-screen items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-900/50" onclick="closeTranslationPrintModal()"></div>
            <div class="relative w-full max-w-2xl rounded-lg bg-white shadow-xl">
                <div class="border-b border-gray-200 px-6 py-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Print Translated Labels</h3>
                            <p class="mt-1 text-sm text-gray-500">These scanned products have a translated label. One label prints per unit scanned. Untick any you don't want to print.</p>
                        </div>
                        <div class="flex flex-shrink-0 gap-2">
                            <button type="button" onclick="setAllTranslationSelections(true)" class="rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">Select all</button>
                            <button type="button" onclick="setAllTranslationSelections(false)" class="rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">Deselect all</button>
                        </div>
                    </div>
                </div>
                <div id="translationPrintList" class="max-h-96 overflow-y-auto px-6 py-3" onchange="updateTranslationPrintCount()"></div>
                <div class="flex justify-end gap-3 border-t border-gray-200 px-6 py-4">
                    <button type="button" onclick="closeTranslationPrintModal()" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="button" id="submitTranslationsBtn" onclick="submitTranslatedLabels()" class="rounded-md bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700 disabled:opacity-50">Print</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Global reference to store the Alpine component instance
        window.deliveryMatchInstance = null;

        // Toggle all deviation checkboxes within one table group (header "select all").
        function toggleDeviationGroup(headerCheckbox, source) {
            document.querySelectorAll('.deviation-select').forEach(function (cb) {
                if (cb.value.startsWith(source + ':')) {
                    cb.checked = headerCheckbox.checked;
                }
            });
        }

        // Build a report/sheet from the ticked pending / mismatch rows and download it.
        function submitSelectedItems(actionUrl, noun) {
            const selected = Array.from(document.querySelectorAll('.deviation-select:checked')).map(cb => cb.value);

            if (selected.length === 0) {
                alert('Please tick at least one item (in Qty Mismatches or Missing) to include in the ' + noun + '.');
                return;
            }

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = actionUrl;
            form.style.display = 'none';

            const fields = {
                '_token': '{{ csrf_token() }}',
                'delID': '{{ $deliveryId }}',
                'supplierID': '{{ $supplierId }}',
            };
            for (const [name, value] of Object.entries(fields)) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value;
                form.appendChild(input);
            }
            selected.forEach(function (value) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'items[]';
                input.value = value;
                form.appendChild(input);
            });

            document.body.appendChild(form);
            form.submit();
            form.remove();
        }

        // Udea deviation report (Excel)
        function generateDeviationReport() {
            submitSelectedItems('{{ route('delivery-legacy.deviation-report') }}', 'deviation report');
        }

        // Scanned products that have a translated label available (barcode, name, scanned qty).
        const TRANSLATABLE_PRODUCTS = @js($translatableProducts ?? []);

        // Open a review modal listing the products whose translated labels will print.
        function printTranslatedLabels() {
            const list = document.getElementById('translationPrintList');
            list.innerHTML = '';

            TRANSLATABLE_PRODUCTS.forEach(function (product) {
                const row = document.createElement('label');
                row.className = 'flex items-center gap-3 py-2 border-b border-gray-100 last:border-0 cursor-pointer';

                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.className = 'translation-print-select rounded border-gray-300 text-teal-600 focus:ring-teal-500';
                checkbox.value = product.barcode;
                checkbox.checked = true;

                // Product image thumbnail (falls back to a placeholder icon).
                const thumb = document.createElement('div');
                thumb.className = 'w-10 h-10 flex-shrink-0 rounded border border-gray-200 bg-white flex items-center justify-center overflow-hidden';
                if (product.image) {
                    const img = document.createElement('img');
                    img.src = product.image;
                    img.alt = product.name;
                    img.loading = 'lazy';
                    img.className = 'w-full h-full object-contain';
                    img.onerror = function () { this.remove(); thumb.appendChild(placeholderIcon()); };
                    thumb.appendChild(img);
                } else {
                    thumb.appendChild(placeholderIcon());
                }

                const name = document.createElement('div');
                name.className = 'flex-1 text-sm text-gray-700 truncate';
                name.title = product.name;
                name.textContent = product.name;

                const qty = document.createElement('div');
                qty.className = 'text-xs font-medium text-gray-500 whitespace-nowrap';
                qty.textContent = product.scanned + (product.scanned === 1 ? ' label' : ' labels');

                row.appendChild(checkbox);
                row.appendChild(thumb);
                row.appendChild(name);
                row.appendChild(qty);
                list.appendChild(row);
            });

            updateTranslationPrintCount();
            document.getElementById('translationPrintModal').classList.remove('hidden');
        }

        // SVG placeholder shown when a product has no image.
        function placeholderIcon() {
            const span = document.createElement('span');
            span.innerHTML = '<svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>';
            return span.firstChild;
        }

        // Tick or untick every product in the review modal.
        function setAllTranslationSelections(checked) {
            document.querySelectorAll('.translation-print-select').forEach(function (cb) {
                cb.checked = checked;
            });
            updateTranslationPrintCount();
        }

        function closeTranslationPrintModal() {
            document.getElementById('translationPrintModal').classList.add('hidden');
        }

        // Update the footer button label with the running selected label total.
        function updateTranslationPrintCount() {
            const checked = Array.from(document.querySelectorAll('.translation-print-select:checked'));
            let labels = 0;
            checked.forEach(function (cb) {
                const product = TRANSLATABLE_PRODUCTS.find(function (p) { return p.barcode === cb.value; });
                if (product) labels += product.scanned;
            });
            const btn = document.getElementById('submitTranslationsBtn');
            btn.disabled = checked.length === 0;
            btn.textContent = checked.length === 0
                ? 'Print'
                : 'Print ' + labels + (labels === 1 ? ' Label' : ' Labels');
        }

        // Send the selected translated labels to the Zebra in a single job.
        function submitTranslatedLabels() {
            const checked = Array.from(document.querySelectorAll('.translation-print-select:checked'));
            if (checked.length === 0) {
                return;
            }
            const barcodes = checked.map(function (cb) { return cb.value; });

            const btn = document.getElementById('submitTranslationsBtn');
            btn.disabled = true;

            fetch('{{ route('delivery-legacy.print-translations') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                },
                body: JSON.stringify({
                    delID: '{{ $deliveryId }}',
                    supplierID: '{{ $supplierId }}',
                    barcodes: barcodes,
                }),
            })
                .then(function (response) { return response.json().then(function (data) { return { ok: response.ok, data: data }; }); })
                .then(function (result) {
                    alert(result.data.message || (result.ok ? 'Print job sent.' : 'Print failed.'));
                    if (result.ok) closeTranslationPrintModal();
                })
                .catch(function (error) {
                    alert('Print request failed: ' + error);
                })
                .finally(function () {
                    updateTranslationPrintCount();
                });
        }

        // Reason codes printed on the IIHF Goods Return Record form.
        const IIHF_RETURN_REASONS = [
            { code: 'A', label: 'Not delivered' },
            { code: 'B', label: 'Damaged' },
            { code: 'C', label: 'Short Date' },
            { code: 'D', label: 'Incorrectly Ordered' },
            { code: 'E', label: 'Delivered, not invoiced' },
            { code: 'F', label: 'Other' },
        ];

        // Independent (IIHF) goods return sheet (PDF) — open a modal to pick a reason per item first.
        function generateGoodsReturnSheet() {
            const checked = Array.from(document.querySelectorAll('.deviation-select:checked'));
            if (checked.length === 0) {
                alert('Please tick at least one item (in Qty Mismatches or Missing) to include in the goods return sheet.');
                return;
            }

            const list = document.getElementById('returnReasonList');
            list.innerHTML = '';

            checked.forEach(function (cb, index) {
                const description = cb.getAttribute('data-description') || cb.value;

                const row = document.createElement('div');
                row.className = 'flex items-center gap-3 py-2 border-b border-gray-100 last:border-0';

                const label = document.createElement('div');
                label.className = 'flex-1 text-sm text-gray-700 truncate';
                label.title = description;
                label.textContent = description;

                const select = document.createElement('select');
                select.className = 'return-reason-select w-56 rounded-md border-gray-300 text-sm focus:border-purple-500 focus:ring-purple-500';
                select.setAttribute('data-item', cb.value);
                IIHF_RETURN_REASONS.forEach(function (reason) {
                    const option = document.createElement('option');
                    option.value = reason.code;
                    option.textContent = reason.code + ' — ' + reason.label;
                    select.appendChild(option);
                });

                row.appendChild(label);
                row.appendChild(select);
                list.appendChild(row);
            });

            document.getElementById('returnReasonModal').classList.remove('hidden');
        }

        function closeReturnReasonModal() {
            document.getElementById('returnReasonModal').classList.add('hidden');
        }

        // Build the POST form with "source:barcode:reason" items and download the PDF.
        function submitGoodsReturnSheet() {
            const selects = Array.from(document.querySelectorAll('#returnReasonList .return-reason-select'));
            if (selects.length === 0) {
                closeReturnReasonModal();
                return;
            }

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route('delivery-legacy.goods-return-sheet') }}';
            form.style.display = 'none';

            const fields = {
                '_token': '{{ csrf_token() }}',
                'delID': '{{ $deliveryId }}',
                'supplierID': '{{ $supplierId }}',
            };
            for (const [name, value] of Object.entries(fields)) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value;
                form.appendChild(input);
            }
            selects.forEach(function (select) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'items[]';
                input.value = select.getAttribute('data-item') + ':' + select.value;
                form.appendChild(input);
            });

            document.body.appendChild(form);
            form.submit();
            form.remove();
            closeReturnReasonModal();
        }

        function deliveryMatch() {
            return {
                filter: 'all',
                dashboardOpen: false,
                showDetails: false,
                showCategories: false,
                categoryDropdownOpen: false,
                allCategories: @js($allCategories),
                selectedCategories: @js($allCategories),
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
                scannerOpen: false,
                scanner: {
                    step: 'scan',       // 'scan' or 'quantity'
                    barcode: '',
                    keyboardEnabled: false,
                    processing: false,
                    lastResult: null,
                    scanCount: 0,
                    history: [],
                    cameraActive: false,
                    cameraVisible: false,
                    cameraStatus: '',
                    incrementQty: 1,
                    lastScannedBarcode: null,
                    lastScanTime: 0,
                    productInfo: null,   // product data from lookup
                    expectedQty: null,
                    currentScanned: 0,
                    scanType: 'unit',    // 'unit' or 'case' (outer barcode)
                    caseUnits: 1,        // units per case for outer barcode scans
                    cameraWasActive: false, // track if camera was used for auto-restart
                    outerBarcodeToAssign: null, // outer barcode being assigned
                    outerAssignBarcode: '',     // unit barcode scanned/typed during assign
                    outerAssignStatus: '',
                    outerAssignError: false,
                },
                scannerDirty: false,
                init() {
                    window.deliveryMatchInstance = this;
                },
                openScanner() {
                    this.scannerOpen = true;
                    this.$nextTick(() => {
                        if (this.$refs.scannerInput) this.$refs.scannerInput.focus();
                    });
                },
                closeScanner() {
                    this.stopScannerCamera();
                    this.scanner.cameraWasActive = false;
                    this.scannerOpen = false;
                    if (this.scannerDirty) {
                        location.reload();
                    }
                },
                resetScanner() {
                    this.scanner.step = 'scan';
                    this.scanner.barcode = '';
                    this.scanner.incrementQty = 1;
                    this.scanner.productInfo = null;
                    this.scanner.expectedQty = null;
                    this.scanner.currentScanned = 0;
                    this.scanner.processing = false;
                    this.scanner.scanType = 'unit';
                    this.scanner.caseUnits = 1;
                    this.$nextTick(() => {
                        if (this.$refs.scannerInput) this.$refs.scannerInput.focus();
                    });
                    this.restartCameraIfActive();
                },
                restartCameraIfActive() {
                    if (this.scanner.cameraWasActive && !this.scanner.cameraActive) {
                        this.$nextTick(() => {
                            this.toggleScannerCamera();
                        });
                    }
                },
                startOuterAssign(outerBarcode) {
                    this.stopScannerCamera();
                    this.scanner.step = 'assign-outer';
                    this.scanner.outerBarcodeToAssign = outerBarcode;
                    this.scanner.outerAssignBarcode = '';
                    this.scanner.outerAssignStatus = '';
                    this.scanner.outerAssignError = false;
                    this.scanner.lastResult = null;
                    // Auto-open camera for scanning the unit barcode
                    this.$nextTick(() => {
                        this.startOuterAssignCamera();
                    });
                },
                cancelOuterAssign() {
                    this.stopOuterAssignCamera();
                    this.scanner.step = 'scan';
                    this.scanner.outerBarcodeToAssign = null;
                    this.scanner.outerAssignBarcode = '';
                    this.scanner.outerAssignStatus = '';
                    this.scanner.outerAssignError = false;
                    this.restartCameraIfActive();
                },
                startOuterAssignCamera() {
                    if (!window.BarcodeScanner) {
                        this.scanner.outerAssignStatus = 'Scanner module not loaded. Type the barcode instead.';
                        this.scanner.outerAssignError = true;
                        return;
                    }
                    this.scanner.cameraVisible = true;
                    this.$nextTick(() => {
                        window.BarcodeScanner.startScanner(
                            'outer-assign-scanner',
                            (decodedText) => this.onScannerDetected(decodedText),
                            (error) => {}
                        ).then(() => {
                            this.scanner.cameraActive = true;
                        }).catch((err) => {
                            this.scanner.outerAssignStatus = 'Camera error: ' + (err.message || err);
                            this.scanner.outerAssignError = true;
                            this.scanner.cameraActive = false;
                        });
                    });
                },
                stopOuterAssignCamera() {
                    if (window.BarcodeScanner && window.BarcodeScanner.isRunning()) {
                        window.BarcodeScanner.stopScanner().catch(() => {});
                    }
                    this.scanner.cameraActive = false;
                    this.scanner.cameraVisible = false;
                },
                async submitOuterAssign() {
                    const unitBarcode = this.scanner.outerAssignBarcode.trim();
                    if (!unitBarcode || this.scanner.processing) return;

                    this.scanner.processing = true;
                    this.scanner.outerAssignStatus = '';
                    this.scanner.outerAssignError = false;

                    try {
                        const response = await fetch('{{ route("delivery-legacy.save-outer-barcode") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                unitBarcode: unitBarcode,
                                supplierID: this.supplierID,
                                outerCode: this.scanner.outerBarcodeToAssign
                            })
                        });

                        const data = await response.json();
                        if (data.success) {
                            this.scanner.outerAssignStatus = 'Linked to: ' + data.productName + ' (' + data.unitBarcode + ')';
                            this.scanner.outerAssignError = false;
                            const savedOuterBarcode = this.scanner.outerBarcodeToAssign;
                            // After a short delay, go back to scanning and auto-scan the outer barcode
                            setTimeout(() => {
                                this.stopOuterAssignCamera();
                                this.scanner.step = 'scan';
                                this.scanner.outerBarcodeToAssign = null;
                                this.scanner.outerAssignBarcode = '';
                                this.scanner.outerAssignStatus = '';
                                // Now scan the outer barcode which should resolve
                                this.scanner.barcode = savedOuterBarcode || '';
                                if (this.scanner.barcode) {
                                    this.lookupBarcode();
                                } else {
                                    this.restartCameraIfActive();
                                }
                            }, 1500);
                        } else {
                            this.scanner.outerAssignStatus = data.message || 'Failed to save.';
                            this.scanner.outerAssignError = true;
                            this.scanner.outerAssignBarcode = '';
                            // Restart camera for another attempt
                            this.$nextTick(() => {
                                this.startOuterAssignCamera();
                            });
                        }
                    } catch (error) {
                        this.scanner.outerAssignStatus = 'Network error. Try again.';
                        this.scanner.outerAssignError = true;
                        this.scanner.outerAssignBarcode = '';
                        this.$nextTick(() => {
                            this.startOuterAssignCamera();
                        });
                    } finally {
                        this.scanner.processing = false;
                    }
                },
                toggleScannerCamera() {
                    if (this.scanner.cameraActive) {
                        this.stopScannerCamera();
                        return;
                    }
                    if (!window.BarcodeScanner) {
                        this.scanner.cameraStatus = 'Scanner module not loaded. Ensure HTTPS is enabled.';
                        return;
                    }
                    this.scanner.cameraVisible = true;
                    this.scanner.cameraStatus = 'Starting camera...';
                    this.$nextTick(() => {
                        window.BarcodeScanner.startScanner(
                            'delivery-scanner',
                            (decodedText) => this.onScannerDetected(decodedText),
                            (error) => {}
                        ).then(() => {
                            this.scanner.cameraActive = true;
                            this.scanner.cameraStatus = 'Point camera at barcode';
                        }).catch((err) => {
                            this.scanner.cameraStatus = 'Camera error: ' + (err.message || err);
                            this.scanner.cameraActive = false;
                        });
                    });
                },
                stopScannerCamera() {
                    if (window.BarcodeScanner && window.BarcodeScanner.isRunning()) {
                        window.BarcodeScanner.stopScanner().catch(() => {});
                    }
                    this.scanner.cameraActive = false;
                    this.scanner.cameraVisible = false;
                    this.scanner.cameraStatus = '';
                },
                parseBarcode(raw) {
                    let text = raw.trim();
                    // Strip GS1 symbology identifier prefixes
                    if (text.startsWith(']C1')) text = text.substring(3);
                    if (text.startsWith(']d2')) text = text.substring(3);
                    if (text.startsWith(']e0')) text = text.substring(3);
                    // Replace GS (group separator) characters
                    text = text.replace(/[\x1D\u001D]/g, '|');

                    // Extract GTIN-14 from AI (01) — always 14 digits after "01"
                    const gtin14Match = text.match(/(?:^|\|)01(\d{14})/);
                    if (gtin14Match) {
                        return { barcode: gtin14Match[1], isGS1: true, raw };
                    }

                    return { barcode: text, isGS1: false, raw };
                },
                onScannerDetected(text) {
                    // Beep
                    try {
                        const ctx = new (window.AudioContext || window.webkitAudioContext)();
                        const osc = ctx.createOscillator();
                        osc.type = 'square';
                        osc.frequency.value = 1000;
                        osc.connect(ctx.destination);
                        osc.start();
                        osc.stop(ctx.currentTime + 0.1);
                    } catch(e) {}
                    if (navigator.vibrate) navigator.vibrate(100);

                    // Duplicate prevention (2s cooldown)
                    const now = Date.now();
                    if (text === this.scanner.lastScannedBarcode && now - this.scanner.lastScanTime < 2000) {
                        return;
                    }
                    this.scanner.lastScannedBarcode = text;
                    this.scanner.lastScanTime = now;

                    // Parse GS1-128 barcodes to extract GTIN-14
                    const parsed = this.parseBarcode(text);

                    if (this.scanner.step === 'assign-outer') {
                        // In outer assign mode: scanned barcode is the unit barcode to link
                        this.stopScannerCamera();
                        this.scanner.outerAssignBarcode = parsed.barcode;
                        this.submitOuterAssign();
                        return;
                    }

                    this.scanner.cameraWasActive = true;
                    this.stopScannerCamera();
                    this.scanner.barcode = parsed.barcode;
                    this.lookupBarcode();
                },
                async lookupBarcode() {
                    // Parse GS1 barcodes from manual input (camera scans are already parsed in onScannerDetected)
                    const parsed = this.parseBarcode(this.scanner.barcode);
                    this.scanner.barcode = parsed.barcode;

                    const barcode = this.scanner.barcode.trim();
                    if (!barcode) return;

                    // Quick product lookup — use quantity=0 to just get info without recording a scan
                    this.scanner.processing = true;
                    try {
                        const response = await fetch('{{ route("delivery-legacy.scan-increment") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                delID: this.deliveryId,
                                barcode: barcode,
                                quantity: 0,
                                supplierID: this.supplierID
                            })
                        });

                        if (response.status === 419) {
                            this.scanner.lastResult = { error: 'Session expired. Please close scanner and reload the page.' };
                            return;
                        }

                        const data = await response.json();
                        if (data.success && data.product) {
                            this.scanner.productInfo = data.product;
                            this.scanner.expectedQty = data.expectedQty;
                            this.scanner.currentScanned = data.newQuantity;
                            this.scanner.scanType = data.scanType || 'unit';
                            this.scanner.caseUnits = data.caseUnits || 1;
                            this.scanner.step = 'quantity';
                            this.scanner.incrementQty = 1;
                            this.$nextTick(() => {
                                if (this.$refs.qtyInput) {
                                    this.$refs.qtyInput.focus();
                                    this.$refs.qtyInput.select();
                                }
                            });
                        } else if (data.success && !data.product) {
                            // Store the best outer code value: GTIN-14 if from GS1, otherwise the barcode itself
                            this.scanner.lastResult = { error: 'Product not found for barcode: ' + barcode, unknownBarcode: barcode };
                            this.scanner.barcode = '';
                            this.restartCameraIfActive();
                        } else {
                            this.scanner.lastResult = { error: data.message || 'Lookup failed' };
                        }
                    } catch (error) {
                        this.scanner.lastResult = { error: 'Network error. Try again.' };
                    } finally {
                        this.scanner.processing = false;
                    }
                },
                async submitScan() {
                    const barcode = this.scanner.barcode.trim();
                    const qty = this.scanner.incrementQty;
                    if (!barcode || qty < 1 || this.scanner.processing) return;

                    this.scanner.processing = true;
                    try {
                        const response = await fetch('{{ route("delivery-legacy.scan-increment") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                delID: this.deliveryId,
                                barcode: barcode,
                                quantity: qty,
                                supplierID: this.supplierID,
                            })
                        });

                        const data = await response.json();
                        if (data.success) {
                            const scanType = this.scanner.scanType || 'unit';
                            const caseUnits = this.scanner.caseUnits || 1;
                            const effectiveQty = scanType === 'case' ? qty * caseUnits : qty;

                            this.scanner.lastResult = {
                                product: data.product,
                                barcode: barcode,
                                addedQty: effectiveQty,
                                expectedQty: data.expectedQty,
                                newQuantity: data.newQuantity,
                                matchStatus: data.matchStatus,
                                scanType: scanType,
                                caseUnits: caseUnits,
                                casesAdded: scanType === 'case' ? qty : null
                            };
                            this.scanner.scanCount++;
                            this.scannerDirty = true;

                            this.scanner.history.unshift({
                                barcode: barcode,
                                name: data.product?.name || 'Unknown',
                                qty: effectiveQty,
                                matchStatus: data.matchStatus,
                                scanType: scanType,
                                caseUnits: caseUnits,
                                time: new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })
                            });
                            if (this.scanner.history.length > 20) this.scanner.history.pop();

                            // Back to scan step, ready for next product
                            this.resetScanner();
                        } else {
                            this.scanner.lastResult = { error: data.message || 'Save failed' };
                            this.scanner.processing = false;
                        }
                    } catch (error) {
                        this.scanner.lastResult = { error: 'Network error - scan not saved. Try again.' };
                        this.scanner.processing = false;
                    }
                },
                adjustIncrement(delta) {
                    this.scanner.incrementQty = Math.max(1, this.scanner.incrementQty + delta);
                },
                categoryVisible(cat) {
                    if (!this.showCategories || !cat) return true;
                    return this.selectedCategories.includes(cat);
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

    {{-- Lazy-resolve supplier images in background --}}
    @if($unresolvedCodes->isNotEmpty())
    <script>
        (function() {
            const unresolvedCodes = @js($unresolvedCodes);
            const supplierId = {{ (int) $supplierId }};
            const batchSize = 5;
            let resolved = 0;

            const indicator = document.createElement('div');
            indicator.className = 'fixed bottom-4 right-4 z-50 px-3 py-2 bg-blue-600 text-white text-sm rounded-lg shadow-lg flex items-center gap-2';
            indicator.innerHTML = `
                <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <span id="legacy-resolve-progress">Resolving images: 0/${unresolvedCodes.length}</span>
            `;
            document.body.appendChild(indicator);

            async function processBatch(startIndex) {
                const batch = unresolvedCodes.slice(startIndex, startIndex + batchSize);
                if (batch.length === 0) {
                    indicator.className = 'fixed bottom-4 right-4 z-50 px-3 py-2 bg-green-600 text-white text-sm rounded-lg shadow-lg';
                    indicator.innerHTML = `Images resolved: ${resolved} found`;
                    setTimeout(() => indicator.remove(), 3000);
                    return;
                }

                try {
                    const response = await fetch('{{ route("delivery-legacy.resolve-images-batch") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ supplier_codes: batch, supplier_id: supplierId })
                    });

                    const data = await response.json();

                    for (const [code, result] of Object.entries(data.results)) {
                        if (result.image_url) {
                            resolved++;
                            // Find all <img> tags whose src contains this supplier code and update them
                            document.querySelectorAll(`img[src*="/${code}_"]`).forEach(img => {
                                img.src = result.image_url;
                                // Also update the hover preview image if present
                                const hoverImg = img.closest('[x-data]')?.querySelector('img:not([src*="' + result.image_url + '"])');
                                if (hoverImg && hoverImg !== img) {
                                    hoverImg.src = result.image_url;
                                }
                            });
                            // Also update fallback icons where the image failed to load (hidden img)
                            document.querySelectorAll(`img[src*="${code}"]`).forEach(img => {
                                if (img.style.display === 'none') {
                                    img.src = result.image_url;
                                    img.style.display = '';
                                    // Hide the fallback icon
                                    const fallback = img.parentElement?.querySelector('.fallback-icon');
                                    if (fallback) fallback.style.display = 'none';
                                }
                            });
                        }
                    }
                } catch (err) {
                    console.warn('Image resolution batch failed:', err);
                }

                const processed = Math.min(startIndex + batchSize, unresolvedCodes.length);
                document.getElementById('legacy-resolve-progress').textContent = `Resolving images: ${processed}/${unresolvedCodes.length}`;

                setTimeout(() => processBatch(startIndex + batchSize), 500);
            }

            setTimeout(() => processBatch(0), 1000);
        })();
    </script>
    @endif

    @push('scripts')
        @vite(['resources/js/barcode-scanner.js'])
    @endpush
</x-admin-layout>
