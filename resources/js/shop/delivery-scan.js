/**
 * Shop mode delivery scan page.
 *
 * Talks to the legacy delivery endpoints, whose URLs arrive as data-* attributes
 * on the page root so no Blade leaks into this file:
 *   - delivery-legacy.items           GET  the classified invoice lines and scans
 *   - delivery-legacy.scan-increment  POST one more unit (or one case)
 *   - delivery-legacy.update-quantity PATCH an absolute corrected quantity
 *
 * A scan is two steps, as on the office scanner: the code is looked up with a
 * zero quantity, which records nothing, a prompt shows the product with what has
 * been scanned so far, the invoice figure and the stock, and only "Add N" records
 * it. Scanning an outer/case barcode adds whole cases, which the endpoint works
 * out. Detecting a code stops the camera, so `shop-scan-saved` reopens it once
 * the prompt has closed — never while it is open, or the code still in frame
 * would confirm the prompt and silently add another unit.
 *
 * Items without a barcode (the weekly cheese) are picked from a list instead:
 * "No barcode? Find by name" opens the supplier's products, with a filter and a
 * search of every product as a fallback. Picking one runs the same lookup a
 * scan does, by its code, and opens the same prompt with an empty "Quantity or
 * weight" field. The list stays open between items until it is closed.
 *
 * Screen 05 v2: a row is a button. Tapping it opens a correction card beside the
 * list, because a button cannot hold the stepper's own buttons. Sort is a single
 * toggle whose label names the order in force.
 *
 * After every write the whole list is refetched: the server owns the expected
 * quantities and the statuses, and re-deriving them here would be a second
 * implementation of the office page's rules.
 */
import mix from './mix.js';
import productImages from './product-images.js';
import productTypeahead from './product-typeahead.js';
import { parseQuantity, quantityText } from './quantity.js';

const TOAST_MS = 3000;

