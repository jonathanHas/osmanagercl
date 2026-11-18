/**
 * The staff customer-requests board: live search over the rows on screen.
 *
 * Every status change is a plain form post, so there is nothing to fetch here.
 * Rows carry their searchable text in a data-text attribute and hide themselves
 * when it does not match; groups hide when none of their rows match.
 *
 * The "New request" sheet has its own tiny Alpine scope in the view, and each row
 * owns the open state of its own actions panel, so none of them interfere.
 */
const SCROLL_KEY = 'shop.requests.scroll';

export default () => ({
    q: '',

    /**
     * A status change reloads the page, which would land back at the top of a
     * long sheet. Remember where the user was when a row's form is submitted
     * and put them back there after the reload.
     *
     * The position is kept only for the same view (URL) and only briefly, so a
     * later, unrelated visit to the board still opens at the top.
     */
    init() {
        this.restoreScroll();

        this.$root.addEventListener('submit', () => {
            try {
                sessionStorage.setItem(SCROLL_KEY, JSON.stringify({
                    url: location.pathname + location.search,
                    y: window.scrollY,
                    at: Date.now(),
                }));
            } catch (e) {
                // Storage unavailable: the page just reloads at the top as before.
            }
        });
    },

    restoreScroll() {
        let saved = null;

        try {
            saved = JSON.parse(sessionStorage.getItem(SCROLL_KEY));
            sessionStorage.removeItem(SCROLL_KEY);
        } catch (e) {
            return;
        }

        if (! saved || saved.url !== location.pathname + location.search || Date.now() - saved.at > 30000) {
            return;
        }

        // After Alpine has drawn the rows, so the page is its full height.
        this.$nextTick(() => window.scrollTo({ top: saved.y, behavior: 'instant' }));
    },

    matches(text) {
        const needle = this.q.trim().toLowerCase();

        return needle === '' || (text ?? '').toLowerCase().includes(needle);
    },

    /**
     * Does any row inside this group still match?
     *
     * `this.q` is read first on purpose: Alpine tracks the properties an
     * expression touches, and without it this would never re-run on a keystroke
     * because everything else is plain DOM.
     */
    groupMatches(el) {
        return this.q.trim() === '' || this.rowsIn(el).some((row) => this.matches(row.dataset.text));
    },

    anyMatch() {
        return this.rowsIn(this.$root).some((row) => this.matches(row.dataset.text));
    },

    rowsIn(el) {
        return el ? Array.from(el.querySelectorAll('[data-text]')) : [];
    },
});
