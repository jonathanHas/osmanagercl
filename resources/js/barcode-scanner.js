/**
 * Barcode Scanner module - wrapper around html5-qrcode
 *
 * Supports two modes:
 * 1. scanFile(file) — decode barcode from a captured photo (works over HTTP)
 * 2. startScanner() — live camera stream (requires HTTPS)
 *
 * Global: window.BarcodeScanner
 */
import { Html5Qrcode } from 'html5-qrcode';

let scanner = null;

const SUPPORTED_FORMATS = [
    0,  // QR_CODE
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
 * Scan a barcode from an image file (File or Blob).
 * Works over HTTP — no camera stream needed.
 * @param {File} file - Image file to scan
 * @returns {Promise<{text: string, format: string}>}
 */
export async function scanFile(file) {
    const tempScanner = new Html5Qrcode('barcode-scanner-temp');
    try {
        const result = await tempScanner.scanFileV2(file, /* showImage */ false);
        return {
            text: result.decodedText,
            format: result.result?.format?.formatName || 'unknown',
        };
    } finally {
        tempScanner.clear();
    }
}

/**
 * Start live camera scanner (requires HTTPS).
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

    await scanner.start(
        { facingMode: 'environment' },
        { fps: 10, qrbox: { width: 300, height: 150 }, formatsToSupport: SUPPORTED_FORMATS },
        (decodedText, decodedResult) => {
            if (onSuccess) onSuccess(decodedText, decodedResult);
        },
        (errorMessage) => {
            if (onError) onError(errorMessage);
        }
    );
}

/**
 * Stop the live camera scanner.
 * @returns {Promise<void>}
 */
export async function stopScanner() {
    if (scanner) {
        try { await scanner.stop(); } catch (e) {}
        scanner.clear();
        scanner = null;
    }
}

export function isRunning() {
    return scanner !== null && scanner.isScanning;
}

// Register on window for production compatibility
window.BarcodeScanner = { scanFile, startScanner, stopScanner, isRunning };
