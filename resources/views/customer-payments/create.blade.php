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
             // The amount is an input here, so ticking an invoice may grow it.
             'amountIsEditable' => true,
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

        <form method="POST" action="{{ route('customer-payments.store') }}" class="space-y-4"
              @submit="confirmPaymentDate($event)">
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

            <x-customer-payments.allocation-table />

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

    @include('customer-payments._allocation-script')

    <script>
        function customerPaymentForm(config) {
            return {
                ...customerAllocationCore(config),

                tills: config.tills,
                customerSearchTerm: '',
                customerResults: [],
                paymentDate: new Date().toISOString().slice(0, 10),
                defaultDate: new Date().toISOString().slice(0, 10),
                amount: config.preselectInvoice ? config.preselectInvoice.outstanding : 0,
                method: 'card_till',
                tillId: '',
                reference: '',
                notes: '',

                init() {
                    this.initAllocations().then(() => {
                        // Preselected invoice arrives via ?customer_invoice_id= and can
                        // only be matched once the fetched rows exist.
                        if (config.preselectInvoice) {
                            const target = this.openInvoices.find(i => i.id == config.preselectInvoice.id);
                            if (target) target.allocate = Math.min(this.amount, target.outstanding);
                        }
                    });
                },

                tillNameForId(id) {
                    const t = this.tills.find(t => t.id === String(id));
                    return t ? t.name : '';
                },

                /**
                 * Guard against silently saving a back-dated payment against today.
                 * If the date is still the untouched default, ask the operator to
                 * confirm before the form submits.
                 */
                confirmPaymentDate(event) {
                    if (this.paymentDate === this.defaultDate) {
                        const ok = window.confirm(
                            `The payment date hasn't been changed from today's default (${this.paymentDate}).\n\n` +
                            `If this payment was made on a different day, click Cancel and update the date first.\n\n` +
                            `Save with this date?`
                        );
                        if (! ok) {
                            event.preventDefault();
                        }
                    }
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
            };
        }
    </script>
</x-admin-layout>
