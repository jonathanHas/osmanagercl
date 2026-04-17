<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-lg text-gray-800 leading-tight py-1">
            Stocking
        </h2>
    </x-slot>

    <div class="py-2" x-data="stockingScanner()">
        <div class="max-w-2xl mx-auto px-2 sm:px-4 lg:px-6">
            <!-- Barcode Input -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-3 mb-3">
                <div class="flex gap-2">
                    <input type="text"
                           x-model="barcode"
                           x-ref="barcodeInput"
                           @keydown.enter="processBarcode"
                           placeholder="Scan barcode..."
                           :inputmode="keyboardEnabled ? 'text' : 'none'"
                           class="flex-1 text-lg py-3 px-3 rounded-lg border-2 border-gray-300 focus:border-blue-500 focus:ring-blue-500 touch-manipulation"
                           autofocus>
                    <button @click="toggleScannerCamera()"
                            class="px-3 py-2 rounded-lg border-2 touch-manipulation"
                            :class="scanner.cameraActive ? 'bg-red-100 border-red-500 text-red-700' : 'bg-green-100 border-green-500 text-green-700'"
                            :title="scanner.cameraActive ? 'Stop camera' : 'Scan with camera'">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </button>
                    <button @click="keyboardEnabled = !keyboardEnabled; $nextTick(() => $refs.barcodeInput.focus())"
                            class="px-3 py-2 rounded-lg border-2 touch-manipulation"
                            :class="keyboardEnabled ? 'bg-blue-100 border-blue-500 text-blue-700' : 'bg-gray-100 border-gray-300 text-gray-500'"
                            :title="keyboardEnabled ? 'Hide keyboard' : 'Show keyboard'">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h18a1 1 0 011 1v12a1 1 0 01-1 1H3a1 1 0 01-1-1V6a1 1 0 011-1zm3 4h2m2 0h2m2 0h2m2 0h2M6 12h2m2 0h2m2 0h2m2 0h2M8 16h8"/>
                        </svg>
                    </button>
                </div>
                <p class="text-sm text-gray-500 mt-2">Scan a product to see its stock level</p>

                <!-- Camera Viewport -->
                <div x-show="scanner.cameraVisible" x-transition class="mt-3">
                    <div id="stocking-scanner" class="rounded-lg overflow-hidden bg-gray-900" style="min-height: 220px;"></div>
                    <p class="text-gray-500 text-sm text-center mt-2" x-text="scanner.cameraStatus"></p>
                </div>
            </div>

            <!-- Loading State -->
            <div x-show="loading" class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4 mb-3 text-center">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
                <p class="text-gray-500 mt-2">Looking up product...</p>
            </div>

            <!-- Current Product Display -->
            <div x-show="currentProduct && !loading" x-transition class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4 mb-3">
                <div class="text-center">
                    <!-- Product Name -->
                    <h3 class="text-lg sm:text-xl font-semibold text-gray-900 mb-1" x-text="currentProduct?.product?.name"></h3>

                    <!-- Barcode -->
                    <p class="text-sm text-gray-500 mb-2" x-text="currentProduct?.product?.code"></p>

                    <!-- Stock Count - Very Prominent -->
                    <div class="py-2">
                        <span class="text-5xl sm:text-6xl font-bold text-gray-900" x-text="Math.floor(currentProduct?.stock || 0)"></span>
                    </div>
                    <p class="text-base text-gray-600 mb-4">in stock</p>

                    <!-- Stock Adjustment -->
                    <div class="border-t pt-4">
                        <p class="text-sm text-gray-500 mb-2">Adjust stock:</p>
                        <div class="flex items-center justify-center gap-2 mb-3">
                            <button @click="newStock = Math.max(0, (newStock ?? currentProduct?.stock ?? 0) - 1)"
                                    class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg text-xl font-bold touch-manipulation">-</button>
                            <input type="number"
                                   x-model="newStock"
                                   min="0"
                                   step="1"
                                   class="w-24 text-center text-xl py-2 rounded-lg border-2 border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                            <button @click="newStock = (newStock ?? currentProduct?.stock ?? 0) + 1"
                                    class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg text-xl font-bold touch-manipulation">+</button>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex gap-2 justify-center">
                            <button @click="updateStock"
                                    :disabled="updating || newStock === null || newStock == currentProduct?.stock"
                                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 text-white rounded-lg font-medium touch-manipulation">
                                <span x-show="!updating">Update Stock</span>
                                <span x-show="updating">Updating...</span>
                            </button>
                            <button @click="addToLabelQueue"
                                    :disabled="addingLabel"
                                    class="px-4 py-2 bg-green-600 hover:bg-green-700 disabled:bg-gray-400 text-white rounded-lg font-medium touch-manipulation">
                                <span x-show="!addingLabel">Add to Labels</span>
                                <span x-show="addingLabel">Adding...</span>
                            </button>
                        </div>
                    </div>

                    <!-- Category (subtle) -->
                    <p class="text-sm text-gray-400 mt-3" x-text="currentProduct?.product?.category"></p>
                </div>
            </div>

            <!-- Success/Error Feedback -->
            <div x-show="feedback" x-transition class="mb-3 p-3 rounded-lg text-center"
                 :class="feedbackSuccess ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                <span x-text="feedback"></span>
            </div>

            <!-- Not Found Message -->
            <div x-show="notFound && !loading" x-transition class="bg-red-50 border border-red-200 overflow-hidden shadow-sm sm:rounded-lg p-4 mb-3">
                <div class="text-center">
                    <p class="text-red-600 font-medium">Product not found</p>
                    <p class="text-sm text-red-500 mt-1">Barcode: <span x-text="lastSearchedBarcode"></span></p>
                </div>
            </div>

            <!-- Scan History -->
            <div x-show="scanHistory.length > 0" class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-3">
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
                newStock: null,
                updating: false,
                addingLabel: false,
                keyboardEnabled: false,
                feedback: null,
                feedbackSuccess: true,
                scanner: {
                    cameraActive: false,
                    cameraVisible: false,
                    cameraStatus: '',
                    lastScannedBarcode: '',
                    lastScanTime: 0,
                    cameraWasActive: false,
                },

                toggleScannerCamera() {
                    if (this.scanner.cameraActive) {
                        this.scanner.cameraWasActive = false;
                        this.stopScannerCamera();
                        return;
                    }
                    if (!window.BarcodeScanner) {
                        this.scanner.cameraStatus = 'Scanner module not loaded. Ensure HTTPS is enabled.';
                        this.scanner.cameraVisible = true;
                        return;
                    }
                    this.scanner.cameraVisible = true;
                    this.scanner.cameraStatus = 'Starting camera...';
                    this.$nextTick(() => {
                        window.BarcodeScanner.startScanner(
                            'stocking-scanner',
                            (decodedText) => this.onScannerDetected(decodedText),
                            (error) => {}
                        ).then(() => {
                            this.scanner.cameraActive = true;
                            this.scanner.cameraWasActive = true;
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

                restartCameraIfActive() {
                    if (this.scanner.cameraWasActive && !this.scanner.cameraActive) {
                        this.$nextTick(() => this.toggleScannerCamera());
                    }
                },

                parseBarcode(raw) {
                    let text = raw.trim();
                    if (text.startsWith(']C1')) text = text.substring(3);
                    if (text.startsWith(']d2')) text = text.substring(3);
                    if (text.startsWith(']e0')) text = text.substring(3);
                    text = text.replace(/[\x1D\u001D]/g, '|');
                    const gtin14Match = text.match(/(?:^|\|)01(\d{14})/);
                    if (gtin14Match) {
                        return { barcode: gtin14Match[1], isGS1: true, raw };
                    }
                    return { barcode: text, isGS1: false, raw };
                },

                onScannerDetected(text) {
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

                    const now = Date.now();
                    if (text === this.scanner.lastScannedBarcode && now - this.scanner.lastScanTime < 2000) {
                        return;
                    }
                    this.scanner.lastScannedBarcode = text;
                    this.scanner.lastScanTime = now;

                    const parsed = this.parseBarcode(text);
                    this.stopScannerCamera();
                    this.barcode = parsed.barcode;
                    this.processBarcode();
                },

                async processBarcode() {
                    if (!this.barcode.trim()) return;

                    this.loading = true;
                    this.notFound = false;
                    this.feedback = null;
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
                            this.newStock = Math.floor(data.stock);
                            this.notFound = false;
                            this.addToHistory(data);
                        } else {
                            this.currentProduct = null;
                            this.newStock = null;
                            this.notFound = true;
                        }
                    } catch (error) {
                        console.error('Lookup error:', error);
                        this.currentProduct = null;
                        this.newStock = null;
                        this.notFound = true;
                    } finally {
                        this.loading = false;
                        this.barcode = '';
                        this.$refs.barcodeInput.focus();
                    }
                },

                async updateStock() {
                    if (!this.currentProduct || this.newStock === null) return;

                    this.updating = true;
                    this.feedback = null;

                    try {
                        const response = await fetch('{{ route("stocking.update-stock") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                            body: JSON.stringify({
                                barcode: this.currentProduct.product.code,
                                new_stock: this.newStock,
                            }),
                        });

                        const data = await response.json();

                        if (data.success) {
                            this.currentProduct.stock = data.stock;
                            this.feedback = 'Stock updated successfully';
                            this.feedbackSuccess = true;
                            // Update history entry
                            this.updateHistoryStock(this.currentProduct.product.code, data.stock);
                            this.restartCameraIfActive();
                        } else {
                            this.feedback = data.message || 'Failed to update stock';
                            this.feedbackSuccess = false;
                        }
                    } catch (error) {
                        console.error('Update error:', error);
                        this.feedback = 'Failed to update stock';
                        this.feedbackSuccess = false;
                    } finally {
                        this.updating = false;
                        this.$refs.barcodeInput.focus();
                        // Clear feedback after 3 seconds
                        setTimeout(() => { this.feedback = null; }, 3000);
                    }
                },

                async addToLabelQueue() {
                    if (!this.currentProduct) return;

                    this.addingLabel = true;
                    this.feedback = null;

                    try {
                        const response = await fetch('{{ route("labels.scan") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                            body: JSON.stringify({
                                barcode: this.currentProduct.product.code,
                            }),
                        });

                        const data = await response.json();

                        if (data.success) {
                            this.feedback = 'Added to label queue';
                            this.feedbackSuccess = true;
                            this.restartCameraIfActive();
                        } else {
                            this.feedback = data.message || 'Failed to add to labels';
                            this.feedbackSuccess = false;
                        }
                    } catch (error) {
                        console.error('Label queue error:', error);
                        this.feedback = 'Failed to add to labels';
                        this.feedbackSuccess = false;
                    } finally {
                        this.addingLabel = false;
                        this.$refs.barcodeInput.focus();
                        // Clear feedback after 3 seconds
                        setTimeout(() => { this.feedback = null; }, 3000);
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

                updateHistoryStock(code, newStock) {
                    const item = this.scanHistory.find(h => h.code === code);
                    if (item) {
                        item.stock = newStock;
                        this.saveHistory();
                    }
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

    @push('scripts')
        @vite(['resources/js/barcode-scanner.js'])
    @endpush
</x-admin-layout>
