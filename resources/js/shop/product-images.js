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
    // Product ids whose image would not load; keyed so a miss is per-row.
    failed: {},

    hasImage(p) {
        return !! p?.image_url && ! this.failed[p.id];
    },

    imageFailed(p) {
        if (! p?.id) {
            return;
        }

        // Reassigned rather than mutated so Alpine sees the change.
        this.failed = { ...this.failed, [p.id]: true };
    },
});
