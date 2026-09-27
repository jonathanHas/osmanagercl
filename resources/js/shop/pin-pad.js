/**
 * The Shop PIN pad (screen 17).
 *
 * The PIN is never held anywhere but this component's `digits`, and it reaches
 * the server once, in a POST body — never a URL, never a fetch the console
 * would log. The pad submits itself the moment the last dot fills, so nobody
 * has to find a button on a greasy touchscreen.
 *
 * The till PC has no touchscreen but does have a keyboard (and a barcode
 * wedge, which types digits), so the number row and Backspace work too.
 */
export default () => ({
    digits: '',
    length: 4,
    message: '',
    busy: false,

    init() {
        this.length = Number(this.$root.dataset.length) || 4;
        // A wrong PIN comes back as a flash on a fresh page load, not as JSON.
        this.message = this.$root.dataset.error || '';
    },

    press(digit) {
        if (this.busy || this.digits.length >= this.length) {
            return;
        }

        this.message = '';
        this.digits += digit;

        if (this.digits.length === this.length) {
            this.submit();
        }
    },

    backspace() {
        if (this.busy) {
            return;
        }

        this.message = '';
        this.digits = this.digits.slice(0, -1);
    },

    submit() {
        if (this.busy || this.digits.length < this.length) {
            return;
        }

        this.busy = true;
        this.$refs.pin.value = this.digits;
        this.$refs.form.submit();
    },

    /**
     * Bound on the window in the view's x-data root, so the keyboard works
     * without the person first tapping a key.
     */
    onKey(event) {
        if (event.key >= '0' && event.key <= '9' && event.key.length === 1) {
            event.preventDefault();
            this.press(event.key);
        } else if (event.key === 'Backspace') {
            event.preventDefault();
            this.backspace();
        } else if (event.key === 'Enter') {
            event.preventDefault();
            this.submit();
        }
    },
});
