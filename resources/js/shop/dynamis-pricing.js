/**
 * Shop mode Dynamis pricing: the office pricing page for a phone, used beside
 * the boxes (resources/views/shop/fv-pricing-invoice.blade.php).
 *
 * The lines and settings arrive as JSON in data-* attributes on the page root,
 * and every write goes to the office page's endpoints under data-line-url:
 *   - {line}/product       POST  choose (or clear) the till product
 *   - {line}/apply         POST  write the price to the till
 *   - {line}/skip          POST  skip the line, or bring it back
 *   - {line}/country       POST  set the till product to the invoice's origin
 *   - {line}/show-on-till  POST  put the product on the till buttons
 *
 * THE FORMULA IS A COPY. guide(), boxPrice(), suggested() and landedCost()
 * mirror App\Services\DynamisPricingService and the Alpine code in
 * resources/views/deliveries/dynamis-pricing.blade.php. Change all three
 * together (docs/features/dynamis-pricing.md).
 *
 * The root's dataset is read once in init(): the line editor sits in an x-if,
 * and a handler on an element its own action removes loses $root.
 */
import mix from './mix.js';
import productTypeahead from './product-typeahead.js';

const TOAST_MS = 3000;

export default () => mix(productTypeahead(), {
    lines: [],
    settings: { transport: 1, markup: 1 },
    lineUrl: '',
    productSearchUrl: '',
    filter: 'todo',
    open: null,
    picking: null,
    toast: null,
    toastTimer: null,

    init() {
        const data = this.$root.dataset;

        this.lineUrl = data.lineUrl;
        this.productSearchUrl = data.searchUrl;
        this.settings = JSON.parse(data.settings || '{}');
        this.lines = JSON.parse(data.lines || '[]').map((line) => ({
            ...line, price: '', priceSource: 'till', busy: false, error: null,
        }));

        // Nothing left to do: show the finished lines rather than an empty list.
        if (! this.lines.some((line) => ! this.isDone(line))) {
            this.filter = 'all';
        }
    },

    // ---- the formula (mirrors App\Services\DynamisPricingService) ----
    guide(line) {
        const vat = line.product ? line.product.vat_rate : 0;

        return line.net_unit_price * this.settings.transport * this.settings.markup * (1 + vat);
    },

    boxPrice(line) {
        return this.guide(line) * line.quantity / line.packages;
    },

    divisor(line) {
        const divisor = line.mode === 'per_kg' ? line.kg_per_box : line.units_per_box;

        return divisor && divisor > 0 ? divisor : null;
    },

    suggested(line) {
        const divisor = this.divisor(line);

        return divisor === null ? null : Math.round(this.boxPrice(line) / divisor * 100) / 100;
    },

    // What one kg / one piece as sold costs us, delivery included (ex VAT).
    landedCost(line) {
        const divisor = this.divisor(line);

        return divisor === null
            ? null
            : line.net_unit_price * this.settings.transport * line.quantity / line.packages / divisor;
    },

    // Share of the price before VAT that is left after the landed cost.
    margin(line) {
        const cost = this.landedCost(line);
        const price = this.priceNumber(line);

        if (cost === null || price === null) {
            return null;
        }

        const product = line.product || line.suggestion;
        const exVat = price / (1 + (product ? product.vat_rate : 0));

        return (exVat - cost) / exVat;
    },

    belowCost(line) {
        const margin = this.margin(line);

        return margin !== null && margin < 0;
    },

    // ---- the price box ----
    // It starts on the price already on the till. The guide only goes in when
    // "Use guide" is tapped; typing overrides both.
    priceValue(line) {
        if (line.priceSource === 'typed') {
            return line.price;
        }

        if (line.priceSource === 'till') {
            // Before a product is confirmed, the suggested product's till price stands in.
            const now = this.tillPrice(line);

            return now === null ? '' : now.toFixed(2);
        }

        const suggested = this.suggested(line);

        return suggested === null ? '' : suggested.toFixed(2);
    },

    typePrice(line, value) {
        // A decimal comma from a phone keyboard is a decimal point.
        line.price = String(value).replace(',', '.');
        line.priceSource = 'typed';
    },

    tillPrice(line) {
        const product = line.product || line.suggestion;

        return product ? Number(product.till_price) : null;
    },

    priceNumber(line) {
        const n = parseFloat(this.priceValue(line));

        return Number.isFinite(n) && n > 0 ? n : null;
    },

    // ---- wording ----
    money(n) {
        return '€' + Number(n).toFixed(2);
    },

    trim(n) {
        return String(Math.round(n * 100) / 100);
    },

    unitWord(unit) {
        return { KG: 'kg', COL: 'box', PCE: 'piece' }[unit] || String(unit || '').toLowerCase();
    },

    per(line) {
        return line.mode === 'per_kg' ? 'per kg' : 'each';
    },

    packText(line) {
        const each = line.quantity / line.packages;
        const boxes = line.packages + (line.packages === 1 ? ' box' : ' boxes');

        if (line.unit === 'KG') {
            return boxes + ' × ' + this.trim(each) + ' kg';
        }

        if (line.unit === 'PCE') {
            return boxes + ' × ' + this.trim(each) + ' pieces';
        }

        return boxes;
    },

    // The closed row: which till product, and what it sells for now.
    headText(line) {
        if (line.product) {
            return line.product.name + ' · now ' + this.money(line.product.till_price);
        }

        if (line.suggestion) {
            return 'Looks like ' + line.suggestion.name;
        }

        return 'No till product';
    },

    guideText(line) {
        const suggested = this.suggested(line);

        return suggested === null ? 'count the box' : 'guide ' + this.money(suggested);
    },

    status(line) {
        if (line.skipped) {
            return ['muted', 'Skipped'];
        }

        if (line.applied_price !== null) {
            return ['ok', this.money(line.applied_price)];
        }

        if (! line.product) {
            return ['warn', line.suggestion ? 'Check product' : 'No product'];
        }

        return ['sage', 'To do'];
    },

    invoiceText(line) {
        return [
            line.code,
            line.origin,
            this.packText(line),
            'cost ' + this.money(line.net_unit_price) + '/' + this.unitWord(line.unit),
        ].filter(Boolean).join(' · ');
    },

    nowText(line) {
        const unit = line.product.till_unit;

        return 'Now ' + this.money(line.product.till_price) + (unit ? ' ' + (unit === 'kg' ? 'per kg' : unit) : '');
    },

    countNote(line) {
        if (! line.units_per_box) {
            return 'Count what is in a box.';
        }

        if (line.count_source === 'size') {
            return 'From “SIZE ' + line.units_per_box + '” on the invoice. Check the box.';
        }

        if (line.count_source === 'saved') {
            return 'Count used last time. Check the box.';
        }

        return '';
    },

    unitClash(line) {
        const unit = line.product ? line.product.till_unit : null;

        if (! unit) {
            return '';
        }

        if (unit === 'kg' && line.mode === 'each') {
            return 'The till sells this per kg.';
        }

        if (unit !== 'kg' && line.mode === 'per_kg') {
            return 'The till sells this per ' + unit + ', not per kg.';
        }

        return '';
    },

    guideLabel(line) {
        const suggested = this.suggested(line);

        return suggested === null ? '' : 'Use guide ' + this.money(suggested);
    },

    keepLabel(line) {
        const now = this.tillPrice(line);

        return now === null ? '' : 'Keep ' + this.money(now);
    },

    changeText(line) {
        const price = this.priceNumber(line);

        if (! line.product || price === null) {
            return '';
        }

        const now = Number(line.product.till_price);

        if (Math.abs(price - now) < 0.005) {
            return 'No change to the till price';
        }

        const pct = now > 0 ? ' (' + (price > now ? '+' : '') + Math.round((price - now) / now * 100) + '%)' : '';

        return (price > now ? 'Up ' : 'Down ') + this.money(Math.abs(price - now)) + pct + ' on the till price';
    },

    costText(line) {
        const cost = this.landedCost(line);

        if (cost === null) {
            return '';
        }

        const margin = this.margin(line);
        const tail = margin === null
            ? ''
            : ' · ' + (margin < 0 ? 'below cost' : 'margin ' + Math.round(margin * 100) + '%');

        return 'Cost ' + this.money(cost) + ' ' + this.per(line) + ' inc. delivery' + tail;
    },

    appliedText(line) {
        return line.applied_price === null
            ? ''
            : this.money(line.applied_price) + ' on the till' + (line.applied_at ? ', ' + line.applied_at : '');
    },

    applyLabel(line) {
        const price = this.priceNumber(line);
        const verb = line.applied_price !== null ? 'Apply again' : 'Apply';

        return price === null ? verb : verb + ' ' + this.money(price);
    },

    countryState(line) {
        if (! line.product || ! line.origin) {
            return null;
        }

        if (! line.origin_country) {
            return 'unknown';
        }

        return line.product.country_id === line.origin_country.id ? 'same' : 'differs';
    },

    countryText(line) {
        const till = line.product?.country_name || 'no country';

        if (! line.origin_country) {
            return 'Till says ' + till + '; invoice origin ' + line.origin + ' is not in the country list.';
        }

        return 'Till says ' + till + ', invoice says ' + line.origin_country.name + '.';
    },

    // Other origins the same till product arrives from on this invoice.
    otherOrigins(line) {
        if (! line.product) {
            return '';
        }

        const names = this.lines
            .filter((other) => other !== line && other.product && other.product.id === line.product.id && other.origin && other.origin !== line.origin)
            .map((other) => (other.origin_country ? other.origin_country.name : other.origin));

        return [...new Set(names)].join(', ');
    },

    // ---- lists ----
    isDone(line) {
        return line.skipped || line.applied_price !== null;
    },

    canApply(line) {
        return ! line.skipped && !! line.product && this.priceNumber(line) !== null;
    },

    get filters() {
        const done = this.lines.filter((line) => this.isDone(line)).length;

        return [
            { key: 'todo', label: 'To do', count: this.lines.length - done },
            { key: 'done', label: 'Done', count: done },
            { key: 'all', label: 'All', count: this.lines.length },
        ];
    },

    get visibleLines() {
        if (this.filter === 'todo') {
            return this.lines.filter((line) => ! this.isDone(line));
        }

        if (this.filter === 'done') {
            return this.lines.filter((line) => this.isDone(line));
        }

        return this.lines;
    },

    setFilter(key) {
        this.filter = key;
        this.open = null;
    },

    toggle(line) {
        this.open = this.open === line.id ? null : line.id;
    },

    // ---- product picker (product-typeahead.js) ----
    get searchUrl() {
        return this.productSearchUrl;
    },

    // Every product, stocked or not, with whether it is on the till.
    searchParams(q) {
        return { q, per_page: 12, stocked: 0, with_till: 1 };
    },

    openPicker(line) {
        this.query = '';
        this.results = [];
        this.searchError = null;
        this.answered = null;
        this.picking = line;
        // x-show reveals the sheet after $nextTick has run, so focus needs a frame.
        this.$nextTick(() => requestAnimationFrame(() => this.$refs.pickerSearch?.focus()));
    },

    closePicker() {
        this.picking = null;
    },

    onPick(product) {
        const line = this.picking;

        this.picking = null;

        if (line) {
            this.link(line, product.id);
        }
    },

    unlink() {
        const line = this.picking;

        this.picking = null;

        if (line) {
            this.link(line, null);
        }
    },

    // ---- actions ----
    get csrf() {
        return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    },

    async post(path, body) {
        let response;

        try {
            response = await fetch(`${this.lineUrl}/${path}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrf,
                },
                credentials: 'same-origin',
                body: JSON.stringify(body),
            });
        } catch (e) {
            throw new Error('No connection. Nothing was saved; try again.');
        }

        if ([401, 419].includes(response.status)) {
            throw new Error('You have been signed out. Reload the page to sign in.');
        }

        const data = await response.json().catch(() => ({}));

        if (! response.ok || data.success === false) {
            const first = data.errors ? Object.values(data.errors)[0][0] : null;

            throw new Error(first || data.message || 'Something went wrong (' + response.status + ').');
        }

        return data;
    },

    merge(line, fresh, keepChoices) {
        const keep = keepChoices
            ? { mode: line.mode, units_per_box: line.units_per_box, kg_per_box: line.kg_per_box, count_source: line.count_source }
            : {};

        Object.assign(line, fresh, keep);
    },

    async run(line, work) {
        line.busy = true;
        line.error = null;

        try {
            await work();

            return true;
        } catch (e) {
            line.error = e.message;
            // The line may be closed or filtered out by now; say it where it is seen.
            this.say('bad', e.message);

            return false;
        } finally {
            line.busy = false;
        }
    },

    setMode(line, mode) {
        line.mode = mode;

        if (line.skipped) {
            this.skip(line, false);
        }
    },

    link(line, productId) {
        return this.run(line, async () => {
            const data = await this.post(`${line.id}/product`, { product_id: productId });

            // A newly chosen product decides per kg / each unless a price was already applied.
            this.merge(line, data.line, line.applied_price !== null);
            line.priceSource = 'till';
        });
    },

    apply(line) {
        return this.run(line, async () => {
            const price = this.priceNumber(line);
            const data = await this.post(`${line.id}/apply`, {
                mode: line.mode,
                units_per_box: line.units_per_box || null,
                kg_per_box: line.kg_per_box || null,
                price,
            });

            this.merge(line, data.line, false);
            line.price = price.toFixed(2);
            line.priceSource = 'typed';

            const name = line.product ? line.product.name : line.description;

            this.say('ok', data.changed === false
                ? name + ': price unchanged, marked done'
                : name + ' is now ' + this.money(price) + ' ' + this.per(line));
            this.open = null;
        });
    },

    skip(line, skip) {
        return this.run(line, async () => {
            const mode = line.mode;
            const data = await this.post(`${line.id}/skip`, { skip });

            this.merge(line, data.line, false);

            if (skip) {
                this.say('ok', 'Skipped. The till price is left alone.');
                this.open = null;
            } else {
                line.mode = mode;
                this.say('ok', 'Back on the to-do list.');
            }
        });
    },

    useInvoiceCountry(line) {
        return this.run(line, async () => {
            const data = await this.post(`${line.id}/country`, {});

            // One till product can sit on several lines; they all show its new country.
            this.lines
                .filter((other) => other.product && other.product.id === data.line.product.id)
                .forEach((other) => {
                    other.product.country_id = data.line.product.country_id;
                    other.product.country_name = data.line.product.country_name;
                });

            this.say('ok', 'Country changed. A new label is queued.');
        });
    },

    showOnTill(line) {
        return this.run(line, async () => {
            const data = await this.post(`${line.id}/show-on-till`, {});

            line.product.on_till = data.line.product.on_till;
        });
    },

    say(tone, text) {
        this.toast = { tone, text };
        clearTimeout(this.toastTimer);
        this.toastTimer = setTimeout(() => { this.toast = null; }, TOAST_MS);
    },
});
