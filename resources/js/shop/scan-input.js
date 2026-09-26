/**
 * The shop-floor scan input.
 *
 * Two kinds of device use this: the till PC with a USB keyboard-wedge scanner,
 * which types into whatever has focus and sends Enter, and a tablet or phone
 * where the camera is the scanner. The input therefore stays focused as much as
 * possible, and on touch devices it reports inputmode="none" so focusing it does
 * not throw up the on-screen keyboard.
 *
 * Emits `scan` (detail: { code }) on its root element. The page listens for it.
 * Listens on window for `shop-scan-done` / `shop-scan-error` so the page can
 * clear or set the error state and hand focus back, and for `shop-scan-saved`
 * so the camera comes back after a save if the user had it open.
 */
export default () => ({
    value: '',
    keyboard: false,
    cameraOpen: false,
    cameraWanted: false,
    error: null,
    cameraId: 'shop-camera-' + Math.random().toString(36).slice(2),
    lastCode: null,
    lastAt: 0,

    init() {
        this.focus();
    },

    get inputMode() {
        return this.keyboard || ! this.isTouch ? 'text' : 'none';
    },

    get isTouch() {
        return document.getElementById('shop-root')?.classList.contains('is-touch') ?? false;
    },

    focus() {
        requestAnimationFrame(() => this.$refs.input?.focus());
    },

    /**
     * GS1 symbology identifiers and group separators, as the office page does:
     * strip the identifier, normalise separators, and prefer an embedded GTIN-14.
     */
    parseBarcode(raw) {
        let text = raw.trim();
        if (text.startsWith(']C1')) text = text.substring(3);
        if (text.startsWith(']d2')) text = text.substring(3);
        if (text.startsWith(']e0')) text = text.substring(3);
        text = text.replace(/[\x1D\u001D]/g, '|');

        const gtin14 = text.match(/(?:^|\|)01(\d{14})/);

        return gtin14 ? gtin14[1] : text;
    },

    submit() {
        const code = this.parseBarcode(this.value);
        if (! code) {
            // Enter on an empty input. A page can treat this as "confirm what is
            // already on screen"; pages that do not listen simply ignore it.
            this.$dispatch('scan-empty');

            return;
        }

        this.error = null;
        this.value = '';
        this.$dispatch('scan', { code });
    },

    /**
     * Keep the scanner's target focused, but let a real interaction take focus:
     * a tap on the number pad must work, and the page hands focus back itself
     * once the action is done.
     */
    refocus(event) {
        const to = event.relatedTarget;
        if (to && ['input', 'textarea', 'select', 'button'].includes(to.tagName?.toLowerCase())) {
            return;
        }

        this.focus();
    },

    done() {
        this.error = null;
        this.focus();
    },

    fail(message) {
        this.error = message;
        this.focus();
    },

    toggleKeyboard() {
        this.keyboard = ! this.keyboard;
        this.focus();
    },

    async toggleCamera() {
        if (this.cameraOpen) {
            this.cameraWanted = false;
            await this.stop();

            return;
        }

        this.cameraOpen = true;
        this.cameraWanted = true;

        try {
            const scanner = await import('../barcode-scanner');
            await scanner.startScanner(this.cameraId, (text) => this.detected(text), () => {});
        } catch (e) {
            console.error('Shop scan: camera failed', e);
            this.cameraOpen = false;
            this.cameraWanted = false;
            this.fail(this.cameraFailureText(e));
        }
    },

    /**
     * Say what actually went wrong. The old fixed "needs HTTPS" text was wrong
     * everywhere except one case and hid a geometry fault for a whole cycle.
     * getUserMedia rejects with a DOMException carrying one of these names; the
     * library itself throws plain strings, which fall through to the last branch.
     */
    cameraFailureText(e) {
        if (! window.isSecureContext) {
            return 'Live scanning needs HTTPS';
        }

        switch (e?.name) {
            case 'NotAllowedError': return 'Camera permission was refused';
            case 'NotFoundError': return 'No camera found';
            case 'NotReadableError': return 'Camera is in use by another app';
        }

        return 'Camera could not start: ' + (e?.message ?? String(e));
    },

    detected(text) {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            osc.type = 'square';
            osc.frequency.value = 1000;
            osc.connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 0.1);
        } catch (e) { /* no audio on this device */ }

        if (navigator.vibrate) {
            navigator.vibrate(100);
        }

        // The camera fires repeatedly while the barcode is in frame.
        const now = Date.now();
        if (text === this.lastCode && now - this.lastAt < 2000) {
            return;
        }
        this.lastCode = text;
        this.lastAt = now;

        this.stop();
        this.value = text;
        this.submit();
    },

    async stop() {
        try {
            const scanner = await import('../barcode-scanner');
            if (scanner.isRunning()) {
                await scanner.stopScanner();
            }
        } catch (e) { /* nothing to stop */ }

        this.cameraOpen = false;
    },

    /**
     * Saving refocuses the input, which would otherwise leave the camera dark;
     * reopen it only if the user had it open.
     */
    restartCameraIfWanted() {
        if (this.cameraWanted && ! this.cameraOpen) {
            this.toggleCamera();
        }
    },
});
