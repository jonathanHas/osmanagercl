/**
 * Shop mode fruit & veg waste log.
 *
 * Reads and writes through the office endpoints, whose URLs arrive as data-*
 * attributes on the page root:
 *   - fruit-veg.waste.rows    GET   today's on-till F&V range with today's entries
 *   - fruit-veg.waste.search  GET   the rest of the F&V range, by name
 *   - fruit-veg.waste.entry   POST  set the day's quantity for one product
 *
 * The endpoint **replaces** the day's row rather than adding to it, so logging
 * another 1 kg means sending today's total plus 1. Zero deletes the row, which is
 * how a wrong entry is removed. This is the opposite of the harvest endpoint, so
 * the two modules deliberately do not share this part.
 */
import mix from './mix.js';
import productImages from './product-images.js';

const TOAST_MS = 3000;

export default () => mix(productImages(), {
    date: null,
    products: [],
    // Server search results while a query is present; null means "show the range".
    results: null,
    query: '',
    selected: null,
    unit: 'kg',
    amount: 0,
    busy: false,
    loading: true,
    toast: null,
    toastTimer: null,

    init() {
        this.load();
    },

    get rowsUrl() {
        return this.$root.dataset.rowsUrl;
    },

    get searchUrl() {
        return this.$root.dataset.searchUrl;
    },

    get entryUrl() {
        return this.$root.dataset.entryUrl;
    },

    get csrf() {
        return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    },

    /**
     * The on-till range is filtered in the browser because it is already here;
     * anything beyond it has to come from the server.
     */
    get shown() {
        if (this.results !== null) {
            return this.results;
        }

        const q = this.query.trim().toLowerCase();

        return q === '' ? this.products : this.products.filter((p) => p.name.toLowerCase().includes(q));
    },

    get today() {
        return this.products.filter((p) => p.quantity > 0);
    },

    get step() {
        return this.unit === 'kg' ? 0.5 : 1;
    },

    get amountText() {
        if (this.unit === 'kg') {
            return this.amount.toFixed(1) + ' kg';
        }

        return this.amount + (this.amount === 1 ? ' unit' : ' units');
    },

    get canLog() {
        return !! this.selected && this.amount > 0;
    },

    get logLabel() {
        return this.selected ? `Log ${this.amountText} ${this.selected.name}` : 'Log waste';
    },

    /**
     * True when logging now would throw away an entry made earlier today in the
     * other unit — the server keeps one row per product per day, so the unit
     * cannot be mixed.
     */
    get unitClash() {
        return !! this.selected && this.selected.quantity > 0 && this.selected.unit !== this.unit;
    },

    async load() {
        this.loading = true;

        try {
            const data = await this.get(this.rowsUrl);
            this.products = data.products;
            this.date = data.date;
        } catch (e) {
            this.showToast('bad', 'Could not load the produce list');
        } finally {
            this.loading = false;
        }
    },

    async search() {
        if (this.query.trim() === '') {
            this.results = null;

            return;
        }

        try {
            const params = new URLSearchParams({ q: this.query.trim(), date: this.date ?? '' });
            const data = await this.get(`${this.searchUrl}?${params}`);
            this.results = data.products;
        } catch (e) {
            this.showToast('bad', 'Search failed');
        }
    },

    select(p) {
        this.selected = p;
        this.unit = p.unit;
        this.amount = this.step;
    },

    setUnit(u) {
        this.unit = u;
        this.amount = this.step;
    },

    bump(n) {
        this.amount = Math.max(0, Math.round((this.amount + n * this.step) * 100) / 100);
    },

    /**
     * What is already logged today in the unit being used now. A different unit
     * counts as zero, because the save will replace that row rather than add to it.
     */
    todayTotal(p) {
        return p && p.unit === this.unit ? (p.quantity ?? 0) : 0;
    },

    async log() {
        if (! this.canLog || this.busy) {
            return;
        }

        this.busy = true;
        const added = this.amountText;
        const total = this.todayTotal(this.selected) + this.amount;

        try {
            const data = await this.post(this.entryUrl, {
                date: this.date,
                product_code: this.selected.code,
                quantity: total.toFixed(2),
                unit: this.unit,
            });

            if (data.saved) {
                this.apply(this.selected.code, { quantity: data.quantity, unit: data.unit, value: data.value });
                this.showToast('ok', `Logged ${added} ${this.selected.name} · ${data.quantity} ${data.unit} today`);
                this.amount = this.step;
            } else {
                this.showToast('bad', data.error ?? 'Could not log that');
            }
        } catch (e) {
            this.showToast('bad', 'Could not log that');
        } finally {
            this.busy = false;
        }
    },

    async remove(p) {
        if (this.busy) {
            return;
        }

        this.busy = true;

        try {
            const data = await this.post(this.entryUrl, {
                date: this.date,
                product_code: p.code,
                quantity: 0,
                unit: p.unit,
            });

            if (data.deleted) {
                this.apply(p.code, { quantity: null, value: null });
                this.showToast('ok', `Removed ${p.name}`);
            } else {
                this.showToast('bad', data.error ?? 'Could not remove that');
            }
        } catch (e) {
            this.showToast('bad', 'Could not remove that');
        } finally {
            this.busy = false;
        }
    },

    /**
     * A product can be in the range list, the search results and `selected` at the
     * same time; they are separate objects, so all three are updated by code.
     */
    apply(code, changes) {
        for (const list of [this.products, this.results ?? []]) {
            const row = list.find((p) => p.code === code);
            if (row) {
                Object.assign(row, changes);
            }
        }

        if (this.selected?.code === code) {
            Object.assign(this.selected, changes);
        }
    },

    async get(url) {
        const response = await fetch(url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        return await response.json();
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

    showToast(tone, text) {
        this.toast = { tone, text };
        clearTimeout(this.toastTimer);
        this.toastTimer = setTimeout(() => { this.toast = null; }, TOAST_MS);
    },
});
