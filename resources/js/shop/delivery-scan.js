/**
 * Shop mode delivery scan page.
 *
 * Talks to the legacy delivery endpoints, whose URLs arrive as data-* attributes
 * on the page root so no Blade leaks into this file:
 *   - delivery-legacy.items           GET  the classified invoice lines and scans
 *   - delivery-legacy.scan-increment  POST one more unit (or one case)
 *   - delivery-legacy.update-quantity PATCH an absolute corrected quantity
 *   - delivery-legacy.save-outer-barcode POST link a code to a product as its outer
 *
 * A scan is two steps, as on the office scanner: the code is looked up with a
 * zero quantity, which records nothing, a prompt shows the product with what has
 * been scanned so far, the invoice figure and the stock, and only "Add N" records
 * it. Scanning an outer/case barcode adds whole cases, which the endpoint works
 * out. Detecting a code stops the camera, so `shop-scan-saved` reopens it once
 * the prompt has closed — never while it is open, or the code still in frame
 * would confirm the prompt and silently add another unit. A code no product
 * has opens a "Not found" card that can link it as a product's outer barcode
 * through delivery-legacy.save-outer-barcode: the next scan (or a pick by name)
 * is looked up as the unit barcode and the product it names is shown, a tap on
 * "Yes, link it" saves, and the code is then looked up again and opens a case
 * prompt. A scan alone never saves a link.
 *
 * Items without a barcode (the weekly cheese) are picked from a list instead:
 * "No barcode? Find by name" opens the supplier's products, with a filter and a
 * search of every product as a fallback. Picking one runs the same lookup a
 * scan does, by its code, and opens the same prompt with an empty "Quantity or
 * weight" field. The list stays open between items until it is closed.
 *
 * Screen 05 v2: a row is a button. Tapping it opens a correction card beside the
 * list, because a button cannot hold the stepper's own buttons. Sort is a single
 * toggle whose label names the order in force. The card itself (local stepper,
 * typed value, the save 400 ms after the last tap) is the shared part
 * delivery-correction.js, which the summary page uses too (cycle 3).
 *
 * After every write the whole list is refetched: the server owns the expected
 * quantities and the statuses, and re-deriving them here would be a second
 * implementation of the office page's rules.
 */
import mix from './mix.js';
import productImages from './product-images.js';
import productTypeahead from './product-typeahead.js';
import deliveryCorrection from './delivery-correction.js';
import { parseQuantity, quantityText } from './quantity.js';

