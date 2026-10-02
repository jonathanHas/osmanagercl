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
 */
const LIMIT = 8;

export default () => ({
    query: '',
    results: [],
    total: 0,
    searching: false,
    searchWhenEmpty: false,

    // Read lazily: composed with mix(), so this runs on the live component.
    get searchUrl() {
        return this.$root.dataset.searchUrl;
    },

    searchParams(q) {
        return { q, limit: LIMIT };
    },

    async search() {
        const q = this.query.trim();

        if (q === '' && ! this.searchWhenEmpty) {
            this.results = [];
            this.total = 0;

            return;
        }

        this.searching = true;

        try {
            const params = new URLSearchParams(this.searchParams(q));
            const response = await fetch(`${this.searchUrl}?${params}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            const data = await response.json();

            this.results = data.data ?? [];
            this.total = data.meta?.total ?? this.results.length;
        } catch (e) {
            this.results = [];
            this.total = 0;
        } finally {
            this.searching = false;
        }
    },

    pickResult(p) {
        this.results = [];
        this.total = 0;
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