export default () => mix(productImages(), productTypeahead(), {
    session: null,
    rows: [],
    progress: { total: 0, checked: 0, issues: 0 },
    sort: 'new',
    // Barcodes scanned on this page, newest first — drives the "New first" order.
    recent: [],
    latest: null,
    // The open prompt: a looked-up product awaiting a quantity. Nothing is
    // recorded while this is set. `pending.typed` is null while the stepper is
    // in charge, or the text of a typed quantity or weight.
    pending: null,
    busy: false,
    editing: null,
    // The correction card's typed quantity: null while its stepper is in charge.
    editTyped: null,
    toast: null,
    toastTimer: null,
    flag: null,
    error: null,
    // The "Find by name" list is open, and whether it searches every product
    // rather than this supplier's.
    manual: false,
    everywhere: false,
    searchWhenEmpty: true,

    init() {
        // A typed correction belongs to the row it was typed for.
        this.$watch('editing', () => { this.editTyped = null; });
        this.load();
    },

    get itemsUrl() {
        return this.$root.dataset.itemsUrl;
    },

    get scanUrl() {
        return this.$root.dataset.scanUrl;
    },

    get updateUrl() {
        return this.$root.dataset.updateUrl;
    },

    get delId() {
        return this.$root.dataset.delId;
    },

    get supplierId() {
        return this.$root.dataset.supplierId;
    },

    get csrf() {
        return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    },

    /**
     * The POS `delivery` scratch table is per sync, not per session, so a supplier
     * whose invoice has not been synced (or has been replaced) has no lines at all.
     * Scans are still recorded; they simply cannot be checked against anything.
     */
    get hasInvoice() {
        return !! this.progress && this.progress.total > 0;
    },

    get percent() {
        return this.progress.total
            ? Math.round((this.progress.checked / this.progress.total) * 100)
            : 0;
    },

    /**
     * The quantity the prompt would record: the stepper's, or the typed one —
     * null while the typed text is not a valid positive quantity (see
     * parseQuantity: a barcode typed into the field never is one).
     */
    get qtyValue() {
        if (! this.pending) {
            return null;
        }

        return this.pending.typed === null ? this.pending.qty : parseQuantity(this.pending.typed);
    },

    /**
     * The prompt's typed text for x-model. Null-safe both ways: the field stays
     * in the DOM (x-show) while no prompt is open.
     */
    get typedQty() {
        return this.pending?.typed ?? '';
    },

    set typedQty(value) {
        if (this.pending) {
            this.pending.typed = value;
        }
    },

    get unitsToAdd() {
        if (this.qtyValue === null) {
            return 0;
        }

        return this.qtyValue * (this.pending.scanType === 'case' ? this.pending.caseUnits : 1);
    },

    get addLabel() {
        const n = this.qtyValue;

        if (n === null) {
            return 'Add';
        }

        if (this.pending.scanType === 'case') {
            return `Add ${n} case${n === 1 ? '' : 's'} · ${this.unitsToAdd} units`;
        }

        // A fraction is a weight, not a number of units.
        if (! Number.isInteger(n)) {
            return `Add ${quantityText(n)}`;
        }

        return `Add ${n} unit${n === 1 ? '' : 's'}`;
    },

    /**
     * The pending scan's product, as a plain reference. `x-shop.product-thumb`
     * puts `expr` inside `imageFailed(...)` too, and an optional chain there would
     * be an assignment target Alpine cannot evaluate.
     */
    get pendingProduct() {
        return this.pending?.product ?? null;
    },

    /** The row the correction card is editing, or null. */
    get editingRow() {
        return this.rows.find((r) => r.barcode === this.editing) ?? null;
    },

    /**
     * "New first" puts the items just scanned at the top, then everything still
     * unscanned, then the rest by name. "Scanned first" is the reverse emphasis:
     * what has been counted, then what has not.
     */
    get sorted() {
        const byName = (a, b) => (a.name ?? '').localeCompare(b.name ?? '');

        if (this.sort === 'scanned') {
            return [...this.rows].sort((a, b) => {
                const seen = (r) => (r.scanned !== null ? 0 : 1);

                return seen(a) - seen(b) || byName(a, b);
            });
        }

        const rank = (r) => {
            const i = this.recent.indexOf(r.barcode);

            if (i !== -1) {
                return i;
            }

            return r.scanned === null ? this.recent.length : this.recent.length + 1;
        };

        return [...this.rows].sort((a, b) => rank(a) - rank(b) || byName(a, b));
    },

    async load() {
        const params = new URLSearchParams({ delID: this.delId, supplierID: this.supplierId });

        try {
            const response = await fetch(`${this.itemsUrl}?${params}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            const data = await response.json();

            this.session = data.session;
            this.rows = data.rows;
            this.progress = data.progress;
            this.error = null;

            // Correcting an unexpected row to 0 deletes it server-side, so the
            // card would otherwise sit there with nothing in it.
            if (this.editing !== null && this.editingRow === null) {
                this.editing = null;
            }
        } catch (e) {
            this.error = 'Could not load the invoice lines';
        }
    },

    /** A code from the scan field or the camera. */
    onScan(code) {
        return this.lookup(code, false);
    },

    /**
     * Step one: look the code up without recording anything. A scan arriving
     * while a prompt is open confirms that prompt first, so "scan, scan, scan"
     * stays as fast as the office page.
     *
     * `manual` is a product picked by name: its prompt starts with an empty
     * field to type the amount into, and keeps focus there rather than handing
     * it to the scan field.
     *
     * A scan over a prompt with no valid amount typed (a picked item whose
     * weight was never entered) cannot confirm it: that prompt is replaced and a
     * toast says the item was not added.
     */
    async lookup(code, manual) {
        let dropped = null;

        if (this.pending && this.qtyValue === null) {
            dropped = this.pending.product?.name ?? 'Item';
        } else if (this.pending) {
            await this.commit();
        }

        // A scan is a new subject; an open correction card for another row would
        // sit there looking current.
        this.editing = null;
        this.busy = true;

        try {
            const data = await this.post(this.scanUrl, {
                delID: this.delId,
                barcode: code,
                quantity: 0,
                supplierID: this.supplierId,
            });

            if (! data.success || ! data.product) {
                // No reportDropped() here: an unknown code leaves the open prompt
                // in place, so the picked item is still waiting for its amount.
                this.announceError(`Product not found for ${code}`);
                this.announceSaved();

                return;
            }

            this.pending = {
                code,
                product: data.product,
                expected: data.expectedQty,
                scannedSoFar: data.newQuantity,
                scanType: data.scanType || 'unit',
                caseUnits: data.caseUnits || 1,
                qty: 1,
                typed: manual ? '' : null,
                manual,
            };

            this.reportDropped(dropped);

            // The prompt sits above the scan field, but someone who has scrolled
            // down the list would still not see it open. Same call the correction
            // card makes.
            this.$nextTick(() => this.$refs.prompt?.scrollIntoView({ block: 'nearest' }));

            if (manual) {
                this.focusField('qty');
            }

            // Not for a picked product: shop-scan-done hands focus to the scan
            // field, away from the quantity field just focused.
            if (! manual) {
                this.announceDone();
            }
        } catch (e) {
            this.announceError('Scan failed');
        } finally {
            this.busy = false;
        }
    },

    /** After a lookup that replaced an unfilled prompt; one toast, shown last. */
    reportDropped(name) {
        if (name) {
            this.showToast('warn', `${name} not added, no amount entered`);
        }
    },

    bump(delta) {
        if (! this.pending) {
            return;
        }

        this.pending.qty = Math.max(1, this.pending.qty + delta);
    },

    /**
     * Swap the stepper for a field, for a weight or a count too big to step to.
     * Not offered on a case prompt: a case count stays a whole number.
     */
    typeQuantity() {
        if (! this.pending || this.pending.scanType === 'case') {
            return;
        }

        this.pending.typed = String(this.pending.qty);
        this.focusField('qty');
    },

    /**
     * Focus and select a typed-quantity field once it is visible. x-show reveals
     * it after $nextTick has run (measured: still display:none there), and a
     * hidden element ignores focus(), so wait a frame as scan-input.js does.
     */
    focusField(ref) {
        this.$nextTick(() => requestAnimationFrame(() => {
            this.$refs[ref]?.focus();
            this.$refs[ref]?.select();
        }));
    },

    /**
     * Step two: record the quantity.
     *
     * The `busy` half of the guard is load-bearing, not decoration. A typed scan
     * while a prompt is open reaches this twice from one keydown — once via
     * onScan, once via the window Enter handler, because scan-input empties the
     * input before dispatching. Setting `busy` synchronously before the first
     * await makes the second call a no-op.
     */
    async commit() {
        if (! this.pending || this.busy || this.qtyValue === null) {
            return;
        }

        this.busy = true;
        const item = this.pending;
        const quantity = this.qtyValue;

        try {
            const data = await this.post(this.scanUrl, {
                delID: this.delId,
                barcode: item.code,
                quantity,
                supplierID: this.supplierId,
            });

            if (! data.success) {
                this.showToast('bad', 'Not saved, try again');

                return;
            }

            this.latest = data.product?.barcode ?? item.code;
            this.remember(this.latest);
            this.flag = data.customerRequests?.length
                ? 'Put aside for '+ data.customerRequests.map((c) => c.customer_name).join(', ')
                : null;
            this.pending = null;

            await this.load();
            this.reportRow(this.latest);
            this.announceDone();
            this.announceSaved();

            if (item.manual) {
                this.resetManual();
            }
        } catch (e) {
            this.showToast('bad', 'Not saved, try again');
        } finally {
            this.busy = false;
        }
    },

    cancelPending() {
        const wasManual = this.pending?.manual;

        this.pending = null;
        this.announceDone();
        this.announceSaved();

        if (wasManual) {
            this.resetManual();
        }
    },

    // ---- Find by name -------------------------------------------------------

    /** The supplier's stocked products by default; every product when asked. */
    searchParams(q) {
        return this.everywhere
            ? { q, stocked: 0, per_page: 20 }
            : { q, supplier_id: this.supplierId, stocked: 1, per_page: 50 };
    },

    openManual() {
        this.manual = true;
        this.everywhere = false;
        this.query = '';
        this.editing = null;
        this.search();

        this.$nextTick(() => {
            this.$refs.manual?.scrollIntoView({ block: 'nearest' });

            // On a touch device the keyboard would cover a list that usually
            // needs no filtering.
            if (! document.getElementById('shop-root')?.classList.contains('is-touch')) {
                this.$refs.filter?.focus();
            }
        });
    },

    closeManual() {
        this.manual = false;
        this.everywhere = false;
        this.query = '';
        this.results = [];
        this.answered = null;
        this.total = 0;
        this.searchError = null;
        // An answer still in flight must not refill the closed list (and, being
        // stale, will not reset the spinner itself).
        this.searchSeq++;
        this.searching = false;
        this.announceDone();
    },

    filterManual() {
        if (this.query.trim() === '') {
            this.everywhere = false;
        }

        this.search();
    },

    searchEverywhere() {
        this.everywhere = true;
        this.search();
    },

    /** Back to the supplier's list for the next item; the list stays open. */
    resetManual() {
        this.query = '';
        this.everywhere = false;
        this.search();
    },

    /**
     * Overrides the typeahead's: the list stays as it is under the prompt, and
     * picking records nothing until the amount is typed and added.
     */
    pickResult(p) {
        return this.lookup(p.code, true);
    },

    /**
     * Report from the reloaded row rather than the increment response, so the
     * toast and the row beside it can never quote different figures. (The legacy
     * endpoint derives `expectedQty` slightly differently for fractional orders.)
     *
     * With no invoice lines there is nothing to check against, so every row is
     * "unexpected"; a warning on every item would be noise. Confirm the add and
     * its running total instead.
     */
    reportRow(barcode) {
        const row = this.rows.find((r) => r.barcode === barcode);

        if (! this.hasInvoice) {
            this.showToast('ok', row ? `Added · ${this.stockText(row.scanned)} so far` : 'Added');

            return;
        }

        if (! row) {
            this.showToast('warn', 'Not on this invoice');

            return;
        }

        switch (row.status) {
            case 'ok':
                this.showToast('ok', 'Matches invoice');
                break;
            case 'short':
                this.showToast('warn', `Short: ${this.stockText(row.scanned)} of ${this.stockText(row.expected)}`);
                break;
            case 'over':
                this.showToast('warn', `Over: ${this.stockText(row.scanned)} of ${this.stockText(row.expected)}`);
                break;
            default:
                this.showToast('warn', 'Not on this invoice');
        }
    },

    remember(barcode) {
        this.recent = [barcode, ...this.recent.filter((b) => b !== barcode)];
    },

    /** The label names the order in force, so tapping it flips to the other. */
    toggleSort() {
        this.sort = this.sort === 'new' ? 'scanned' : 'new';
    },

    edit(row) {
        const opening = this.editing !== row.barcode;

        this.editing = opening ? row.barcode : null;

        // On a phone the card is below the fold; bring it into view as the
        // scan prompt does.
        if (opening) {
            this.$nextTick(() => this.$refs.correct?.scrollIntoView({ block: 'nearest' }));
        }
    },

    adjust(row, delta) {
        return this.saveQuantity(row, Math.max(0, Number(((row.scanned ?? 0) + delta).toFixed(3))));
    },

    /** Swap the correction card's stepper for a field. */
    typeCorrection() {
        if (! this.editingRow) {
            return;
        }

        this.editTyped = this.stockText(this.editingRow.scanned ?? 0);
        this.focusField('editQty');
    },

    /**
     * Set the typed correction. 0 is allowed, as on the stepper: it deletes an
     * unexpected row, and load() then closes the card.
     */
    async setCorrection() {
        const value = parseQuantity(this.editTyped, { allowZero: true });

        if (value === null || ! this.editingRow || this.busy) {
            return;
        }

        // A failed save keeps the field open with what was typed, so it can be
        // retried rather than looking as if it had worked.
        if (await this.saveQuantity(this.editingRow, value)) {
            this.editTyped = null;
        }
    },

    /**
     * PATCH an absolute quantity for a row, then refetch the list. True when the
     * save went through.
     */
    async saveQuantity(row, target) {
        this.busy = true;

        try {
            const data = await this.post(this.updateUrl, {
                delID: this.delId,
                barcode: row.barcode,
                quantity: target,
                supplierID: this.supplierId,
            }, 'PATCH');

            if (! data.success) {
                this.showToast('bad', 'Could not save');

                return false;
            }

            await this.load();

            return true;
        } catch (e) {
            this.showToast('bad', 'Could not save');

            return false;
        } finally {
            this.busy = false;
        }
    },

    async post(url, body, method = 'POST') {
        const response = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': this.csrf,
            },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        });

        return await response.json();
    },

    /**
     * A stock figure or a quantity as a person would read it (see quantity.js).
     * Takes the number rather than the row, because the scan prompt has the figure
     * on `pending.product.currentStock` while rows have it on `row.stock`.
     */
    stockText(value) {
        return quantityText(value);
    },

    expectedLabel(row) {
        return `/ ${row.expected ?? '—'}`;
    },

    /**
     * A pill only where there is something to say. A matching line needs no
     * badge, and without an invoice nothing is "unexpected" — every row is just
     * a count.
     */
    pill(row) {
        switch (row.status) {
            case 'ok':
                return null;
            case 'short':
                return { text: 'Short', tone: 'shop-pill--warn' };
            case 'over':
                return { text: 'Over', tone: 'shop-pill--warn' };
            case 'unexpected':
                return this.hasInvoice ? { text: 'Unexpected', tone: 'shop-pill--bad' } : null;
            default:
                return { text: 'Not scanned', tone: 'shop-pill--muted' };
        }
    },

    showToast(tone, text) {
        this.toast = { tone, text };
        clearTimeout(this.toastTimer);
        this.toastTimer = setTimeout(() => { this.toast = null; }, TOAST_MS);
    },

    announceDone() {
        window.dispatchEvent(new CustomEvent('shop-scan-done'));
    },

    announceError(message) {
        window.dispatchEvent(new CustomEvent('shop-scan-error', { detail: message }));
    },

    /**
     * Detecting a code stops the camera; bring it back once the prompt has
     * closed. Deliberately not called when a lookup opens the prompt: the code
     * is usually still under the lens, and a second detection would commit the
     * open prompt and reopen it for one more unit that nobody scanned.
     */
    announceSaved() {
        window.dispatchEvent(new CustomEvent('shop-scan-saved'));
    },
});
