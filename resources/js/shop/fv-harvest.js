/**
 * Shop mode harvest log — Jon's own produce.
 *
 * Reads and writes through the office endpoints, whose URLs arrive as data-*
 * attributes on the page root:
 *   - fruit-veg.harvest.rows      GET   recent and today's picks, plus the rest
 *                                       of Jon's range
 *   - fruit-veg.harvest.save-row  POST  add an amount to today's total
 *
 * The endpoint **accumulates**: the amount sent is added to whatever is already
 * logged for that product today, and it stamps the time and the user. So after a
 * save this re-reads rather than predicting the new total — the opposite of the
 * waste screen, which has to send the total itself.
 */
import mix from './mix.js';
import productImages from './product-images.js';

const TOAST_MS = 3000;
const MAX_DECIMALS = 2;
const MAX_CHARS = 7;

export default () => mix(productImages(), {
    date: null,
    rows: [],
    available: [],
    query: '',
    selected: null,
    unit: 'kg',
    typed: '',
    busy: false,
    loading: true,
    toast: null,
    toastTimer: null,
    // { label, product, copies, sending, result } while a print is on offer.
    print: null,

    init() {
        this.load();
    },

    get rowsUrl() {
        return this.$root.dataset.rowsUrl;
    },

    get saveUrl() {
        return this.$root.dataset.saveUrl;
    },

    /**
     * The per-label print URL with `__ID__` where the id goes. Blade builds it so
     * no route helper appears here, the same rule as every other URL on this screen.
     */
    get printUrlTemplate() {
        return this.$root.dataset.printUrlTemplate;
    },

    get sizeText() {
        const l = this.print?.label;

        return `${l?.width_mm ?? '?'} × ${l?.height_mm ?? '?'} mm`;
    },

    get csrf() {
        return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    },

    get searching() {
        return this.query.trim() !== '';
    },

    get hasRecent() {
        return this.rows.length > 0;
    },

    /**
     * Recent picks by default, everything by search — the office page's shape.
     * Jon supplies 111 products and most days only a handful are picked, so
     * listing the whole range reads as noise rather than a choice.
     *
     * A product logged from a search needs no special handling: the save is
     * followed by a re-read, and the server then lists it under `rows`, so it
     * becomes a recent pick straight away.
     */
    get choices() {
        if (! this.searching) {
            return this.rows;
        }

        const q = this.query.trim().toLowerCase();

        return [...this.rows, ...this.available].filter((p) => p.name.toLowerCase().includes(q));
    },

    get today() {
        return this.rows.filter((r) => r.logged > 0);
    },

    get amount() {
        return this.typed === '' ? 0 : Number(this.typed);
    },

    /**
     * No forced decimals here, unlike waste: a harvest reads "3 kg" or "4.2 kg",
     * whichever the picker actually typed.
     */
    get amountText() {
        if (this.unit === 'kg') {
            return this.amount + ' kg';
        }

        return this.amount + (this.amount === 1 ? ' unit' : ' units');
    },

    get canLog() {
        return !! this.selected && this.amount > 0;
    },

    get logLabel() {
        return this.selected ? `Log ${this.amountText} ${this.selected.name}` : 'Log harvest';
    },

    async load() {
        this.loading = true;

        try {
            const data = await this.get(this.rowsUrl);
            this.rows = data.rows;
            this.available = data.available;
            this.date = data.date;

            // Keep the picked product selected across a reload after a save.
            if (this.selected) {
                const again = [...this.rows, ...this.available].find((p) => p.code === this.selected.code);
                if (again) {
                    this.selected = again;
                }
            }
        } catch (e) {
            this.showToast('bad', 'Could not load the harvest list');
        } finally {
            this.loading = false;
        }
    },

    /**
     * The unit today's row is already in, or null when nothing is logged yet.
     *
     * The endpoint keeps one unit per product per day and refuses a mismatch, so
     * the switch follows the day's row rather than the product's preference and
     * staff never meet the refusal in normal use.
     */
    get lockedUnit() {
        return this.selected?.logged > 0 ? (this.selected.logged_unit ?? null) : null;
    },

    select(p) {
        this.selected = p;
        this.unit = p.logged > 0 && p.logged_unit ? p.logged_unit : p.unit;
        this.typed = '';
    },

    setUnit(u) {
        if (this.lockedUnit && u !== this.lockedUnit) {
            return;
        }

        this.unit = u;
    },

    key(k) {
        if (k === 'backspace') {
            this.typed = this.typed.slice(0, -1);

            return;
        }

        if (k === '.') {
            if (this.typed.includes('.') || this.typed.length >= MAX_CHARS - 1) {
                return;
            }

            this.typed = this.typed === '' ? '0.' : this.typed + '.';

            return;
        }

        const next = this.typed + k;
        const decimals = next.split('.')[1]?.length ?? 0;

        if (next.length > MAX_CHARS || decimals > MAX_DECIMALS) {
            return;
        }

        this.typed = next;
    },

    async log() {
        if (! this.canLog || this.busy) {
            return;
        }

        this.busy = true;
        const added = this.amountText;
        const amount = this.amount;
        const name = this.selected.name;

        try {
            const data = await this.post(this.saveUrl, {
                date: this.date,
                code: this.selected.code,
                amount: this.amount.toFixed(2),
                unit: this.unit,
            });

            if (data.success) {
                this.typed = '';
                this.showToast('ok', `Logged ${added} ${name} · ${data.logged} ${data.unit} today`);

                // The entry is already saved; the offer never blocks it.
                if (this.selected?.label) {
                    this.offerPrint(this.selected, amount);
                }

                // The server accumulated and stamped who and when; re-read it.
                await this.load();
            } else {
                this.showToast('bad', data.message ?? 'Could not log that');
                // Most likely a unit clash from a stale screen — another till logged
                // this product first. Re-read so the lock catches up and the second
                // attempt is not refused too.
                await this.load();
            }
        } catch (e) {
            this.showToast('bad', 'Could not log that');
        } finally {
            this.busy = false;
        }
    },

    /**
     * Offer a print. Copies default to the amount just logged, rounded as the office
     * page rounds it: 4.2 kg of leaves is four labels, and a count maps one to one.
     */
    offerPrint(p, amount) {
        if (! p?.label) {
            return;
        }

        this.print = {
            label: p.label,
            product: p.name,
            copies: this.clampCopies(Math.round(amount)),
            sending: false,
            result: null,
        };
    },

    /** From a Today row: the same offer, defaulting to everything logged so far. */
    reprint(row) {
        this.offerPrint(row, row.logged);
    },

    dismissPrint() {
        this.print = null;
    },

    clampCopies(n) {
        return Math.min(99, Math.max(1, Math.round(Number(n) || 1)));
    },

    bumpCopies(n) {
        if (this.print) {
            this.print.copies = this.clampCopies(this.print.copies + n);
        }
    },

    async sendPrint() {
        if (! this.print || this.print.sending) {
            return;
        }

        this.print.sending = true;
        const copies = this.print.copies;

        try {
            const url = this.printUrlTemplate.replace('__ID__', this.print.label.id);
            const data = await this.post(url, { copies });

            if (data.success) {
                this.showToast('ok', data.message);
                this.print = null;

                return;
            }

            // Keep the card open so the printer can be fixed and Print tried again.
            this.showToast('bad', data.message || 'Print failed');
            this.print.result = data.message || 'Print failed';
        } catch (e) {
            this.showToast('bad', 'Could not reach the printer');
            if (this.print) {
                this.print.result = 'Could not reach the printer';
            }
        } finally {
            if (this.print) {
                this.print.sending = false;
            }
        }
    },

    when(iso) {
        if (! iso) {
            return '';
        }

        return new Date(iso).toLocaleTimeString('en-IE', { hour: '2-digit', minute: '2-digit', hour12: false });
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
