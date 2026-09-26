/**
 * New request form on the Shop customer-requests board.
 *
 * The form itself is a plain HTML POST to the existing endpoint, so the redirect
 * and flash contract is untouched. The only behaviour here is the product
 * typeahead for a pre-order, which is shared with the edit screen.
 *
 * `seed` is the line that came back from a failed submission, so the person does
 * not retype it.
 */
import mix from './mix.js';
import productImages from './product-images.js';
import productTypeahead from './product-typeahead.js';

export default (seed = null) => mix(productImages(), productTypeahead(), {
    // Sourcing when the bounced line had a description and no product behind it.
    kind: seed && ! seed.product_code && seed.description ? 'sourcing' : 'preorder',
    picked: seed?.product_code
        ? { id: null, code: seed.product_code, name: seed.product_name ?? seed.product_code, image_url: null }
        : null,
    description: seed?.description ?? '',

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

    onPick(p) {
        this.picked = { id: p.id, code: p.code, name: p.name, image_url: p.image_url ?? null };
    },

    unpick() {
        this.picked = null;
        this.query = '';
        this.results = [];
    },
});
