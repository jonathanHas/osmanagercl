/**
 * ZPL Preview module - lazy-loaded wrapper around zpl-renderer-js
 *
 * Works in two modes:
 * 1. ES module import: import(...).then(m => m.renderToBase64(...))
 * 2. Global fallback: window.ZplPreview.renderToBase64(...)
 *
 * Vite production builds may strip ES exports, so we also register on window.
 */
import { zplToBase64Async } from 'zpl-renderer-js';

// Label dimensions: 76mm wide x 50mm high (landscape), 12 dpmm = ~300dpi
const DEFAULT_WIDTH_MM = 76;
const DEFAULT_HEIGHT_MM = 50;
const DEFAULT_DPMM = 12;

/**
 * Render ZPL code to a base64 PNG string.
 */
export async function renderToBase64(zpl, widthMm = DEFAULT_WIDTH_MM, heightMm = DEFAULT_HEIGHT_MM, dpmm = DEFAULT_DPMM) {
    return await zplToBase64Async(zpl, widthMm, heightMm, dpmm);
}

/**
 * Render ZPL code and set it as the src of an <img> element.
 */
export async function renderToImg(zpl, imgElement, widthMm = DEFAULT_WIDTH_MM, heightMm = DEFAULT_HEIGHT_MM, dpmm = DEFAULT_DPMM) {
    const base64 = await renderToBase64(zpl, widthMm, heightMm, dpmm);
    imgElement.src = 'data:image/png;base64,' + base64;
}

// Register on window as fallback for when Vite strips ES exports in production
window.ZplPreview = { renderToBase64, renderToImg };
