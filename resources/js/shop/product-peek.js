/**
 * The floating larger picture beside a hovered product thumbnail, shared by any
 * Shop screen that lists products with `<x-shop.product-thumb>` (Find product,
 * Order review). Moved here from find-product.js in order_clean cycle 2.
 *
 * Composed with mix() after productImages() (it uses `hasImage()`):
 * `mix(productImages(), productPeek(), {…})`. It defines no `init`/`destroy`,
 * because mix() would let the host's silently replace them; the host calls
 * `watchPeekScroll()` from its init() and `unwatchPeekScroll()` from destroy().
 *
 * On a mouse, hovering a thumbnail opens the panel. On touch there is no hover,
 * so a tap on a thumbnail pins the panel until the next tap (`pinPeek`,
 * `maybeUnpin`); the stylesheet shows `.is-pinned` whatever the pointer.
 */

// Hover preview panel: 272px wide, and the panel's outer height rounded up.
// Measured from the APP ADDITIONS rules: 8 + 8 padding, 1 + 1 border, 256 image,
// 8 caption margin and one 16px line at 1.4 => ~304px. Used to keep it inside
// the viewport, so rounding up is the safe direction.
const PEEK_W = 272;
const PEEK_H = 320;
const PEEK_GAP = 12;
const PEEK_EDGE = 8;

export default () => ({
    // { product, x, y } while a thumbnail is hovered or pinned; null otherwise.
    peek: null,
    // Touch: a tapped thumbnail keeps the panel until the next tap.
    peekPinned: false,
    // Held so unwatchPeekScroll() can remove the same reference.
    onPeekScroll: null,

    /**
     * Whether this pointer can hover. The stylesheet gates `.is-open` the same
     * way; this check just avoids doing the work on touch.
     */
    get canHover() {
        return window.matchMedia?.('(hover: hover) and (pointer: fine)').matches ?? false;
    },

    /**
     * The panel is positioned against the viewport, so a scroll would leave it
     * stranded beside the wrong row.
     */
    watchPeekScroll() {
        this.onPeekScroll = () => this.unpinPeek();
        window.addEventListener('scroll', this.onPeekScroll, { passive: true });
    },

    unwatchPeekScroll() {
        if (this.onPeekScroll) {
            window.removeEventListener('scroll', this.onPeekScroll);
            this.onPeekScroll = null;
        }
    },

    peekAt(p, el) {
        if (! this.canHover || this.peekPinned) {
            return;
        }

        this.placePeek(p, el);
    },

    /** Touch only: a tap pins the panel; a second tap on the same product clears it. */
    pinPeek(p, el) {
        if (this.canHover) {
            return;
        }

        if (this.peekPinned && this.peek && this.key(this.peek.product) === this.key(p)) {
            this.unpinPeek();

            return;
        }

        this.placePeek(p, el);
        this.peekPinned = !! this.peek;
    },

    /** A tap anywhere but a thumbnail clears a pinned panel (wire to click.window). */
    maybeUnpin(e) {
        if (this.peekPinned && ! e.target.closest?.('.shop-thumb')) {
            this.unpinPeek();
        }
    },

    unpeek() {
        if (! this.peekPinned) {
            this.peek = null;
        }
    },

    unpinPeek() {
        this.peek = null;
        this.peekPinned = false;
    },

    /**
     * Place the panel to the right of the thumbnail, flipping to the left when it
     * would run off the right edge, and clamped so it never hangs below the
     * window. Coordinates are viewport-relative, matching `position: fixed`.
     */
    placePeek(p, el) {
        if (! this.hasImage(p)) {
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
});
