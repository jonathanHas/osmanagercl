/**
 * The end-of-day till count (cash reconciliation).
 *
 * One component for both shells: the Shop screen and the office page render the
 * same partial around it. Everything it starts from arrives as JSON in the root's
 * data-state (the form state for the selected till close, the people who may
 * identify themselves, the current "counting as", the suppliers), and the three
 * endpoints as data-*-url attributes:
 *   - till-count.identify  POST  user_id + pin | password
 *   - till-count.forget    POST  "not you?"
 *   - till-count.store     POST  the count
 *
 * The maths mirrors TillCountService::variance() and is only a preview; the
 * server recomputes everything on save and sends the stored figures back.
 */

const DENOMINATIONS = [
    { key: 'cash_50', face: 50, label: '€50', kind: 'note' },
    { key: 'cash_20', face: 20, label: '€20', kind: 'note' },
    { key: 'cash_10', face: 10, label: '€10', kind: 'note' },
    { key: 'cash_5', face: 5, label: '€5', kind: 'note' },
    { key: 'cash_2', face: 2, label: '€2', kind: 'coin' },
    { key: 'cash_1', face: 1, label: '€1', kind: 'coin' },
    { key: 'cash_50c', face: 0.5, label: '50c', kind: 'coin' },
    { key: 'cash_20c', face: 0.2, label: '20c', kind: 'coin' },
    { key: 'cash_10c', face: 0.1, label: '10c', kind: 'coin' },
];

const MAX_CHARS = 9;
const TOAST_MS = 4000;

const round2 = (n) => Math.round((Number(n) || 0) * 100) / 100;

