<?php

namespace App\Services;

use App\Models\Product;

class LabelService
{
    /**
     * Generate label HTML for a product.
     */
    public function generateLabelHtml(Product $product): string
    {
        $name = htmlspecialchars($product->NAME);
        $barcode = htmlspecialchars($product->CODE);
        $price = number_format($product->PRICESELL, 2);
        $priceWithVat = $product->getFormattedPriceWithVatAttribute();
        
        return <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Product Label - {$name}</title>
            <style>
                @page {
                    size: 58mm 40mm;
                    margin: 0;
                }
                body {
                    margin: 0;
                    padding: 4mm;
                    font-family: Arial, sans-serif;
                    width: 50mm;
                    height: 32mm;
                    display: flex;
                    flex-direction: column;
                    justify-content: space-between;
                }
                .product-name {
                    font-size: 11pt;
                    font-weight: bold;
                    line-height: 1.2;
                    height: 2.4em;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    display: -webkit-box;
                    -webkit-line-clamp: 2;
                    -webkit-box-orient: vertical;
                }
                .barcode {
                    text-align: center;
                    margin: 8px 0;
                }
                .barcode-number {
                    font-family: monospace;
                    font-size: 10pt;
                    letter-spacing: 2px;
                }
                .barcode-bars {
                    display: flex;
                    justify-content: center;
                    height: 20px;
                    margin: 4px 0;
                }
                .bar {
                    background: black;
                    margin: 0 1px;
                }
                .price-section {
                    display: flex;
                    justify-content: space-between;
                    align-items: baseline;
                }
                .price {
                    font-size: 16pt;
                    font-weight: bold;
                }
                .price-vat {
                    font-size: 9pt;
                    color: #666;
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
            
            <div class="barcode">
                <div class="barcode-bars">
                    {$this->generateBarcodePattern($barcode)}
                </div>
                <div class="barcode-number">{$barcode}</div>
            </div>
            
            <div class="price-section">
                <div class="price">€{$price}</div>
                <div class="price-vat">{$priceWithVat} incl. VAT</div>
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
     * Generate a simple barcode pattern representation.
     */
    private function generateBarcodePattern(string $barcode): string
    {
        $pattern = '';
        $barcodeArray = str_split($barcode);
        
        foreach ($barcodeArray as $digit) {
            $width = (intval($digit) % 3) + 1;
            $pattern .= '<div class="bar" style="width: ' . $width . 'px;"></div>';
            if (intval($digit) % 2 == 0) {
                $pattern .= '<div style="width: 2px;"></div>';
            }
        }
        
        return $pattern;
    }
}