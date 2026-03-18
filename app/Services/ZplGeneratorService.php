<?php

namespace App\Services;

class ZplGeneratorService
{
    /**
     * Generate ZPL code from structured label data.
     */
    public function generateZpl(array $data, string $labelSize = 'large', float $fontScale = 1.0): string
    {
        [$zpl] = $this->generateZplWithScale($data, $labelSize, $fontScale);

        return $zpl;
    }

    /**
     * Generate ZPL and return [zpl_string, effective_scale].
     */
    public function generateZplWithScale(array $data, string $labelSize = 'large', float $fontScale = 1.0): array
    {
        $dims = $this->getLabelDimensions($labelSize);

        // Auto-fit: if content overflows at requested scale, reduce until it fits
        $scale = $fontScale;
        while ($scale >= 0.5) {
            $totalHeight = $this->calculateContentHeight($data, $dims, $scale);
            if ($totalHeight <= $dims['height']) {
                break;
            }
            $scale -= 0.05;
        }
        $scale = round($scale, 2);

        return [$this->buildZpl($data, $dims, $scale), $scale];
    }

    /**
     * Get label dimensions from config.
     */
    public function getLabelDimensions(string $labelSize): array
    {
        $sizes = config('label-sizes');

        return $sizes[$labelSize] ?? $sizes['large'];
    }

    /**
     * Get all available label sizes.
     */
    public function getAvailableSizes(): array
    {
        return config('label-sizes');
    }

    /**
     * Get valid size keys for validation rules.
     */
    public function getValidSizeKeys(): string
    {
        return implode(',', array_keys(config('label-sizes')));
    }

    /**
     * Estimate how many ^FB lines a text string needs at a given font/field width.
     */
    public function estimateLines(string $text, int $fontSize, int $fieldWidth, int $maxLines): int
    {
        // Average character width is ~50% of font size for Zebra default scalable font (^A0)
        $charsPerLine = max(1, (int) floor($fieldWidth / ($fontSize * 0.5)));
        $needed = (int) ceil(mb_strlen($text) / $charsPerLine);

        return min($needed, $maxLines);
    }

    public function calculateContentHeight(array $data, array $dims, float $scale): int
    {
        $nameFont = (int) round($dims['nameFont'] * $scale);
        $bodyFont = (int) round($dims['bodyFont'] * $scale);
        $smallFont = (int) round($dims['smallFont'] * $scale);
        $gap = $dims['gap'];
        $fieldWidth = $dims['width'] - ($dims['margin'] * 2);

        $y = $dims['startY'];

        // Name
        $nameLines = $this->estimateLines($data['product_name'] ?? '', $nameFont, $fieldWidth, 2);
        $y += $nameFont * $nameLines + $gap;

        // Ingredients (uncapped — auto-fit scaling handles overflow)
        if (! empty($data['ingredients'])) {
            $lines = $this->estimateLines('Ingredients: '.$data['ingredients'], $bodyFont, $fieldWidth, 999);
            $y += $bodyFont * $lines + $gap;
        }

        // Nutrition
        if (! empty($data['nutrition_inline'])) {
            $lines = $this->estimateLines($data['nutrition_inline'], $smallFont, $fieldWidth, 3);
            $y += $smallFont * $lines + $gap;
        }

        // Storage
        if (! empty($data['storage'])) {
            $lines = $this->estimateLines($data['storage'], $smallFont, $fieldWidth, 2);
            $y += $smallFont * $lines + $gap;
        }

        // Origin
        if (! empty($data['origin'])) {
            $y += $smallFont + $gap;
        }

        // Address
        if (! empty($data['address'])) {
            $lines = $this->estimateLines($data['address'], $smallFont, $fieldWidth, 2);
            $y += $smallFont * $lines + $gap;
        }

        return $y;
    }

    public function buildZpl(array $data, array $dims, float $scale): string
    {
        $nameFontSize = (int) round($dims['nameFont'] * $scale);
        $bodyFontSize = (int) round($dims['bodyFont'] * $scale);
        $smallFontSize = (int) round($dims['smallFont'] * $scale);
        $gap = $dims['gap'];

        $width = $dims['width'];
        $height = $dims['height'];
        $margin = $dims['margin'];
        $fieldWidth = $width - ($margin * 2);
        $y = $dims['startY'];

        $zpl = '^XA';
        $zpl .= "^PW{$width}^LL{$height}";
        $zpl .= '^CI28';

        // Product Name (centered)
        $nameLines = $this->estimateLines($data['product_name'] ?? '', $nameFontSize, $fieldWidth, 2);
        $zpl .= "^FO{$margin},{$y}^A0N,{$nameFontSize},{$nameFontSize}^FB{$fieldWidth},2,0,C^FD".($data['product_name'] ?? '').'^FS';
        $y += $nameFontSize * $nameLines + $gap;

        // Ingredients (uncapped lines — auto-fit scaling handles overflow)
        if (! empty($data['ingredients'])) {
            $lines = $this->estimateLines('Ingredients: '.$data['ingredients'], $bodyFontSize, $fieldWidth, 999);
            $zpl .= "^FO{$margin},{$y}^A0N,{$bodyFontSize},{$bodyFontSize}^FB{$fieldWidth},{$lines},0,L^FDIngredients: ".$data['ingredients'].'^FS';
            $y += $bodyFontSize * $lines + $gap;
        }

        // Nutrition
        if (! empty($data['nutrition_inline'])) {
            $lines = $this->estimateLines($data['nutrition_inline'], $smallFontSize, $fieldWidth, 3);
            $zpl .= "^FO{$margin},{$y}^A0N,{$smallFontSize},{$smallFontSize}^FB{$fieldWidth},3,0,L^FD".$data['nutrition_inline'].'^FS';
            $y += $smallFontSize * $lines + $gap;
        }

        // Storage
        if (! empty($data['storage'])) {
            $lines = $this->estimateLines($data['storage'], $smallFontSize, $fieldWidth, 2);
            $zpl .= "^FO{$margin},{$y}^A0N,{$smallFontSize},{$smallFontSize}^FB{$fieldWidth},2,0,L^FD".$data['storage'].'^FS';
            $y += $smallFontSize * $lines + $gap;
        }

        // Origin
        if (! empty($data['origin'])) {
            $zpl .= "^FO{$margin},{$y}^A0N,{$smallFontSize},{$smallFontSize}^FDOrigin: ".$data['origin'].'^FS';
            $y += $smallFontSize + $gap;
        }

        // Address
        if (! empty($data['address'])) {
            $lines = $this->estimateLines($data['address'], $smallFontSize, $fieldWidth, 2);
            $zpl .= "^FO{$margin},{$y}^A0N,{$smallFontSize},{$smallFontSize}^FB{$fieldWidth},2,0,L^FD".$data['address'].'^FS';
            $y += $smallFontSize * $lines + $gap;
        }

        $zpl .= '^XZ';

        return $zpl;
    }
}
