/**
 * Shop mode print labels, Zebra labels tab.
 *
 * Reads from the office endpoints, whose URLs arrive as data-* attributes on the
 * page root:
 *   - labels.zebra-list     GET   saved Zebra labels (on the till, other,
 *                                 standalone) then translated labels; each item
 *                                 carries its own print_url
 *   - labels.printer-queue  GET   the counter printer's spool (up to 15 s)
 *   - labels.printer-cancel POST  cancel every job (managers only; the URL is
 *                                 blank otherwise)
 *
 * A price or country that no longer matches the till is printed with the till's
 * value through the print endpoint's `fields` override, for that print only. The
 * saved label is never edited from here; that stays on the office page.
 */
const TOAST_MS = 4000;
const MAX_COPIES = 99;

const GROUPS = [
    { key: 'till', title: 'On the till', hint: '' },
    { key: 'other', title: 'Other products', hint: 'Not on the till right now.' },
    { key: 'standalone', title: 'Standalone labels', hint: 'Not linked to a product, so a scan won’t find them. Search or tap.' },
];

export default () => ({
    labels: [],
    query: '',
    selected: null,
    copies: 1,
    useTillPrice: true,
    useTillCountry: true,
    busy: false,
    loading: true,
    error: null,
    // { ok, count } once the spool has answered; null while checking.
    printer: null,
    toast: null,
    toastTimer: null,

    init() {
        // Not awaited together: the spool call can take 15 s and must not hold
        // up the list.
        this.load();
        this.loadPrinter();
    },

    get listUrl() {
        return this.$root.dataset.listUrl;
    },

    get queueUrl() {
        return this.$root.dataset.queueUrl;
    },

    get cancelUrl() {
        return this.$root.dataset.cancelUrl;
    },

    get csrf() {
        return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    },

    async load() {
        try {
            const response = await fetch(this.listUrl, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            const data = await response.json();

            this.labels = data.labels;
            this.error = null;
        } catch (e) {
            this.error = 'Could not load labels';
        } finally {
            this.loading = false;
        }
    },

    async loadPrinter() {
        try {
            const response = await fetch(this.queueUrl, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            const data = await response.json();

            this.printer = { ok: !! data.success, count: data.count ?? 0 };
        } catch (e) {
            this.printer = { ok: false, count: 0 };
        }
    },

    matches(l) {
        const q = this.query.trim().toLowerCase();

        if (! q) {
            return true;
        }

        return [l.name, l.label, l.product_code].some((v) => (v ?? '').toString().toLowerCase().includes(q));
    },

    get translatedCount() {
        return this.labels.filter((l) => l.group === 'translated').length;
    },

    /**
     * The three Zebra groups, plus the translated labels once there is a query:
     * there are hundreds of them, so they are found, not browsed.
     */
    get groups() {
        const groups = GROUPS.map((g) => {
            const rows = this.labels.filter((l) => l.group === g.key && this.matches(l));

            return { ...g, rows, count: String(rows.length) };
        });

        if (this.query.trim()) {
            const rows = this.labels.filter((l) => l.group === 'translated' && this.matches(l));

            groups.push({ key: 'translated', title: 'Translated labels', hint: '', rows, count: rows.length + ' of ' + this.translatedCount });
        }

        return groups;
    },

    get shown() {
        return this.groups.reduce((sum, g) => sum + g.rows.length, 0);
    },

    onScan(code) {
        // Array order puts saved Zebra labels before translations.
        const label = this.labels.find((l) => l.product_code === code);

        if (! label) {
            this.close();
            this.announceError('No Zebra label for this product');

            return;
        }

        this.open(label);
        this.announceDone();
    },

    open(l) {
        this.selected = l;
        this.copies = l.default_copies || 1;
        this.useTillPrice = true;
        this.useTillCountry = true;

        try {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } catch (e) {
            // Scrolling is a nicety.
        }
    },

    close() {
        this.selected = null;
    },

    isSelected(r) {
        return !! this.selected && this.selected.kind === r.kind && this.selected.id === r.id;
    },

    bump(n) {
        this.copies = Math.min(MAX_COPIES, Math.max(1, this.copies + n));
    },

    get printLabel() {
        return 'Print ' + this.copies + (this.copies === 1 ? ' label' : ' labels');
    },

    sizeText(r) {
        if (r?.width_mm == null || r?.height_mm == null) {
            return '';
        }

        return `${Number(r.width_mm)} × ${Number(r.height_mm)} mm`;
    },

    rowMeta(r) {
        if (! r) {
            return '';
        }

        const size = this.sizeText(r);

        if (r.group === 'standalone') {
            return 'Label only · ' + size;
        }

        if (r.kind === 'translated') {
            return ['Translated', size, r.product_code].filter(Boolean).join(' · ');
        }

        return [r.label, size, r.product_code].filter(Boolean).join(' · ');
    },

    get selectedMeta() {
        return this.rowMeta(this.selected);
    },

    pill(r) {
        if (r.group === 'standalone') {
            return { tone: 'muted', text: 'No product' };
        }

        if (r.kind === 'translated') {
            return r.auto_print ? { tone: 'muted', text: 'Auto-print' } : null;
        }

        const price = !! r.mismatches?.price;
        const country = !! r.mismatches?.country;

        if (price && country) {
            return { tone: 'warn', text: 'Price + country differ' };
        }

        if (price) {
            return { tone: 'warn', text: 'Price differs' };
        }

        if (country) {
            return { tone: 'warn', text: 'Country differs' };
        }

        return { tone: 'ok', text: 'Matches till' };
    },

    get priceText() {
        const m = this.selected?.mismatches?.price;

        return m ? `Label says €${m.label_value}, till says €${m.db_value}` : '';
    },

    get countryText() {
        const m = this.selected?.mismatches?.country;

        return m ? `Label says ${m.label_value}, till says ${m.db_value}` : '';
    },

    get stockText() {
        const s = this.selected;

        if (s?.width_mm == null || s?.height_mm == null) {
            return 'Check the printer has the right labels loaded';
        }

        return `Check the printer has ${Math.round(s.width_mm)} × ${Math.round(s.height_mm)} mm labels loaded`;
    },

    /**
     * Field index → text, applied by the print endpoint for this print only.
     * Saved Zebra labels only; translations carry no mismatches.
     */
    overrides() {
        const fields = {};
        const m = this.selected?.mismatches;

        if (this.selected?.kind !== 'zebra' || ! m) {
            return fields;
        }

        if (m.price && this.useTillPrice) {
            fields[m.price.field_index] = m.price.new_field;
        }

        if (m.country && this.useTillCountry) {
            fields[m.country.field_index] = m.country.new_field;
        }

        return fields;
    },

    async print() {
        if (! this.selected) {
            return;
        }

        this.busy = true;

        try {
            const body = { copies: this.copies };
            const fields = this.overrides();

            if (Object.keys(fields).length) {
                body.fields = fields;
            }

            const response = await fetch(this.selected.print_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrf,
                },
                credentials: 'same-origin',
                body: JSON.stringify(body),
            });

            const data = await response.json().catch(() => ({}));

            if (response.ok && data.success) {
                this.showToast('ok', data.message || 'Print job sent');
                this.close();
                this.loadPrinter();
                // Bring the camera / scan field back for the next label.
                window.dispatchEvent(new CustomEvent('shop-scan-saved'));
            } else {
                this.showToast('bad', data.message || 'Print failed');
            }
        } catch (e) {
            this.showToast('bad', 'Print failed');
        } finally {
            this.busy = false;
        }
    },

    async cancelAll() {
        if (! this.cancelUrl) {
            return;
        }

        this.busy = true;

        try {
            const data = await this.post(this.cancelUrl, { all: true });

            this.showToast(data.success ? 'ok' : 'bad', data.message || (data.success ? 'Printer queue cleared' : 'Could not cancel'));
        } catch (e) {
            this.showToast('bad', 'Could not cancel');
        } finally {
            this.busy = false;
        }

        this.loadPrinter();
    },

    get printerClass() {
        if (this.printer === null) {
            return '';
        }

        if (! this.printer.ok) {
            return 'is-off';
        }

        return this.printer.count === 0 ? 'is-ok' : 'is-busy';
    },

    get printerText() {
        if (this.printer === null) {
            return 'Counter Zebra · checking printer…';
        }

        if (! this.printer.ok) {
            return 'Counter Zebra · printer not reachable';
        }

        if (this.printer.count === 0) {
            return 'Counter Zebra · printer queue empty';
        }

        return 'Counter Zebra · ' + this.printer.count + (this.printer.count === 1 ? ' job' : ' jobs') + ' waiting';
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
