/**
 * New request form on the Shop customer-requests board.
 *
 * The form itself is a plain HTML POST to the existing endpoint, so the redirect
 * and flash contract is untouched. The only behaviour here is the product
 * typeahead for a pre-order, which reads the canonical search endpoint from a
 * data-* attribute on the form.
 *
 * `seed` is the line that came back from a failed submission, so the person does
 * not retype it.
 */
const LIMIT = 8;

export default (seed = null) => ({
    // Sourcing when the bounced line had a description and no product behind it.
    kind: seed && ! seed.product_code && seed.description ? 'sourcing' : 'preorder',
    query: '',
    results: [],
    picked: seed?.product_code
        ? { code: seed.product_code, name: seed.product_name ?? seed.product_code, image_url: null }
        : null,
    description: seed?.description ?? '',
    searching: false,
    // Product ids whose image would not load; keyed so a miss is per-row.
    failed: {},

    get searchUrl() {
        return this.$root.dataset.searchUrl;
    },

    /**
     * The hidden inputs. A pre-order sends the code and the snapshot name; a
     * sourcing request sends only the free text, which is what the endpoint
     * requires when there is no product_code.
     */
    get productCode() {
        return this.kind === 'preorder' && this.picked ? this.picked.code : '';
    },

    get productName() {
        return this.kind === 'preorder' && this.picked ? this.picked.name : '';
    },

    get descriptionValue() {
        return this.kind === 'preorder'
            ? (this.picked ? this.picked.name : '')
            : this.description;
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

    pick(p) {
        this.picked = { code: p.code, name: p.name, image_url: p.image_url ?? null };
        this.results = [];
        this.query = '';
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
     * pick() clears `query`, so the debounced search that fires afterwards sees
     * an empty query and clears `results` instead of repopulating them.
     */
    async pickFirst() {
        if (! this.results.length && this.query.trim() !== '') {
            await this.search();
        }

        if (this.results.length) {
            this.pick(this.results[0]);
        }
    },

    /**
     * image_url is a candidate, not a promise: the supplier CDN fallback can
     * 404. Hide the image on the browser's error event and show the placeholder,
     * exactly as the Find product screen does.
     */
    hasImage(p) {
        return !! p.image_url && ! this.failed[p.id];
    },

    imageFailed(p) {
        // Reassigned rather than mutated so Alpine sees the change.
        this.failed = { ...this.failed, [p.id]: true };
    },

    unpick() {
        this.picked = null;
        this.query = '';
        this.results = [];
    },
});
