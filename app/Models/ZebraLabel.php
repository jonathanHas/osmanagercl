<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZebraLabel extends Model
{
    protected $fillable = [
        'name',
        'product_code',
        'product_id',
        'description',
        'zpl_content',
        'original_filename',
        'label_width_mm',
        'label_height_mm',
        'default_copies',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'label_width_mm' => 'float',
        'label_height_mm' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_code', 'CODE');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Extract barcode from ZPL content.
     * Looks for ^FD field after ^BC (Code 128) barcode command.
     */
    public static function extractBarcodeFromZpl(string $zpl): ?string
    {
        // Match barcode command (^BC Code128, ^BE EAN-13, ^B8 EAN-8, etc.)
        // followed by ^FD field data — allow whitespace/newlines and intervening commands between them
        if (preg_match('/\^B[CE8][^\^]*.*?\^FD([^\^]+)\^FS/si', $zpl, $matches)) {
            $barcode = trim($matches[1]);
            // Strip ZPL Code 128 subset selectors: >; (subset C), >: (subset B), etc.
            // These are single >X pairs, not part of the barcode data itself
            $barcode = preg_replace('/^(>[;:0-9A-Z])+/', '', $barcode);

            return $barcode ?: null;
        }

        return null;
    }

    /**
     * Extract label dimensions from ZPL.
     * Uses ^PW (print width) and ^LL (label length) in dots,
     * converted to mm using DPI from ^JM (A=203, B=300, default 203).
     */
    public static function extractDimensions(string $zpl): ?array
    {
        $pw = null;
        $ll = null;

        if (preg_match('/\^PW(\d+)/i', $zpl, $m)) {
            $pw = (int) $m[1];
        }
        if (preg_match('/\^LL(\d+)/i', $zpl, $m)) {
            $ll = (int) $m[1];
        }

        if (! $pw && ! $ll) {
            return null;
        }

        // Detect DPI: ^JMA = 203, ^JMB = 300
        $dpi = 203;
        if (preg_match('/\^JM([AB])/i', $zpl, $m)) {
            $dpi = strtoupper($m[1]) === 'B' ? 300 : 203;
        }

        return [
            'width_mm' => $pw ? round($pw / $dpi * 25.4, 1) : null,
            'height_mm' => $ll ? round($ll / $dpi * 25.4, 1) : null,
        ];
    }

    /**
     * Find the price field in extracted text fields.
     * Price fields contain \15 (ZPL hex for €) followed by a number.
     * Returns [index, numeric_value] or null.
     */
    public static function findPriceField(array $fields): ?array
    {
        foreach ($fields as $i => $field) {
            if (preg_match('/\\\\15(\d+\.?\d*)/', $field, $m)) {
                return [$i, (float) $m[1]];
            }
        }

        return null;
    }

    /**
     * Find the country field in extracted text fields.
     * Matches against a list of known country names.
     * Returns [index, country_name] or null.
     */
    public static function findCountryField(array $fields, array $countryNames): ?array
    {
        foreach ($fields as $i => $field) {
            $trimmed = trim($field);
            if ($trimmed && in_array($trimmed, $countryNames, true)) {
                return [$i, $trimmed];
            }
        }

        return null;
    }

    /**
     * Extract print quantity from ZPL ^PQ command.
     * Format: ^PQq,p,r,o where q=quantity
     */
    public static function extractPrintQuantity(string $zpl): int
    {
        if (preg_match('/\^PQ(\d+)/i', $zpl, $matches)) {
            return (int) $matches[1];
        }

        return 1;
    }

    /**
     * Return ZPL with ^PQ set to the desired quantity.
     * Replaces existing ^PQ or inserts one before ^XZ.
     */
    public static function setZplQuantity(string $zpl, int $copies): string
    {
        if (preg_match('/\^PQ\d+[^\\^]*/i', $zpl)) {
            // Replace existing ^PQ command
            return preg_replace('/\^PQ\d+[^\\^]*/i', "^PQ{$copies},0,1,Y", $zpl);
        }

        // No ^PQ found — insert before final ^XZ
        return preg_replace('/\^XZ\s*$/', "^PQ{$copies},0,1,Y^XZ", $zpl);
    }

    /**
     * Extract editable text fields from ZPL.
     * Returns indexed array of ^FD values that follow ^A (font) commands.
     * Skips barcode data (^BC), graphic references (^XG), and cleanup commands (^ID).
     */
    public static function extractTextFields(string $zpl): array
    {
        $fields = [];

        // Find the main label block — the ^XA...^XZ that contains ^FT positioning
        if (! preg_match('/(\^XA(?:(?!\^XA).)*\^FT(?:(?!\^XA).)*\^XZ)/s', $zpl, $blockMatch)) {
            return $fields;
        }
        $block = $blockMatch[1];

        // Split into segments by ^FT or ^FO (field positioning commands)
        // Each segment represents one positioned element
        $segments = preg_split('/(?=\^F[TO])/', $block);

        foreach ($segments as $segment) {
            // Skip graphic references (^XG) and barcode data (^BC)
            if (preg_match('/\^XG/', $segment) || preg_match('/\^BC/', $segment)) {
                continue;
            }

            // Only match segments with a font command (^A) followed by field data (^FD)
            // This ensures we get text fields, not other ^FD uses
            if (preg_match('/\^A\d*\w/', $segment) && preg_match('/\^FD([^\^]*)\^FS/', $segment, $fdMatch)) {
                // Strip ^FH\ hex escape prefix indicator if present
                $value = $fdMatch[1];
                $fields[] = $value;
            }
        }

        return $fields;
    }

    /**
     * Replace text fields in ZPL with new values.
     * Takes indexed array matching extractTextFields() output order.
     */
    public static function replaceTextFields(string $zpl, array $replacements): string
    {
        // Find the main label block
        if (! preg_match('/(\^XA(?:(?!\^XA).)*\^FT(?:(?!\^XA).)*\^XZ)/s', $zpl, $blockMatch)) {
            return $zpl;
        }
        $block = $blockMatch[1];
        $modifiedBlock = $block;

        // Extract fields in order, then replace by index
        $segments = preg_split('/(?=\^F[TO])/', $block);
        $fieldIndex = 0;

        foreach ($segments as $segment) {
            if (preg_match('/\^XG/', $segment) || preg_match('/\^BC/', $segment)) {
                continue;
            }

            if (preg_match('/\^A\d*\w/', $segment) && preg_match('/\^FD([^\^]*)\^FS/', $segment, $fdMatch)) {
                if (array_key_exists($fieldIndex, $replacements)) {
                    $oldFd = '^FD' . $fdMatch[1] . '^FS';
                    $newFd = '^FD' . $replacements[$fieldIndex] . '^FS';
                    // Replace only the first occurrence in the modified block
                    $pos = strpos($modifiedBlock, $oldFd);
                    if ($pos !== false) {
                        $modifiedBlock = substr_replace($modifiedBlock, $newFd, $pos, strlen($oldFd));
                    }
                }
                $fieldIndex++;
            }
        }

        return str_replace($block, $modifiedBlock, $zpl);
    }
}
