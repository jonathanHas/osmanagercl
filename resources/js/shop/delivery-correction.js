/**
 * The correction card, shared by the delivery scan and summary pages (cycle 3:
 * the summary gets the same card, so there is one implementation of it).
 *
 * Composed into a page with mix(): `mix(productImages(), deliveryCorrection(), {...})`,
 * before the page object so a page can still override. The page provides:
 *   - `rows`, each with `barcode`, `scanned`, `name`, `code` and `stock`;
 *   - `load()`, which refetches `rows` from delivery-legacy.items;
 *   - `$refs.correct` on the card (shop/partials/delivery-correction.blade.php)
 *     and a `.shop-toasts` block for "Could not save";
 *   - `data-update-url`, `data-del-id`, `data-supplier-id` on the page root;
 * and calls `initCorrection()` from its `init()` and `closeCardIfRowGone()`
 * from its `load()`.
 *
 * Tapping a row opens the card. Its + / − step a local number and one PATCH of
 * the absolute quantity goes 400 ms after the last tap (or when the card closes,
 * a scan follows, or the page is left), then the list is reloaded so the row
 * and its pill come from the server. Each tap used to wait on the PATCH and the
 * reload, about a second per tap on a 163-line delivery (owner, 2026-10-08;
 * deliveries cycle 2).
 */
import { parseQuantity, quantityText } from './quantity.js';

const TOAST_MS = 3000;

export default () => ({
    // The barcode of the row the card is open on, or null.
    editing: null,
    // The correction card's typed quantity: null while its stepper is in charge.
    editTyped: null,
    // The card's number while it is open: stepped locally, saved once taps stop.
    // `flushPending` is true while the server has not yet been told; `flushTimer`
    // is the pending save. See flushNow().
    editValue: null,
    flushTimer: null,
    flushPending: false,
    busy: false,
    toast: null,
    toastTimer: null,

    /** Called from the page's init(). */
    initCorrection() {
        // Closing the card by any route (Done, ×, another row, a scan) saves what
        // was tapped on the row just left — `editing` has already moved on, so the
        // old value names it. A typed correction belongs to the row it was typed for.
        this.$watch('editing', (value, old) => {
            this.flushNow(old);
            this.editTyped = null;
        });

        // Leaving the page (a phone backgrounding the tab fires this too) with a
        // tap not yet saved: send the PATCH with keepalive and no reload.
        window.addEventListener('pagehide', () => {
            if (! this.flushPending || this.editing === null) {
                return;
            }

            this.flushPending = false;
            clearTimeout(this.flushTimer);
            fetch(this.updateUrl, { ...this.requestInit(this.quantityBody(this.editing, this.editValue), 'PATCH'), keepalive: true });
        });
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

    /** The row the correction card is editing, or null. */
    get editingRow() {
        return this.rows.find((r) => r.barcode === this.editing) ?? null;
    },

    /**
     * Called from the page's load(): correcting an unexpected row to 0 deletes
     * it server-side, so the card would otherwise sit there with nothing in it.
     */
    closeCardIfRowGone() {
        if (this.editing !== null && this.editingRow === null) {
            this.editing = null;
        }
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

    edit(row) {
        const opening = this.editing !== row.barcode;

        // Leaving a row (for none, or for another) saves what was tapped on it,
        // before editValue is reused.
        this.flushNow();

        this.editing = opening ? row.barcode : null;

        // On a phone the card is below the fold; bring it into view as the
        // scan prompt does.
        if (opening) {
            this.editValue = row.scanned ?? 0;
            this.$nextTick(() => this.$refs.correct?.scrollIntoView({ block: 'nearest' }));
        }
    },

    /**
     * A tap steps the card's number at once; the save follows once taps stop.
     * No request here, and the buttons are never disabled: a tap must never be
     * swallowed.
     */
    adjust(row, delta) {
        this.editValue = Math.max(0, Number(((this.editValue ?? row.scanned ?? 0) + delta).toFixed(3)));
        this.flushPending = true;
        clearTimeout(this.flushTimer);
        this.flushTimer = setTimeout(() => this.flushNow(), 400);
    },

    /**
     * Save the card's number if taps have changed it: PATCH, then reload the
     * list so the row and its pill come from the server. Called 400 ms after
     * the last tap, when the card closes (the `editing` watch passes the row
     * just left), before a scan, and by Set. True when the save went through.
     *
     * The barcode and value are taken before the first await, so the card can
     * move to another row meanwhile. While another save or a scan is in flight
     * (`busy`) the save is simply due again 400 ms later; the PATCH is absolute,
     * so the last value always wins.
     */
    async flushNow(barcode = this.editing) {
        if (! this.flushPending || barcode === null || barcode === undefined) {
            return false;
        }

        if (this.busy) {
            clearTimeout(this.flushTimer);
            this.flushTimer = setTimeout(() => this.flushNow(barcode), 400);

            return false;
        }

        clearTimeout(this.flushTimer);
        this.flushPending = false;

        const row = this.rows.find((r) => r.barcode === barcode);
        const value = this.editValue;

        if (! row || value === null) {
            return false;
        }

        if (await this.saveQuantity(row, value)) {
            return true;
        }

        // Not saved ("Could not save" has shown): the next tap or close retries,
        // while the card is still on this row.
        if (this.editing === barcode) {
            this.flushPending = true;
        }

        return false;
    },

    /** Swap the correction card's stepper for a field. */
    typeCorrection() {
        if (! this.editingRow) {
            return;
        }

        this.editTyped = this.stockText(this.editValue ?? this.editingRow.scanned ?? 0);
        this.focusField('editQty');
    },

    /**
     * Set the typed correction, saved at once. 0 is allowed, as on the stepper:
     * it deletes an unexpected row, and load() then closes the card.
     */
    async setCorrection() {
        const value = parseQuantity(this.editTyped, { allowZero: true });

        if (value === null || ! this.editingRow || this.busy) {
            return;
        }

        this.editValue = value;
        this.flushPending = true;

        // A failed save keeps the field open with what was typed, so it can be
        // retried rather than looking as if it had worked.
        if (await this.flushNow()) {
            this.editTyped = null;
        }
    },

    /** The PATCH body for a correction; one place, so the keepalive path cannot drift. */
    quantityBody(barcode, quantity) {
        return {
            delID: this.delId,
            barcode,
            quantity,
            supplierID: this.supplierId,
            // The office financials are never read here; items is reloaded instead.
            financials: false,
        };
    },

    /**
     * PATCH an absolute quantity for a row, then refetch the list. True when the
     * save went through.
     */
    async saveQuantity(row, target) {
        this.busy = true;

        try {
            const data = await this.post(this.updateUrl, this.quantityBody(row.barcode, target), 'PATCH');

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

    requestInit(body, method) {
        return {
            method,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': this.csrf,
            },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        };
    },

    async post(url, body, method = 'POST') {
        const response = await fetch(url, this.requestInit(body, method));

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

    showToast(tone, text) {
        this.toast = { tone, text };
        clearTimeout(this.toastTimer);
        this.toastTimer = setTimeout(() => { this.toast = null; }, TOAST_MS);
    },
});
