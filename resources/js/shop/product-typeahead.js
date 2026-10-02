/**
 * Product search-as-you-type, shared by any Shop screen that picks a product.
 *
 * Reads the canonical endpoint from `data-search-url` on the page root, so no
 * route helper appears in JavaScript. The caller supplies `onPick(product)` and
 * decides what picking means.
 *
 * Composed into an Alpine data object with mix(): `mix(productTypeahead(), {…})`.
 *
 * Two hooks a caller may override (later mix() parts win):
 *   - `searchParams(q)` returns the query parameters for the endpoint
 *     (default `{ q, limit }`), e.g. to add `supplier_id` or `stocked`;
 *   - `searchWhenEmpty` (default false) lets an empty query search too, for a
 *     list that is useful before anything is typed.
 * `total` holds the endpoint's `meta.total`, so a caller can say when it is
 * showing only the first page.
 *
 * A search that fails must not look like "no products": `searchError` is null,
 * 'failed' (offline, a server error, a body that is not JSON) or 'signed-out'
 * (401/419), and `searchMessage` is the sentence a screen shows for it, next to
 * a "Try again" that calls search() for 'failed'. Each call takes a sequence
 * number, and only the latest call writes results, total, searchError and
 * searching, so a slow older answer cannot replace a newer one.
 *
 * `answered` is the trimmed query the current `results` belong to (null before
 * any answer, after a failure, after a pick or a cleared query), and the
 * `noMatches` getter is true only when the search for the text on screen has
 * answered with nothing. A screen shows "No products match" on it, so the line
 * never flashes during the debounce before the search has run.
 */
const LIMIT = 8;

export default () => ({
    query: '',
    results: [],
    total: 0,
    searching: false,
    searchWhenEmpty: false,
    searchError: null,
    searchSeq: 0,
    answered: null,

    get noMatches() {
        const q = this.query.trim();

        return this.answered !== null && this.answered === q && q !== ''
            && ! this.results.length && ! this.searchError;
    },

    get searchMessage() {
        switch (this.searchError) {
            case 'failed':
                return 'Could not load products.';
            case 'signed-out':
                return 'You have been signed out. Reload the page to sign in.';
            default:
                return '';
        }
    },

    // Read lazily: composed with mix(), so this runs on the live component.
    get searchUrl() {
        return this.$root.dataset.searchUrl;
    },

    searchParams(q) {
        return { q, limit: LIMIT };
    },

    async search() {
        const q = this.query.trim();
        // Taken even for an empty query, so an answer still in flight for the
        // previous text cannot repopulate a cleared list.
        const seq = ++this.searchSeq;

        if (q === '' && ! this.searchWhenEmpty) {
            this.results = [];
            this.total = 0;
            this.searchError = null;
            this.searching = false;
            this.answered = null;

            return;
        }

        this.searching = true;

        try {
            const params = new URLSearchParams(this.searchParams(q));
            const response = await fetch(`${this.searchUrl}?${params}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (seq !== this.searchSeq) {
                return;
            }

            if (! response.ok) {
                this.searchError = [401, 419].includes(response.status) ? 'signed-out' : 'failed';
                this.results = [];
                this.total = 0;
                this.answered = null;

                return;
            }

            const data = await response.json();

            if (seq !== this.searchSeq) {
                return;
            }

            this.results = data.data ?? [];
            this.total = data.meta?.total ?? this.results.length;
            this.searchError = null;
            this.answered = q;
        } catch (e) {
            if (seq !== this.searchSeq) {
                return;
            }

            this.results = [];
            this.total = 0;
            this.searchError = 'failed';
            this.answered = null;
        } finally {
            // Only the latest call owns the spinner; a stale one must not switch
            // it off while the newer request is still in flight.
            if (seq === this.searchSeq) {
                this.searching = false;
            }
        }
    },

    pickResult(p) {
        this.answered = null;
        this.results = [];
        this.total = 0;
        this.searchError = null;
        this.query = '';
        this.onPick(p);
    },

    /**
     * Enter in the search box takes the first hit rather than submitting the
     * form, so a scanned barcode picks its product in one motion.
     *
     * A keyboard-wedge scanner types the code and sends Enter within tens of
     * milliseconds — well inside the 250 ms debounce — so `results` is usually
     * still empty at this point. Run the search first rather than doing nothing
     * and making the person tap the hit that appears a moment later.
     *
     * pickResult() clears `query`, so the debounced search that fires afterwards
     * sees an empty query and clears `results` instead of repopulating them.
     */
    async pickFirst() {
        if (! this.results.length && this.query.trim() !== '') {
            await this.search();
        }

        if (this.results.length) {
            this.pickResult(this.results[0]);
        }
    },
});
