/**
 * Shop mode delivery summary page.
 *
 * Reads the same JSON the scan screen does (`delivery-legacy.items`, whose URL
 * arrives as a data-* attribute) and totals it. Completion itself is a plain POST
 * form to `delivery-legacy.complete`, so the irreversible step is a real form
 * submission rather than a fetch this file could fire by accident; all this
 * object does is gate the confirmation.
 */
export default () => ({
    session: null,
    rows: [],
    progress: null,
    loading: true,
    error: null,
    confirming: false,

    init() {
        this.load();
    },

    get itemsUrl() {
        return this.$root.dataset.itemsUrl;
    },

    get delId() {
        return this.$root.dataset.delId;
    },

    get supplierId() {
        return this.$root.dataset.supplierId;
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
            this.error = 'Could not load the delivery';
        } finally {
            this.loading = false;
        }
    },

    /**
     * Row counts, not unit counts — "OK 2" means two invoice lines agree.
     */
    get totals() {
        const count = (fn) => this.rows.filter(fn).length;

        return {
            scanned: count((r) => r.scanned > 0),
            ok: count((r) => r.status === 'ok'),
            short: count((r) => r.status === 'short'),
            over: count((r) => r.status === 'over'),
            unexpected: count((r) => r.status === 'unexpected'),
            missing: count((r) => r.status === 'not_scanned'),
        };
    },

    /**
     * What completion will actually add to stock. An unexpected scan counts —
     * completeDelivery() increments those too — but only when it resolved to a
     * product, because that is the condition the write itself applies. Counting
     * the rest here would promise units the flash message then contradicts.
     */
    get stockableScanned() {
        return this.rows.filter((r) => r.scanned > 0 && r.stockable);
    },

    get unitsToAdd() {
        return this.stockableScanned.reduce((sum, r) => sum + r.scanned, 0);
    },

    get productsToUpdate() {
        return this.stockableScanned.length;
    },

    /**
     * Scanned items POS does not know. They stay in the totals and in the
     * discrepancy list, but nothing can be added to stock for them.
     */
    get unstockableCount() {
        return this.rows.filter((r) => r.scanned > 0 && ! r.stockable).length;
    },

    /**
     * Everything that does not simply match, worst-explained first: what is
     * missing units, then what has too many, then what should not be here at all,
     * then what was never scanned.
     */
    get discrepancies() {
        const order = ['short', 'over', 'unexpected', 'not_scanned'];

        return order.flatMap((status) => this.rows.filter((r) => r.status === status));
    },

    get canComplete() {
        return !! this.session && ! this.session.completed && this.totals.scanned > 0;
    },

    label(row) {
        switch (row.status) {
            case 'short':
                return `Short ${row.expected - row.scanned}`;
            case 'over':
                return `Over ${row.scanned - row.expected}`;
            case 'unexpected':
                return 'Unexpected';
            default:
                return 'Not scanned';
        }
    },

    tone(row) {
        switch (row.status) {
            case 'short':
            case 'over':
                return 'shop-pill--warn';
            case 'unexpected':
                return 'shop-pill--bad';
            default:
                return 'shop-pill--muted';
        }
    },

    meta(row) {
        return `Expected ${row.expected ?? '—'} · scanned ${row.scanned ?? 0}`;
    },

    askToComplete() {
        this.confirming = true;
    },

    cancelComplete() {
        this.confirming = false;
    },
});
