<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Zebra Labels</h2>
    </x-slot>

    <style>[x-cloak] { display: none !important; }</style>

    <div class="py-6" x-data="zebraLabels()" x-cloak>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            @include('labels._nav', ['current' => 'zebra'])

            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="p-5 border-b border-gray-200">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">Products on Till with Labels</h3>
                            <p class="text-xs text-gray-500 mt-0.5">Labels linked to products currently visible on the till</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <form method="GET" action="{{ route('labels.zebra') }}" class="relative">
                                <input type="text" name="search" value="{{ $search }}" placeholder="Search products..."
                                       class="w-56 pl-8 pr-3 py-1.5 text-sm border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                                <svg class="w-4 h-4 text-gray-400 absolute left-2.5 top-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </form>
                            <a href="{{ route('zebra-labels.index') }}" class="px-3 py-1.5 bg-gray-100 border border-gray-300 rounded-md text-xs font-medium text-gray-700 hover:bg-gray-200 transition whitespace-nowrap">
                                Manage Labels
                            </a>
                        </div>
                    </div>
                </div>

                @if (count($zebraLabels) > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                    <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase">Label</th>
                                    <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase">Size</th>
                                    <th class="px-4 py-2.5 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="w-20 px-4 py-2.5 text-center text-xs font-medium text-gray-500 uppercase">Print</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <template x-for="label in labels" :key="label.id">
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3">
                                            <div class="text-sm font-medium text-gray-900" x-text="label.product_name"></div>
                                            <div class="text-xs text-gray-400 font-mono" x-text="label.product_code"></div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <a :href="'/labels/zebra/manage/' + label.id" class="text-sm text-blue-600 hover:text-blue-800" x-text="label.name"></a>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600">
                                            <span x-text="(label.width_mm || '?') + ' × ' + (label.height_mm || '?') + 'mm'"></span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span x-show="!label.mismatches" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">OK</span>
                                            <span x-show="label.mismatches" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 cursor-pointer" @click="openPrintModal(label)">
                                                <span x-show="label.mismatches?.price && label.mismatches?.country">Price + Country</span>
                                                <span x-show="label.mismatches?.price && !label.mismatches?.country">Price</span>
                                                <span x-show="!label.mismatches?.price && label.mismatches?.country">Country</span>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <button @click="openPrintModal(label)"
                                                    class="relative p-1.5 text-gray-500 hover:text-green-600 hover:bg-green-50 rounded transition">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                                </svg>
                                                <span x-show="label.mismatches" class="absolute -top-0.5 -right-0.5 w-2.5 h-2.5 bg-amber-500 rounded-full"></span>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="p-8 text-center text-gray-400 text-sm">
                        @if ($search)
                            No till labels found matching "{{ $search }}".
                        @else
                            No zebra labels linked to products currently on till.
                        @endif
                    </div>
                @endif
            </div>

            {{-- Other Labels (not on till) --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 mt-6">
                <div class="p-5 border-b border-gray-200">
                    <h3 class="text-base font-semibold text-gray-900">Other Labels</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Labels linked to products not currently on the till</p>
                </div>

                <template x-if="otherLabels.length > 0">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                    <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase">Label</th>
                                    <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase">Size</th>
                                    <th class="px-4 py-2.5 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="w-20 px-4 py-2.5 text-center text-xs font-medium text-gray-500 uppercase">Print</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <template x-for="label in otherLabels" :key="label.id">
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3">
                                            <div class="text-sm font-medium text-gray-900" x-text="label.product_name"></div>
                                            <div class="text-xs text-gray-400 font-mono" x-text="label.product_code"></div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <a :href="'/labels/zebra/manage/' + label.id" class="text-sm text-blue-600 hover:text-blue-800" x-text="label.name"></a>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600">
                                            <span x-text="(label.width_mm || '?') + ' × ' + (label.height_mm || '?') + 'mm'"></span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span x-show="!label.mismatches" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">OK</span>
                                            <span x-show="label.mismatches" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 cursor-pointer" @click="openPrintModal(label)">
                                                <span x-show="label.mismatches?.price && label.mismatches?.country">Price + Country</span>
                                                <span x-show="label.mismatches?.price && !label.mismatches?.country">Price</span>
                                                <span x-show="!label.mismatches?.price && label.mismatches?.country">Country</span>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <button @click="openPrintModal(label)"
                                                    class="relative p-1.5 text-gray-500 hover:text-green-600 hover:bg-green-50 rounded transition">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                                </svg>
                                                <span x-show="label.mismatches" class="absolute -top-0.5 -right-0.5 w-2.5 h-2.5 bg-amber-500 rounded-full"></span>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </template>
                <template x-if="otherLabels.length === 0">
                    <div class="p-8 text-center text-gray-400 text-sm">
                        @if ($search)
                            No other labels found matching "{{ $search }}".
                        @else
                            All labels are linked to products on the till.
                        @endif
                    </div>
                </template>
            </div>

            {{-- Print Modal --}}
            <div x-show="printModal.open" x-cloak
                 class="fixed inset-0 z-50 overflow-y-auto"
                 @keydown.escape.window="printModal.open = false">
                <div class="flex items-center justify-center min-h-screen px-4">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="printModal.open = false"></div>
                    <div class="relative bg-white rounded-lg shadow-xl max-w-sm w-full p-6" @click.stop>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Print Label</h3>

                        <div class="space-y-3 text-sm">
                            <div>
                                <span class="text-gray-500">Product:</span>
                                <span class="font-medium text-gray-900 ml-1" x-text="printModal.productName"></span>
                            </div>
                            <div>
                                <span class="text-gray-500">Label:</span>
                                <span class="text-gray-900 ml-1" x-text="printModal.labelName"></span>
                            </div>
                            <div>
                                <span class="text-gray-500">Size:</span>
                                <span class="text-gray-900 ml-1" x-text="printModal.labelSize"></span>
                            </div>

                            {{-- Mismatches --}}
                            <template x-if="printModal.mismatches?.price">
                                <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="text-xs font-medium text-amber-800">Price mismatch</div>
                                            <div class="text-xs text-amber-700 mt-0.5">
                                                Label: <span class="font-mono font-medium" x-text="'€' + printModal.mismatches.price.label_value"></span>
                                                &rarr; DB: <span class="font-mono font-medium" x-text="'€' + printModal.mismatches.price.db_value"></span>
                                            </div>
                                        </div>
                                        <button @click="fixMismatch('price')" :disabled="printModal.fixing"
                                                class="px-2 py-1 bg-amber-600 text-white rounded text-xs font-medium hover:bg-amber-500 transition disabled:opacity-50">
                                            Update
                                        </button>
                                    </div>
                                </div>
                            </template>
                            <template x-if="printModal.mismatches?.country">
                                <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="text-xs font-medium text-amber-800">Country mismatch</div>
                                            <div class="text-xs text-amber-700 mt-0.5">
                                                Label: <span class="font-medium" x-text="printModal.mismatches.country.label_value"></span>
                                                &rarr; DB: <span class="font-medium" x-text="printModal.mismatches.country.db_value"></span>
                                            </div>
                                        </div>
                                        <button @click="fixMismatch('country')" :disabled="printModal.fixing"
                                                class="px-2 py-1 bg-amber-600 text-white rounded text-xs font-medium hover:bg-amber-500 transition disabled:opacity-50">
                                            Update
                                        </button>
                                    </div>
                                </div>
                            </template>

                            <div class="pt-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Number of labels</label>
                                <input type="number" x-model.number="printModal.copies" min="1" max="99"
                                       class="w-24 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm text-center">
                            </div>
                        </div>

                        <div class="mt-5 flex items-center gap-3">
                            <button @click="sendPrint()" :disabled="printModal.printing"
                                    class="px-4 py-2 bg-green-600 text-white rounded-md text-sm font-medium hover:bg-green-500 transition disabled:opacity-50">
                                <span x-text="printModal.printing ? 'Printing...' : 'Print'"></span>
                            </button>
                            <button @click="printModal.open = false" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-200 transition">
                                Cancel
                            </button>
                            <p x-show="printModal.message" :class="printModal.success ? 'text-green-600' : 'text-red-600'" class="text-xs" x-text="printModal.message"></p>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        function zebraLabels() {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

            return {
                labels: @json($zebraLabels),
                otherLabels: @json($otherLabels),
                printModal: {
                    open: false,
                    labelId: null,
                    labelName: '',
                    labelSize: '',
                    productName: '',
                    copies: 1,
                    printing: false,
                    fixing: false,
                    message: '',
                    success: false,
                    mismatches: null,
                    fields: [],
                },

                openPrintModal(label) {
                    this.printModal = {
                        open: true,
                        labelId: label.id,
                        labelName: label.name,
                        labelSize: (label.width_mm || '?') + 'mm × ' + (label.height_mm || '?') + 'mm',
                        productName: label.product_name,
                        copies: label.default_copies || 1,
                        printing: false,
                        fixing: false,
                        message: '',
                        success: false,
                        mismatches: label.mismatches ? JSON.parse(JSON.stringify(label.mismatches)) : null,
                        fields: label.fields ? [...label.fields] : [],
                    };
                },

                async sendPrint() {
                    if (this.printModal.printing) return;
                    this.printModal.printing = true;
                    this.printModal.message = '';
                    try {
                        const res = await fetch('/labels/zebra/manage/' + this.printModal.labelId + '/print', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                            body: JSON.stringify({ copies: this.printModal.copies }),
                        });
                        const data = await res.json();
                        this.printModal.message = data.success ? data.message : ('Failed: ' + (data.output || data.message));
                        this.printModal.success = data.success;
                        if (data.success) {
                            setTimeout(() => { this.printModal.open = false; }, 1500);
                        }
                    } catch (err) {
                        this.printModal.message = 'Failed: ' + err.message;
                        this.printModal.success = false;
                    }
                    this.printModal.printing = false;
                },

                async fixMismatch(type) {
                    const mismatch = this.printModal.mismatches?.[type];
                    if (!mismatch || this.printModal.fixing) return;
                    this.printModal.fixing = true;

                    const fields = [...this.printModal.fields];
                    fields[mismatch.field_index] = mismatch.new_field;

                    try {
                        const res = await fetch('/labels/zebra/manage/' + this.printModal.labelId + '/fields', {
                            method: 'PATCH',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                            body: JSON.stringify({ fields }),
                        });
                        const data = await res.json();
                        if (data.success) {
                            delete this.printModal.mismatches[type];
                            if (Object.keys(this.printModal.mismatches).length === 0) {
                                this.printModal.mismatches = null;
                            }
                            this.printModal.fields = data.fields;

                            // Update the label in whichever list it belongs to
                            const label = this.labels.find(l => l.id === this.printModal.labelId)
                                || this.otherLabels.find(l => l.id === this.printModal.labelId);
                            if (label) {
                                label.fields = data.fields;
                                label.mismatches = this.printModal.mismatches
                                    ? JSON.parse(JSON.stringify(this.printModal.mismatches))
                                    : null;
                            }
                        }
                    } catch (err) {
                        // silently fail
                    }
                    this.printModal.fixing = false;
                },
            };
        }
    </script>
</x-admin-layout>
