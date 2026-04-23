<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Parses a Dynamis fruit-and-veg delivery XLSX (e.g. Historique(NN).xlsx)
 * into the same item-shape that DeliveryParsingService produces for PDFs,
 * so DeliveryService::importFromPdfData() can consume the result unchanged.
 *
 * Column layout (single sheet, row 1 = header):
 *   A = BL (delivery/bill-of-lading ref)
 *   B = Code Article (supplier SKU — e.g. POM0481)
 *   C = Code douanier
 *   D = Provenance
 *   E = Article (English description, BIO marker)
 *   F = Colis (box count)
 *   G = Pièces (piece count, for C/P unit rows)
 *   H = Poids Brut (gross weight kg)
 *   I = Poids Net (net weight kg, used for K-unit rows)
 *   J = Prix (unit cost, EUR)
 *   K = Unité (K = kilo, C = count, P = pot)
 *   L = Total (line cost, EUR)
 *
 * The row coded DIV0010 / "MISCELLANEOUS TRANSPORT" is a freight fee, not a
 * product line, and is routed into data.costs.items so freight_charge gets
 * populated on the delivery.
 */
class DynamisXlsxParserService
{
    private const HEADER_ROW = 1;

    private const DATA_START_ROW = 2;

    public function parse(string $filePath): array
    {
        if (! file_exists($filePath)) {
            return $this->failure("File not found: {$filePath}");
        }

        try {
            $spreadsheet = IOFactory::load($filePath);
        } catch (\Throwable $e) {
            return $this->failure('Failed to open XLSX: '.$e->getMessage());
        }

        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();

        $items = [];
        $costs = [];
        $warnings = [];
        $orderNumber = null;
        $productsTotal = 0.0;
        $costsTotal = 0.0;

        for ($row = self::DATA_START_ROW; $row <= $highestRow; $row++) {
            $cells = $this->readRow($sheet, $row);

            if ($this->isEmptyRow($cells)) {
                continue;
            }

            $code = $this->str($cells['B']);
            $description = $this->str($cells['E']);

            if ($code === '' && $description === '') {
                continue;
            }

            if ($orderNumber === null && $cells['A'] !== null && $cells['A'] !== '') {
                $orderNumber = (string) $cells['A'];
            }

            if ($this->isTransportRow($code, $description)) {
                $lineTotal = $this->num($cells['L']);
                $costs[] = [
                    'code' => $code,
                    'description' => $description,
                    'total' => round($lineTotal, 2),
                ];
                $costsTotal += $lineTotal;

                continue;
            }

            $item = $this->mapRowToItem($cells, $row);

            if ($item === null) {
                $warnings[] = "Row {$row}: could not interpret unit '".$this->str($cells['K'])."' for {$code}";

                continue;
            }

            $items[] = $item;
            $productsTotal += $item['line_total'];
        }

        $grand = $productsTotal + $costsTotal;

        return [
            'success' => ! empty($items),
            'data' => [
                'supplier' => 'Dynamis',
                'items' => $items,
                'costs' => [
                    'items' => $costs,
                    'total' => round($costsTotal, 2),
                ],
                'totals' => [
                    'line_count' => count($items),
                    'products_total' => round($productsTotal, 2),
                    'costs_total' => round($costsTotal, 2),
                    'total_value' => round($grand, 2),
                    'products_stated' => null,
                    'grand_stated' => null,
                    'products_calculated' => round($productsTotal, 2),
                    'costs_calculated' => round($costsTotal, 2),
                    'grand_calculated' => round($grand, 2),
                    'totals_match' => true,
                    'discrepancy' => 0.0,
                ],
                'metadata' => [
                    'supplier' => 'dynamis',
                    'order_number' => $orderNumber,
                ],
            ],
            'warnings' => $warnings,
            'errors' => [],
            'confidence' => 100.0,
            'metadata' => [
                'supplier_detected' => 'Dynamis',
                'order_number' => $orderNumber,
                'unmatched_lines' => [],
            ],
        ];
    }

    /**
     * Convert parsed data into rows compatible with DeliveryService::importFromPdfData().
     * Mirrors DeliveryParsingService::convertToDeliveryItems() shape.
     */
    public function convertToDeliveryItems(array $parsed): array
    {
        if (empty($parsed['success']) || empty($parsed['data']['items'])) {
            return [];
        }

        $orderNumber = $parsed['data']['metadata']['order_number'] ?? null;
        $rows = [];

        foreach ($parsed['data']['items'] as $item) {
            $rows[] = [
                'Code' => $item['code'],
                'Product' => $item['product'],
                'Ordered' => (string) $item['total_ordered_units'],
                'Qty' => (string) $item['total_delivered_units'],
                'Case_Size' => $item['case_size'],
                'Total_Ordered_Units' => $item['total_ordered_units'],
                'Total_Delivered_Units' => $item['total_delivered_units'],
                'RSP' => 0,
                'Price' => $item['unit_cost'],
                'Unit_Cost' => $item['unit_cost'],
                'Tax' => 0,
                'Value' => $item['line_total'],
                'Price_Valid' => '✓',
                'is_weight_based' => $item['is_weight_based'],
                'weight_per_unit' => $item['weight_per_unit'],
                'weight_unit' => $item['weight_unit'],
                'total_weight' => $item['total_weight'],
                'order_number' => $orderNumber,
            ];
        }

        return $rows;
    }

