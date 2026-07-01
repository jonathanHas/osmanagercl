<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Harvest Log') }}
            </h2>
            <div class="flex items-center gap-3">
                <a href="{{ route('fruit-veg.harvest.history') }}"
                   class="text-sm text-indigo-600 hover:text-indigo-800">View history &rarr;</a>
                <a href="{{ route('fruit-veg.index') }}"
                   class="text-sm text-gray-500 hover:text-gray-700">&larr; Back to F&amp;V</a>
            </div>
        </div>
    </x-slot>

    <style>[x-cloak] { display: none !important; }</style>

    <div class="py-6">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8"
             x-data="harvestForm({
                 date: {{ Js::from($selectedDate) }},
                 rows: {{ Js::from($rows) }},
                 available: {{ Js::from($availableProducts) }},
             })">

            <!-- Date selector -->
            <div class="bg-white rounded-lg shadow mb-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Harvest date</h3>
                </div>
                <form method="GET" action="{{ route('fruit-veg.harvest') }}" class="p-6 flex items-end gap-4">
                    <div>
                        <label for="date" class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                        <input type="date" id="date" name="date" value="{{ $selectedDate }}"
                               max="{{ now()->toDateString() }}"
                               class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <button type="submit"
                            class="px-4 py-2 bg-gray-700 text-white rounded-md hover:bg-gray-800 transition">
                        Load
                    </button>
                </form>
            </div>

            <!-- Harvest entry -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="text-lg font-medium text-gray-900">
                        What was harvested on {{ \Carbon\Carbon::parse($selectedDate)->format('D j M Y') }}?
                    </h3>
                    <span class="text-sm text-gray-500">Supplier: Jon (own farm)</span>
                </div>

                <!-- Add product search -->
                <div class="px-6 py-4 border-b border-gray-200 relative">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Add a product</label>
                    <input type="text" x-model="search" @focus="open = true" @click.away="open = false"
                           placeholder="Search Jon's products to add a row..."
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <ul x-show="open && filtered.length > 0" x-cloak
                        class="absolute z-10 mt-1 w-[calc(100%-3rem)] max-h-64 overflow-auto bg-white border border-gray-200 rounded-md shadow-lg">
                        <template x-for="p in filtered" :key="p.code">
                            <li @click="addRow(p)"
                                class="px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50 cursor-pointer flex justify-between">
                                <span x-text="p.name"></span>
                                <span class="text-gray-400" x-text="p.unit"></span>
                            </li>
                        </template>
                    </ul>
                    <p x-show="open && search.length > 0 && filtered.length === 0" x-cloak
                       class="mt-1 text-sm text-gray-400">No matching products.</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-28">Logged</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-80">Add amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                            </tr>
                        </thead>

                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr x-show="rows.length === 0">
                                <td colspan="4" class="px-6 py-8 text-center text-sm text-gray-400">
                                    Nothing harvested recently. Use the search above to add products.
                                </td>
                            </tr>

                            <template x-for="row in rows" :key="row.code">
                                <tr>
                                    <td class="px-6 py-3 text-sm font-medium text-gray-900">
                                        <span x-html="row.name"></span>
                                        <span x-show="row.label" x-cloak title="Has a printable label"
                                              class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-blue-100 text-blue-700 align-middle">label</span>
                                    </td>
                                    <td class="px-6 py-3 text-sm text-gray-700">
                                        <span class="font-medium" x-text="parseFloat(row.logged || 0)"></span>
                                        <span class="text-gray-400" x-text="row.unit"></span>
                                    </td>
                                    <td class="px-6 py-3">
                                        <div class="flex items-center gap-2">
                                            <input type="number" step="0.01" min="0" x-model="row.input"
                                                   @keydown.enter.prevent="saveRow(row)"
                                                   :disabled="row.saving"
                                                   placeholder="add..."
                                                   class="w-24 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <label class="inline-flex items-center text-sm text-gray-600">
                                                <input type="radio" :name="`unit_${row.code}`" value="kg"
                                                       x-model="row.unit" class="mr-1 text-indigo-600 focus:ring-indigo-500"> kg
                                            </label>
                                            <label class="inline-flex items-center text-sm text-gray-600">
                                                <input type="radio" :name="`unit_${row.code}`" value="unit"
                                                       x-model="row.unit" class="mr-1 text-indigo-600 focus:ring-indigo-500"> unit
                                            </label>
                                            <button type="button" @click="saveRow(row)" :disabled="row.saving"
                                                    class="px-3 py-1.5 bg-teal-600 text-white rounded-md text-sm font-medium hover:bg-teal-700 transition disabled:opacity-50">
                                                <span x-text="row.saving ? '...' : 'Save'"></span>
                                            </button>
                                            <button type="button" x-show="row.label" x-cloak @click="printRow(row)"
                                                    title="Print labels"
                                                    class="p-1.5 text-gray-500 hover:text-green-600 hover:bg-green-50 rounded transition">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                                </svg>
                                            </button>
                                            <button type="button" x-show="row.added" @click="removeRow(row.code)"
                                                    class="text-gray-400 hover:text-red-600" title="Remove row">&times;</button>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3">
                                        <input type="text" x-model="row.notes"
                                               @keydown.enter.prevent="saveRow(row)"
                                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Print Modal --}}
            <div x-show="printModal.open" x-cloak
                 class="fixed inset-0 z-50 overflow-y-auto"
                 @keydown.escape.window="printModal.open = false">
                <div class="flex items-center justify-center min-h-screen px-4">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="printModal.open = false"></div>
                    <div class="relative bg-white rounded-lg shadow-xl max-w-sm w-full p-6" @click.stop>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Print Labels</h3>

                        <div class="space-y-3 text-sm">
                            <div>
                                <span class="text-gray-500">Product:</span>
                                <span class="font-medium text-gray-900 ml-1" x-text="printModal.productName"></span>
                            </div>
                            <div x-show="printModal.labelName">
                                <span class="text-gray-500">Label:</span>
                                <span class="text-gray-900 ml-1" x-text="printModal.labelName"></span>
                            </div>

                            <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-amber-800">
                                <div class="text-xs font-medium">⚠ Check the loaded labels</div>
                                <div class="text-xs mt-0.5">
                                    Make sure the <span class="font-semibold" x-text="printModal.labelSize"></span>
                                    labels are loaded in the Zebra printer before printing.
                                </div>
                            </div>

                            <div class="pt-1">
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

    @push('scripts')
    <script>
        function harvestForm({ date, rows, available }) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

            return {
                date,
                available,          // [{ code, name, category, unit, label }]
                // Server rows get client-only fields for inline editing.
                rows: rows.map(r => ({ ...r, input: '', notes: '', saving: false, added: false })),
                search: '',
                open: false,
                printModal: {
                    open: false, labelId: null, labelName: '', labelSize: '',
                    productName: '', copies: 1, printing: false, message: '', success: false,
                },

                get filtered() {
                    const q = this.search.toLowerCase();
                    const taken = new Set(this.rows.map(r => r.code));
                    return this.available
                        .filter(p => !taken.has(p.code))
                        .filter(p => p.name.toLowerCase().includes(q) || p.code.includes(q))
                        .slice(0, 25);
                },

                addRow(p) {
                    this.rows.unshift({ ...p, logged: 0, input: '', notes: '', saving: false, added: true });
                    this.search = '';
                    this.open = false;
                },

                removeRow(code) {
                    this.rows = this.rows.filter(r => r.code !== code);
                },

                async saveRow(row) {
                    const amount = parseFloat(row.input);
                    if (row.saving || !amount || amount <= 0) return;
                    row.saving = true;

                    try {
                        const res = await fetch('{{ route('fruit-veg.harvest.save-row') }}', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                            body: JSON.stringify({
                                date: this.date, code: row.code, amount,
                                unit: row.unit, notes: row.notes,
                            }),
                        });
                        const data = await res.json();
                        if (data.success) {
                            row.logged = data.logged;
                            row.added = false;   // now persisted for the date
                            row.input = '';
                            if (row.label) this.openPrintModal(row, amount);
                        } else {
                            alert(data.message || 'Save failed.');
                        }
                    } catch (err) {
                        alert('Save failed: ' + err.message);
                    }
                    row.saving = false;
                },

                printRow(row) {
                    if (!row.label) return;
                    // Reprint on demand: use the amount typed in the box, else 1.
                    const amount = parseFloat(row.input) || 1;
                    this.openPrintModal(row, amount);
                },

                openPrintModal(row, amount) {
                    const l = row.label;
                    this.printModal = {
                        open: true,
                        labelId: l.id,
                        labelName: l.name,
                        labelSize: (l.width_mm || '?') + ' × ' + (l.height_mm || '?') + ' mm',
                        productName: row.name.replace(/<[^>]*>/g, ''),
                        copies: Math.min(99, Math.max(1, Math.round(amount))),
                        printing: false, message: '', success: false,
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
            };
        }
    </script>
    @endpush
</x-admin-layout>
