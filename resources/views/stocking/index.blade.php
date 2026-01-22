<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Stocking
        </h2>
    </x-slot>

    <div class="py-4" x-data="stockingScanner()">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Barcode Input -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4 mb-4">
                <input type="text"
                       x-model="barcode"
                       x-ref="barcodeInput"
                       @keydown.enter="processBarcode"
                       placeholder="Scan barcode..."
                       class="w-full text-xl py-4 px-4 rounded-lg border-2 border-gray-300 focus:border-blue-500 focus:ring-blue-500 touch-manipulation"
                       autofocus>
                <p class="text-sm text-gray-500 mt-2">Scan a product to see its stock level</p>
            </div>

            <!-- Loading State -->
            <div x-show="loading" class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-8 mb-4 text-center">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
                <p class="text-gray-500 mt-2">Looking up product...</p>
            </div>

            <!-- Current Product Display -->
            <div x-show="currentProduct && !loading" x-transition class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 mb-4">
                <div class="text-center">
                    <!-- Product Name -->
                    <h3 class="text-lg sm:text-xl font-semibold text-gray-900 mb-1" x-text="currentProduct?.product?.name"></h3>

                    <!-- Barcode -->
                    <p class="text-sm text-gray-500 mb-4" x-text="currentProduct?.product?.code"></p>

                    <!-- Stock Count - Very Prominent -->
                    <div class="py-6">
                        <span class="text-6xl sm:text-7xl font-bold text-gray-900" x-text="Math.floor(currentProduct?.stock || 0)"></span>
                    </div>
                    <p class="text-lg text-gray-600">in stock</p>

                    <!-- Category (subtle) -->
                    <p class="text-sm text-gray-400 mt-4" x-text="currentProduct?.product?.category"></p>
                </div>
            </div>

            <!-- Not Found Message -->
            <div x-show="notFound && !loading" x-transition class="bg-red-50 border border-red-200 overflow-hidden shadow-sm sm:rounded-lg p-6 mb-4">
                <div class="text-center">
                    <p class="text-red-600 font-medium">Product not found</p>
                    <p class="text-sm text-red-500 mt-1">Barcode: <span x-text="lastSearchedBarcode"></span></p>
                </div>
            </div>

            <!-- Scan History -->
            <div x-show="scanHistory.length > 0" class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4">
                <div class="flex justify-between items-center mb-3">
                    <h4 class="text-sm font-medium text-gray-700">Recent Scans</h4>
                    <button @click="clearHistory" class="text-xs text-gray-400 hover:text-gray-600">Clear</button>
                </div>
                <ul class="divide-y divide-gray-100">
                    <template x-for="(item, index) in scanHistory" :key="index">
                        <li class="py-2 flex justify-between items-center">
                            <span class="text-sm text-gray-700 truncate flex-1 mr-2" x-text="item.name"></span>
                            <span class="text-sm font-semibold text-gray-900" x-text="Math.floor(item.stock)"></span>
                        </li>
                    </template>
                </ul>
            </div>
        </div>
    </div>

    <script>
        function stockingScanner() {
            return {
                barcode: '',
                loading: false,
                currentProduct: null,
                notFound: false,
                lastSearchedBarcode: '',
                scanHistory: JSON.parse(localStorage.getItem('stockingScanHistory') || '[]'),

                async processBarcode() {
                    if (!this.barcode.trim()) return;

                    this.loading = true;
                    this.notFound = false;
                    this.lastSearchedBarcode = this.barcode.trim();

                    try {
                        const response = await fetch('{{ route("stocking.lookup") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                            body: JSON.stringify({ barcode: this.barcode.trim() }),
                        });

                        const data = await response.json();

                        if (data.found) {
                            this.currentProduct = data;
                            this.notFound = false;
                            this.addToHistory(data);
                        } else {
                            this.currentProduct = null;
                            this.notFound = true;
                        }
                    } catch (error) {
                        console.error('Lookup error:', error);
                        this.currentProduct = null;
                        this.notFound = true;
                    } finally {
                        this.loading = false;
                        this.barcode = '';
                        this.$refs.barcodeInput.focus();
                    }
                },

                addToHistory(data) {
                    // Add to beginning of history
                    this.scanHistory.unshift({
                        name: data.product.name,
                        code: data.product.code,
                        stock: data.stock,
                        time: new Date().toISOString(),
                    });

                    // Keep only last 10 items
                    if (this.scanHistory.length > 10) {
                        this.scanHistory = this.scanHistory.slice(0, 10);
                    }

                    this.saveHistory();
                },

                saveHistory() {
                    localStorage.setItem('stockingScanHistory', JSON.stringify(this.scanHistory));
                },

                clearHistory() {
                    this.scanHistory = [];
                    localStorage.removeItem('stockingScanHistory');
                },
            };
        }
    </script>
</x-app-layout>
