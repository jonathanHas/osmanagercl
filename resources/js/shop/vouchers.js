/**
 * Shop mode vouchers page.
 *
 * Scan a voucher, read its balance and history, tap an amount, deduct. Every
 * endpoint is the office one, whose URL arrives as a data-* attribute on the page
 * root:
 *   - vouchers.lookup   POST  the voucher, its status, balance and history
 *   - vouchers.deduct   POST  redeem an amount (row-locked, re-checked server side)
 *   - vouchers.activate POST  managers only; blank for everyone else
 *
 * `activateUrl` is empty for an employee, which is how this module knows to show
 * "ask a manager" rather than a starting-balance pad. That is a courtesy, not the
 * control: `vouchers.manage` is enforced on the route.
 *
 * Money is only ever previewed here. The server rounds and re-checks the balance
 * under a lock, so a rounding quirk in the client can produce a refusal but never
 * an over-deduction.
 */
const TOAST_MS = 3000;
const MAX_DECIMALS = 2;
const MAX_CHARS = 7;

export default () => ({
    code: '',
    voucher: null,
    mode: 'idle',
    typed: '',
    busy: false,
    toast: null,
    toastTimer: null,

    get lookupUrl() {
        return this.$root.dataset.lookupUrl;
    },

    get deductUrl() {
        return this.$root.dataset.deductUrl;
    },

    /**
     * Empty for anyone without vouchers.manage, which is how the view knows not to
     * offer activation.
     */
    get activateUrl() {
        return this.$root.dataset.activateUrl;
    },

    get csrf() {
        return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    },

    get balance() {
        return Number(this.voucher?.current_balance ?? 0);
    },

    get amount() {
        return this.typed === '' ? 0 : Number(this.typed);
    },

    get remaining() {
        return Math.max(0, this.balance - this.amount);
    },

    /**
     * The epsilon lets "Use full balance" through: 42.5 typed back as "42.50" can
     * land a hair above the balance in binary floating point.
     */
    get canDeduct() {
        return this.mode === 'active' && this.amount > 0 && this.amount <= this.balance + 0.0001;
    },

    /** A manager is looking at a code that has no balance on it yet. */
    get activating() {
        return !! this.activateUrl && (this.mode === 'unknown' || this.mode === 'inactive');
    },

    get canActivate() {
        return this.activating && this.amount > 0;
    },

    /** The same state, seen by someone who cannot fix it. */
    get needsManager() {
        return ! this.activateUrl && (this.mode === 'unknown' || this.mode === 'inactive');
    },

    get displayLabel() {
        return this.activating ? 'Starting balance €' : 'Deduct €';
    },

    get deductLabel() {
        return 'Deduct ' + this.format(this.amount);
    },

    get activateLabel() {
        return 'Activate with ' + this.format(this.amount);
    },

    get statusPill() {
        switch (this.mode) {
            case 'active': return { tone: 'ok', text: 'Active' };
            case 'deactivated': return { tone: 'bad', text: 'Deactivated' };
            case 'exhausted': return { tone: 'muted', text: 'Exhausted' };
            default: return { tone: 'warn', text: 'Not active' };
        }
    },

    get issuedText() {
        if (! this.voucher?.issued_at) {
            return this.voucher?.code ?? '';
        }

        const d = new Date(this.voucher.issued_at);
        const when = d.toLocaleDateString('en-IE', { day: 'numeric', month: 'short', year: 'numeric' });

        return `Issued ${when} · ${this.voucher.code}`;
    },

    get history() {
        return this.voucher?.history ?? [];
    },

    async onScan(code) {
        this.busy = true;

        try {
            const data = await this.post(this.lookupUrl, { code });

            this.typed = '';

            if (data.found) {
                this.voucher = data;
                this.mode = data.status;
                this.code = data.code;
            } else {
                this.voucher = null;
                this.mode = 'unknown';
                this.code = code;
            }

            // Either answer is a successful lookup; the screen explains the state.
            this.announceDone();
        } catch (e) {
            this.announceError('Could not look up voucher');
        } finally {
            this.busy = false;
        }
    },

    /**
     * Refresh status, balance and history from the server after a write, so what
     * is on screen is what was recorded rather than what the client predicted.
     */
    async refresh() {
        try {
            const data = await this.post(this.lookupUrl, { code: this.code });

            if (data.found) {
                this.voucher = data;
                this.mode = data.status;
            }
        } catch (e) { /* the toast already said what happened */ }
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

            // Never a leading point: "0." reads as an amount, "." does not.
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

    useFull() {
        this.typed = this.balance.toFixed(2);
    },

    async deduct() {
        if (! this.canDeduct || this.busy) {
            return;
        }

        this.busy = true;
        const spent = this.amount;

        try {
            const data = await this.post(this.deductUrl, { code: this.code, amount: spent.toFixed(2) });

            if (data.success) {
                this.voucher.current_balance = data.new_balance;
                this.voucher.status = data.status;
                this.mode = data.status;
                this.typed = '';
                this.showToast('ok', `Deducted ${this.format(spent)} · ${this.format(data.new_balance)} left`);
                await this.refresh();
            } else {
                if (data.current_balance !== undefined && this.voucher) {
                    this.voucher.current_balance = data.current_balance;
                }
                this.showToast('bad', data.message);
            }
        } catch (e) {
            this.showToast('bad', 'Could not deduct');
        } finally {
            this.busy = false;
        }
    },

    async activate() {
        if (! this.canActivate || this.busy) {
            return;
        }

        this.busy = true;
        const opening = this.amount;

        try {
            const data = await this.post(this.activateUrl, {
                code: this.code,
                starting_balance: opening.toFixed(2),
            });

            if (data.success) {
                this.typed = '';
                // Status, balance and the issue row all come back from the lookup.
                await this.refresh();
                this.showToast('ok', `Activated with ${this.format(opening)}`);
            } else {
                this.showToast('bad', data.message);
            }
        } catch (e) {
            this.showToast('bad', 'Could not activate');
        } finally {
            this.busy = false;
        }
    },

    format(n) {
        return '€' + Number(n).toFixed(2);
    },

    /** The design uses a typographic minus, not a hyphen. */
    signed(n) {
        return (n < 0 ? '−' : '+') + this.format(Math.abs(n));
    },

    when(iso) {
        if (! iso) {
            return '';
        }

        return new Date(iso).toLocaleDateString('en-IE', { day: 'numeric', month: 'short' });
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

    announceDone() {
        window.dispatchEvent(new CustomEvent('shop-scan-done'));
    },

    announceError(message) {
        window.dispatchEvent(new CustomEvent('shop-scan-error', { detail: message }));
    },
});
