/**
 * Shop mode print labels page.
 *
 * Reads the shelf-label queue from the office endpoints, whose URLs arrive as
 * data-* attributes on the page root:
 *   - labels.queue    GET   the queue, its per-event counts and the sheet size
 *   - labels.scan     POST  add a product (the office scan-to-label endpoint)
 *   - labels.dismiss  POST  take one product off
 *   - labels.clear-all POST empty it (managers only; the URL is blank otherwise)
 *
 * Printing is a plain form post to the office A4 page in a new tab, so it is not
 * handled here — see the view.
 */
const TOAST_MS = 3000;
const DEFAULT_PER_SHEET = 24;

export default () => ({
    rows: [],
    counts: null,
    perSheet: DEFAULT_PER_SHEET,
    loading: true,
    busy: false,
    toast: null,
    toastTimer: null,
    error: null,

    init() {
        this.load();
    },

    get queueUrl() {
        return this.$root.dataset.queueUrl;
    },

    get scanUrl() {
        return this.$root.dataset.scanUrl;
    },

    get dismissUrl() {
        return this.$root.dataset.dismissUrl;
    },

    /**
     * Empty for anyone without labels.manage, which is how the view knows not to
     * offer the button.
     */
    get clearUrl() {
        return this.$root.dataset.clearUrl;
    },

    get csrf() {
        return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    },

    get total() {
        return this.rows.length;
    },

    get sheets() {
        return Math.ceil(this.total / this.perSheet);
    },

    get ids() {
        return this.rows.map((r) => r.id);
    },

    async load() {
        try {
            const response = await fetch(this.queueUrl, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            const data = await response.json();

            this.rows = data.rows;
            this.counts = data.counts;
            this.perSheet = data.labels_per_sheet || DEFAULT_PER_SHEET;
            this.error = null;
        } catch (e) {
            this.error = 'Could not load the queue';
        } finally {
            this.loading = false;
        }
    },

    async onScan(code) {
        this.busy = true;

        try {
            const data = await this.post(this.scanUrl, { barcode: code });

            if (! data.success) {
                this.announceError(data.message || 'Could not add');

                return;
            }

            await this.load();
            this.showToast('ok', `Added: ${data.product?.name ?? code}`);
            this.announceDone();
        } catch (e) {
            this.announceError('Could not add');
        } finally {
            this.busy = false;
        }
    },

    async dismiss(row) {
        this.busy = true;

        try {
            const data = await this.post(this.dismissUrl, { barcode: row.code });

            if (! data.success) {
                this.showToast('bad', 'Could not remove');

                return;
            }

            // Local removal: the queue is a derivation, so a reload would give the
            // same answer and cost a round trip.
            this.rows = this.rows.filter((r) => r.id !== row.id);
            this.showToast('ok', `Removed: ${row.name}`);
        } catch (e) {
            this.showToast('bad', 'Could not remove');
        } finally {
            this.busy = false;
        }
    },

    async clear() {
        if (! this.clearUrl) {
            return;
        }

        this.busy = true;

        try {
            const data = await this.post(this.clearUrl, {});

            await this.load();
            this.showToast('ok', data.message || 'Queue cleared');
        } catch (e) {
            this.showToast('bad', 'Could not clear the queue');
        } finally {
            this.busy = false;
        }
    },

    async post(url, body) {
        const response = await fetch(url, {
            method: 'POST',
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

    reasonLabel(row) {
        return `Shelf label · ${row.reason}`;
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
