<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Upload Zebra Label</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8" x-data="zebraUpload()">
            @include('labels._nav', ['current' => 'zebra'])

            @if ($errors->any())
                <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-md text-red-700 text-sm">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('zebra-labels.store') }}" enctype="multipart/form-data" id="labelForm">
                    @csrf

                    {{-- Input Mode Tabs --}}
                    <div class="flex border-b border-gray-200 mb-6">
                        <button type="button" @click="mode = 'file'" :class="mode === 'file' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="px-4 py-2 text-sm font-medium border-b-2 transition">Upload .prn/.zpl File</button>
                        <button type="button" @click="mode = 'paste'" :class="mode === 'paste' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="px-4 py-2 text-sm font-medium border-b-2 transition">Paste ZPL Code</button>
                    </div>

                    {{-- File Upload --}}
                    <div x-show="mode === 'file'" class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">ZPL/PRN File</label>
                        <input type="file" name="zpl_file" accept=".prn,.zpl,.txt" @change="handleFileUpload($event)"
                            class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                        <p class="text-xs text-gray-400 mt-1">Accepts .prn, .zpl, .txt files exported from ZebraDesigner</p>
                    </div>

                    {{-- Paste ZPL --}}
                    <div x-show="mode === 'paste'" class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">ZPL Code</label>
                        <textarea name="zpl_content" x-model="zplContent" rows="8" placeholder="Paste ZPL code starting with ^XA..."
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs font-mono"></textarea>
                    </div>

                    {{-- Metadata --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                            <input type="text" name="name" x-model="labelName" value="{{ old('name') }}" required
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Barcode / Product Code</label>
                            <input type="text" name="product_code" x-model="productCode" value="{{ old('product_code') }}" placeholder="Auto-extracted from ZPL"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm font-mono">
                        </div>
                    </div>

                    {{-- Product Match --}}
                    <div x-show="productMatch" class="mb-4 p-4 bg-gray-50 border rounded-lg" x-cloak>
                        <h4 class="text-sm font-medium text-gray-700 mb-2">Matched Product</h4>
                        <div class="grid grid-cols-2 gap-3 text-sm" x-show="productMatch?.product">
                            <div>
                                <span class="text-gray-500">Name:</span>
                                <span class="font-medium text-gray-900 ml-1" x-text="productMatch?.product?.name"></span>
                            </div>
                            <div>
                                <span class="text-gray-500">Category:</span>
                                <span class="text-gray-900 ml-1" x-text="productMatch?.product?.category"></span>
                            </div>
                            <div>
                                <span class="text-gray-500">Buy:</span>
                                <span class="text-gray-900 ml-1">&euro;<span x-text="parseFloat(productMatch?.product?.buy_price || 0).toFixed(2)"></span></span>
                            </div>
                            <div>
                                <span class="text-gray-500">Sell:</span>
                                <span class="text-gray-900 ml-1">&euro;<span x-text="parseFloat(productMatch?.product?.sell_price || 0).toFixed(2)"></span></span>
                            </div>
                        </div>
                        <div x-show="productMatch?.existing_label" class="mt-2 p-2 bg-yellow-50 border border-yellow-200 rounded text-xs text-yellow-700">
                            This product already has a label: <a :href="'/labels/zebra/manage/' + productMatch?.existing_label?.id" class="font-medium underline" x-text="productMatch?.existing_label?.name"></a>
                            — saving will create an additional label.
                        </div>
                    </div>
                    <div x-show="productLookupNone" class="mb-4 p-3 bg-gray-50 border rounded-lg text-sm text-gray-500" x-cloak>
                        No matching product found for this barcode.
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <input type="text" name="description" value="{{ old('description') }}" placeholder="Optional notes about this label"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    </div>

                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Width (mm)</label>
                            <input type="number" name="label_width_mm" value="{{ old('label_width_mm', 76) }}" step="0.1" min="1"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Height (mm)</label>
                            <input type="number" name="label_height_mm" value="{{ old('label_height_mm', 50) }}" step="0.1" min="1"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        </div>
                    </div>

                    {{-- Editable Fields --}}
                    <div class="mb-4" x-show="fields.length > 0">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Label Fields <span class="text-gray-400 font-normal">(edit before printing)</span></label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <template x-for="(field, i) in fields" :key="i">
                                <input type="text" x-model="fields[i]"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            </template>
                        </div>
                    </div>

                    {{-- Preview --}}
                    <div class="mb-6" x-show="zplContent">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Label Preview</label>
                        <div class="bg-gray-50 border rounded-lg p-4 min-h-[120px] flex items-center justify-center">
                            <img x-ref="previewImg" x-show="previewSrc" :src="previewSrc" class="max-h-48 rounded border" alt="Label preview">
                            <p x-show="previewError" class="text-red-500 text-xs" x-text="previewError"></p>
                            <p x-show="!previewSrc && !previewError && zplContent" class="text-gray-400 text-xs">Click "Preview" to render the label</p>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Note: Labels with embedded graphics (~DG commands) may not render in preview but will print correctly.</p>
                    </div>

                    {{-- Actions --}}
                    <div class="flex flex-wrap gap-3 items-center">
                        <button type="button" @click="renderPreview()" :disabled="!zplContent"
                            class="px-4 py-2 bg-gray-600 text-white rounded-md text-sm hover:bg-gray-500 transition disabled:opacity-50">
                            Preview
                        </button>

                        <div class="flex items-center gap-2">
                            <button type="button" @click="testPrint()" :disabled="!zplContent || printing"
                                class="px-4 py-2 bg-green-600 text-white rounded-md text-sm hover:bg-green-500 transition disabled:opacity-50">
                                <span x-show="!printing">Test Print</span>
                                <span x-show="printing">Printing...</span>
                            </button>
                            <div class="flex items-center gap-1">
                                <label class="text-xs text-gray-500">Copies:</label>
                                <input type="number" x-model.number="copies" min="1" max="99" class="w-16 rounded-md border-gray-300 shadow-sm text-sm text-center">
                            </div>
                        </div>

                        <button type="submit" :disabled="!zplContent"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm hover:bg-indigo-500 transition disabled:opacity-50">
                            Save to Database
                        </button>
                    </div>

                    {{-- Print Result --}}
                    <div x-show="printMessage" class="mt-3" x-cloak>
                        <p :class="printSuccess ? 'text-green-600' : 'text-red-600'" class="text-sm" x-text="printMessage"></p>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @vite('resources/js/zpl-preview.js')
    <script>
        document.addEventListener('alpine:init', () => {
            let _zplRenderer = null;
            async function getZplRenderer() {
                if (_zplRenderer) return _zplRenderer;
                // Try window.ZplPreview first (set by zpl-preview.js)
                if (window.ZplPreview) {
                    _zplRenderer = window.ZplPreview;
                    return _zplRenderer;
                }
                throw new Error('ZPL renderer not available yet — try again in a moment');
            }

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

            // ZPL hex decode/encode for display
            const zplHexMap = { '\\15': '€', '\\06': '£', '\\04': '$' };
            const reverseHexMap = Object.fromEntries(Object.entries(zplHexMap).map(([k, v]) => [v, k]));
            function zplHexDecode(str) {
                return str.replace(/\\[0-9a-fA-F]{2}/g, m => zplHexMap[m] || m);
            }
            function zplHexEncode(str) {
                let result = str;
                for (const [char, hex] of Object.entries(reverseHexMap)) {
                    result = result.replaceAll(char, hex);
                }
                return result;
            }

            let productLookupTimer = null;

            Alpine.data('zebraUpload', () => ({
                mode: 'file',
                zplContent: '',
                labelName: '{{ old("name") }}',
                productCode: '{{ old("product_code") }}',
                previewSrc: null,
                previewError: null,
                copies: 1,
                fields: [],
                rawFields: [],
                printing: false,
                printMessage: '',
                printSuccess: false,
                productMatch: null,
                productLookupNone: false,

                init() {
                    // Watch productCode for manual edits — debounced lookup
                    this.$watch('productCode', (val) => {
                        clearTimeout(productLookupTimer);
                        if (!val || val.length < 3) {
                            this.productMatch = null;
                            this.productLookupNone = false;
                            return;
                        }
                        productLookupTimer = setTimeout(() => this.lookupProduct(val), 400);
                    });
                },

                handleFileUpload(event) {
                    const file = event.target.files[0];
                    if (!file) return;

                    // Auto-fill name from filename
                    if (!this.labelName) {
                        this.labelName = file.name.replace(/\.(prn|zpl|txt)$/i, '');
                    }

                    const reader = new FileReader();
                    reader.onload = (e) => {
                        this.zplContent = e.target.result;
                        this.previewSrc = null;
                        this.previewError = null;

                        // Try to extract barcode, copies, dimensions, and editable fields from ZPL
                        this.autoExtractBarcode();
                        this.autoExtractCopies();
                        this.autoExtractDimensions();
                        this.autoExtractFields();
                    };
                    reader.readAsText(file);
                },

                autoExtractCopies() {
                    const match = this.zplContent.match(/\^PQ(\d+)/i);
                    if (match) this.copies = parseInt(match[1]) || 1;
                },

                autoExtractDimensions() {
                    // ^JMA = 203 DPI, ^JMB = 300 DPI
                    let dpi = 203;
                    const jmMatch = this.zplContent.match(/\^JM([AB])/i);
                    if (jmMatch && jmMatch[1].toUpperCase() === 'B') dpi = 300;

                    const pwMatch = this.zplContent.match(/\^PW(\d+)/i);
                    const llMatch = this.zplContent.match(/\^LL(\d+)/i);

                    const widthEl = document.querySelector('[name="label_width_mm"]');
                    const heightEl = document.querySelector('[name="label_height_mm"]');

                    if (pwMatch && widthEl) {
                        widthEl.value = (parseInt(pwMatch[1]) / dpi * 25.4).toFixed(1);
                    }
                    if (llMatch && heightEl) {
                        heightEl.value = (parseInt(llMatch[1]) / dpi * 25.4).toFixed(1);
                    }
                },

                autoExtractFields() {
                    // Find main label block (^XA with ^FT positioning through ^XZ)
                    const blockMatch = this.zplContent.match(/(\^XA(?:(?!\^XA)[\s\S])*\^FT(?:(?!\^XA)[\s\S])*\^XZ)/);
                    if (!blockMatch) { this.fields = []; this.rawFields = []; return; }
                    const block = blockMatch[1];

                    // Split by positioning commands and extract text ^FD values
                    const segments = block.split(/(?=\^F[TO])/);
                    const raw = [];
                    for (const seg of segments) {
                        if (/\^XG/.test(seg) || /\^BC/.test(seg)) continue;
                        if (/\^A\d*\w/.test(seg)) {
                            const fdMatch = seg.match(/\^FD([^^]*)\^FS/);
                            if (fdMatch) raw.push(fdMatch[1]);
                        }
                    }
                    this.rawFields = raw;
                    this.fields = raw.map(f => zplHexDecode(f));
                },

                autoExtractBarcode() {
                    if (this.productCode) return;
                    const match = this.zplContent.match(/\^B[CE8][^^]*[\s\S]*?\^FD([^^]+)\^FS/i);
                    if (match) {
                        let barcode = match[1].trim();
                        // Strip Code 128 subset selectors (>;  >:  etc.)
                        barcode = barcode.replace(/^(>[;:0-9A-Z])+/, '');
                        if (barcode) this.productCode = barcode;
                    }
                },

                async lookupProduct(code) {
                    this.productMatch = null;
                    this.productLookupNone = false;
                    try {
                        const res = await fetch('{{ route("zebra-labels.lookup-product") }}', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                            body: JSON.stringify({ code }),
                        });
                        const data = await res.json();
                        if (data.found) {
                            this.productMatch = data;
                        } else {
                            this.productLookupNone = true;
                        }
                    } catch (err) {
                        // silently fail
                    }
                },

                applyFieldEdits(zpl) {
                    if (!this.fields.length || !this.rawFields.length) return zpl;
                    let modified = zpl;
                    for (let i = 0; i < this.fields.length; i++) {
                        const encoded = zplHexEncode(this.fields[i]);
                        if (encoded !== this.rawFields[i]) {
                            const oldFd = '^FD' + this.rawFields[i] + '^FS';
                            const newFd = '^FD' + encoded + '^FS';
                            modified = modified.replace(oldFd, newFd);
                        }
                    }
                    return modified;
                },

                async renderPreview() {
                    if (!this.zplContent) return;
                    this.previewSrc = null;
                    this.previewError = null;
                    try {
                        const renderer = await getZplRenderer();
                        const widthMm = parseFloat(document.querySelector('[name="label_width_mm"]').value) || 76;
                        const heightMm = parseFloat(document.querySelector('[name="label_height_mm"]').value) || 50;
                        const base64 = await renderer.renderToBase64(this.zplContent, widthMm, heightMm);
                        this.previewSrc = 'data:image/png;base64,' + base64;
                    } catch (err) {
                        this.previewError = 'Preview failed: ' + err.message;
                    }
                },

                async testPrint() {
                    if (!this.zplContent || this.printing) return;
                    this.printing = true;
                    this.printMessage = '';

                    // Apply field edits to ZPL
                    let zpl = this.applyFieldEdits(this.zplContent);

                    // Set ^PQ quantity in ZPL instead of repeating (avoids re-downloading ~DG graphics)
                    if (/\^PQ\d+/i.test(zpl)) {
                        zpl = zpl.replace(/\^PQ\d+[^^]*/i, '^PQ' + this.copies + ',0,1,Y');
                    } else {
                        zpl = zpl.replace(/\^XZ\s*$/, '^PQ' + this.copies + ',0,1,Y^XZ');
                    }

                    try {
                        const res = await fetch('{{ route("labels.print-zpl") }}', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                            body: JSON.stringify({ zpl }),
                        });
                        const data = await res.json();
                        this.printMessage = data.success ? 'Print job sent!' : ('Print failed: ' + (data.output || data.message));
                        this.printSuccess = data.success;
                    } catch (err) {
                        this.printMessage = 'Print failed: ' + err.message;
                        this.printSuccess = false;
                    }
                    this.printing = false;
                },
            }));
        });
    </script>
</x-admin-layout>
