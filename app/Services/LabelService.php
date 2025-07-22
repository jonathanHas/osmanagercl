<?php

namespace App\Services;

use App\Models\LabelTemplate;
use App\Models\Product;
use Exception;
use Picqer\Barcode\BarcodeGeneratorSVG;

class LabelService
{
    /**
     * Generate label HTML for a product using a template.
     */
    public function generateLabelHtml(Product $product, ?LabelTemplate $template = null): string
    {
        $template = $template ?? LabelTemplate::getDefault();
        if (! $template) {
            throw new Exception('No label template available');
        }

        $name = htmlspecialchars($product->NAME);
        $barcode = htmlspecialchars($product->CODE);
        $priceWithVat = $product->getFormattedPriceWithVatAttribute();

        $css = $template->css_dimensions;
        $barcodeImage = $this->generateBarcode($barcode);

        return <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Product Label - {$name}</title>
            <style>
                @page {
                    size: {$css['width']} {$css['height']};
                    margin: 0;
                }
                body {
                    margin: 0;
                    padding: {$css['margin']};
                    font-family: Arial, sans-serif;
                    width: calc({$css['width']} - 2 * {$css['margin']});
                    height: calc({$css['height']} - 2 * {$css['margin']});
                    display: flex;
                    flex-direction: column;
                }
                .product-name {
                    font-size: {$css['font_size_name']};
                    font-weight: 600;
                    line-height: 1.3;
                    flex-grow: 1;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    display: -webkit-box;
                    -webkit-line-clamp: 4;
                    -webkit-box-orient: vertical;
                    word-break: break-word;
                    hyphens: auto;
                    margin: 0 0 3px 0;
                }
                .price-section {
                    text-align: center;
                    flex-shrink: 0;
                    margin: 2px 0;
                }
                .price {
                    font-size: {$css['font_size_price']};
                    font-weight: bold;
                    color: #000;
                    margin: 0;
                }
                .barcode {
                    text-align: center;
                    flex-shrink: 0;
                    margin-top: auto;
                }
                .barcode-image {
                    height: calc({$css['barcode_height']} * 0.6);
                    margin: 1px 0;
                }
                .barcode-image svg {
                    width: auto;
                    max-width: 90%;
                    height: 100%;
                }
                .barcode-number {
                    font-family: monospace;
                    font-size: calc({$css['font_size_barcode']} * 0.7);
                    letter-spacing: 0.5px;
                    color: #555;
                    margin: 1px 0;
                }
                @media print {
                    body {
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                    }
                }
            </style>
        </head>
        <body>
            <div class="product-name">{$name}</div>
            
            <div class="price-section">
                <div class="price">{$priceWithVat}</div>
            </div>
            
            <div class="barcode">
                <div class="barcode-image">
                    {$barcodeImage}
                </div>
                <div class="barcode-number">{$barcode}</div>
            </div>
            
            <script>
                window.onload = function() {
                    window.print();
                }
            </script>
        </body>
        </html>
        HTML;
    }

    /**
     * Generate proper barcode using barcode library.
     */
    public function generateBarcode(string $barcode): string
    {
        try {
            $generator = new BarcodeGeneratorSVG;
            $type = $this->detectBarcodeType($barcode);

            return $generator->getBarcode($barcode, $type, 1.5, 35);
        } catch (Exception $e) {
            // Fallback to simple pattern if barcode generation fails
            return $this->generateFallbackPattern($barcode);
        }
    }

    /**
     * Detect barcode type based on format.
     */
    private function detectBarcodeType(string $barcode): string
    {
        if (preg_match('/^\d{13}$/', $barcode)) {
            return BarcodeGeneratorSVG::TYPE_EAN_13;
        } elseif (preg_match('/^\d{8}$/', $barcode)) {
            return BarcodeGeneratorSVG::TYPE_EAN_8;
        } else {
            return BarcodeGeneratorSVG::TYPE_CODE_128;
        }
    }

    /**
     * Generate fallback pattern for invalid barcodes.
     */
    private function generateFallbackPattern(string $barcode): string
    {
        $pattern = '<div style="display: flex; justify-content: center; height: 100%;">';
        $barcodeArray = str_split($barcode);

        foreach ($barcodeArray as $digit) {
            $width = (intval($digit) % 3) + 1;
            $pattern .= '<div style="background: black; width: '.$width.'px; height: 100%; margin: 0 0.5px;"></div>';
            if (intval($digit) % 2 == 0) {
                $pattern .= '<div style="width: 1px; height: 100%;"></div>';
            }
        }

        return $pattern.'</div>';
    }
}
