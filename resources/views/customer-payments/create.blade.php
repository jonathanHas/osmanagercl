<x-admin-layout>
    @php
        $preselectInvoiceId = $preselectInvoice?->id;
        $preselectInvoiceData = $preselectInvoice ? [
            'id' => $preselectInvoice->id,
            'invoice_number' => $preselectInvoice->invoice_number,
            'issue_date' => optional($preselectInvoice->issue_date)->toDateString(),
            'total' => (float) $preselectInvoice->total,
            'outstanding' => $preselectInvoice->outstanding_amount,
        ] : null;
    @endphp

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6"
         x-data="customerPaymentForm({{ \Illuminate\Support\Js::from([
             'urls' => [
                 'customerSearch' => route('customer-invoices.api.customers.search'),
                 'openInvoices' => route('customer-payments.api.open-invoices', ['customer' => '__ID__']),
             ],
             'preselectCustomer' => $preselectCustomer ? [
                 'id' => $preselectCustomer->id,
                 'name' => $preselectCustomer->name,
                 'email' => $preselectCustomer->email,
                 'phone' => $preselectCustomer->phone,
             ] : null,
             'preselectInvoice' => $preselectInvoiceData,
             'tills' => $tills->map(fn ($host, $id) => ['id' => (string) $id, 'name' => $host])->values(),
         ]) }})">

        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-100">Record Payment</h2>
            <a href="{{ route('customer-payments.index') }}" class="text-gray-400 hover:text-gray-200">← Back</a>
        </div>

        @if ($errors->any())
            <div class="mb-4 bg-red-800 text-white p-3 rounded text-sm">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('customer-payments.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="customer_id" :value="customer.id ?? ''">

            {{-- Customer block --}}
            <div class="bg-gray-800 p-4 rounded">
                <h3 class="text-gray-200 font-semibold mb-2">Customer</h3>
                <template x-if="!customer.id">
                    <div class="relative">
                        <input type="text" placeholder="Search customer by name, email, phone…"
                               x-model="customerSearchTerm"
                               @input.debounce.250ms="runCustomerSearch()"
                               class="w-full bg-gray-900 border border-gray-700 rounded px-3 py-2 text-gray-100">
                        <div x-show="customerResults.length > 0" x-cloak
                             class="absolute z-10 left-0 right-0 top-full mt-1 bg-gray-900 border border-gray-700 rounded shadow-lg max-h-60 overflow-y-auto">
                            <template x-for="c in customerResults" :key="c.id">
                                <button type="button"
                                        @click="pickCustomer(c)"
                                        class="w-full text-left px-3 py-2 hover:bg-gray-700 border-b border-gray-800">
                                    <div class="text-gray-100" x-text="c.name"></div>
                                    <div class="text-gray-500 text-xs" x-text="[c.email, c.phone].filter(Boolean).join(' · ')"></div>
                                </button>
                            </template>
                        </div>
                    </div>
                </template>
                <template x-if="customer.id">
                    <div class="flex justify-between items-center bg-gray-900 px-3 py-2 rounded">
                        <div>
                            <div class="text-gray-100 font-semibold" x-text="customer.name"></div>
                            <div class="text-gray-500 text-xs" x-text="[customer.email, customer.phone].filter(Boolean).join(' · ')"></div>
                        </div>
                        <button type="button" @click="resetCustomer()"
                                class="text-sm text-blue-400 hover:text-blue-300">Change</button>
                    </div>
                </template>
            </div>

            {{-- Payment block --}}
            <div class="bg-gray-800 p-4 rounded grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Date *</label>
                    <input type="date" name="payment_date" required x-model="paymentDate"
                           class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-2 text-gray-100">
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Amount (€) *</label>
                    <input type="number" name="amount" required step="0.01" min="0.01" inputmode="decimal"
                           x-model.number="amount"
                           class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-2 text-gray-100 text-right font-mono">
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Method *</label>
                    <select name="method" required x-model="method"
                            class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-2 text-gray-100">
                        <option value="card_till">Card (Till)</option>
                        <option value="cash_till">Cash (Till)</option>
                        <option value="online">Online (Bank)</option>
                    </select>
                </div>

                <template x-if="method === 'card_till' || method === 'cash_till'">
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Till *</label>
                        <select name="till_id" x-model="tillId" required
                                class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-2 text-gray-100">
                            <option value="">Pick a till…</option>
                            <template x-for="t in tills" :key="t.id">
                                <option :value="t.id" x-text="`Till ${t.id} — ${t.name}`"></option>
                            </template>
                        </select>
                        <input type="hidden" name="till_name" :value="tillNameForId(tillId)">
                    </div>
                </template>

                <div :class="(method === 'card_till' || method === 'cash_till') ? 'md:col-span-2' : 'md:col-span-3'">
                    <label class="block text-xs text-gray-400 mb-1">Reference</label>
                    <input type="text" name="reference" x-model="reference"
                           placeholder="Card receipt #, bank ref, etc."
                           class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-2 text-gray-100">
                </div>

                <div class="md:col-span-3">
                    <label class="block text-xs text-gray-400 mb-1">Notes</label>
                    <textarea name="notes" rows="2" x-model="notes"
                              class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-2 text-gray-100"></textarea>
                </div>
            </div>

            {{-- Allocations block --}}
            <div class="bg-gray-800 p-4 rounded">
                <div class="flex flex-wrap justify-between items-center gap-2 mb-3">
                    <h3 class="text-gray-200 font-semibold">Apply to invoices</h3>
                    <div class="flex flex-wrap gap-2 text-sm">
                        <button type="button" @click="autoAllocate()"
                                :disabled="!customer.id || openInvoices.length === 0"
                                :class="(!customer.id || openInvoices.length === 0) ? 'opacity-50 cursor-not-allowed' : ''"
                                class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded inline-flex items-center gap-1"
                                title="Fill the amount with the customer's outstanding total and apply oldest first">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            Auto-allocate
                        </button>
                        <button type="button" @click="autoAllocateOldest()"
                                :disabled="openInvoices.length === 0"
                                :class="openInvoices.length === 0 ? 'opacity-50 cursor-not-allowed' : ''"
                                class="bg-gray-700 hover:bg-gray-600 text-gray-100 px-3 py-1.5 rounded">
                            Apply oldest first
                        </button>
                        <button type="button" @click="splitEqually()"
                                :disabled="openInvoices.length === 0"
                                :class="openInvoices.length === 0 ? 'opacity-50 cursor-not-allowed' : ''"
                                class="bg-gray-700 hover:bg-gray-600 text-gray-100 px-3 py-1.5 rounded">
                            Split equally
                        </button>
                        <button type="button" @click="clearAllocations()"
                                class="text-gray-400 hover:text-gray-200 px-2">Clear</button>
                    </div>
                </div>

                <template x-if="!customer.id">
                    <div class="text-gray-500 text-sm">Pick a customer to load their open invoices.</div>
                </template>

                <template x-if="customer.id && loadingInvoices">
                    <div class="text-gray-500 text-sm">Loading invoices…</div>
                </template>

                <template x-if="customer.id && !loadingInvoices && openInvoices.length === 0">
                    <div class="text-gray-500 text-sm">
                        This customer has no open invoices. The full €<span x-text="(amount || 0).toFixed(2)"></span> will be recorded as on-account credit.
                    </div>
                </template>

                <template x-if="customer.id && !loadingInvoices && openInvoices.length > 0">
                    <div>
                        <table class="min-w-full text-sm">
                            <thead class="text-gray-400 text-xs uppercase">
                                <tr>
                                    <th class="px-2 py-1 text-left">Invoice</th>
                                    <th class="px-2 py-1 text-left">Date</th>
                                    <th class="px-2 py-1 text-right">Total</th>
                                    <th class="px-2 py-1 text-right">Outstanding</th>
                                    <th class="px-2 py-1 text-right w-32">Apply</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(inv, idx) in openInvoices" :key="inv.id">
                                    <tr class="border-t border-gray-700">
                                        <td class="px-2 py-1 font-mono">
                                            <input type="hidden" :name="`allocations[${idx}][customer_invoice_id]`" :value="inv.id">
                                            <span x-text="inv.invoice_number || '(draft)'"></span>
                                        </td>
                                        <td class="px-2 py-1 text-gray-300" x-text="inv.issue_date"></td>
                                        <td class="px-2 py-1 text-right font-mono">€<span x-text="inv.total.toFixed(2)"></span></td>
                                        <td class="px-2 py-1 text-right font-mono text-yellow-400">€<span x-text="inv.outstanding.toFixed(2)"></span></td>
                                        <td class="px-2 py-1 text-right">
                                            <input type="number" step="0.01" min="0" inputmode="decimal"
                                                   :name="`allocations[${idx}][amount]`"
                                                   x-model.number="inv.allocate"
                                                   :max="inv.outstanding"
                                                   class="w-28 bg-gray-900 border border-gray-700 rounded px-2 py-1 text-gray-100 text-right font-mono">
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>

                        <div class="mt-3 flex justify-between items-center text-sm border-t border-gray-700 pt-3">
                            <div class="text-gray-300">
                                <span class="text-gray-500">Allocated:</span>
                                <span class="font-mono">€<span x-text="totalAllocated().toFixed(2)"></span></span>
                                <span class="text-gray-500"> / €<span x-text="(amount || 0).toFixed(2)"></span></span>
                            </div>
                            <div :class="overAllocated() ? 'text-red-400' : (unallocated() > 0.005 ? 'text-blue-300' : 'text-green-400')">
                                <template x-if="overAllocated()">
                                    <span>Over-allocated by €<span x-text="(totalAllocated() - amount).toFixed(2)"></span></span>
                                </template>
                                <template x-if="!overAllocated() && unallocated() > 0.005">
                                    <span>€<span x-text="unallocated().toFixed(2)"></span> on-account credit</span>
                                </template>
                                <template x-if="!overAllocated() && unallocated() <= 0.005 && totalAllocated() > 0">
                                    <span>Fully allocated</span>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <div class="flex justify-end gap-2">
                <a href="{{ route('customer-payments.index') }}"
                   class="px-4 py-2 text-gray-300 hover:text-white">Cancel</a>
                <button type="submit"
                        :disabled="!customer.id || !amount || amount <= 0 || overAllocated()"
                        :class="!customer.id || !amount || amount <= 0 || overAllocated() ? 'opacity-50 cursor-not-allowed' : ''"
                        class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">
                    Save Payment
                </button>
            </div>
        </form>
    </div>

    <script>
        function customerPaymentForm(config) {
            return {
                urls: config.urls,
                tills: config.tills,
                customer: config.preselectCustomer ?? { id: null, name: '', email: '', phone: '' },
                customerSearchTerm: '',
                customerResults: [],
                paymentDate: new Date().toISOString().slice(0, 10),
                amount: config.preselectInvoice ? config.preselectInvoice.outstanding : 0,
                method: 'card_till',
                tillId: '',
                reference: '',
                notes: '',
                openInvoices: [],
                loadingInvoices: false,

                init() {
                    if (this.customer.id) {
                        this.loadOpenInvoices().then(() => {
                            if (config.preselectInvoice) {
                                const target = this.openInvoices.find(i => i.id == config.preselectInvoice.id);
                                if (target) target.allocate = Math.min(this.amount, target.outstanding);
                            }
                        });
                    }
                },

                tillNameForId(id) {
                    const t = this.tills.find(t => t.id === String(id));
                    return t ? t.name : '';
                },

                async runCustomerSearch() {
                    const term = this.customerSearchTerm.trim();
                    if (term === '') { this.customerResults = []; return; }
                    const r = await fetch(`${this.urls.customerSearch}?q=${encodeURIComponent(term)}`, { headers: { Accept: 'application/json' } });
                    const j = await r.json();
                    this.customerResults = j.data ?? [];
                },

                pickCustomer(c) {
                    this.customer = { id: c.id, name: c.name, email: c.email ?? '', phone: c.phone ?? '' };
                    this.customerResults = [];
                    this.customerSearchTerm = '';
                    this.loadOpenInvoices();
                },

                resetCustomer() {
                    this.customer = { id: null, name: '', email: '', phone: '' };
                    this.openInvoices = [];
                },

                async loadOpenInvoices() {
                    if (!this.customer.id) return;
                    this.loadingInvoices = true;
                    try {
                        const url = this.urls.openInvoices.replace('__ID__', this.customer.id);
                        const r = await fetch(url, { headers: { Accept: 'application/json' } });
                        const j = await r.json();
                        this.openInvoices = (j.data ?? []).map(inv => ({ ...inv, allocate: 0 }));
                    } finally {
                        this.loadingInvoices = false;
                    }
                },

                totalAllocated() {
                    return Math.round(
                        this.openInvoices.reduce((s, i) => s + (parseFloat(i.allocate) || 0), 0) * 100
                    ) / 100;
                },
                unallocated() {
                    return Math.round(((this.amount || 0) - this.totalAllocated()) * 100) / 100;
                },
                overAllocated() {
                    return this.totalAllocated() > (this.amount || 0) + 0.005;
                },

                autoAllocateOldest() {
                    let remaining = this.amount || 0;
                    for (const inv of this.openInvoices) {
                        if (remaining <= 0.005) { inv.allocate = 0; continue; }
                        const apply = Math.min(remaining, inv.outstanding);
                        inv.allocate = Math.round(apply * 100) / 100;
                        remaining = Math.round((remaining - apply) * 100) / 100;
                    }
                },

                /**
                 * One-click flow: set the amount to the customer's full outstanding total
                 * and apply it oldest-first. Common case for "pay everything they owe".
                 */
                autoAllocate() {
                    if (this.openInvoices.length === 0) return;
                    const totalOutstanding = this.openInvoices
                        .reduce((s, inv) => s + (parseFloat(inv.outstanding) || 0), 0);
                    this.amount = Math.round(totalOutstanding * 100) / 100;
                    this.autoAllocateOldest();
                },

                splitEqually() {
                    if (this.openInvoices.length === 0) return;
                    const each = Math.round(((this.amount || 0) / this.openInvoices.length) * 100) / 100;
                    for (const inv of this.openInvoices) {
                        inv.allocate = Math.min(each, inv.outstanding);
                    }
                },

                clearAllocations() {
                    for (const inv of this.openInvoices) inv.allocate = 0;
                },
            };
        }
    </script>
</x-admin-layout>
