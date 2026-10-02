/**
 * Quantities on the delivery screens: shown without floating-point tails, and
 * typed with a rule a barcode can never satisfy.
 */

/**
 * A quantity or a STOCKCURRENT figure as a person would read it. The columns are
 * decimals, so a whole number arrives as 4 and must not read "4.0", and a weighed
 * item can arrive as 1.5200000000000011. Up to 3 dp, trailing zeros trimmed;
 * null or undefined reads as 0.
 */
export function quantityText(value) {
    const n = Number(value ?? 0);

    return Number.isInteger(n) ? String(n) : String(parseFloat(n.toFixed(3)));
}

/**
 * A typed quantity or weight, or null when the text is not one.
 *
 * At most 4 whole digits and 3 decimals, so a barcode typed or wedge-scanned
 * into the field (8–14 digits) can never be saved as a quantity. A single
 * decimal comma is accepted, as staff may type one. Zero is refused unless
 * `allowZero` (a correction to nothing is legitimate; an addition is not).
 */
export function parseQuantity(text, { allowZero = false } = {}) {
    const t = String(text ?? '').trim().replace(/^(\d*),(\d*)$/, '$1.$2');

    if (! /^\d{1,4}(\.\d{1,3})?$/.test(t)) {
        return null;
    }

    const n = Number(t);

    return n === 0 && ! allowZero ? null : n;
}
