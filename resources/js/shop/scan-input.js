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
 *
 * The camera has three states, not two. Detecting a code *pauses* the decoder
 * and freezes the picture on the frame it read; the page's `shop-scan-saved`
 * resumes it, which is instant because the stream never closed; only the
 * user's own camera button stops it and releases the device. Pausing rather
 * than stopping is what makes scanning a delivery feel continuous — a restart
 * re-enumerates cameras and re-initialises the decoder, one to three seconds
 * on a phone.
 */
export default () => ({
    value: '',
    keyboard: false,
    cameraOpen: false,
    cameraWanted: false,
    // The stream is open but the decoder is frozen on the frame it just read.
    paused: false,
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

        // The camera fires repeatedly while the barcode is in frame. The window
        // is measured from the last detection, and restartCameraIfWanted()
        // pushes lastAt forward, so after a reopen the same code is ignored for
        // about 3.5 s in total while a different code is accepted at once.
        const now = Date.now();
        if (text === this.lastCode && now - this.lastAt < 2000) {
            return;
        }
        this.lastCode = text;
        this.lastAt = now;

        // Pause, not stop: the page resumes us on `shop-scan-saved` and a
        // resume is instant, where a restart costs a second or three.
        this.pause();
        this.value = text;
        this.submit();
    },

    /**
     * Freeze the decoder on the frame just read. The camera block stays on
     * screen showing that frame, so `cameraOpen` stays true.
     */
    async pause() {
        try {
            const scanner = await import('../barcode-scanner');
            this.paused = scanner.pauseScanner();
        } catch (e) {
            this.paused = false;
        }
    },

    /**
     * Unfreeze. Returns false when the scanner was not in a resumable state —
     * a backgrounded tab on a phone can have had its stream dropped from under
     * us — so the caller can fall back to a full restart.
     */
    async resume() {
        try {
            const scanner = await import('../barcode-scanner');

            if (scanner.resumeScanner()) {
                this.paused = false;

                return true;
            }
        } catch (e) { /* fall through to the caller's restart */ }

        return false;
    },

    async stop() {
        try {
            const scanner = await import('../barcode-scanner');
            if (scanner.isRunning()) {
                await scanner.stopScanner();
            }
        } catch (e) { /* nothing to stop */ }

        this.cameraOpen = false;
        this.paused = false;
    },

    /**
     * The page has finished with the scan, so give the camera back.
     *
     * Normally that is a resume of a paused decoder, which is instant. The
     * restart path below is for a camera that was never started, that failed,
     * or whose stream a backgrounded tab dropped.
     *
     * The item just recorded is usually still under the lens, and the
     * de-duplication window in detected() is measured from the *original*
     * detection, which by then has expired. Push lastAt forward so the camera
     * does not read the same item straight back in. One mechanism, not a
     * second flag: the window stays wherever detected() left it for any other
     * code.
     */
    async restartCameraIfWanted() {
        if (! this.cameraWanted) {
            return;
        }

        this.lastAt = Date.now() + 1500;

        if (this.cameraOpen && this.paused) {
            if (await this.resume()) {
                return;
            }

            // Paused but not resumable: the stream is gone. Tear down and
            // start again so the user still ends up with a camera.
            await this.stop();
        }

        if (! this.cameraOpen) {
            this.toggleCamera();
        }
    },
});
