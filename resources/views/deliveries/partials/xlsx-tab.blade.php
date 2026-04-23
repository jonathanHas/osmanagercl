{{-- Dynamis XLSX upload tab. Mounted from deliveries/create.blade.php. --}}
<div x-show="activeTab === 'xlsx'" class="p-6" x-data="dynamisXlsx()">
    <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700 rounded-lg p-4 mb-6">
        <h4 class="text-sm font-medium text-emerald-800 dark:text-emerald-200 mb-1">Dynamis XLSX Import</h4>
        <p class="text-sm text-emerald-700 dark:text-emerald-300">
            Upload a Dynamis <code>Historique(NN).xlsx</code> delivery file. Lines are matched to till-visible
            fruit &amp; veg products. Matches you confirm are remembered for future imports.
        </p>
    </div>

    <form method="POST" action="{{ route('deliveries.store-xlsx') }}" enctype="multipart/form-data"
          @submit="onSubmit($event)">
        @csrf

        <div class="mb-6">
            <label for="xlsx_delivery_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Delivery Date
            </label>
            <input type="date" name="delivery_date" id="xlsx_delivery_date"
                   value="{{ old('delivery_date', date('Y-m-d')) }}" required
                   class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
        </div>

        <div class="mb-6">
            <label for="xlsx_file" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Dynamis Delivery File (.xlsx)
            </label>
            <input type="file" name="xlsx_file" id="xlsx_file" accept=".xlsx,.xls" required
                   @change="onFileSelect($event)"
                   class="block w-full text-sm text-gray-700 dark:text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Max 10 MB.</p>
        </div>

        <div class="mb-4 flex flex-wrap gap-2">
            <button type="button" @click="parseFile()" :disabled="!fileReady || parsing"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-medium rounded-md">
                <span x-show="!parsing">Parse &amp; Preview</span>
                <span x-show="parsing">Parsing…</span>
            </button>
            <button type="button" @click="reset()" x-show="parsed"
                    class="inline-flex items-center px-4 py-2 bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-sm font-medium rounded-md">
                Clear
            </button>
        </div>

        <div x-show="error" class="mb-4 p-3 rounded bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 text-sm text-red-800 dark:text-red-200" x-text="error"></div>

        <div x-show="parsed" class="space-y-6">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                <div class="bg-gray-50 dark:bg-gray-900 rounded p-3">
                    <div class="text-gray-500 dark:text-gray-400 text-xs">Order</div>
                    <div class="font-medium" x-text="orderNumber || '—'"></div>
                </div>
                <div class="bg-gray-50 dark:bg-gray-900 rounded p-3">
                    <div class="text-gray-500 dark:text-gray-400 text-xs">Matched</div>
                    <div class="font-medium text-emerald-700 dark:text-emerald-400" x-text="matched.length"></div>
                </div>
                <div class="bg-gray-50 dark:bg-gray-900 rounded p-3">
                    <div class="text-gray-500 dark:text-gray-400 text-xs">Unmatched</div>
                    <div class="font-medium text-amber-700 dark:text-amber-400" x-text="unmatched.length"></div>
                </div>
                <div class="bg-gray-50 dark:bg-gray-900 rounded p-3">
                    <div class="text-gray-500 dark:text-gray-400 text-xs">Grand total</div>
                    <div class="font-medium">€<span x-text="(totals.grand_calculated || 0).toFixed(2)"></span></div>
                </div>
            </div>

            <!-- Matched -->
            <section x-show="matched.length">
                <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-100 mb-2">Matched items (will be imported)</h4>
                <div class="overflow-x-auto rounded border border-gray-200 dark:border-gray-700">
                    <table class="min-w-full text-xs">
                        <thead class="bg-gray-50 dark:bg-gray-900 text-gray-600 dark:text-gray-300">
                            <tr>
                                <th class="px-2 py-1 text-left w-8">Use</th>
                                <th class="px-2 py-1 text-left">Dynamis SKU</th>
                                <th class="px-2 py-1 text-left">Dynamis description</th>
                                <th class="px-2 py-1 text-left">Till product</th>
                                <th class="px-2 py-1 text-left">Via</th>
                                <th class="px-2 py-1 text-right">Qty</th>
                                <th class="px-2 py-1 text-right">Cost</th>
                                <th class="px-2 py-1 text-right">Line total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(item, idx) in matched" :key="'m'+idx">
                                <tr class="border-t border-gray-100 dark:border-gray-800">
                                    <td class="px-2 py-1"><input type="checkbox" x-model="item._include"></td>
                                    <td class="px-2 py-1 font-mono" x-text="item.code"></td>
                                    <td class="px-2 py-1" x-text="item.product"></td>
                                    <td class="px-2 py-1" x-text="item.product_name"></td>
                                    <td class="px-2 py-1">
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-medium"
                                              :class="badgeClass(item.match_status)"
                                              x-text="badgeLabel(item.match_status, item.confidence)"></span>
                                    </td>
                                    <td class="px-2 py-1 text-right" x-text="item.total_ordered_units + (item.is_weight_based ? ' box' : ' pc')"></td>
                                    <td class="px-2 py-1 text-right" x-text="'€' + (item.unit_cost || 0).toFixed(2)"></td>
                                    <td class="px-2 py-1 text-right" x-text="'€' + (item.line_total || 0).toFixed(2)"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Unmatched -->
            <section x-show="unmatched.length">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                        Unmatched items
                        <span class="text-xs font-normal text-gray-500">(pick a till product or leave out)</span>
                    </h4>
                    <button type="button" @click="aiSuggest()" :disabled="aiPending"
                            class="inline-flex items-center px-3 py-1.5 bg-purple-600 hover:bg-purple-700 disabled:opacity-50 text-white text-xs font-medium rounded-md">
                        <span x-show="!aiPending">Suggest with AI</span>
                        <span x-show="aiPending">Asking AI…</span>
                    </button>
                </div>
                <div class="overflow-x-auto rounded border border-gray-200 dark:border-gray-700">
                    <table class="min-w-full text-xs">
                        <thead class="bg-gray-50 dark:bg-gray-900 text-gray-600 dark:text-gray-300">
                            <tr>
                                <th class="px-2 py-1 text-left w-8">Use</th>
                                <th class="px-2 py-1 text-left">Dynamis SKU</th>
                                <th class="px-2 py-1 text-left">Dynamis description</th>
                                <th class="px-2 py-1 text-left">Pick till product</th>
                                <th class="px-2 py-1 text-right">Qty</th>
                                <th class="px-2 py-1 text-right">Line total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(item, idx) in unmatched" :key="'u'+idx">
                                <tr class="border-t border-gray-100 dark:border-gray-800">
                                    <td class="px-2 py-1"><input type="checkbox" x-model="item._include"></td>
                                    <td class="px-2 py-1 font-mono" x-text="item.code"></td>
                                    <td class="px-2 py-1" x-text="item.product"></td>
                                    <td class="px-2 py-1">
                                        <select x-model="item.product_id" @change="onUnmatchedPick(item)"
                                                class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-xs">
                                            <option value="">— leave out —</option>
                                            <template x-for="s in item.suggestions" :key="s.product_id">
                                                <option :value="s.product_id"
                                                        x-text="s.product_name + ' (fuzzy ' + s.score + ')'"></option>
                                            </template>
                                            <template x-if="item.product_id && !item.suggestions.some(s => s.product_id === item.product_id)">
                                                <option :value="item.product_id" x-text="item.product_name"></option>
                                            </template>
                                        </select>
                                        <div x-show="item.match_status === 'matched_ai'" class="mt-1 text-[10px] text-purple-700 dark:text-purple-400">
                                            AI suggestion (<span x-text="item.confidence"></span>)
                                        </div>
                                    </td>
                                    <td class="px-2 py-1 text-right" x-text="item.total_ordered_units + (item.is_weight_based ? ' box' : ' pc')"></td>
                                    <td class="px-2 py-1 text-right" x-text="'€' + (item.line_total || 0).toFixed(2)"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Freight -->
            <section x-show="freight.items && freight.items.length">
                <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-100 mb-2">Freight / transport</h4>
                <div class="rounded border border-gray-200 dark:border-gray-700 px-3 py-2 text-sm text-gray-700 dark:text-gray-300 flex justify-between">
                    <span>Recorded as delivery freight charge</span>
                    <span class="font-medium">€<span x-text="(freight.total || 0).toFixed(2)"></span></span>
                </div>
            </section>

            <input type="hidden" name="confirmed" :value="confirmedPayload()">
            <button type="submit" :disabled="!canSubmit()"
                    class="inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white font-medium rounded-md">
                Import <span class="ml-1" x-text="selectedCount()"></span> items
            </button>
        </div>
    </form>
