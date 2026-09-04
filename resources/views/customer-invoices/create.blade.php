<x-admin-layout>
    @php
        $isEdit = ! is_null($invoice);
        $action = $isEdit ? route('customer-invoices.update', $invoice) : route('customer-invoices.store');
        $existingItems = $isEdit ? $invoice->items->map(fn ($i) => [
            'pos_product_id' => $i->pos_product_id,
            'pos_product_code' => $i->pos_product_code,
            'description' => $i->description,
            'quantity' => (float) $i->quantity,
            'unit_price' => (float) $i->unit_price,
            'vat_rate' => (float) $i->vat_rate,
        ])->values() : collect();
        $invoiceNumber = $isEdit && $invoice->invoice_number ? $invoice->invoice_number : 'INV-'.now()->year.'-DRAFT';
        $isDraft = ! $isEdit || $invoice->isEditable();
        $adminEdit = $adminEdit ?? false;

        // A failed save must never cost the user their invoice. Laravel flashes the
        // input on a validation bounce; this view previously ignored it and re-seeded
        // the composer from the model (or from nothing, on create), which is why the
        // page came back blank. Old input wins wholesale when present.
        //
        // Deliberately all-or-nothing rather than per-field old('x', $invoice->x):
        // on an edit bounce, per-field fallback would silently restore DB values for
        // any field the user had deliberately cleared.
        $hasOld = session()->hasOldInput();

        // Keep non-numeric values raw so the user sees exactly what they typed (e.g.
        // the 0 quantity that failed gt:0); cast when numeric so x-model.number and
        // the VAT <select> bind correctly.
        $num = fn ($v) => is_numeric($v) ? (float) $v : ($v ?? '');

        // Items come back from items_json, not old('items'): the FormRequest merges
        // the decoded array into its *own* instance, but Laravel flashes the original
        // request's input, so old('items') is empty. items_json is flashed verbatim.
        $oldItems = $hasOld
            ? (json_decode((string) old('items_json', ''), true) ?: old('items', []))
            : [];

        $seedItems = $hasOld
            ? collect($oldItems)->map(fn ($i) => [
                'pos_product_id' => ($i['pos_product_id'] ?? '') ?: null,
                'pos_product_code' => ($i['pos_product_code'] ?? '') ?: null,
                'description' => $i['description'] ?? '',
                'quantity' => $num($i['quantity'] ?? null),
                'unit_price' => $num($i['unit_price'] ?? null),
                'vat_rate' => $num($i['vat_rate'] ?? null),
            ])->values()
            : $existingItems;

        $seedInvoice = $hasOld ? [
            'customer_id' => old('customer_id') ?: null,
            'customer_name' => old('customer_name', ''),
            'customer_address' => old('customer_address', ''),
            'customer_vat_number' => old('customer_vat_number', ''),
            'customer_email' => old('customer_email', ''),
            'issue_date' => old('issue_date', ''),
            'due_date' => old('due_date', ''),
            'discount_percent' => $num(old('discount_percent', 0)),
            'notes' => old('notes', ''),
        ] : ($isEdit ? [
            'customer_id' => $invoice->customer_id,
            'customer_name' => $invoice->customer_name,
            'customer_address' => $invoice->customer_address,
            'customer_vat_number' => $invoice->customer_vat_number,
            'customer_email' => $invoice->customer_email,
            'issue_date' => optional($invoice->issue_date)->toDateString(),
            'due_date' => optional($invoice->due_date)?->toDateString(),
            'discount_percent' => (float) $invoice->discount_percent,
            'notes' => $invoice->notes,
        ] : null);
    @endphp

    <div class="invoice-composer"
         x-data="customerInvoiceForm({{ \Illuminate\Support\Js::from([
             'isEdit' => $isEdit,
             'invoice' => $seedInvoice,
             'items' => $seedItems,
             'errorKeys' => array_keys($errors->messages()),
             'limits' => ['maxItems' => 500],
             'urls' => [
                 'tillCategories' => route('customer-invoices.api.till.categories'),
                 'tillProducts' => route('customer-invoices.api.till.products'),
                 'productSearch' => route('customer-invoices.api.products.search'),
                 'customerSearch' => route('customer-invoices.api.customers.search'),
                 'customerStore' => route('customer-invoices.api.customers.store'),
             ],
             'adminEdit' => $adminEdit,
         ]) }})">

        {{-- Top bar --}}
        <header class="top-bar">
            <div class="crumb">
                <a href="{{ route('customer-invoices.index') }}" class="back">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="m15 18-6-6 6-6"/></svg>
                    Invoices
                </a>
                <span class="sep">/</span>
                <span class="muted">{{ $isEdit ? 'Edit' : 'New invoice' }}</span>
                @if ($isDraft)
                    <span class="draft-pill">Draft</span>
                @elseif ($isEdit && $invoice->isIssued())
                    <span class="issued-pill">Issued</span>
                @endif
            </div>
            <div class="top-right">
                <span class="autosave"><span class="dot"></span> EUR €</span>
            </div>
        </header>

        @if ($errors->any())
            <div class="errors">
                <strong>This invoice wasn't saved &mdash; your work has been kept below.</strong>
                <ul>
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Client-side guard results: caught before submitting, so nothing is lost. --}}
        <div class="errors client-errors" x-show="clientErrors.length" x-cloak>
            <strong>Please fix these before saving:</strong>
            <ul>
                <template x-for="(e, i) in clientErrors" :key="i">
                    <li class="client-error-item" @click="focusError(e)" x-text="e.message"></li>
                </template>
            </ul>
        </div>

        @if ($adminEdit)
            <div class="errors" style="background: oklch(0.78 0.13 80 / 0.12); border-color: oklch(0.78 0.13 80 / 0.4); color: #f7c777;">
                <strong>Admin edit</strong> — you are editing the issued invoice <strong>{{ $invoice->invoice_number }}</strong>.
                The invoice number and status are preserved; the change will be recorded in the audit trail (last_edited_at, last_edited_by).
                Use this only to correct mistakes — for substantive changes, void and reissue instead.
            </div>
        @endif

        {{-- ================== FORM (one form, two layouts) ================== --}}
        <form id="invoice-form" method="POST" action="{{ $action }}">
            @csrf
            @if ($isEdit) @method('PUT') @endif

            {{-- Hidden source-of-truth inputs (drive what gets submitted regardless of which UI is visible) --}}
            <input type="hidden" name="customer_id" :value="customer.id ?? ''">
            <input type="hidden" name="customer_name" :value="customer.name">
            <input type="hidden" name="customer_email" :value="customer.email">
            <input type="hidden" name="customer_address" :value="customer.address">
            <input type="hidden" name="customer_vat_number" :value="customer.vat_number">
            <input type="hidden" name="issue_date" :value="invoice.issue_date">
            <input type="hidden" name="due_date" :value="invoice.due_date">
            <input type="hidden" name="discount_percent" :value="invoice.discount_percent || 0">
            <input type="hidden" name="notes" :value="invoice.notes">
            <input type="hidden" name="issue" :value="submitMode">

            {{-- Line items travel as one JSON field, not 6 inputs per line.
                 The old per-item inputs hit PHP's max_input_vars ceiling (1000) at
                 roughly 165 lines and PHP truncates *silently* — the tail never
                 arrived, validation failed on a half-delivered item, and the user
                 got a blank page. items_count lets the server detect any remaining
                 truncation and say so instead of failing mysteriously. --}}
            <input type="hidden" name="items_json" :value="serializedItems()">
            <input type="hidden" name="items_count" :value="items.length">

            {{-- =========================== DESKTOP =========================== --}}
            <div class="desktop-only invoice-desktop">
                <div class="invoice-grid">
                    {{-- Left column: scan + categories --}}
                    <aside class="col-left">
                        @include('customer-invoices._partials.scan-bar', ['variant' => 'desktop'])
                        @include('customer-invoices._partials.category-browser')
                    </aside>

                    {{-- Main column --}}
                    <main class="col-main">
                        <div class="page-title">
                            <div>
                                <div class="eyebrow">Invoice</div>
                                <h1>{{ $invoiceNumber }}</h1>
                            </div>
                            <div class="page-meta">
                                <div class="meta-row"><span>Currency</span><strong>EUR €</strong></div>
                                @if (config('app.business.name'))
                                    <div class="meta-row"><span>Issued by</span><strong>{{ config('app.business.name') }}</strong></div>
                                @endif
                            </div>
                        </div>

                        @include('customer-invoices._partials.customer-card')
                        @include('customer-invoices._partials.dates-card')
                        @include('customer-invoices._partials.line-items-desktop')
                    </main>

                    {{-- Right column: sticky totals --}}
                    <aside class="col-right">
                        @include('customer-invoices._partials.totals-card', ['variant' => 'desktop'])
                    </aside>
                </div>
            </div>

            {{-- =========================== MOBILE =========================== --}}
            <div class="mobile-only invoice-mobile">
                <header class="m-top">
                    <a href="{{ route('customer-invoices.index') }}" class="m-back">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="m15 18-6-6 6-6"/></svg>
                        Back
                    </a>
                    <div class="m-title">
                        <div class="m-eyebrow {{ $isDraft ? '' : 'issued' }}">{{ $isDraft ? 'Draft' : ($isEdit && $invoice->isIssued() ? 'Issued' : 'Draft') }}</div>
                        <div class="m-num">{{ $invoiceNumber }}</div>
                    </div>
                    <div style="width: 32px;"></div>
                </header>

                @include('customer-invoices._partials.scan-bar', ['variant' => 'mobile'])

                <nav class="m-tabs">
                    <button type="button" :class="{ active: mobileTab === 'items' }" @click="mobileTab = 'items'">
                        Items <span class="m-pill" x-text="items.length"></span>
                    </button>
                    <button type="button" :class="{ active: mobileTab === 'customer' }" @click="mobileTab = 'customer'">Customer</button>
                    <button type="button" :class="{ active: mobileTab === 'browse' }" @click="mobileTab = 'browse'">Browse</button>
                </nav>

                <div class="m-body">
                    {{-- Items tab --}}
                    <div x-show="mobileTab === 'items'" class="m-stack">
                        <template x-if="items.length === 0">
                            <div class="m-empty">
                                <div class="m-empty-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/><path d="M7 8v8M11 8v8M15 8v8M19 8v8"/></svg>
                                </div>
                                <div class="m-empty-title">No items yet</div>
                                <div class="m-empty-sub">Scan above or browse categories.</div>
                                <button type="button" class="m-link" @click="addBlankItem()">+ Add ad-hoc line</button>
                            </div>
                        </template>
                        <template x-for="(item, idx) in items" :key="`m-${idx}`">
                            <div class="m-line" :class="{ 'line-invalid': serverItemErrors.has(idx) || clientItemErrors.has(idx) }">
                                <div class="m-line-top">
                                    <input type="text" class="m-line-name" :data-item-idx="idx" data-field="description" x-model="item.description">
                                    <div class="m-line-gross mono">
                                        <template x-if="isAdHoc(item)">
                                            <span class="m-gross-edit">€<input type="number" step="0.01" min="0" inputmode="decimal"
                                                   :value="item._grossFocused ? item._grossDraft : lineGross(item).toFixed(2)"
                                                   @focus="item._grossFocused = true; item._grossDraft = lineGross(item).toFixed(2)"
                                                   @input="item._grossDraft = $event.target.value; applyLineGross(item, $event.target.value)"
                                                   @blur="item._grossFocused = false"></span>
                                        </template>
                                        <template x-if="! isAdHoc(item)">
                                            <span>€<span x-text="lineGross(item).toFixed(2)"></span></span>
                                        </template>
                                    </div>
                                </div>
                                <div class="m-line-bot">
                                    <div class="m-stepper">
                                        <button type="button" @click="item.quantity = Math.max(0, (parseFloat(item.quantity) || 0) - 1)">−</button>
                                        <input type="number" step="0.001" min="0" inputmode="decimal" :data-item-idx="idx" data-field="quantity" x-model.number="item.quantity">
                                        <button type="button" @click="item.quantity = (parseFloat(item.quantity) || 0) + 1">+</button>
                                    </div>
                                    <div class="m-line-meta">
                                        <span class="mono">€<span x-text="(parseFloat(item.unit_price) || 0).toFixed(2)"></span></span>
                                        <select :data-item-idx="idx" data-field="vat_rate" x-model.number="item.vat_rate">
                                            <option value="0">0%</option>
                                            <option value="0.09">9%</option>
                                            <option value="0.135">13.5%</option>
                                            <option value="0.23">23%</option>
                                        </select>
                                        <button type="button" class="m-line-x" @click="removeItem(idx)" aria-label="Remove">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>
                        <button type="button" x-show="items.length > 0" class="m-link" @click="addBlankItem()">+ Add ad-hoc line</button>
                    </div>

                    {{-- Customer tab --}}
                    <div x-show="mobileTab === 'customer'" class="m-stack">
                        <div class="m-field">
                            <label>Search saved</label>
                            <input type="text" placeholder="Type a name…" x-model="customerSearchTerm"
                                   @input.debounce.250ms="runCustomerSearch()">
                            <div x-show="customerResults.length > 0" x-cloak class="search-results" style="margin-top: 4px;">
                                <template x-for="c in customerResults" :key="c.id">
                                    <button type="button" @click="pickCustomer(c)">
                                        <span class="sr-name">
                                            <span x-text="c.name"></span>
                                            <span class="sr-code" x-text="[c.email, c.phone].filter(Boolean).join(' · ')"></span>
                                        </span>
                                    </button>
                                </template>
                            </div>
                        </div>
                        <div class="m-field">
                            <label>Customer name <span class="req">*</span></label>
                            <input type="text" required data-field="customer_name" x-model="customer.name">
                        </div>
                        <div class="m-field">
                            <label>Email</label>
                            <input type="email" inputmode="email" x-model="customer.email">
                        </div>
                        <div class="m-field">
                            <label>Address</label>
                            <textarea rows="2" x-model="customer.address"></textarea>
                        </div>
                        <div class="m-field">
                            <label>VAT Number</label>
                            <input type="text" x-model="customer.vat_number">
                        </div>
                        <button type="button" class="m-link" @click="showCustomerModal = true">+ New customer</button>
                        <div class="m-row-2">
                            <div class="m-field">
                                <label>Issue</label>
                                <input type="date" data-field="issue_date" x-model="invoice.issue_date" required>
                            </div>
                            <div class="m-field">
                                <label>Due</label>
                                <input type="date" data-field="due_date" x-model="invoice.due_date">
                            </div>
                        </div>
                        <div class="m-field">
                            <label>Notes</label>
                            <textarea rows="2" placeholder="Visible on invoice" x-model="invoice.notes"></textarea>
                        </div>
                    </div>

                    {{-- Browse tab --}}
                    <div x-show="mobileTab === 'browse'" class="m-stack m-cats">
                        <div class="m-field" style="margin-bottom: 8px;">
                            <input type="text" placeholder="Filter categories" x-model="categoryFilter">
                        </div>
                        <template x-for="cat in filteredCategories()" :key="`mc-${cat.id}`">
                            <button type="button" class="m-cat" @click="selectCategory(cat); mobileTab = 'items'">
                                <span x-text="cat.name"></span>
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="m9 6 6 6-6 6"/></svg>
                            </button>
                        </template>

                        {{-- After tapping a category, show its products as a sheet --}}
                        <template x-if="selectedCategoryId && categoryProducts.length > 0">
                            <div class="m-stack" style="margin-top: 10px;">
                                <div class="m-empty-title" x-text="`Products in ${selectedCategoryName}`"></div>
                                <template x-for="p in categoryProducts" :key="`mcp-${p.id}`">
                                    <button type="button" class="m-cat" @click="addProduct(p); mobileTab = 'items'">
                                        <span class="m-line-name" x-text="p.name"></span>
                                        <span class="mono" x-text="'€' + p.gross_price.toFixed(2)"></span>
                                    </button>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>

                <footer class="m-foot">
                    <div class="m-totals">
                        <div class="m-trow" x-show="hasDiscount()" x-cloak>
                            <span>Subtotal (before)</span>
                            <span class="mono">€<span x-text="preDiscountNet().toFixed(2)"></span></span>
                        </div>
                        <div class="m-trow" x-show="hasDiscount()" x-cloak>
                            <span style="display: inline-flex; align-items: center; gap: 6px;">
                                Discount
                                <input type="number" step="0.01" min="0" max="100" inputmode="decimal"
                                       x-model.number="invoice.discount_percent"
                                       style="width: 56px; background: var(--bg-2); border: 1px solid var(--line); border-radius: 4px; padding: 2px 4px; color: var(--text); font-family: var(--mono); font-size: 11.5px; text-align: right;">
                                <span style="color: var(--text-mute); font-size: 11px;">%</span>
                                <button type="button" @click="invoice.discount_percent = 0"
                                        style="background: none; border: 0; color: var(--text-mute); font-size: 11px;">×</button>
                            </span>
                            <span class="mono" style="color: var(--danger);">−€<span x-text="discountAmount().toFixed(2)"></span></span>
                        </div>
                        <div class="m-trow" x-show="!hasDiscount()" x-cloak>
                            <button type="button"
                                    @click="invoice.discount_percent = 10"
                                    class="link-btn" style="text-align: left; padding: 0;">
                                + Add discount
                            </button>
                            <span></span>
                        </div>
                        <div class="m-trow"><span>Subtotal</span><span class="mono">€<span x-text="totalNet().toFixed(2)"></span></span></div>
                        <div class="m-trow"><span>VAT</span><span class="mono">€<span x-text="totalVat().toFixed(2)"></span></span></div>
                        <div class="m-trow total"><span>Total</span><span class="mono">€<span x-text="totalGross().toFixed(2)"></span></span></div>
                    </div>
                    @include('customer-invoices._partials.totals-card', ['variant' => 'mobile'])
                </footer>
            </div>
        </form>

        {{-- New customer modal --}}
        <div x-show="showCustomerModal" x-cloak class="modal-backdrop" @click.self="showCustomerModal = false">
            <div class="modal-card">
                <h3 style="margin: 0 0 14px; font-size: 16px; color: var(--text);">New customer</h3>
                <div class="field full"><label>Name <span class="req">*</span></label><input type="text" x-model="newCustomer.name"></div>
                <div class="grid-2">
                    <div class="field"><label>Email</label><input type="email" inputmode="email" x-model="newCustomer.email"></div>
                    <div class="field"><label>Phone</label><input type="tel" inputmode="tel" x-model="newCustomer.phone"></div>
                </div>
                <div class="field full"><label>Address line 1</label><input type="text" x-model="newCustomer.address_line1"></div>
                <div class="field full"><label>Address line 2</label><input type="text" x-model="newCustomer.address_line2"></div>
                <div class="grid-2">
                    <div class="field"><label>City</label><input type="text" x-model="newCustomer.city"></div>
                    <div class="field"><label>Postcode</label><input type="text" x-model="newCustomer.postcode"></div>
                </div>
                <div class="grid-2">
                    <div class="field"><label>VAT Number</label><input type="text" x-model="newCustomer.vat_number"></div>
                    <div class="field">
                        <label>Default discount %</label>
                        <input type="number" step="0.01" min="0" max="100" inputmode="decimal" placeholder="0" x-model.number="newCustomer.default_discount_percent">
                    </div>
                </div>
                <div class="totals-actions">
                    <button type="button" class="btn ghost" @click="showCustomerModal = false">Cancel</button>
                    <button type="button" class="btn primary" @click="saveCustomer()">Save customer</button>
                </div>
            </div>
        </div>

        {{-- Scan toast --}}
        <div x-show="scanFeedback" x-cloak x-transition class="scan-toast" :class="{ error: !scanSuccess }" x-text="scanFeedback"></div>

        {{-- Hidden temp container for html5-qrcode scanFile --}}
        <div id="barcode-scanner-temp" style="display: none;"></div>
    </div>

    @php
        // Pre-render category data for any client-side fallback. We still load via API on init().
    @endphp

    <script>
        function customerInvoiceForm(config) {
            return {
                urls: config.urls,
                isEdit: config.isEdit,
                adminEdit: config.adminEdit ?? false,
                invoice: config.invoice ?? {
                    issue_date: new Date().toISOString().slice(0, 10),
                    due_date: '',
                    discount_percent: 0,
                    notes: '',
                },
                customer: {
                    id: config.invoice?.customer_id ?? null,
                    name: config.invoice?.customer_name ?? '',
                    email: config.invoice?.customer_email ?? '',
                    address: config.invoice?.customer_address ?? '',
                    vat_number: config.invoice?.customer_vat_number ?? '',
                },
                items: [...(config.items ?? [])],

                searchTerm: '',
                searchResults: [],

                customerSearchTerm: '',
                customerResults: [],

                categories: [],
                categoryFilter: '',
                selectedCategoryId: null,
                selectedCategoryName: '',
                categoryProducts: [],
                loadingCategories: false,
                loadingProducts: false,

                showCustomerModal: false,
                newCustomer: { name: '', email: '', phone: '', address_line1: '', address_line2: '', city: '', postcode: '', country: 'IE', vat_number: '', default_discount_percent: 0 },

                mobileTab: 'items',
                submitMode: '0',

                // Pre-submit guard state. The buttons are type="button" and we submit
                // via form.submit(), which bypasses HTML5 constraint validation
                // entirely — so the `required` attributes in the markup never fire
                // and every mistake used to cost a full server round-trip.
                clientErrors: [],
                clientItemErrors: new Set(),
                submitting: false,
                maxItems: config.limits?.maxItems ?? 500,

                // Line indices the server rejected, so a bounced save highlights them.
                serverItemErrors: new Set(
                    (config.errorKeys ?? [])
                        .map(k => k.match(/^items\.(\d+)\./))
                        .filter(Boolean)
                        .map(m => Number(m[1]))
                ),
                scanFeedback: null,
                scanSuccess: true,
                scanner: {
                    cameraActive: false,
                    cameraVisible: false,
                    cameraStatus: '',
                    lastScannedBarcode: '',
                    lastScanTime: 0,
                },

                init() {
                    this.loadCategories();

                    // A bounced save restores the form, so the error box is the only
                    // thing telling the user why nothing happened — make sure they see it.
                    if ((config.errorKeys ?? []).length) {
                        this.$nextTick(() => {
                            document.querySelector('.errors')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        });
                    }
                },

                async loadCategories() {
                    this.loadingCategories = true;
                    try {
                        const r = await fetch(this.urls.tillCategories, { headers: { Accept: 'application/json' } });
                        const j = await r.json();
                        this.categories = j.data ?? [];
                    } finally {
                        this.loadingCategories = false;
                    }
                },

                filteredCategories() {
                    const q = this.categoryFilter.trim().toLowerCase();
                    if (!q) return this.categories;
                    return this.categories.filter(c => (c.name || '').toLowerCase().includes(q));
                },

                groupedCategories() {
                    const filtered = this.filteredCategories();
                    const groups = {};
                    filtered.forEach(c => {
                        const key = ((c.name || '?').charAt(0) || '?').toUpperCase();
                        (groups[key] = groups[key] || []).push(c);
                    });
                    return Object.entries(groups).sort((a, b) => a[0].localeCompare(b[0]));
                },

                async selectCategory(cat) {
                    this.selectedCategoryId = cat.id;
                    this.selectedCategoryName = cat.name;
                    this.loadingProducts = true;
                    this.categoryProducts = [];
                    try {
                        const r = await fetch(`${this.urls.tillProducts}?category=${encodeURIComponent(cat.id)}`, { headers: { Accept: 'application/json' } });
                        const j = await r.json();
                        this.categoryProducts = j.data ?? [];
                    } finally {
                        this.loadingProducts = false;
                    }
                },

                async runSearch() {
                    const term = this.searchTerm.trim();
                    if (term === '') { this.searchResults = []; return; }
                    const r = await fetch(`${this.urls.productSearch}?q=${encodeURIComponent(term)}`, { headers: { Accept: 'application/json' } });
                    const j = await r.json();
                    this.searchResults = j.data ?? [];
                },

                async commitTopMatch() {
                    if (this.searchResults.length === 0 && this.searchTerm.trim() !== '') {
                        await this.runSearch();
                    }
                    if (this.searchResults.length > 0) {
                        this.addProduct(this.searchResults[0]);
                        this.searchResults = [];
                        this.searchTerm = '';
                    }
                },

                addProduct(p) {
                    if (p.id) {
                        const existing = this.items.find(it => it.pos_product_id === p.id);
                        if (existing) {
                            existing.quantity = (parseFloat(existing.quantity) || 0) + 1;
                            this.flashScan(`+1 ${p.name}`);
                            return;
                        }
                    }
                    this.items.push({
                        pos_product_id: p.id,
                        pos_product_code: p.code,
                        description: p.name,
                        quantity: 1,
                        unit_price: p.unit_price_net,
                        vat_rate: p.vat_rate,
                    });
                    this.flashScan(`Added ${p.name}`);
                },

                addBlankItem() {
                    this.items.push({
                        pos_product_id: null,
                        pos_product_code: null,
                        description: '',
                        quantity: 1,
                        unit_price: 0,
                        vat_rate: 0.23,
                        _grossFocused: false,
                        _grossDraft: '',
                    });

                    // A blank description is the single most common cause of a
                    // rejected save — put the cursor there so it gets filled in.
                    const idx = this.items.length - 1;
                    this.$nextTick(() => {
                        const el = [...document.querySelectorAll(`[data-item-idx="${idx}"][data-field="description"]`)]
                            .find(e => e.offsetParent !== null);
                        el?.focus();
                    });
                },

                clearAllItems() {
                    if (this.items.length > 0 && confirm(`Remove all ${this.items.length} item${this.items.length === 1 ? '' : 's'}?`)) {
                        this.items = [];
                    }
                },

                removeItem(idx) { this.items.splice(idx, 1); },

                lineNet(item) {
                    const q = parseFloat(item.quantity) || 0;
                    const u = parseFloat(item.unit_price) || 0;
                    return Math.round(q * u * 100) / 100;
                },
                lineVat(item) {
                    const r = parseFloat(item.vat_rate) || 0;
                    return Math.round(this.lineNet(item) * r * 100) / 100;
                },
                lineGross(item) {
                    return Math.round((this.lineNet(item) + this.lineVat(item)) * 100) / 100;
                },

                /** Ad-hoc lines carry no POS product, so their gross is safe to type into. */
                isAdHoc(item) {
                    return ! item.pos_product_id;
                },

                /**
                 * Back-calculate the unit net from a typed LINE gross (qty included).
                 * Kept at 4 dp to match the decimal:4 unit_price column, so that the
                 * server's round(qty * unit_price, 2) reproduces the gross that was typed.
                 */
                applyLineGross(item, raw) {
                    const g = parseFloat(raw);
                    const q = parseFloat(item.quantity) || 0;
                    const r = parseFloat(item.vat_rate) || 0;
                    if (! isFinite(g) || q <= 0) return;
                    item.unit_price = Math.round(((g / (1 + r)) / q) * 10000) / 10000;
                },

                discountFactor() {
                    const p = parseFloat(this.invoice.discount_percent) || 0;
                    return Math.max(0, 1 - p / 100);
                },

                /**
                 * Mirror the server-side calculateTotals(): group line nets by VAT band,
                 * apply discount factor per band, recompute VAT on the discounted net.
                 * This keeps live preview consistent with what the controller will store.
                 */
                computedBands() {
                    const bands = {
                        '0.23': { rate: 0.23, net: 0 },
                        '0.135': { rate: 0.135, net: 0 },
                        '0.09': { rate: 0.09, net: 0 },
                        '0': { rate: 0, net: 0 },
                    };
                    for (const it of this.items) {
                        const r = parseFloat(it.vat_rate) || 0;
                        const key = String(r);
                        if (bands[key]) bands[key].net += this.lineNet(it);
                        else bands['0.23'].net += this.lineNet(it);
                    }
                    const factor = this.discountFactor();
                    for (const k of Object.keys(bands)) {
                        bands[k].postNet = Math.round(bands[k].net * factor * 100) / 100;
                        bands[k].postVat = Math.round(bands[k].postNet * bands[k].rate * 100) / 100;
                    }
                    return bands;
                },

                preDiscountNet() {
                    return this.items.reduce((s, i) => s + this.lineNet(i), 0);
                },

                totalNet() {
                    const b = this.computedBands();
                    return b['0.23'].postNet + b['0.135'].postNet + b['0.09'].postNet + b['0'].postNet;
                },
                totalVat() {
                    const b = this.computedBands();
                    return b['0.23'].postVat + b['0.135'].postVat + b['0.09'].postVat + b['0'].postVat;
                },
                totalGross() { return this.totalNet() + this.totalVat(); },
                discountAmount() {
                    return Math.round((this.preDiscountNet() - this.totalNet()) * 100) / 100;
                },
                hasDiscount() {
                    return (parseFloat(this.invoice.discount_percent) || 0) > 0;
                },

                // Only the whitelisted keys travel to the server; _grossFocused and
                // _grossDraft are view-only scratch state from addBlankItem().
                serializedItems() {
                    return JSON.stringify(this.items.map(i => ({
                        pos_product_id: i.pos_product_id ?? null,
                        pos_product_code: i.pos_product_code ?? null,
                        description: i.description,
                        quantity: i.quantity,
                        unit_price: i.unit_price,
                        vat_rate: i.vat_rate,
                    })));
                },

                // Mirrors CustomerInvoiceRequest::rules() exactly — same gt/gte/lte
                // semantics — so nothing that passes here can fail server-side.
                validateBeforeSubmit() {
                    const errs = [];
                    const bad = new Set();
                    const num = v => (v === '' || v === null || v === undefined || isNaN(parseFloat(v)))
                        ? NaN
                        : parseFloat(v);

                    const name = String(this.customer.name ?? '').trim();
                    if (! name) {
                        errs.push({ field: 'customer_name', message: 'A customer name is required.' });
                    } else if (name.length > 255) {
                        errs.push({ field: 'customer_name', message: 'Customer name is too long (max 255 characters).' });
                    }

                    if (! this.invoice.issue_date) {
                        errs.push({ field: 'issue_date', message: 'An issue date is required.' });
                    }
                    if (this.invoice.due_date && this.invoice.issue_date && this.invoice.due_date < this.invoice.issue_date) {
                        errs.push({ field: 'due_date', message: 'The due date cannot be before the issue date.' });
                    }

                    const disc = num(this.invoice.discount_percent || 0);
                    if (isNaN(disc) || disc < 0 || disc > 100) {
                        errs.push({ field: 'discount_percent', message: 'Discount must be between 0 and 100%.' });
                    }

                    if (this.items.length === 0) {
                        errs.push({ field: 'items', message: 'Add at least one line item before saving.' });
                    }
                    if (this.items.length > this.maxItems) {
                        errs.push({ field: 'items', message: `This invoice has ${this.items.length} lines; the maximum is ${this.maxItems}. Please split it into two invoices.` });
                    }

                    this.items.forEach((it, idx) => {
                        const push = (field, message) => {
                            errs.push({ field, idx, message: `Line ${idx + 1} ${message}` });
                            bad.add(idx);
                        };
                        const desc = String(it.description ?? '').trim();
                        if (! desc) push('description', 'needs a description.');
                        else if (desc.length > 255) push('description', 'has a description longer than 255 characters.');

                        const q = num(it.quantity);
                        const u = num(it.unit_price);
                        const r = num(it.vat_rate);
                        if (isNaN(q) || q <= 0) push('quantity', 'must have a quantity greater than 0.');
                        if (isNaN(u) || u < 0) push('unit_price', 'cannot have a negative unit price.');
                        if (isNaN(r) || r < 0 || r > 1) push('vat_rate', 'needs a valid VAT rate.');
                    });

                    this.clientErrors = errs;
                    this.clientItemErrors = bad;

                    if (errs.length) {
                        this.focusError(errs[0]);
                        return false;
                    }
                    return true;
                },

                // Desktop and mobile render the same fields; the hidden layout is
                // display:none, so offsetParent === null identifies the dead copy.
                focusError(err) {
                    if (['customer_name', 'issue_date', 'due_date'].includes(err.field)) {
                        this.mobileTab = 'customer';
                    } else if (err.idx != null) {
                        this.mobileTab = 'items';
                    }

                    this.$nextTick(() => {
                        const sel = err.idx == null
                            ? `[data-field="${err.field}"]`
                            : `[data-item-idx="${err.idx}"][data-field="${err.field}"]`;
                        const el = [...document.querySelectorAll(sel)].find(e => e.offsetParent !== null);

                        if (el) {
                            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            el.focus({ preventScroll: true });
                        } else {
                            document.querySelector('.client-errors, .errors')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }
                    });
                },

                submitForm(mode) {
                    if (this.submitting) return;          // also kills double-click duplicate drafts
                    if (! this.validateBeforeSubmit()) return;

                    this.submitMode = mode;
                    this.submitting = true;
                    this.$nextTick(() => document.getElementById('invoice-form').submit());
                },

                async runCustomerSearch() {
                    const term = this.customerSearchTerm.trim();
                    if (term === '') { this.customerResults = []; return; }
                    const r = await fetch(`${this.urls.customerSearch}?q=${encodeURIComponent(term)}`, { headers: { Accept: 'application/json' } });
                    const j = await r.json();
                    this.customerResults = j.data ?? [];
                },
                pickCustomer(c) {
                    this.customer = {
                        id: c.id,
                        name: c.name,
                        email: c.email ?? '',
                        address: [c.address_line1, c.address_line2, c.city, c.postcode].filter(Boolean).join('\n'),
                        vat_number: c.vat_number ?? '',
                    };
                    // Auto-apply customer's default wholesale discount if set
                    const defDisc = parseFloat(c.default_discount_percent);
                    if (!isNaN(defDisc) && defDisc > 0) {
                        this.invoice.discount_percent = defDisc;
                    }
                    this.customerResults = [];
                    this.customerSearchTerm = '';
                },
                async saveCustomer() {
                    if (!this.newCustomer.name.trim()) return;
                    const r = await fetch(this.urls.customerStore, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                            Accept: 'application/json',
                        },
                        body: JSON.stringify(this.newCustomer),
                    });
                    if (!r.ok) {
                        const j = await r.json().catch(() => ({}));
                        alert('Could not save customer: ' + (j.message ?? 'validation error'));
                        return;
                    }
                    const { data } = await r.json();
                    this.pickCustomer(data);
                    this.showCustomerModal = false;
                    this.newCustomer = { name: '', email: '', phone: '', address_line1: '', address_line2: '', city: '', postcode: '', country: 'IE', vat_number: '', default_discount_percent: 0 };
                },

                // ===== Camera =====
                toggleCamera(viewportId) {
                    if (this.scanner.cameraActive) {
                        this.stopCamera();
                        return;
                    }
                    if (!window.BarcodeScanner) {
                        this.scanner.cameraStatus = 'Scanner module not loaded. Ensure HTTPS is enabled.';
                        this.scanner.cameraVisible = true;
                        return;
                    }
                    this.scanner.cameraVisible = true;
                    this.scanner.cameraStatus = 'Starting camera…';
                    this.$nextTick(() => {
                        window.BarcodeScanner.startScanner(
                            viewportId,
                            (decodedText) => this.onCameraDetected(decodedText),
                            () => {}
                        ).then(() => {
                            this.scanner.cameraActive = true;
                            this.scanner.cameraStatus = 'Point camera at barcode';
                        }).catch((err) => {
                            this.scanner.cameraStatus = 'Camera error: ' + (err.message || err);
                            this.scanner.cameraActive = false;
                        });
                    });
                },

                stopCamera() {
                    if (window.BarcodeScanner && window.BarcodeScanner.isRunning()) {
                        window.BarcodeScanner.stopScanner().catch(() => {});
                    }
                    this.scanner.cameraActive = false;
                    this.scanner.cameraVisible = false;
                    this.scanner.cameraStatus = '';
                },

                parseBarcode(raw) {
                    let text = raw.trim();
                    if (text.startsWith(']C1')) text = text.substring(3);
                    if (text.startsWith(']d2')) text = text.substring(3);
                    if (text.startsWith(']e0')) text = text.substring(3);
                    text = text.replace(/[\x1D]/g, '|');
                    const gtin14Match = text.match(/(?:^|\|)01(\d{14})/);
                    if (gtin14Match) return gtin14Match[1];
                    return text;
                },

                onCameraDetected(text) {
                    try {
                        const ctx = new (window.AudioContext || window.webkitAudioContext)();
                        const osc = ctx.createOscillator();
                        osc.type = 'square'; osc.frequency.value = 1000;
                        osc.connect(ctx.destination); osc.start();
                        osc.stop(ctx.currentTime + 0.1);
                    } catch (e) {}
                    if (navigator.vibrate) navigator.vibrate(80);

                    const now = Date.now();
                    if (text === this.scanner.lastScannedBarcode && now - this.scanner.lastScanTime < 2000) return;
                    this.scanner.lastScannedBarcode = text;
                    this.scanner.lastScanTime = now;

                    const code = this.parseBarcode(text);
                    this.searchTerm = code;
                    this.runSearch().then(() => {
                        if (this.searchResults.length > 0) {
                            this.addProduct(this.searchResults[0]);
                            this.searchResults = [];
                            this.searchTerm = '';
                        } else {
                            this.flashScan(`No match for ${code}`, false);
                        }
                    });
                },

                flashScan(msg, success = true) {
                    this.scanFeedback = msg;
                    this.scanSuccess = success;
                    setTimeout(() => { this.scanFeedback = null; }, 1500);
                },
            };
        }
    </script>

    @push('scripts')
        @vite(['resources/css/customer-invoice.css', 'resources/js/barcode-scanner.js'])
    @endpush
</x-admin-layout>
