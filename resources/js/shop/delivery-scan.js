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
 * out. Corrections after the fact use the row stepper.
 *
 * After every write the whole list is refetched: the server owns the expected
 * quantities and the statuses, and re-deriving them here would be a second
 * implementation of the office page's rules.
 */
const TOAST_MS = 3000;

export default () => ({
    session: null,
    rows: [],
    progress: { total: 0, checked: 0, issues: 0 },
    sort: 'new',
    // Barcodes scanned on this page, newest first — drives the "New first" order.
    recent: [],
    latest: null,
    // The open prompt: a looked-up product awaiting a quantity. Nothing is
    // recorded while this is set.
    pending: null,
    busy: false,
    editing: null,
    toast: null,
    toastTimer: null,
    flag: null,
    error: null,

    init() {
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

    get percent() {
        return this.progress.total
            ? Math.round((this.progress.checked / this.progress.total) * 100)
            : 0;
    },

    get unitsToAdd() {
        if (! this.pending) {
            return 0;
        }

        return this.pending.qty * (this.pending.scanType === 'case' ? this.pending.caseUnits : 1);
    },

    get addLabel() {
        if (! this.pending) {
            return '';
        }

        const n = this.pending.qty;

        if (this.pending.scanType === 'case') {
            return `Add ${n} case${n === 1 ? '' : 's'} · ${this.unitsToAdd} units`;
        }

        return `Add ${n} unit${n === 1 ? '' : 's'}`;
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
        } catch (e) {
            this.error = 'Could not load the invoice lines';
        }
    },

    /**
     * Step one: look the code up without recording anything. A scan arriving
     * while a prompt is open confirms that prompt first, so "scan, scan, scan"
     * stays as fast as the office page.
     */
    async onScan(code) {
        if (this.pending) {
            await this.commit();
        }

        this.busy = true;

        try {
            const data = await this.post(this.scanUrl, {
                delID: this.delId,
                barcode: code,
                quantity: 0,
                supplierID: this.supplierId,
            });

            if (! data.success || ! data.product) {
                this.announceError(`Product not found for ${code}`);

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
            };

            this.announceDone();
        } catch (e) {
            this.announceError('Scan failed');
        } finally {
            this.busy = false;
        }
    },

    bump(delta) {
        if (! this.pending) {
            return;
        }

        this.pending.qty = Math.max(1, this.pending.qty + delta);
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
        if (! this.pending || this.busy) {
            return;
        }

        this.busy = true;
        const item = this.pending;

        try {
            const data = await this.post(this.scanUrl, {
                delID: this.delId,
                barcode: item.code,
                quantity: item.qty,
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
        } catch (e) {
            this.showToast('bad', 'Not saved, try again');
        } finally {
            this.busy = false;
        }
    },

    cancelPending() {
        this.pending = null;
        this.announceDone();
    },

    /**
     * Report from the reloaded row rather than the increment response, so the
     * toast and the row beside it can never quote different figures. (The legacy
     * endpoint derives `expectedQty` slightly differently for fractional orders.)
     */
    reportRow(barcode) {
        const row = this.rows.find((r) => r.barcode === barcode);

        if (! row) {
            this.showToast('warn', 'Not on this invoice');

            return;
        }

        switch (row.status) {
            case 'ok':
                this.showToast('ok', 'Matches invoice');
                break;
            case 'short':
                this.showToast('warn', `Short: ${row.scanned} of ${row.expected}`);
                break;
            case 'over':
                this.showToast('warn', `Over: ${row.scanned} of ${row.expected}`);
                break;
            default:
                this.showToast('warn', 'Not on this invoice');
        }
    },

    remember(barcode) {
        this.recent = [barcode, ...this.recent.filter((b) => b !== barcode)];
    },

    edit(row) {
        this.editing = this.editing === row.barcode ? null : row.barcode;
    },

    async adjust(row, delta) {
        const target = Math.max(0, (row.scanned ?? 0) + delta);

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

                return;
            }

            await this.load();
        } catch (e) {
            this.showToast('bad', 'Could not save');
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

    qtyLabel(row) {
        return `${row.scanned ?? 0} / ${row.expected ?? '—'}`;
    },

    expectedLabel(row) {
        return `/ ${row.expected ?? '—'}`;
    },

    pill(row) {
        switch (row.status) {
            case 'ok':
                return { text: 'OK', tone: 'shop-pill--ok' };
            case 'short':
                return { text: 'Short', tone: 'shop-pill--warn' };
            case 'over':
                return { text: 'Over', tone: 'shop-pill--warn' };
            case 'unexpected':
                return { text: 'Unexpected', tone: 'shop-pill--bad' };
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
});
