/**
 * Shop mode find product page.
 *
 * Read-only: it calls the canonical product search endpoint
 * (`api.products.search`, whose URL arrives as a data-* attribute on the page
 * root so no Blade leaks into this file) and shows price and stock. Nothing
 * here creates, edits, adjusts or prints; the API's edit link is ignored.
 *
 * Search runs as the user types, debounced, with a minimum query length. Every
 * request carries a sequence number so a slow earlier response can never
 * overwrite a newer one; this mirrors the office component's request counter.
 */
const DEBOUNCE_MS = 300;
const MIN_CHARS = 2;
const PER_PAGE = 20;
// Hover preview panel: 272px wide, and the panel's outer height rounded up.
// Measured from the APP ADDITIONS rules: 8 + 8 padding, 1 + 1 border, 256 image,
// 8 caption margin and one 16px line at 1.4 => ~304px. Used to keep it inside
// the viewport, so rounding up is the safe direction.
const PEEK_W = 272;
const PEEK_H = 320;
const PEEK_GAP = 12;
const PEEK_EDGE = 8;

export default () => ({
    q: '',
    stocked: true,
    results: [],
    meta: null,
    page: 1,
    loading: false,
    selected: null,
    error: null,
    // Product ids whose image 404'd or was blocked; keyed so a miss is per-row.
    failed: {},
    enlarged: false,
    // { product, x, y } while a row thumbnail is hovered; null otherwise.
    peek: null,
    // Held so destroy() can remove the same reference init() added.
    onScroll: null,
    seq: 0,
    timer: null,

    init() {
        this.$refs.input?.focus();
        // The panel is positioned against the viewport, so a scroll would leave
        // it stranded beside the wrong row.
        this.onScroll = () => this.unpeek();
        window.addEventListener('scroll', this.onScroll, { passive: true });
    },

    /**
     * Alpine calls this when the component's element leaves the DOM. This screen
     * lives as long as the page, but a window listener that cannot be removed is
     * a leak waiting for the next module to copy it.
     */
    destroy() {
        if (this.onScroll) {
            window.removeEventListener('scroll', this.onScroll);
            this.onScroll = null;
        }
    },

    get searchUrl() {
        return this.$root.dataset.searchUrl;
    },

    /**
     * Touch devices get nothing: there is no hover, and the detail card already
     * enlarges on tap. The stylesheet gates this too; this check just avoids
     * doing the work.
     */
    get canHover() {
        return window.matchMedia?.('(hover: hover) and (pointer: fine)').matches ?? false;
    },

    get hasMore() {
        return !! this.meta && this.meta.page < this.meta.last_page;
    },

    get correctedQuery() {
        return this.meta?.corrected_query ?? null;
    },

    /**
     * A scanned barcode is simply a long digit token; a single hit for one is
     * almost certainly the product the user is holding, so it opens itself.
     */
    get isBarcodeQuery() {
        return /^\d{8,}$/.test(this.q.trim());
    },

    onInput() {
        clearTimeout(this.timer);

        if (this.q.trim().length < MIN_CHARS) {
            this.results = [];
            this.meta = null;
            this.selected = null;

            return;
        }

        this.timer = setTimeout(() => this.search(), DEBOUNCE_MS);
    },

    onEnter() {
        clearTimeout(this.timer);

        if (this.q.trim().length < MIN_CHARS) {
            return;
        }

        this.search({ autoSelect: true });
    },

    setStocked(value) {
        this.stocked = value;

        if (this.q.trim().length >= MIN_CHARS) {
            this.search();
        }
    },

    async search({ append = false, autoSelect = false } = {}) {
        const mine = ++this.seq;

        this.loading = true;
        this.page = append ? this.page + 1 : 1;

        if (! append) {
            this.failed = {};
            this.unpeek();
        }

        const params = new URLSearchParams({
            q: this.q.trim(),
            stocked: this.stocked ? 1 : 0,
            page: this.page,
            per_page: PER_PAGE,
        });

        try {
            const response = await fetch(`${this.searchUrl}?${params}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            const data = await response.json();

            // A newer search has started; this answer is stale.
            if (mine !== this.seq) {
                return;
            }

            this.results = append ? [...this.results, ...data.data] : data.data;
            this.meta = data.meta;
            this.error = null;

            if (! append) {
                this.selected = null;

                if (autoSelect && this.isBarcodeQuery && this.results.length === 1) {
                    this.select(this.results[0]);
                }
            }
        } catch (e) {
            if (mine !== this.seq) {
                return;
            }

            this.results = [];
            this.meta = null;
            this.error = 'Search failed';
        } finally {
            if (mine === this.seq) {
                this.loading = false;
            }
        }
    },

    more() {
        this.search({ append: true });
    },

    select(p) {
        // A new product always opens small, whatever the last one was showing.
        this.enlarged = false;
        this.unpeek();
        this.selected = p;
        // The card renders above the list, so a nearest-edge nudge is enough.
        this.$nextTick(() => this.$refs.card?.scrollIntoView({ block: 'nearest' }));
    },

    close() {
        this.enlarged = false;
        this.unpeek();
        this.selected = null;
        this.$refs.input?.focus();
    },

    toggleImage() {
        this.enlarged = ! this.enlarged;
    },

    /**
     * `image_url` is a candidate, not a promise: the supplier CDN fallback is a
     * template URL that may 404. The row hides the image on the browser's error
     * event and shows the placeholder instead, as the office component does.
     */
    hasImage(p) {
        return !! p.image_url && ! this.failed[p.id];
    },

    imageFailed(p) {
        // Reassigned rather than mutated so Alpine sees the change.
        this.failed = { ...this.failed, [p.id]: true };

        // An image that fails while enlarged would leave a full-width empty button.
        if (p.id === this.selected?.id) {
            this.enlarged = false;
        }

        if (this.peek?.product.id === p.id) {
            this.unpeek();
        }
    },

    /**
     * Place the panel to the right of the thumbnail, flipping to the left when it
     * would run off the right edge, and clamped so it never hangs below the
     * window. Coordinates are viewport-relative, matching `position: fixed`.
     */
    peekAt(p, el) {
        if (! this.canHover || ! this.hasImage(p)) {
            return;
        }

        const r = el.getBoundingClientRect();
        let x = r.right + PEEK_GAP;

        if (x + PEEK_W > window.innerWidth - PEEK_EDGE) {
            x = r.left - PEEK_GAP - PEEK_W;
        }

        if (x < PEEK_EDGE) {
            x = PEEK_EDGE;
        }

        const y = Math.min(
            Math.max(PEEK_EDGE, r.top - PEEK_EDGE),
            window.innerHeight - PEEK_H - PEEK_EDGE,
        );

        this.peek = { product: p, x, y };
    },

    unpeek() {
        this.peek = null;
    },

    price(p) {
        return '€' + Number(p.price_with_vat).toFixed(2);
    },

    /**
     * Whole units only, as the office page shows them. Weighed goods floor, so
     * 2.5 reads as 2 rather than "0.5 in stock".
     */
    units(p) {
        return Math.floor(Number(p.stock_units));
    },

    stockLabel(p) {
        const n = this.units(p);

        return n > 0 ? `${n} in stock` : 'Out of stock';
    },

    stockTone(p) {
        return this.units(p) > 0 ? 'shop-pill--ok' : 'shop-pill--bad';
    },

    rowMeta(p) {
        return [p.category_name, p.code].filter(Boolean).join(' · ');
    },

    supplierLabel(p) {
        if (! p.supplier) {
            return '—';
        }

        return p.supplier.code ? `${p.supplier.name} · ${p.supplier.code}` : p.supplier.name;
    },
});
