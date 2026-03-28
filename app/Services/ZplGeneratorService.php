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
     * Simulates ZPL word-wrapping with a safety margin for font width variation.
     */
    public function estimateLines(string $text, int $fontSize, int $fieldWidth, int $maxLines): int
    {
        // Average character width ~50% of font size, with 10% safety margin for variation
        $charWidth = $fontSize * 0.5;
        $charsPerLine = max(1, (int) floor(($fieldWidth * 0.9) / $charWidth));

        // Simulate word wrapping (ZPL ^FB wraps at word boundaries)
        $words = preg_split('/\s+/', trim($text));
        if (empty($words) || ($words === [''])) {
            return 1;
        }

        $lines = 1;
        $currentLineLength = 0;

        foreach ($words as $word) {
            $wordLen = mb_strlen($word);
            if ($currentLineLength === 0) {
                $currentLineLength = $wordLen;
            } elseif ($currentLineLength + 1 + $wordLen <= $charsPerLine) {
                $currentLineLength += 1 + $wordLen;
            } else {
                $lines++;
                $currentLineLength = $wordLen;
            }
        }

        return min($lines, $maxLines);
    }

    public function calculateContentHeight(array $data, array $dims, float $scale): int
    {
        $bodyFont = (int) round($dims['bodyFont'] * $scale);
        $smallFont = (int) round($dims['smallFont'] * $scale);
        $gap = $dims['gap'];
        $fieldWidth = $dims['width'] - ($dims['margin'] * 2);

        $y = $dims['startY'];

        // Name (uses bodyFont, single line to save space)
        $y += $bodyFont + $gap;

        // Ingredients (uncapped — auto-fit scaling handles overflow)
        if (! empty($data['ingredients'])) {
            $lines = $this->estimateLines('Ingredients: '.$data['ingredients'], $bodyFont, $fieldWidth, 999);
            $y += $bodyFont * $lines + $gap;
        }

        // Nutrition (uncapped)
        if (! empty($data['nutrition_inline'])) {
            $lines = $this->estimateLines($data['nutrition_inline'], $smallFont, $fieldWidth, 999);
            $y += $smallFont * $lines + $gap;
        }

        // Storage (uncapped)
        if (! empty($data['storage'])) {
            $lines = $this->estimateLines($data['storage'], $smallFont, $fieldWidth, 999);
            $y += $smallFont * $lines + $gap;
        }

        // Origin
        if (! empty($data['origin'])) {
            $y += $smallFont + $gap;
        }

        // Address (uncapped)
        if (! empty($data['address'])) {
            $lines = $this->estimateLines($data['address'], $smallFont, $fieldWidth, 999);
            $y += $smallFont * $lines + $gap;
        }

        return $y;
    }

    public function buildZpl(array $data, array $dims, float $scale): string
    {
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

        // Product Name (centered, bodyFont, single line to maximise content space)
        $zpl .= "^FO{$margin},{$y}^A0N,{$bodyFontSize},{$bodyFontSize}^FB{$fieldWidth},1,0,C^FD".($data['product_name'] ?? '').'^FS';
        $y += $bodyFontSize + $gap;

        // Ingredients (uncapped lines — auto-fit scaling handles overflow)
        if (! empty($data['ingredients'])) {
            $lines = $this->estimateLines('Ingredients: '.$data['ingredients'], $bodyFontSize, $fieldWidth, 999);
            $zpl .= "^FO{$margin},{$y}^A0N,{$bodyFontSize},{$bodyFontSize}^FB{$fieldWidth},{$lines},0,L^FDIngredients: ".$data['ingredients'].'^FS';
            $y += $bodyFontSize * $lines + $gap;
        }

        // Nutrition (uncapped — ^FB maxLines matches estimated lines)
        if (! empty($data['nutrition_inline'])) {
            $lines = $this->estimateLines($data['nutrition_inline'], $smallFontSize, $fieldWidth, 999);
            $zpl .= "^FO{$margin},{$y}^A0N,{$smallFontSize},{$smallFontSize}^FB{$fieldWidth},{$lines},0,L^FD".$data['nutrition_inline'].'^FS';
            $y += $smallFontSize * $lines + $gap;
        }

        // Storage (uncapped)
        if (! empty($data['storage'])) {
            $lines = $this->estimateLines($data['storage'], $smallFontSize, $fieldWidth, 999);
            $zpl .= "^FO{$margin},{$y}^A0N,{$smallFontSize},{$smallFontSize}^FB{$fieldWidth},{$lines},0,L^FD".$data['storage'].'^FS';
            $y += $smallFontSize * $lines + $gap;
        }

        // Origin
        if (! empty($data['origin'])) {
            $zpl .= "^FO{$margin},{$y}^A0N,{$smallFontSize},{$smallFontSize}^FDOrigin: ".$data['origin'].'^FS';
            $y += $smallFontSize + $gap;
        }

        // Address (uncapped)
        if (! empty($data['address'])) {
            $lines = $this->estimateLines($data['address'], $smallFontSize, $fieldWidth, 999);
            $zpl .= "^FO{$margin},{$y}^A0N,{$smallFontSize},{$smallFontSize}^FB{$fieldWidth},{$lines},0,L^FD".$data['address'].'^FS';
            $y += $smallFontSize * $lines + $gap;
        }

        $zpl .= '^XZ';

        return $zpl;
    }
}