export default () => mix(productImages(), productTypeahead(), deliveryCorrection(), {
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
    // An unknown code as { code, linking, candidate, error }. `linking` is true
    // once "Link as outer barcode" was tapped, and then the next code scanned
    // (or product picked by name) is looked up as the unit barcode; `candidate`
    // is null or { code, product } for the product that lookup found, waiting
    // for "Yes, link it". Nothing is saved until that tap.
    unknown: null,
    flag: null,
    error: null,
    // The "Find by name" list is open, and whether it searches every product
    // rather than this supplier's.
    manual: false,
    everywhere: false,
    searchWhenEmpty: true,

    init() {
        this.initCorrection();
        this.load();
    },

    get itemsUrl() {
        return this.$root.dataset.itemsUrl;
    },

    get scanUrl() {
        return this.$root.dataset.scanUrl;
    },

    get outerUrl() {
        return this.$root.dataset.outerUrl;
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

    /** The product the link step found, for the thumb component (as above). */
    get candidateProduct() {
        return this.unknown?.candidate?.product ?? null;
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

            this.closeCardIfRowGone();
        } catch (e) {
            this.error = 'Could not load the invoice lines';
        }
    },

    /**
     * A code from the scan field or the camera. While the Not found card waits
     * for a unit barcode, that is what this code is.
     */
    onScan(code) {
        if (this.unknown?.linking) {
            return this.findUnit(code);
        }

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

        // A scan is a new subject; an open correction card for another row, or
        // a Not found card for another code, would sit there looking current.
        // Its unsaved taps are saved first, so the reload below cannot run
        // ahead of the PATCH (the watch cannot be awaited).
        await this.flushNow();
        this.editing = null;
        this.unknown = null;
        this.busy = true;

        try {
            const data = await this.post(this.scanUrl, {
                delID: this.delId,
                barcode: code,
                quantity: 0,
                supplierID: this.supplierId,
            });

            if (! data.success || ! data.product) {
                // A code no product has (not a validation failure): offer to link
                // it as an outer barcode. The card sits above the scan field like
                // the prompt, and is scrolled to for the same reason.
                if (data.success) {
                    this.unknown = { code, linking: false, candidate: null, error: null };
                    this.$nextTick(() => this.$refs.unknown?.scrollIntoView({ block: 'nearest' }));
                }

                // No reportDropped() here: an unknown code leaves the open prompt
                // in place, so the picked item is still waiting for its amount.
                // The camera comes back so the next scan can be the unit barcode.
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
                ? 'Put aside for ' + data.customerRequests.map((c) => c.customer_name + ' (' + c.quantity_label + ')').join(', ')
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

    // ---- Not found: link an outer barcode ------------------------------------

    /** "Link as outer barcode": the next code is looked up as the unit barcode. */
    startLink() {
        if (! this.unknown) {
            return;
        }

        this.unknown.linking = true;
        this.unknown.candidate = null;
        this.unknown.error = null;
        this.editing = null;
        // Clears "Product not found" under the field and hands focus back to it,
        // so a hand scanner or a typed unit barcode lands there.
        this.announceDone();
    },

    dismissUnknown() {
        // The camera was left paused while a candidate was showing (the item
        // was still under the lens); closing the card is the moment to give it
        // back. In the other states it is already running.
        const paused = !! this.unknown?.candidate;

        this.unknown = null;
        this.announceDone();

        if (paused) {
            this.announceSaved();
        }
    },

    /**
     * The scan half of linking: look the code up with a zero quantity (records
     * nothing) and show the product it names, so that a tap saves the link. A
     * scan alone never saves. Revision 1 saved on the scan, and the owner's
     * browser check found that someone who tapped Link by accident, or forgot
     * they had, would silently link the next delivery item as the unit of this
     * outer code.
     */
    async findUnit(code) {
        const outer = this.unknown?.code;

        if (! outer || this.busy) {
            return;
        }

        if (code === outer) {
            this.backToLinking('That is the outer barcode again. Scan the barcode on one item from the case.');

            return;
        }

        this.busy = true;

        try {
            const data = await this.post(this.scanUrl, {
                delID: this.delId,
                barcode: code,
                quantity: 0,
                supplierID: this.supplierId,
            });

            // Dismissed while the lookup was in flight.
            if (! this.unknown) {
                return;
            }

            if (! data.success || ! data.product) {
                this.backToLinking(`No product has barcode ${code}. Scan the barcode on one item from the case.`);

                return;
            }

            if (data.scanType === 'case') {
                this.backToLinking(`${code} is already the case barcode of ${data.product.name}. Scan the barcode on one item.`);

                return;
            }

            this.unknown.candidate = { code: data.product.barcode, product: data.product };
            this.unknown.error = null;
            // No announceSaved() here: the item is still under the lens, the
            // same rule as the quantity prompt.
            this.announceDone();
            this.$nextTick(() => this.$refs.unknown?.scrollIntoView({ block: 'nearest' }));
        } catch (e) {
            this.backToLinking('Could not look that up, try again');
        } finally {
            this.busy = false;
        }
    },

    /**
     * "Yes, link it": save through the office page's own endpoint. On success
     * the outer code now resolves, so it is looked up again and opens a case
     * prompt — the scan the person made a moment ago, completed. A refusal
     * (already another product's case barcode, or a unit barcode the supplier
     * does not carry) goes back to waiting with the server's message.
     */
    async confirmLink() {
        const outer = this.unknown?.code;
        const candidate = this.unknown?.candidate;

        if (! outer || ! candidate || this.busy) {
            return;
        }

        this.busy = true;
        let data = null;

        try {
            data = await this.post(this.outerUrl, {
                unitBarcode: candidate.code,
                supplierID: this.supplierId,
                outerCode: outer,
            });
        } catch (e) {
            data = null;
        } finally {
            this.busy = false;
        }

        if (! data?.success) {
            this.backToLinking(data?.message || 'Could not link, try again');

            return;
        }

        this.showToast('ok', `Linked to ${data.productName}`);
        this.unknown = null;

        if (this.manual) {
            this.resetManual();
        }

        // Opens the case prompt itself; lookup() never resumes the camera there.
        await this.lookup(outer, false);
    },

    /** "Not this one": back to waiting for the unit barcode. */
    rejectCandidate() {
        this.backToLinking(null);
    },

    /**
     * Back to the waiting state, with a message or none. The one camera resume
     * of the link flow: whatever brought us here (a refused or failed link, a
     * lookup that found nothing, Not this one), the person needs to scan again.
     */
    backToLinking(message) {
        if (this.unknown) {
            this.unknown.candidate = null;
            this.unknown.linking = true;
            this.unknown.error = message;
        }

        this.announceSaved();
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
        if (this.unknown?.linking) {
            return this.findUnit(p.code);
        }

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
