/**
 * Editing a customer request's details and its lines.
 *
 * Statuses are never changed here — the board does that — so a line that has
 * moved past pending can be renamed but not removed, which is what the endpoint
 * enforces too.
 *
 * `seed` is the request's lines, or the old input when a submission bounced.
 * `statusLabels` maps a status to its human label for the read-only pill.
 */
import mix from './mix.js';
import productImages from './product-images.js';
import productTypeahead from './product-typeahead.js';

let nextKey = 0;

/**
 * A stable key per line so x-for does not reuse DOM across a removal, plus the
 * product object the thumbnail needs (seeded lines have no image to show).
 */
const withKey = (line) => ({
    id: line.id ?? null,
    product_code: line.product_code ?? '',
    product_name: line.product_name ?? '',
    description: line.description ?? '',
    quantity: line.quantity ?? 1,
    // 'unit' or 'case'. A line by the case keeps the size it was taken with;
    // otherwise the product's current case size, so the line can switch to Case.
    unit: line.unit === 'case' ? 'case' : 'unit',
    case_units: line.case_units ?? line.product?.case_units ?? null,
    notes: line.notes ?? '',
    status: line.status ?? null,
    product: line.product ?? null,
    _key: `line-${nextKey++}`,
});

export default (seed = null, statusLabels = {}) => mix(productImages(), productTypeahead(), {
    items: (seed ?? []).map(withKey),
    statusLabels,
    tooFew: false,

    /**
     * Picking a product the request already has adds one to that line rather
     * than making a second line for the same thing — but only when that line is
     * still editable, since a line already ordered is a separate commitment.
     */
    onPick(p) {
        const existing = this.items.find(
            (item) => item.product_code === p.code && this.canRemove(item)
        );

        if (existing) {
            existing.quantity = Number(existing.quantity || 0) + 1;

            return;
        }

        this.items.push(withKey({
            product_code: p.code,
            product_name: p.name,
            description: p.name,
            quantity: 1,
            case_units: p.case_units ?? null,
            product: { id: p.id, image_url: p.image_url ?? null },
        }));
    },

    addBlank() {
        this.items.push(withKey({ quantity: 1 }));
        this.focusLine(this.items.length - 1);
    },

    /**
     * Keep the text but drop the link to the stocked product, so the line
     * becomes something to source.
     */
    unlink(index) {
        const item = this.items[index];

        if (! item) {
            return;
        }

        item.product_code = '';
        item.product_name = '';
        item.product = null;
        item.unit = 'unit';
        item.case_units = null;
    },

    remove(index) {
        this.items.splice(index, 1);
    },

    /**
     * A line that exists and has moved past pending is not the edit screen's to
     * delete; the board takes it out of play by cancelling it.
     */
    canRemove(item) {
        return ! item.id || ! item.status || item.status === 'pending';
    },

    /** What a line's hidden unit input posts: Case only for a product with a case size. */
    unitValue(item) {
        return item.product_code && item.case_units ? item.unit : 'unit';
    },

    statusLabel(item) {
        return this.statusLabels[item.status] ?? item.status;
    },

    focusLine(index) {
        this.$nextTick(() => {
            this.$root?.querySelector(`[data-line-idx="${index}"]`)?.focus();
        });
    },

    /**
     * The endpoint requires at least one line. Say so in the page rather than in
     * a browser dialog, which would block the extension and read as a crash on a
     * tablet.
     */
    submitGuard(event) {
        if (this.items.length === 0) {
            event.preventDefault();
            this.tooFew = true;
        }
    },
});
