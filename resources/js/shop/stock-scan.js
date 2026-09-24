/**
 * Shop mode stock scan page.
 *
 * Talks to the existing office endpoints (stocking.lookup / stocking.update-stock),
 * whose URLs arrive as data-* attributes on the page root so no Blade leaks into
 * this file. Works in whole units, as the office page does.
 *
 * Shares the `stockingScanHistory` localStorage key with the office page, so the
 * last scans carry over between the two. Rows written there have no `note`, so
 * the meta line falls back to the time alone.
 */
const HISTORY_KEY = 'stockingScanHistory';
const HISTORY_MAX = 10;

export default () => ({
    product: null,
    stock: 0,
    delta: 0,
    typed: '',
    // Explicit, because deriving it from delta loses the sign at zero: -0 < 0 is
    // false, so pressing ± on a fresh product then typing gave a positive figure.
    sign: 1,
    busy: false,
    history: [],
    toast: null,
    toastTimer: null,

    init() {
        this.history = this.readHistory();
    },

    get lookupUrl() {
        return this.$root.dataset.lookupUrl;
    },

    get updateUrl() {
        return this.$root.dataset.updateUrl;
    },

    get csrf() {
        return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    },

    get newStock() {
        return Math.max(0, this.stock + this.delta);
    },

    get deltaLabel() {
        if (this.delta > 0) {
            return '+' + this.delta;
        }

        // A real minus sign, matching the design.
        return this.delta < 0 ? '−' + Math.abs(this.delta) : '0';
    },

    get deltaTone() {
        if (this.delta > 0) {
            return 'is-plus';
        }

        return this.delta < 0 ? 'is-minus' : '';
    },

    async lookup(code) {
        this.busy = true;

        try {
            const data = await this.post(this.lookupUrl, { barcode: code });

            if (! data.found) {
                this.product = null;
                this.announceError(`No product for ${code}`);

                return;
            }

            this.product = data.product;
            this.stock = Math.floor(data.stock);
            this.reset();
            this.remember({ name: data.product.name, code: data.product.code, stock: this.stock, note: 'scanned' });
            this.announceDone();
        } catch (e) {
            this.announceError('Lookup failed');
        } finally {
            this.busy = false;
        }
    },

    step(n) {
        this.delta = this.clamp(this.delta + n);
        this.typed = '';
        if (this.delta !== 0) {
            this.sign = this.delta < 0 ? -1 : 1;
        }
        this.announceDone();
    },

    key(k) {
        if (k === 'sign') {
            this.sign = -this.sign;
            this.delta = this.clamp(this.sign * Math.abs(this.delta));
        } else if (k === 'backspace') {
            this.typed = this.typed.slice(0, -1);
            this.applyTyped();
        } else if (this.typed.length < 4) {
            this.typed += k;
            this.applyTyped();
        }

        this.announceDone();
    },

    /**
     * The number pad types a magnitude; the sign stays whatever the user chose.
     */
    applyTyped() {
        this.delta = this.clamp(this.sign * Number(this.typed || 0));
    },

    /**
     * Stock can never be driven below zero.
     */
    clamp(delta) {
        return Math.max(delta, -this.stock);
    },

    cancel() {
        this.reset();
        this.announceDone();
    },

    reset() {
        this.delta = 0;
        this.typed = '';
        this.sign = 1;
    },

    async save() {
        if (! this.product || this.delta === 0) {
            return;
        }

        this.busy = true;
        const target = this.newStock;

        try {
            const data = await this.post(this.updateUrl, { barcode: this.product.code, new_stock: target });

            if (! data.success) {
                this.showToast('bad', data.message || 'Could not save');

                return;
            }

            this.stock = Math.floor(data.stock);
            this.reset();
            this.remember({ name: this.product.name, code: this.product.code, stock: this.stock, note: `set to ${this.stock}` });
            this.showToast('ok', 'Stock updated');
            this.announceDone();
            // Detecting a code stops the camera; bring it back for the next item.
            window.dispatchEvent(new CustomEvent('shop-scan-saved'));
        } catch (e) {
            this.showToast('bad', 'Could not save');
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

    readHistory() {
        try {
            const rows = JSON.parse(localStorage.getItem(HISTORY_KEY) || '[]');

            return Array.isArray(rows) ? rows : [];
        } catch (e) {
            return [];
        }
    },

    /**
     * Newest first, one row per product, capped. Re-scanning a product moves it
     * back to the top rather than duplicating it.
     */
    remember(row) {
        this.history = [
            { ...row, time: new Date().toISOString() },
            ...this.history.filter((r) => r.code !== row.code),
        ].slice(0, HISTORY_MAX);

        try {
            localStorage.setItem(HISTORY_KEY, JSON.stringify(this.history));
        } catch (e) { /* private mode, or storage full */ }
    },

    rowMeta(row) {
        const time = this.timeOf(row.time);

        if (this.product && row.code === this.product.code && this.delta !== 0) {
            return `${time} · editing`;
        }

        return row.note ? `${time} · ${row.note}` : time;
    },

    timeOf(iso) {
        const at = new Date(iso);

        return Number.isNaN(at.getTime())
            ? ''
            : `${String(at.getHours()).padStart(2, '0')}:${String(at.getMinutes()).padStart(2, '0')}`;
    },

    announceDone() {
        window.dispatchEvent(new CustomEvent('shop-scan-done'));
    },

    announceError(message) {
        window.dispatchEvent(new CustomEvent('shop-scan-error', { detail: message }));
    },

    showToast(tone, text) {
        this.toast = { tone, text };
        clearTimeout(this.toastTimer);
        this.toastTimer = setTimeout(() => { this.toast = null; }, 3000);
    },
});
