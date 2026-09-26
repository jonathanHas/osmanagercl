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
export default () => ({
    q: '',

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
