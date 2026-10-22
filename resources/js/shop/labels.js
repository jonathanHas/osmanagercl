/**
 * Shop mode print labels page.
 *
 * Reads the shelf-label queue from the office endpoints, whose URLs arrive as
 * data-* attributes on the page root:
 *   - labels.queue    GET   the queue, its per-event counts and the sheet size
 *   - labels.scan     POST  add a product (the office scan-to-label endpoint)
 *   - labels.dismiss  POST  take one product off
 *   - labels.clear-all POST empty it (managers only; the URL is blank otherwise)
 *   - labels.printed-today GET  today's prints in five-minute batches (or
 *                               yesterday's when today has none)
 *   - labels.requeue  POST  put one product back on the queue ("Back to queue",
 *                           and Undo after Remove)
 *   - labels.restore-batch POST put several back (Undo after Clear; managers
 *                           only, the URL is blank otherwise)
 *
 * Printing is a plain form post to the office A4 page in a new tab, so it is not
 * handled here — see the view.
 */
const TOAST_MS = 4000;
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
    batches: [],
    historyOpen: false,
    openBatch: null,
    day: 'today',

    init() {
        // Not awaited together: the history must never hold up the queue.
        this.load();
        this.loadPrinted();
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

    get printedUrl() {
        return this.$root.dataset.printedUrl;
    },

    get requeueUrl() {
        return this.$root.dataset.requeueUrl;
    },

    /**
     * Empty for anyone without labels.manage, as with clearUrl; the Undo after
     * Clear queue is the only caller.
     */
    get restoreUrl() {
        return this.$root.dataset.restoreUrl;
    },

    get historyMeta() {
        const labels = this.batches.reduce((sum, b) => sum + b.count, 0);
        const meta = this.plural(this.batches.length, 'batch', 'batches') + ' · ' + this.plural(labels, 'label');

        return this.day === 'yesterday' ? 'Yesterday · ' + meta : meta;
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

    /**
     * The history is a convenience: a failure empties it rather than showing an
     * error over the queue.
     */
    async loadPrinted() {
        try {
            const response = await fetch(this.printedUrl, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            const data = await response.json();

            this.batches = data.batches ?? [];
            this.day = data.day ?? 'today';
        } catch (e) {
            this.batches = [];
        }
    },

    inQueue(it) {
        return this.rows.some((r) => r.id === it.id);
    },

    async requeue(b, it) {
        this.busy = true;

        try {
            const data = await this.post(this.requeueUrl, { product_id: it.id });

            if (! data.success) {
                this.showToast('bad', data.message || data.error || 'Could not re-queue');

                return;
            }

            await this.load();
            this.showToast('ok', `${it.name} is back in the queue`);
        } catch (e) {
            this.showToast('bad', 'Could not re-queue');
        } finally {
            this.busy = false;
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
            // Detecting a code stops the camera; bring it back for the next item.
            window.dispatchEvent(new CustomEvent('shop-scan-saved'));
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
            this.showToast('ok', `Removed: ${row.name}`, (c) => c.restore([row]));
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

        // Kept before the post: once cleared, the queue no longer knows them.
        const ids = this.ids;

        this.busy = true;

        try {
            const data = await this.post(this.clearUrl, {});

            await this.load();
            this.showToast('ok', data.message || 'Queue cleared', ids.length && this.restoreUrl ? (c) => c.restoreBatch(ids) : null);
        } catch (e) {
            this.showToast('bad', 'Could not clear the queue');
        } finally {
            this.busy = false;
        }
    },

    /**
     * Undo after Remove: a requeue is newer than the dismissal, so the product
     * is back on the queue.
     */
    async restore([row]) {
        try {
            const data = await this.post(this.requeueUrl, { product_id: row.id });

            if (! data.success) {
                this.showToast('bad', data.message || data.error || 'Could not undo');

                return;
            }

            await this.load();
        } catch (e) {
            this.showToast('bad', 'Could not undo');
        }
    },

    /**
     * Undo after Clear queue. The endpoint only echoes the timestamp in its
     * message; any string will do.
     */
    async restoreBatch(ids) {
        try {
            const data = await this.post(this.restoreUrl, {
                timestamp: new Date().toISOString(),
                product_ids: ids,
            });

            if (! data.success) {
                this.showToast('bad', data.message || data.error || 'Could not undo');

                return;
            }

            await this.load();
        } catch (e) {
            this.showToast('bad', 'Could not undo');
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

    plural(n, one, many = one + 's') {
        return `${n} ${n === 1 ? one : many}`;
    },

    /**
     * @param {((component) => void)|null} undo  offered as an Undo button while
     *   the toast shows; called with the component as undo() sees it
     */
    showToast(tone, text, undo = null) {
        this.toast = { tone, text, undo };
        clearTimeout(this.toastTimer);
        this.toastTimer = setTimeout(() => { this.toast = null; }, TOAST_MS);
    },

    /**
     * The callback gets `this` from the Undo button's scope, not the scope it
     * was created in: Remove's button sits in the x-for row it removes, and once
     * that row is gone its scope has no $root, so every URL getter would throw.
     */
    undo() {
        const callback = this.toast?.undo;

        clearTimeout(this.toastTimer);
        this.toast = null;

        if (callback) {
            callback(this);
        }
    },

    announceDone() {
        window.dispatchEvent(new CustomEvent('shop-scan-done'));
    },

    announceError(message) {
        window.dispatchEvent(new CustomEvent('shop-scan-error', { detail: message }));
    },
});