export default () => ({
    denominations: DENOMINATIONS,

    // From data-state
    state: null,
    people: [],
    suppliers: [],
    identity: null,
    tillId: null,

    // The count
    counts: {},
    noteFloat: 0,
    keepAllCoins: true,
    coinFloatManual: 0,
    card: 0,
    moneyAdded: 0,
    previousFloat: 0,
    previousKnown: false,
    payments: [],
    note: '',

    // Who is counting
    person: null,
    pinDigits: '',
    password: '',
    usePassword: false,
    idError: '',

    // Numpad sheet
    pad: null,

    busy: false,
    saved: null,
    toast: null,
    toastTimer: null,

    init() {
        const data = JSON.parse(this.$root.dataset.state || '{}');
        this.state = data.state ?? null;
        this.people = data.people ?? [];
        this.suppliers = data.suppliers ?? [];
        this.identity = data.identity ?? null;
        this.tillId = data.till_id ?? null;

        if (this.state) {
            this.loadState(this.state);
        }
    },

    loadState(s) {
        this.counts = {};
        for (const d of DENOMINATIONS) {
            this.counts[d.key] = Number(s.counts?.[d.key] ?? 0);
        }

        this.noteFloat = round2(s.note_float ?? 0);

        const coins = this.coinsTotal;
        if (s.coin_float === null || s.coin_float === undefined || Math.abs(round2(s.coin_float) - coins) < 0.005) {
            this.keepAllCoins = true;
            this.coinFloatManual = coins;
        } else {
            this.keepAllCoins = false;
            this.coinFloatManual = round2(s.coin_float);
        }

        this.card = round2(s.card ?? 0);
        this.moneyAdded = round2(s.money_added ?? 0);

        if (s.previous_float) {
            this.previousKnown = true;
            this.previousFloat = round2(s.previous_float.amount);
        } else if (s.stored_previous_float !== null && s.stored_previous_float !== undefined) {
            this.previousKnown = false;
            this.previousFloat = round2(s.stored_previous_float);
        } else {
            this.previousKnown = false;
            this.previousFloat = 0;
        }

        this.payments = (s.payments ?? []).map((p) => ({ ...p, amount: round2(p.amount) }));
        this.note = s.note ?? '';
    },

    // ------------------------------------------------------------- endpoints

    get identifyUrl() {
        return this.$root.dataset.identifyUrl;
    },

    get forgetUrl() {
        return this.$root.dataset.forgetUrl;
    },

    get storeUrl() {
        return this.$root.dataset.storeUrl;
    },

    get csrf() {
        return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    },

    // ----------------------------------------------------------------- maths

    get notesTotal() {
        return round2(DENOMINATIONS.filter((d) => d.kind === 'note').reduce((t, d) => t + (this.counts[d.key] || 0) * d.face, 0));
    },

    get coinsTotal() {
        return round2(DENOMINATIONS.filter((d) => d.kind === 'coin').reduce((t, d) => t + (this.counts[d.key] || 0) * d.face, 0));
    },

    get countedTotal() {
        return round2(this.notesTotal + this.coinsTotal);
    },

    get coinFloat() {
        return this.keepAllCoins ? this.coinsTotal : round2(this.coinFloatManual);
    },

    get floatTotal() {
        return round2(this.noteFloat + this.coinFloat);
    },

    get bag() {
        return Math.max(0, round2(this.countedTotal - this.floatTotal));
    },

    get supplierTotal() {
        return round2(this.payments.reduce((t, p) => t + (Number(p.amount) || 0), 0));
    },

    get daysCash() {
        return round2(this.countedTotal + this.supplierTotal - this.previousFloat - this.moneyAdded);
    },

    get expectedCash() {
        return round2((this.state?.pos?.cash ?? 0) + (this.state?.customer?.cash ?? 0));
    },

    get expectedCard() {
        return round2((this.state?.pos?.card ?? 0) + (this.state?.customer?.card ?? 0));
    },

    get variance() {
        return round2(this.daysCash - this.expectedCash);
    },

    get cardVariance() {
        return round2(this.card - this.expectedCard);
    },

    get hasCard() {
        return this.card > 0;
    },

    get anythingCounted() {
        return this.countedTotal > 0;
    },

    get noteRequired() {
        const at = this.state?.thresholds?.note_required_at ?? 20;

        return Math.abs(this.variance) >= at;
    },

    get canSave() {
        return !! this.identity && !! this.state && ! this.busy && this.anythingCounted
            && (! this.noteRequired || this.note.trim() !== '');
    },

    get locked() {
        return ! this.identity;
    },

    tone(v) {
        const a = Math.abs(v);
        const t = this.state?.thresholds ?? { ok: 5, warn: 20 };

        return a < t.ok ? 'ok' : a < t.warn ? 'warn' : 'bad';
    },

    money(v, signed = false) {
        const n = round2(v);
        const sign = signed && n > 0 ? '+' : n < 0 ? '−' : '';

        return `${sign}€${Math.abs(n).toFixed(2)}`;
    },

    initials(name) {
        return String(name ?? '').trim().split(/\s+/).slice(0, 2).map((w) => w[0] ?? '').join('').toUpperCase();
    },

    subtotal(d) {
        return round2((this.counts[d.key] || 0) * d.face);
    },

    // ----------------------------------------------------------------- count

    bump(key, n) {
        if (this.locked) {
            return;
        }

        this.counts[key] = Math.max(0, (this.counts[key] || 0) + n);
    },

    /**
     * Open the numpad for a field. Counts take whole numbers; amounts take two
     * decimals. `payment:<index>` edits a supplier payment's amount.
     */
    open(field, label) {
        if (this.locked) {
            return;
        }

        let current = 0;
        let decimals = 2;

        if (field.startsWith('cash_')) {
            current = this.counts[field] || 0;
            decimals = 0;
        } else if (field.startsWith('payment:')) {
            current = this.payments[Number(field.split(':')[1])]?.amount ?? 0;
        } else {
            current = this[field] ?? 0;
        }

        this.pad = { field, label, typed: '', decimals, current };
    },

    key(k) {
        if (! this.pad) {
            return;
        }

        if (k === 'backspace') {
            this.pad.typed = this.pad.typed.slice(0, -1);

            return;
        }

        if (k === '.') {
            if (this.pad.decimals === 0 || this.pad.typed.includes('.') || this.pad.typed.length >= MAX_CHARS - 1) {
                return;
            }

            this.pad.typed = this.pad.typed === '' ? '0.' : this.pad.typed + '.';

            return;
        }

        const next = this.pad.typed + k;
        const decimals = next.split('.')[1]?.length ?? 0;

        if (next.length > MAX_CHARS || decimals > this.pad.decimals) {
            return;
        }

        this.pad.typed = next === '0' && this.pad.decimals === 0 ? '0' : next;
    },

    get padValue() {
        return this.pad ? (this.pad.typed === '' ? '' : this.pad.typed) : '';
    },

    confirmPad() {
        if (! this.pad) {
            return;
        }

        const { field, typed, decimals } = this.pad;
        const value = typed === '' ? this.pad.current : (decimals === 0 ? parseInt(typed, 10) || 0 : round2(typed));

        if (field.startsWith('cash_')) {
            this.counts[field] = value;
        } else if (field.startsWith('payment:')) {
            const i = Number(field.split(':')[1]);
            if (this.payments[i]) {
                this.payments[i].amount = value;
            }
        } else if (field === 'coinFloatManual') {
            this.coinFloatManual = value;
            this.keepAllCoins = false;
        } else {
            this[field] = value;
        }

        this.pad = null;
    },

    cancelPad() {
        this.pad = null;
    },

    onKey(event) {
        if (this.pad) {
            if (event.key >= '0' && event.key <= '9' && event.key.length === 1) {
                event.preventDefault();
                this.key(event.key);
            } else if (event.key === '.' || event.key === ',') {
                event.preventDefault();
                this.key('.');
            } else if (event.key === 'Backspace') {
                event.preventDefault();
                this.key('backspace');
            } else if (event.key === 'Enter') {
                event.preventDefault();
                this.confirmPad();
            } else if (event.key === 'Escape') {
                event.preventDefault();
                this.cancelPad();
            }

            return;
        }

        if (this.person && ! this.usePassword && ! this.identity) {
            if (event.key >= '0' && event.key <= '9' && event.key.length === 1) {
                event.preventDefault();
                this.pressPin(event.key);
            } else if (event.key === 'Backspace') {
                event.preventDefault();
                this.pinDigits = this.pinDigits.slice(0, -1);
            }
        }
    },

    addPayment() {
        if (this.locked) {
            return;
        }

        this.payments.push({ id: null, supplier_id: '', payee_name: '', amount: 0 });
        this.$nextTick(() => this.open(`payment:${this.payments.length - 1}`, 'Paid to supplier'));
    },

    removePayment(i) {
        if (this.locked) {
            return;
        }

        this.payments.splice(i, 1);
    },

    supplierName(id) {
        return this.suppliers.find((s) => s.id === String(id))?.name ?? '';
    },

    // -------------------------------------------------------------- identity

    choose(person) {
        this.person = person;
        this.pinDigits = '';
        this.password = '';
        this.idError = '';
        this.usePassword = ! person.pin;
    },

    unchoose() {
        this.person = null;
        this.pinDigits = '';
        this.password = '';
        this.idError = '';
    },

    pressPin(digit) {
        if (this.busy || ! this.person) {
            return;
        }

        const length = this.person.pin_length || 4;
        if (this.pinDigits.length >= length) {
            return;
        }

        this.idError = '';
        this.pinDigits += digit;

        if (this.pinDigits.length === length) {
            this.identify({ pin: this.pinDigits });
        }
    },

    submitPassword() {
        if (this.password.trim() === '') {
            this.idError = 'Enter your password.';

            return;
        }

        this.identify({ password: this.password });
    },

    async identify(credential) {
        if (this.busy || ! this.person) {
            return;
        }

        this.busy = true;

        try {
            const { status, data } = await this.post(this.identifyUrl, { user_id: this.person.id, ...credential });

            if (status === 422 && data.errors) {
                this.idError = Object.values(data.errors).flat()[0] ?? 'Could not check that.';
            } else if (! data.ok) {
                this.idError = data.error ?? 'Could not check that.';
            } else {
                this.identity = { id: data.user.id, name: data.user.name, first_name: data.user.first_name, expires_at: data.expires_at };
                this.person = null;
                this.showToast('ok', `Counting as ${data.user.first_name}`);
            }
        } catch (e) {
            this.idError = 'Could not reach the server.';
        } finally {
            this.pinDigits = '';
            this.password = '';
            this.busy = false;
        }
    },

    async forget() {
        this.identity = null;
        this.person = null;

        try {
            await this.post(this.forgetUrl, {});
        } catch (e) { /* the next save would refuse anyway */ }
    },

    // ------------------------------------------------------------------ save

    async save() {
        if (! this.canSave) {
            return;
        }

        this.busy = true;

        const body = {
            session_id: this.state.session.id,
            till_id: this.tillId,
            ...this.counts,
            note_float: this.noteFloat,
            coin_float: this.keepAllCoins ? null : this.coinFloat,
            card: this.card,
            money_added: this.moneyAdded,
            previous_float: this.previousKnown ? null : this.previousFloat,
            payments: this.payments
                .filter((p) => Number(p.amount) > 0)
                .map((p) => ({ id: p.id, supplier_id: p.supplier_id || null, payee_name: p.payee_name || null, amount: p.amount })),
            note: this.note,
        };

        try {
            const { status, data } = await this.post(this.storeUrl, body);

            if (status === 403 && data.identify) {
                this.identity = null;
                this.showToast('warn', 'Your identification lapsed. Identify yourself again.');
            } else if (status === 422 && data.errors) {
                this.showToast('bad', Object.values(data.errors).flat()[0] ?? 'Check the form.');
            } else if (! data.ok) {
                this.showToast('bad', data.error ?? 'Could not save the count.');
            } else {
                this.saved = data;
                this.identity = null;
                this.state = data.state;
                this.loadState(data.state);
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        } catch (e) {
            this.showToast('bad', 'Could not reach the server.');
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

        let data = {};
        try {
            data = await response.json();
        } catch (e) {
            data = {};
        }

        return { status: response.status, data };
    },

    showToast(tone, text) {
        this.toast = { tone, text };
        clearTimeout(this.toastTimer);
        this.toastTimer = setTimeout(() => { this.toast = null; }, TOAST_MS);
    },
});
