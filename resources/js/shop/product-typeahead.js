/**
 * Product search-as-you-type, shared by any Shop screen that picks a product.
 *
 * Reads the canonical endpoint from `data-search-url` on the page root, so no
 * route helper appears in JavaScript. The caller supplies `onPick(product)` and
 * decides what picking means.
 *
 * Composed into an Alpine data object with mix(): `mix(productTypeahead(), {…})`.
 */
const LIMIT = 8;

export default () => ({
    query: '',
    results: [],
    searching: false,

    // Read lazily: composed with mix(), so this runs on the live component.
    get searchUrl() {
        return this.$root.dataset.searchUrl;
    },

    async search() {
        const q = this.query.trim();

        if (q === '') {
            this.results = [];

            return;
        }

        this.searching = true;

        try {
            const params = new URLSearchParams({ q, limit: LIMIT });
            const response = await fetch(`${this.searchUrl}?${params}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            const data = await response.json();

            this.results = data.data ?? [];
        } catch (e) {
            this.results = [];
        } finally {
            this.searching = false;
        }
    },

    pickResult(p) {
        this.results = [];
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
