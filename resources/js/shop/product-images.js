/**
 * Product thumbnails, shared by any Shop screen that lists products.
 *
 * `image_url` from the search API is a candidate, not a promise: the supplier CDN
 * fallback is a template URL that can 404. Hide the image on the browser's error
 * event and let the caller's placeholder show instead.
 *
 * Composed into an Alpine data object with mix(): `mix(productImages(), {…})`.
 */
export default () => ({
    // Products whose image would not load; keyed so a miss is per-row.
    failed: {},

    /**
     * The search API's products carry `id`; the fruit & veg rows are built from POS
     * products and carry `code` instead. `id` wins where both exist, so nothing
     * that worked before changes key.
     */
    key(p) {
        return p?.id ?? p?.code ?? null;
    },

    hasImage(p) {
        return !! p?.image_url && ! this.failed[this.key(p)];
    },

    imageFailed(p) {
        const k = this.key(p);

        if (k === null) {
            return;
        }

        // Reassigned rather than mutated so Alpine sees the change.
        this.failed = { ...this.failed, [k]: true };
    },
});
