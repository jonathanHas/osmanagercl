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
import { quantityText } from './quantity.js';

export default (seed = null) => mix(productImages(), productTypeahead(), {
    // Sourcing when the bounced line had a description and no product behind it.
    kind: seed && ! seed.product_code && seed.description ? 'sourcing' : 'preorder',
    picked: seed?.product_code
        ? {
            id: null,
            code: seed.product_code,
            name: seed.product_name ?? seed.product_code,
            image_url: null,
            case_units: seed.product?.case_units ?? null,
            stock_units: null,
            price_with_vat: null,
        }
        : null,
    description: seed?.description ?? '',
    // 'unit' or 'case'; Case is only offered when the product has a case size.
    unit: seed?.unit === 'case' ? 'case' : 'unit',

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

    /** What the hidden items[0][unit] input posts. */
    get unitValue() {
        return this.kind === 'preorder' && this.picked?.case_units ? this.unit : 'unit';
    },

    /** "Case of 6 · 7 in stock · €6.25", each part only when known. */
    get pickedFacts() {
        const p = this.picked;
        if (! p) {
            return '';
        }

        const parts = [p.case_units ? `Case of ${p.case_units}` : 'Sold singly'];
        if (p.stock_units !== null && p.stock_units !== undefined) {
            parts.push(`${quantityText(p.stock_units)} in stock`);
        }
        if (p.price_with_vat !== null && p.price_with_vat !== undefined) {
            parts.push(`€${Number(p.price_with_vat).toFixed(2)}`);
        }

        return parts.join(' · ');
    },

    get descriptionValue() {
        return this.kind === 'preorder'
            ? (this.picked ? this.picked.name : '')
            : this.description;
    },

    onPick(p) {
        this.picked = {
            id: p.id,
            code: p.code,
            name: p.name,
            image_url: p.image_url ?? null,
            case_units: p.case_units ?? null,
            stock_units: p.stock_units ?? null,
            price_with_vat: p.price_with_vat ?? null,
        };
        this.unit = 'unit';
    },

    unpick() {
        this.picked = null;
        this.unit = 'unit';
        this.query = '';
        this.results = [];
        this.answered = null;
    },
});