    private function mapRowToItem(array $cells, int $rowNum): ?array
    {
        $unit = strtoupper($this->str($cells['K']));
        $code = $this->str($cells['B']);
        $description = $this->str($cells['E']);
        $unitCost = $this->num($cells['J']);
        $lineTotal = $this->num($cells['L']);

        $base = [
            'row' => $rowNum,
            'code' => $code,
            'product' => $description,
            'origin' => $this->str($cells['D']) ?: null,
            'unit_cost' => round($unitCost, 4),
            'line_total' => round($lineTotal, 2),
            'case_size' => 1,
            'is_weight_based' => false,
            'weight_per_unit' => null,
            'weight_unit' => null,
            'total_weight' => null,
        ];

        if ($unit === 'K') {
            $boxes = max(1, (int) round($this->num($cells['F'])));
            $netWeight = $this->num($cells['I']);

            $base['is_weight_based'] = true;
            $base['weight_unit'] = 'kg';
            $base['total_weight'] = round($netWeight, 3);
            $base['total_ordered_units'] = $boxes;
            $base['total_delivered_units'] = $boxes;
            $base['weight_per_unit'] = $boxes > 0 ? round($netWeight / $boxes, 3) : null;

            return $base;
        }

        if ($unit === 'C' || $unit === 'P') {
            $pieces = (int) round($this->num($cells['G']));
            if ($pieces <= 0) {
                $pieces = max(1, (int) round($this->num($cells['F'])));
            }

            $base['total_ordered_units'] = $pieces;
            $base['total_delivered_units'] = $pieces;

            return $base;
        }

        return null;
    }

    private function isTransportRow(string $code, string $description): bool
    {
        if ($code !== '' && strcasecmp($code, 'DIV0010') === 0) {
            return true;
        }

        return (bool) preg_match('/MISCELLANEOUS\s+TRANSPORT/i', $description);
    }

    private function readRow(Worksheet $sheet, int $row): array
    {
        $cells = [];
        foreach (range('A', 'L') as $col) {
            $cells[$col] = $sheet->getCell($col.$row)->getValue();
        }

        return $cells;
    }

    private function isEmptyRow(array $cells): bool
    {
        foreach ($cells as $v) {
            if ($v !== null && $v !== '') {
                return false;
            }
        }

        return true;
    }

    private function str($v): string
    {
        if ($v === null) {
            return '';
        }

        return trim((string) $v);
    }

    /**
     * Parse a numeric cell. Handles native floats, strings, and European
     * "1 234,56" style numbers just in case.
     */
    private function num($v): float
    {
        if ($v === null || $v === '') {
            return 0.0;
        }

        if (is_numeric($v)) {
            return (float) $v;
        }

        $s = preg_replace('/[^0-9,.\-]/', '', (string) $v);
        if ($s === null || $s === '') {
            return 0.0;
        }

        if (substr_count($s, ',') === 1 && substr_count($s, '.') === 0) {
            $s = str_replace(',', '.', $s);
        } else {
            $s = str_replace(',', '', $s);
        }

        return is_numeric($s) ? (float) $s : 0.0;
    }

    private function failure(string $message): array
    {
        return [
            'success' => false,
            'data' => [
                'supplier' => 'Dynamis',
                'items' => [],
                'costs' => ['items' => [], 'total' => 0.0],
                'totals' => [
                    'line_count' => 0,
                    'products_total' => 0.0,
                    'costs_total' => 0.0,
                    'total_value' => 0.0,
                    'products_calculated' => 0.0,
                    'costs_calculated' => 0.0,
                    'grand_calculated' => 0.0,
                    'totals_match' => true,
                    'discrepancy' => 0.0,
                ],
                'metadata' => [
                    'supplier' => 'dynamis',
                    'order_number' => null,
                ],
            ],
            'warnings' => [],
            'errors' => [$message],
            'confidence' => 0.0,
            'metadata' => [
                'supplier_detected' => 'Dynamis',
                'order_number' => null,
                'unmatched_lines' => [],
            ],
        ];
    }
}
