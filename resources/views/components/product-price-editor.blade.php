@props(['product'])

@php
    $vatRate = $product->getVatRate();
    $currentNetPrice = $product->PRICESELL;
    $currentGrossPrice = $currentNetPrice * (1 + $vatRate);
    $costPrice = $product->PRICEBUY;
    $margin = $currentNetPrice - $costPrice;
    $marginPercent = $costPrice > 0 ? ($margin / $costPrice) * 100 : 0;
@endphp

<div id="priceEditModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>

        <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
            <form method="POST" action="{{ route('products.update-price', $product->ID) }}" id="priceUpdateForm">
                @csrf
                @method('PATCH')
                
                <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="w-full">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/>
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"/>
                                    </svg>
                                    Update Product Price
                                </h3>
                                <button type="button" onclick="closePriceEditorModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>

                            <!-- Input Mode Toggle -->
                            <div class="mb-6">
                                <div class="flex items-center justify-center">
                                    <div class="bg-gray-100 dark:bg-gray-700 rounded-lg p-1 flex">
                                        <button type="button" 
                                                id="grossModeBtn" 
                                                onclick="switchToGrossMode()" 
                                                class="px-4 py-2 text-sm font-medium rounded-md transition-colors duration-200 bg-white dark:bg-gray-600 text-gray-900 dark:text-gray-100 shadow-sm">
                                            Gross Price (inc VAT)
                                        </button>
                                        <button type="button" 
                                                id="netModeBtn" 
                                                onclick="switchToNetMode()" 
                                                class="px-4 py-2 text-sm font-medium rounded-md transition-colors duration-200 text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100">
                                            Net Price (ex VAT)
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Price Input Section -->
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <!-- Left Column: Price Input -->
                                <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4 border-2 border-green-200 dark:border-green-700">
                                    <div id="grossPriceInput">
                                        <label for="gross_price" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Selling Price (inc VAT) *
                                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                Gross Price Mode
                                            </span>
                                        </label>
                                        <input type="number" 
                                               id="gross_price" 
                                               name="gross_price"
                                               value="{{ number_format($currentGrossPrice, 4) }}" 
                                               step="0.0001" 
                                               min="0" 
                                               max="999999.9999"
                                               oninput="calculateFromGross()"
                                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:border-green-500 focus:ring-green-500 text-lg font-semibold">
                                        <p class="mt-1 text-sm text-green-600 dark:text-green-400">
                                            Enter the final selling price including VAT
                                        </p>
                                    </div>

                                    <div id="netPriceInput" class="hidden">
                                        <label for="net_price" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Selling Price (ex VAT) *
                                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                Net Price Mode
                                            </span>
                                        </label>
                                        <input type="number" 
                                               id="net_price" 
                                               name="net_price"
                                               value="{{ number_format($currentNetPrice, 4) }}" 
                                               step="0.0001" 
                                               min="0" 
                                               max="999999.9999"
                                               oninput="calculateFromNet()"
                                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:border-blue-500 focus:ring-blue-500 text-lg font-semibold">
                                        <p class="mt-1 text-sm text-blue-600 dark:text-blue-400">
                                            Enter the net selling price excluding VAT
                                        </p>
                                    </div>

                                    <!-- Hidden field for actual submission -->
                                    <input type="hidden" id="final_net_price" name="final_net_price" value="{{ $currentNetPrice }}">
                                    <input type="hidden" name="price_input_mode" id="price_input_mode" value="gross">
                                </div>

                                <!-- Right Column: Pricing Breakdown -->
                                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Pricing Breakdown</h4>
                                    
                                    <div class="space-y-2 text-sm">
                                        <div class="flex justify-between">
                                            <span class="text-gray-600 dark:text-gray-400">Cost Price:</span>
                                            <span id="breakdown-cost" class="font-medium">€{{ number_format($costPrice, 2) }}</span>
                                        </div>
                                        <hr class="border-gray-300 dark:border-gray-600">
                                        <div class="flex justify-between">
                                            <span class="text-gray-600 dark:text-gray-400">Net Price (ex VAT):</span>
                                            <span id="breakdown-net" class="font-medium text-blue-600 dark:text-blue-400">€{{ number_format($currentNetPrice, 2) }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600 dark:text-gray-400">VAT Amount:</span>
                                            <span id="breakdown-vat" class="font-medium">€{{ number_format($currentNetPrice * $vatRate, 2) }} (<span id="vat-rate">{{ number_format($vatRate * 100, 1) }}%</span>)</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600 dark:text-gray-400">Gross Price (inc VAT):</span>
                                            <span id="breakdown-gross" class="font-medium text-green-600 dark:text-green-400">€{{ number_format($currentGrossPrice, 2) }}</span>
                                        </div>
                                        <hr class="border-gray-300 dark:border-gray-600">
                                        <div class="flex justify-between">
                                            <span class="text-gray-700 dark:text-gray-300 font-medium">Profit Margin:</span>
                                            <div class="text-right">
                                                <div id="margin-amount" class="font-semibold text-green-600 dark:text-green-400">€{{ number_format($margin, 2) }}</div>
                                                <div id="margin-percentage" class="text-sm text-green-600 dark:text-green-400">{{ number_format($marginPercent, 1) }}%</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Price Change Summary -->
                            <div id="priceChangeSummary" class="mt-4 p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg hidden">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                    </svg>
                                    <div>
                                        <p class="text-sm font-medium text-blue-800 dark:text-blue-200">Price Change</p>
                                        <p id="changeSummaryText" class="text-sm text-blue-700 dark:text-blue-300"></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" 
                            id="updatePriceBtn"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:ml-3 sm:w-auto sm:text-sm transition duration-200">
                        Update Price
                    </button>
                    <button type="button" 
                            onclick="closePriceEditorModal()" 
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm transition duration-200">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Price editor state - ensure proper number formatting
let currentMode = 'gross';
const priceEditorVatRate = {{ number_format($vatRate, 4, '.', '') }};
const originalNetPrice = {{ number_format($currentNetPrice, 4, '.', '') }};
const originalGrossPrice = {{ number_format($currentGrossPrice, 4, '.', '') }};
const costPrice = {{ number_format($costPrice, 4, '.', '') }};

function openPriceEditor() {
    document.getElementById('priceEditModal').classList.remove('hidden');
    // Focus on the appropriate input
    setTimeout(() => {
        if (currentMode === 'gross') {
            document.getElementById('gross_price').focus();
        } else {
            document.getElementById('net_price').focus();
        }
    }, 100);
}

function closePriceEditor() {
    const modal = document.getElementById('priceEditModal');
    if (modal) {
        modal.classList.add('hidden');
        console.log('Modal closed');
    }
    
    // Reset to original values
    const grossInput = document.getElementById('gross_price');
    const netInput = document.getElementById('net_price');
    
    if (grossInput) {
        grossInput.value = originalGrossPrice.toFixed(4);
    }
    if (netInput) {
        netInput.value = originalNetPrice.toFixed(4);
    }
    
    if (typeof updateBreakdown === 'function') {
        updateBreakdown();
    }
}

function switchToGrossMode() {
    currentMode = 'gross';
    document.getElementById('price_input_mode').value = 'gross';
    
    // Update UI
    document.getElementById('grossPriceInput').classList.remove('hidden');
    document.getElementById('netPriceInput').classList.add('hidden');
    
    // Update button styles
    document.getElementById('grossModeBtn').className = 'px-4 py-2 text-sm font-medium rounded-md transition-colors duration-200 bg-white dark:bg-gray-600 text-gray-900 dark:text-gray-100 shadow-sm';
    document.getElementById('netModeBtn').className = 'px-4 py-2 text-sm font-medium rounded-md transition-colors duration-200 text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100';
    
    calculateFromGross();
}

function switchToNetMode() {
    currentMode = 'net';
    document.getElementById('price_input_mode').value = 'net';
    
    // Update UI
    document.getElementById('grossPriceInput').classList.add('hidden');
    document.getElementById('netPriceInput').classList.remove('hidden');
    
    // Update button styles
    document.getElementById('netModeBtn').className = 'px-4 py-2 text-sm font-medium rounded-md transition-colors duration-200 bg-white dark:bg-gray-600 text-gray-900 dark:text-gray-100 shadow-sm';
    document.getElementById('grossModeBtn').className = 'px-4 py-2 text-sm font-medium rounded-md transition-colors duration-200 text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100';
    
    calculateFromNet();
}

function calculateFromGross() {
    const grossPrice = parseFloat(document.getElementById('gross_price').value) || 0;
    const netPrice = priceEditorVatRate > 0 ? grossPrice / (1 + priceEditorVatRate) : grossPrice;
    
    // Update net price field
    document.getElementById('net_price').value = netPrice.toFixed(4);
    document.getElementById('final_net_price').value = netPrice.toFixed(4);
    
    updateBreakdown();
    updateChangeSummary();
}

function calculateFromNet() {
    const netPrice = parseFloat(document.getElementById('net_price').value) || 0;
    const grossPrice = netPrice * (1 + priceEditorVatRate);
    
    // Update gross price field
    document.getElementById('gross_price').value = grossPrice.toFixed(4);
    document.getElementById('final_net_price').value = netPrice.toFixed(4);
    
    updateBreakdown();
    updateChangeSummary();
}

function updateBreakdown() {
    const netPrice = parseFloat(document.getElementById('final_net_price').value) || 0;
    const grossPrice = netPrice * (1 + priceEditorVatRate);
    const vatAmount = grossPrice - netPrice;
    const margin = netPrice - costPrice;
    const marginPercent = netPrice > 0 ? (margin / netPrice) * 100 : 0;
    
    // Update breakdown display
    document.getElementById('breakdown-net').textContent = '€' + netPrice.toFixed(2);
    document.getElementById('breakdown-gross').textContent = '€' + grossPrice.toFixed(2);
    document.getElementById('breakdown-vat').textContent = '€' + vatAmount.toFixed(2) + ' (' + (priceEditorVatRate * 100).toFixed(1) + '%)';
    document.getElementById('margin-amount').textContent = '€' + margin.toFixed(2);
    document.getElementById('margin-percentage').textContent = marginPercent.toFixed(1) + '%';
    
    // Color code margin
    const marginAmountEl = document.getElementById('margin-amount');
    const marginPercentageEl = document.getElementById('margin-percentage');
    
    if (marginPercent < 10) {
        marginAmountEl.className = 'font-semibold text-red-600 dark:text-red-400';
        marginPercentageEl.className = 'text-sm text-red-600 dark:text-red-400';
    } else if (marginPercent < 20) {
        marginAmountEl.className = 'font-semibold text-yellow-600 dark:text-yellow-400';
        marginPercentageEl.className = 'text-sm text-yellow-600 dark:text-yellow-400';
    } else {
        marginAmountEl.className = 'font-semibold text-green-600 dark:text-green-400';
        marginPercentageEl.className = 'text-sm text-green-600 dark:text-green-400';
    }
}

function updateChangeSummary() {
    const newNetPrice = parseFloat(document.getElementById('final_net_price').value) || 0;
    const newGrossPrice = newNetPrice * (1 + priceEditorVatRate);
    const netChange = newNetPrice - originalNetPrice;
    const grossChange = newGrossPrice - originalGrossPrice;
    
    const summaryEl = document.getElementById('priceChangeSummary');
    const textEl = document.getElementById('changeSummaryText');
    
    if (Math.abs(netChange) < 0.001) {
        summaryEl.classList.add('hidden');
    } else {
        summaryEl.classList.remove('hidden');
        const direction = netChange > 0 ? 'increase' : 'decrease';
        const netChangeAbs = Math.abs(netChange);
        const grossChangeAbs = Math.abs(grossChange);
        
        textEl.textContent = `Price ${direction}: €${netChangeAbs.toFixed(2)} net (€${grossChangeAbs.toFixed(2)} gross)`;
    }
}

// Make functions globally available immediately
window.openPriceEditor = openPriceEditor;
window.closePriceEditor = closePriceEditor;

// Add simple global close function for onclick
window.closePriceEditorModal = function() {
    closePriceEditor();
};

// Initialize on load
document.addEventListener('DOMContentLoaded', function() {
    console.log('Price editor component loaded');
    if (typeof updateBreakdown === 'function') {
        updateBreakdown();
    }
});
</script>