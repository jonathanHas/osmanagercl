<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-lg text-gray-800 leading-tight py-1">
                Vouchers
            </h2>
            <div class="flex gap-2 text-sm">
                <a href="{{ route('vouchers.generate') }}" class="text-blue-600 hover:text-blue-800">Generate</a>
                <a href="{{ route('vouchers.list') }}" class="text-blue-600 hover:text-blue-800">All vouchers</a>
            </div>
        </div>
    </x-slot>

    <div class="py-2" x-data="voucherTill()">
        <div class="max-w-2xl mx-auto px-2 sm:px-4 lg:px-6">
            <!-- Barcode Input -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-3 mb-3">
                <div class="flex gap-2">
                    <input type="text"
                           x-model="code"
                           x-ref="codeInput"
                           @keydown.enter="lookup()"
                           placeholder="Scan voucher..."
                           :inputmode="keyboardEnabled ? 'text' : 'none'"
                           autocapitalize="characters"
                           class="flex-1 text-lg py-3 px-3 rounded-lg border-2 border-gray-300 focus:border-blue-500 focus:ring-blue-500 touch-manipulation uppercase"
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
                    <button @click="keyboardEnabled = !keyboardEnabled; $nextTick(() => $refs.codeInput.focus())"
                            class="px-3 py-2 rounded-lg border-2 touch-manipulation"
                            :class="keyboardEnabled ? 'bg-blue-100 border-blue-500 text-blue-700' : 'bg-gray-100 border-gray-300 text-gray-500'"
                            :title="keyboardEnabled ? 'Hide keyboard' : 'Show keyboard'">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h18a1 1 0 011 1v12a1 1 0 01-1 1H3a1 1 0 01-1-1V6a1 1 0 011-1zm3 4h2m2 0h2m2 0h2m2 0h2M6 12h2m2 0h2m2 0h2m2 0h2M8 16h8"/>
                        </svg>
                    </button>
                </div>
                <div class="flex items-center justify-between mt-2">
                    <p class="text-sm text-gray-500">Scan or type a voucher code</p>
                    <!-- Photo fallback (works over plain HTTP) -->
                    <label class="text-sm text-blue-600 hover:text-blue-800 cursor-pointer touch-manipulation">
                        Use photo
                        <input type="file" accept="image/*" capture="environment" class="hidden" @change="onPhotoSelected($event)">
                    </label>
                </div>

                <!-- Camera Viewport -->
                <div x-show="scanner.cameraVisible" x-transition class="mt-3">
                    <div id="voucher-scanner" class="rounded-lg overflow-hidden bg-gray-900" style="min-height: 220px;"></div>
                    <p class="text-gray-500 text-sm text-center mt-2" x-text="scanner.cameraStatus"></p>
                </div>
            </div>

            <!-- Loading State -->
            <div x-show="loading" class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4 mb-3 text-center">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
                <p class="text-gray-500 mt-2">Looking up voucher...</p>
            </div>

            <!-- Feedback -->
            <div x-show="feedback" x-transition class="mb-3 p-3 rounded-lg text-center"
                 :class="feedbackSuccess ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                <span x-text="feedback"></span>
            </div>

            <!-- ACTIVATE: unknown or inactive voucher -->
            <div x-show="mode === 'activate' && !loading" x-transition class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4 mb-3">
                <div class="text-center">
                    <p class="text-sm text-gray-500">New voucher</p>
                    <p class="text-lg font-mono font-semibold text-gray-900 mb-4" x-text="code"></p>
                    <p class="text-sm text-gray-600 mb-2">Enter starting balance to activate:</p>
                    <div class="flex items-center justify-center gap-2 mb-4">
                        <span class="text-2xl font-bold text-gray-700">€</span>
                        <input type="number"
                               x-model="startingBalance"
                               min="0.01"
                               step="0.01"
                               inputmode="decimal"
                               placeholder="0.00"
                               class="w-40 text-center text-2xl py-3 rounded-lg border-2 border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div class="flex gap-2 justify-center">
                        <button @click="activate()"
                                :disabled="processing || !startingBalance || startingBalance <= 0"
                                class="px-6 py-3 bg-green-600 hover:bg-green-700 disabled:bg-gray-400 text-white rounded-lg font-medium text-lg touch-manipulation">
                            <span x-show="!processing">Activate</span>
                            <span x-show="processing">Activating...</span>
                        </button>
                        <button @click="reset()" class="px-4 py-3 bg-gray-200 hover:bg-gray-300 rounded-lg font-medium touch-manipulation">Cancel</button>
                    </div>
                </div>
            </div>

            <!-- ACTIVE: show balance + deduct -->
            <div x-show="mode === 'active' && !loading" x-transition class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4 mb-3">
                <div class="text-center">
                    <p class="text-sm font-mono text-gray-500 mb-1" x-text="code"></p>
                    <p class="text-sm text-gray-600">Balance</p>
                    <div class="py-2">
                        <span class="text-5xl sm:text-6xl font-bold text-green-700" x-text="'€' + balance.toFixed(2)"></span>
                    </div>

                    <div class="border-t pt-4 mt-2">
                        <p class="text-sm text-gray-500 mb-2">Deduct amount:</p>
                        <div class="flex items-center justify-center gap-2 mb-3">
                            <span class="text-2xl font-bold text-gray-700">€</span>
                            <input type="number"
                                   x-model="deductAmount"
                                   min="0.01"
                                   step="0.01"
                                   :max="balance"
                                   inputmode="decimal"
                                   placeholder="0.00"
                                   class="w-40 text-center text-2xl py-3 rounded-lg border-2 border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <button @click="deductAmount = balance.toFixed(2)"
                                class="text-sm text-blue-600 hover:text-blue-800 mb-3 touch-manipulation">Use full balance</button>

                        <div class="flex gap-2 justify-center">
                            <button @click="deduct()"
                                    :disabled="processing || !deductAmount || deductAmount <= 0 || Number(deductAmount) > balance"
                                    class="px-6 py-3 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 text-white rounded-lg font-medium text-lg touch-manipulation">
                                <span x-show="!processing">Deduct</span>
                                <span x-show="processing">Processing...</span>
                            </button>
                            <button @click="reset()" class="px-4 py-3 bg-gray-200 hover:bg-gray-300 rounded-lg font-medium touch-manipulation">Done</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- EXHAUSTED -->
            <div x-show="mode === 'exhausted' && !loading" x-transition class="bg-gray-50 border border-gray-200 overflow-hidden shadow-sm sm:rounded-lg p-6 mb-3">
                <div class="text-center">
                    <p class="text-sm font-mono text-gray-500 mb-2" x-text="code"></p>
                    <p class="text-2xl font-bold text-gray-700">Voucher exhausted</p>
                    <p class="text-sm text-gray-500 mt-1">€0.00 remaining</p>
                    <button @click="reset()" class="mt-4 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium touch-manipulation">Scan next</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden container required by BarcodeScanner.scanFile() photo decoding -->
    <div id="barcode-scanner-temp" style="display:none;"></div>

    <script>
        function voucherTill() {
            return {
                code: '',
                loading: false,
                processing: false,
                mode: 'idle', // idle | activate | active | exhausted
                balance: 0,
                startingBalance: '',
                deductAmount: '',
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
                            'voucher-scanner',
                            (decodedText) => this.onScannerDetected(decodedText),
                            (error) => {}
                        ).then(() => {
                            this.scanner.cameraActive = true;
                            this.scanner.cameraWasActive = true;
                            this.scanner.cameraStatus = 'Point camera at voucher barcode';
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
                    text = text.replace(/[\x1D]/g, '|');
                    return text;
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

                    this.stopScannerCamera();
                    this.code = this.parseBarcode(text);
                    this.lookup();
                },

                async onPhotoSelected(event) {
                    const file = event.target.files[0];
                    if (!file) return;
                    if (!window.BarcodeScanner) {
                        this.flash('Scanner module not loaded.', false);
                        event.target.value = '';
                        return;
                    }
                    this.loading = true;
                    try {
                        const decoded = await window.BarcodeScanner.scanFile(file);
                        this.code = this.parseBarcode(decoded);
                        await this.lookup();
                    } catch (e) {
                        this.flash('Could not read a barcode from that photo.', false);
                    } finally {
                        this.loading = false;
                        event.target.value = '';
                    }
                },

                async lookup() {
                    const code = this.code.trim();
                    if (!code) return;
                    this.code = code;

                    this.loading = true;
                    this.feedback = null;
                    this.mode = 'idle';

                    try {
                        const response = await fetch('{{ route("vouchers.lookup") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                            body: JSON.stringify({ code }),
                        });
                        const data = await response.json();

                        if (!data.found || data.status === 'inactive') {
                            // Unknown or not-yet-issued → offer to activate
                            this.startingBalance = '';
                            this.mode = 'activate';
                        } else if (data.status === 'active') {
                            this.balance = Number(data.current_balance);
                            this.deductAmount = '';
                            this.mode = 'active';
                        } else if (data.status === 'exhausted') {
                            this.mode = 'exhausted';
                        }
                    } catch (error) {
                        console.error('Lookup error:', error);
                        this.flash('Lookup failed. Please try again.', false);
                    } finally {
                        this.loading = false;
                    }
                },

                async activate() {
                    if (!this.startingBalance || this.startingBalance <= 0) return;
                    this.processing = true;
                    this.feedback = null;
                    try {
                        const response = await fetch('{{ route("vouchers.activate") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                            body: JSON.stringify({ code: this.code.trim(), starting_balance: this.startingBalance }),
                        });
                        const data = await response.json();

                        if (data.success) {
                            this.balance = Number(data.current_balance);
                            this.flash('Voucher activated with €' + this.balance.toFixed(2), true);
                            this.deductAmount = '';
                            this.mode = 'active';
                            this.restartCameraIfActive();
                        } else {
                            this.flash(data.message || 'Failed to activate voucher.', false);
                        }
                    } catch (error) {
                        console.error('Activate error:', error);
                        this.flash('Failed to activate voucher.', false);
                    } finally {
                        this.processing = false;
                    }
                },

                async deduct() {
                    if (!this.deductAmount || this.deductAmount <= 0) return;
                    this.processing = true;
                    this.feedback = null;
                    try {
                        const response = await fetch('{{ route("vouchers.deduct") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                            body: JSON.stringify({ code: this.code.trim(), amount: this.deductAmount }),
                        });
                        const data = await response.json();

                        if (data.success) {
                            this.balance = Number(data.new_balance);
                            this.flash('Deducted. New balance €' + this.balance.toFixed(2), true);
                            this.deductAmount = '';
                            if (data.status === 'exhausted') {
                                this.mode = 'exhausted';
                            }
                            this.restartCameraIfActive();
                        } else {
                            if (typeof data.current_balance !== 'undefined') {
                                this.balance = Number(data.current_balance);
                            }
                            this.flash(data.message || 'Failed to deduct.', false);
                        }
                    } catch (error) {
                        console.error('Deduct error:', error);
                        this.flash('Failed to deduct.', false);
                    } finally {
                        this.processing = false;
                    }
                },

                flash(message, success) {
                    this.feedback = message;
                    this.feedbackSuccess = success;
                    setTimeout(() => { this.feedback = null; }, 4000);
                },

                reset() {
                    this.code = '';
                    this.mode = 'idle';
                    this.balance = 0;
                    this.startingBalance = '';
                    this.deductAmount = '';
                    this.scanner.lastScannedBarcode = '';
                    this.$nextTick(() => this.$refs.codeInput.focus());
                    this.restartCameraIfActive();
                },
            };
        }
    </script>

    @push('scripts')
        @vite(['resources/js/barcode-scanner.js'])
    @endpush
</x-admin-layout>
