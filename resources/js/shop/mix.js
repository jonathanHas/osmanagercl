/**
 * Compose Alpine data objects from shared parts without losing getters.
 *
 * Object spread reads each property's value, so a getter on a part would run
 * at construction — before Alpine has attached $root — and be copied as a
 * plain value. Copying property descriptors keeps accessors as accessors, so
 * `get searchUrl()` is evaluated when Alpine reads it, on the live component.
 * Later parts override earlier ones, as with spread.
 */
export default function mix(...parts) {
    const target = {};

    for (const part of parts) {
        Object.defineProperties(target, Object.getOwnPropertyDescriptors(part));
    }

    return target;
}