</div>

<script>
function dynamisXlsx() {
    return {
        fileReady: false,
        parsing: false,
        parsed: false,
        aiPending: false,
        error: null,
        orderNumber: null,
        matched: [],
        unmatched: [],
        freight: { items: [], total: 0 },
        totals: {},

        onFileSelect(e) {
            this.fileReady = e.target.files.length > 0;
            this.parsed = false;
            this.error = null;
        },
        reset() {
            this.parsed = false;
            this.matched = [];
            this.unmatched = [];
            this.freight = { items: [], total: 0 };
            this.totals = {};
            this.orderNumber = null;
            this.error = null;
        },
        async parseFile() {
            this.parsing = true;
            this.error = null;
            const fileInput = document.getElementById('xlsx_file');
            const fd = new FormData();
            fd.append('xlsx_file', fileInput.files[0]);
            fd.append('_token', document.querySelector('meta[name="csrf-token"]')?.content
                || document.querySelector('input[name="_token"]').value);

            try {
                const res = await fetch('{{ route('deliveries.parse-xlsx') }}', { method: 'POST', body: fd });
                const json = await res.json();
                if (!json.success) {
                    this.error = json.message || 'Parse failed';
                    this.parsed = false;
                    return;
                }
                this.orderNumber = json.order_number;
                this.totals = json.totals || {};
                this.freight = json.freight || { items: [], total: 0 };
                this.matched = (json.matched || []).map(i => ({ ...i, _include: true }));
                this.unmatched = (json.unmatched || []).map(i => ({
                    ...i,
                    _include: false,
                    product_id: i.product_id || '',
                    suggestions: i.suggestions || [],
                }));
                this.parsed = true;
            } catch (e) {
                this.error = 'Network error: ' + e.message;
            } finally {
                this.parsing = false;
            }
        },
        onUnmatchedPick(item) {
            if (item.product_id) {
                const s = (item.suggestions || []).find(s => s.product_id === item.product_id);
                if (s) {
                    item.product_name = s.product_name;
                    item.product_code = s.product_code;
                    item.confidence = s.score;
                    item.match_status = 'manual';
                }
                item._include = true;
            } else {
                item._include = false;
                item.match_status = 'unmatched';
            }
        },
        async aiSuggest() {
            const toSend = this.unmatched.filter(i => !i.product_id);
            if (toSend.length === 0) return;
            this.aiPending = true;
            this.error = null;
            try {
                const res = await fetch('{{ route('deliveries.ai-suggest-xlsx') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                    },
                    body: JSON.stringify({ unmatched: toSend }),
                });
                const json = await res.json();
                if (!json.success) {
                    this.error = json.message || 'AI suggestion failed';
                    return;
                }
                const byCode = Object.fromEntries((json.items || []).map(x => [x.code, x]));
                this.unmatched = this.unmatched.map(item => {
                    const s = byCode[item.code];
                    if (s && s.match_status === 'matched_ai') {
                        return { ...item,
                            product_id: s.product_id,
                            product_code: s.product_code,
                            product_name: s.product_name,
                            confidence: s.confidence,
                            ai_model: s.ai_model,
                            match_status: 'matched_ai',
                            _include: true,
                            suggestions: [
                                ...(item.suggestions || []).filter(x => x.product_id !== s.product_id),
                                { product_id: s.product_id, product_code: s.product_code, product_name: s.product_name, score: s.confidence },
                            ],
                        };
                    }
                    return item;
                });
            } catch (e) {
                this.error = 'Network error: ' + e.message;
            } finally {
                this.aiPending = false;
            }
        },
        badgeClass(status) {
            return {
                matched_saved: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
                matched_fuzzy: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
                matched_ai: 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300',
                manual: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
                unmatched: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
            }[status] || 'bg-gray-100 text-gray-700';
        },
        badgeLabel(status, conf) {
            const label = {
                matched_saved: 'saved',
                matched_fuzzy: 'fuzzy',
                matched_ai: 'AI',
                manual: 'manual',
                unmatched: '—',
            }[status] || status;
            return conf ? `${label} ${conf}` : label;
        },
        selectedCount() {
            const m = this.matched.filter(i => i._include).length;
            const u = this.unmatched.filter(i => i._include && i.product_id).length;
            return m + u;
        },
        canSubmit() {
            return this.selectedCount() > 0;
        },
        confirmedPayload() {
            const rows = [];
            this.matched.forEach(i => {
                if (i._include) rows.push({
                    code: i.code,
                    product_id: i.product_id,
                    product_code: i.product_code,
                    matched_by: i.match_status === 'matched_saved' ? 'manual' : (i.match_status === 'matched_fuzzy' ? 'fuzzy' : (i.match_status === 'matched_ai' ? 'ai' : 'manual')),
                    confidence: i.confidence,
                    ai_model: i.ai_model || null,
                });
            });
            this.unmatched.forEach(i => {
                if (i._include && i.product_id) rows.push({
                    code: i.code,
                    product_id: i.product_id,
                    product_code: i.product_code,
                    matched_by: i.match_status === 'matched_ai' ? 'ai' : 'manual',
                    confidence: i.confidence,
                    ai_model: i.ai_model || null,
                });
            });
            return JSON.stringify(rows);
        },
        onSubmit(e) {
            if (!this.canSubmit()) {
                e.preventDefault();
                this.error = 'Select at least one item to import.';
            }
        },
    };
}
</script>
