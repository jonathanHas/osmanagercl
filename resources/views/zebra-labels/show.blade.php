<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $zebraLabel->name }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8" x-data="zebraShow()">
            @include('labels._nav', ['current' => 'zebra'])

            @if (session('success'))
                <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-md text-green-700 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Label Info --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 mb-4">
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-gray-500">Name:</span>
                        <span class="font-medium text-gray-900 ml-1">{{ $zebraLabel->name }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Barcode:</span>
                        <span class="font-mono text-gray-900 ml-1">{{ $zebraLabel->product_code ?? '-' }}</span>
                    </div>
                    @if ($zebraLabel->description)
                        <div class="col-span-2">
                            <span class="text-gray-500">Description:</span>
                            <span class="text-gray-900 ml-1">{{ $zebraLabel->description }}</span>
                        </div>
                    @endif
                    <div>
                        <span class="text-gray-500">File:</span>
                        <span class="text-gray-900 ml-1">{{ $zebraLabel->original_filename ?? 'Pasted' }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Size:</span>
                        <span class="text-gray-900 ml-1">
                            @if ($zebraLabel->label_width_mm && $zebraLabel->label_height_mm)
                                {{ $zebraLabel->label_width_mm }}mm x {{ $zebraLabel->label_height_mm }}mm
                            @else
                                -
                            @endif
                        </span>
                    </div>
                    <div>
                        <span class="text-gray-500">Default Copies:</span>
                        <span class="text-gray-900 ml-1">{{ $defaultCopies }}
                            @if ($zebraLabel->default_copies)
                                (saved)
                            @else
                                (from ZPL)
                            @endif
                        </span>
                    </div>
                    <div>
                        <span class="text-gray-500">Created:</span>
                        <span class="text-gray-900 ml-1">{{ $zebraLabel->created_at->format('M j, Y g:ia') }}</span>
                    </div>
                    @if ($zebraLabel->user)
                        <div>
                            <span class="text-gray-500">By:</span>
                            <span class="text-gray-900 ml-1">{{ $zebraLabel->user->name }}</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Product Details --}}
            @if ($productDetails)
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 mb-4">
                    <h3 class="text-sm font-medium text-gray-700 mb-3">Linked Product</h3>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-gray-500">Name:</span>
                            <span class="font-medium text-gray-900 ml-1">{{ $productDetails['name'] }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">Barcode:</span>
                            <span class="font-mono text-gray-900 ml-1">{{ $productDetails['code'] }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">Buy Price:</span>
                            <span class="text-gray-900 ml-1">&euro;{{ number_format((float) $productDetails['buy_price'], 2) }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">Sell Price:</span>
                            <span class="text-gray-900 ml-1">&euro;{{ number_format((float) $productDetails['sell_price'], 2) }}</span>
                        </div>
                        @if ($productDetails['category'])
                            <div>
                                <span class="text-gray-500">Category:</span>
                                <span class="text-gray-900 ml-1">{{ $productDetails['category'] }}</span>
                            </div>
                        @endif
                        <div>
                            <span class="text-gray-500">Margin:</span>
                            @php
                                $buy = (float) $productDetails['buy_price'];
                                $sell = (float) $productDetails['sell_price'];
                                $margin = $sell > 0 ? (($sell - $buy) / $sell) * 100 : 0;
                            @endphp
                            <span class="ml-1 {{ $margin >= 30 ? 'text-green-600' : ($margin >= 15 ? 'text-yellow-600' : 'text-red-600') }}">
                                {{ number_format($margin, 1) }}%
                            </span>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Preview --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 mb-4">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-medium text-gray-700">Preview</h3>
                    <button type="button" @click="updatePreview()" class="px-3 py-1 bg-gray-100 border border-gray-300 rounded text-xs font-medium text-gray-700 hover:bg-gray-200 transition">
                        Update Preview
                    </button>
                </div>
                <div class="bg-gray-50 border rounded-lg p-4 min-h-[120px] flex items-center justify-center">
                    <img x-show="previewSrc" :src="previewSrc" class="max-h-48 rounded border" alt="Label preview">
                    <p x-show="previewError" class="text-red-500 text-xs" x-text="previewError"></p>
                    <p x-show="!previewSrc && !previewError" class="text-gray-400 text-xs">Loading preview...</p>
                </div>
            </div>

            {{-- Editable Fields --}}
            @if (count($fields) > 0)
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 mb-4">
                    <h3 class="text-sm font-medium text-gray-700 mb-3">Label Fields <span class="text-gray-400 font-normal">(edit before printing)</span></h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @foreach ($fields as $i => $value)
                            <input type="text" x-model="fields[{{ $i }}]"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        @endforeach
                    </div>
                    <div class="mt-3 flex items-center gap-3">
                        <button type="button" @click="saveFields()" :disabled="savingFields"
                            class="px-3 py-1.5 bg-indigo-600 text-white rounded-md text-xs font-semibold hover:bg-indigo-500 transition disabled:opacity-50">
                            <span x-text="savingFields ? 'Saving...' : 'Save fields'"></span>
                        </button>
                        <button type="button" @click="resetFields()" class="text-xs text-gray-400 hover:text-gray-600">Reset</button>
                        <p x-show="fieldsMessage" :class="fieldsSuccess ? 'text-green-600' : 'text-red-600'" class="text-xs" x-text="fieldsMessage"></p>
                    </div>
                </div>
            @endif

            {{-- Print --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 mb-4">
                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" @click="printLabel()" :disabled="printing"
                        class="px-4 py-2 bg-green-600 text-white rounded-md text-sm hover:bg-green-500 transition disabled:opacity-50">
                        <span x-show="!printing">Print</span>
                        <span x-show="printing">Printing...</span>
                    </button>
                    <div class="flex items-center gap-1">
                        <label class="text-xs text-gray-500">Copies:</label>
                        <input type="number" x-model.number="copies" min="1" max="99" class="w-16 rounded-md border-gray-300 shadow-sm text-sm text-center">
                    </div>
                    <button type="button" @click="saveDefaultCopies()" :disabled="savingCopies"
                        class="px-3 py-2 bg-gray-100 border border-gray-300 rounded-md text-xs font-medium text-gray-700 hover:bg-gray-200 transition disabled:opacity-50">
                        <span x-text="savingCopies ? 'Saving...' : 'Save as default'"></span>
                    </button>
                    <p x-show="copiesMessage" class="text-green-600 text-xs" x-text="copiesMessage"></p>
                    <p x-show="printMessage" :class="printSuccess ? 'text-green-600' : 'text-red-600'" class="text-sm" x-text="printMessage"></p>
                </div>
            </div>

            {{-- ZPL Code --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 mb-4">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-medium text-gray-700">ZPL Code</h3>
                    <button type="button" @click="showZpl = !showZpl" class="text-xs text-indigo-600 hover:underline" x-text="showZpl ? 'Hide' : 'Show'"></button>
                </div>
                <div x-show="showZpl" x-transition>
                    <pre class="bg-gray-900 text-green-400 rounded-lg p-4 text-xs overflow-auto max-h-64">{{ $zebraLabel->zpl_content }}</pre>
                </div>
            </div>

            {{-- Delete --}}
            <div class="flex justify-end">
                <form method="POST" action="{{ route('zebra-labels.destroy', $zebraLabel) }}" onsubmit="return confirm('Delete this label?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="px-3 py-1.5 bg-red-50 border border-red-200 rounded text-xs font-medium text-red-600 hover:bg-red-100 transition">Delete Label</button>
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
                if (window.ZplPreview) {
                    _zplRenderer = window.ZplPreview;
                    return _zplRenderer;
                }
                // Wait briefly for ZPL renderer to load
                await new Promise(r => setTimeout(r, 500));
                if (window.ZplPreview) {
                    _zplRenderer = window.ZplPreview;
                    return _zplRenderer;
                }
                throw new Error('ZPL renderer not available');
            }

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            let rawFields = @json($fields);
            let zplContent = @json($zebraLabel->zpl_content);
            const widthMm = {{ $previewWidth }};
            const heightMm = {{ $previewHeight }};

            // ZPL hex map: \xx codes used with ^FH\ — common ones for display
            const zplHexMap = {
                '\\15': '€', '\\06': '£', '\\04': '$',
            };
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

            // Decode fields for display, keep raw originals for replacement
            const displayFields = rawFields.map(f => zplHexDecode(f));

            // Build modified ZPL from current field values (user-facing → re-encoded)
            function buildModifiedZpl(fields) {
                const encodedFields = fields.map(f => zplHexEncode(f));
                const hasChanges = encodedFields.some((val, i) => val !== rawFields[i]);
                if (!hasChanges) return zplContent;

                let zpl = zplContent;
                for (let i = 0; i < rawFields.length; i++) {
                    if (encodedFields[i] !== rawFields[i]) {
                        zpl = zpl.replace('^FD' + rawFields[i] + '^FS', '^FD' + encodedFields[i] + '^FS');
                    }
                }
                return zpl;
            }

            Alpine.data('zebraShow', () => ({
                previewSrc: null,
                previewError: null,
                copies: {{ $defaultCopies }},
                fields: [...displayFields],
                printing: false,
                printMessage: '',
                printSuccess: false,
                savingCopies: false,
                copiesMessage: '',
                savingFields: false,
                fieldsMessage: '',
                fieldsSuccess: false,
                showZpl: false,

                async init() {
                    await this.renderPreview(zplContent);
                },

                async renderPreview(zpl) {
                    this.previewSrc = null;
                    this.previewError = null;
                    try {
                        const renderer = await getZplRenderer();
                        const base64 = await renderer.renderToBase64(zpl, widthMm, heightMm);
                        this.previewSrc = 'data:image/png;base64,' + base64;
                    } catch (err) {
                        this.previewError = 'Preview failed: ' + err.message;
                    }
                },

                async updatePreview() {
                    const zpl = buildModifiedZpl(this.fields);
                    await this.renderPreview(zpl);
                },

                resetFields() {
                    this.fields = [...displayFields];
                },

                async printLabel() {
                    if (this.printing) return;
                    this.printing = true;
                    this.printMessage = '';

                    // Encode fields back to ZPL hex and check for changes
                    const encodedFields = this.fields.map(f => zplHexEncode(f));
                    const hasChanges = encodedFields.some((val, i) => val !== rawFields[i]);

                    try {
                        const res = await fetch('{{ route("zebra-labels.print", $zebraLabel) }}', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                            body: JSON.stringify({
                                copies: this.copies,
                                fields: hasChanges ? encodedFields : null,
                            }),
                        });
                        const data = await res.json();
                        this.printMessage = data.success ? data.message : ('Print failed: ' + (data.output || data.message));
                        this.printSuccess = data.success;
                    } catch (err) {
                        this.printMessage = 'Print failed: ' + err.message;
                        this.printSuccess = false;
                    }
                    this.printing = false;
                },

                async saveDefaultCopies() {
                    if (this.savingCopies) return;
                    this.savingCopies = true;
                    this.copiesMessage = '';
                    try {
                        const res = await fetch('{{ route("zebra-labels.update-copies", $zebraLabel) }}', {
                            method: 'PATCH',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                            body: JSON.stringify({ default_copies: this.copies }),
                        });
                        const data = await res.json();
                        if (data.success) {
                            this.copiesMessage = 'Default saved (' + data.default_copies + ')';
                            setTimeout(() => this.copiesMessage = '', 3000);
                        }
                    } catch (err) {
                        this.copiesMessage = '';
                    }
                    this.savingCopies = false;
                },

                async saveFields() {
                    if (this.savingFields) return;
                    this.savingFields = true;
                    this.fieldsMessage = '';
                    const encodedFields = this.fields.map(f => zplHexEncode(f));
                    try {
                        const res = await fetch('{{ route("zebra-labels.update-fields", $zebraLabel) }}', {
                            method: 'PATCH',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                            body: JSON.stringify({ fields: encodedFields }),
                        });
                        const data = await res.json();
                        if (data.success) {
                            // Update the canonical values so Reset and Print use the new saved state
                            rawFields.splice(0, rawFields.length, ...data.fields);
                            const newDisplay = data.fields.map(f => zplHexDecode(f));
                            this.fields.splice(0, this.fields.length, ...newDisplay);
                            // Update the in-memory ZPL for preview
                            zplContent = buildModifiedZpl(this.fields);
                            this.fieldsMessage = 'Fields saved';
                            this.fieldsSuccess = true;
                            setTimeout(() => this.fieldsMessage = '', 3000);
                        }
                    } catch (err) {
                        this.fieldsMessage = 'Save failed: ' + err.message;
                        this.fieldsSuccess = false;
                    }
                    this.savingFields = false;
                },
            }));
        });
    </script>
</x-admin-layout>
