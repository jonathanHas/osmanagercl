/**
 * Barcode Scanner module - wrapper around html5-qrcode
 *
 * Usage:
 * 1. ES module import: import(...).then(m => m.startScanner(...))
 * 2. Global fallback: window.BarcodeScanner.startScanner(...)
 */
import { Html5Qrcode } from 'html5-qrcode';

let scanner = null;

const SUPPORTED_FORMATS = [
    0,  // QR_CODE (for testing)
    1,  // AZTEC
    2,  // CODABAR
    3,  // CODE_39
    4,  // CODE_93
    5,  // CODE_128
    7,  // EAN_8
    8,  // EAN_13
    12, // UPC_A
    13, // UPC_E
];

/**
 * Start the barcode scanner.
 * @param {string} elementId - ID of the container div
 * @param {function} onSuccess - Callback with (decodedText, decodedResult)
 * @param {function} onError - Optional error callback
 * @returns {Promise<void>}
 */
export async function startScanner(elementId, onSuccess, onError = null) {
    if (scanner) {
        await stopScanner();
    }

    scanner = new Html5Qrcode(elementId);

    const config = {
        fps: 10,
        qrbox: { width: 300, height: 150 },
        formatsToSupport: SUPPORTED_FORMATS,
    };

    await scanner.start(
        { facingMode: 'environment' },
        config,
        (decodedText, decodedResult) => {
            if (onSuccess) onSuccess(decodedText, decodedResult);
        },
        (errorMessage) => {
            if (onError) onError(errorMessage);
        }
    );
}

/**
 * Stop the barcode scanner.
 * @returns {Promise<void>}
 */
export async function stopScanner() {
    if (scanner) {
        try {
            await scanner.stop();
        } catch (e) {
            // Scanner may already be stopped
        }
        scanner.clear();
        scanner = null;
    }
}

/**
 * Check if scanner is currently running.
 * @returns {boolean}
 */
export function isRunning() {
    return scanner !== null && scanner.isScanning;
}

// Register on window as fallback for when Vite strips ES exports in production
window.BarcodeScanner = { startScanner, stopScanner, isRunning };
