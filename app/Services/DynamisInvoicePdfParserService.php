<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;

/**
 * Reads a Dynamis invoice PDF (the current "INVOICE FC… dated …" layout) into
 * line items and totals.
 *
 * The text comes from `pdftotext -layout` (poppler-utils), which keeps the
 * invoice's columns aligned; the rules below are a port of the owner's
 * dynamis_invoice.py spreadsheet script. Barcoded grocery lines are the ones
 * with an EAN printed on the line below the item.
 */
class DynamisInvoicePdfParserService
{
    private const ITEM_RE = '/^\s*(?P<ref>[A-Z0-9]{3,})\s+(?P<name>.+?)\s{2,}(?P<uv>[A-Z]{2,4})\s+(?P<orig>[A-Z]{2})(?:\s*\/)?\s+'
        .'(?:(?:BIO|REC|COSM)\s+)?(?P<pkg>\d+)\s+(?P<qty>[\d.,]+)\s+.*?'
        .'(?P<unit>[\d.,]+)\s+(?P<total>[\d.,]+)\s+\d+(?:\s+\S)?\s*$/u';

    private const EAN_RE = '/^\s*(?P<ean>\d{8,14})(?=\s|$)/u';

    private const AMOUNT_RE = '/[\d.\s]*\d,\d{2}\s*€/u';

    /**
     * @return array{success: bool, errors: string[], warnings: string[], data: array}
     */
    public function parseFile(string $pdfPath): array
    {
        $result = Process::timeout(30)->run(['pdftotext', '-layout', $pdfPath, '-']);

        if (! $result->successful()) {
            return $this->failure('Could not read the PDF (pdftotext failed): '.trim($result->errorOutput()));
        }

        return $this->parseText($result->output());
    }

    /**
     * @return array{success: bool, errors: string[], warnings: string[], data: array}
     */
    public function parseText(string $text): array
    {
        $lines = preg_split('/\R/u', str_replace("\f", "\n", $text));
        $items = [];
        $totals = [];
        $prevItem = false;
        $nameCols = [0, 0];
        $deliveryNote = null;

        foreach ($lines as $i => $line) {
            if (preg_match('/^\s*(BL\d{6,})\s*$/u', $line, $bl)) {
                $deliveryNote = $bl[1];
                $prevItem = false;

                continue;
            }

            if (preg_match(self::ITEM_RE, $line, $m, PREG_OFFSET_CAPTURE)) {
                $items[] = [
                    'code' => $m['ref'][0],
                    'product' => trim($m['name'][0]),
                    'origin' => $m['orig'][0],
                    'unit' => $m['uv'][0],
                    'packages' => (int) $m['pkg'][0],
                    'quantity' => $this->num($m['qty'][0]),
                    'net_unit_price' => $this->num($m['unit'][0]),
                    'line_total' => $this->num($m['total'][0]),
                    'ean' => null,
                    'delivery_note' => $deliveryNote,
                ];
                $nameCols = [$this->charOffset($line, $m['name'][1]), $this->charOffset($line, $m['uv'][1])];
                $prevItem = true;

                continue;
            }

            $last = count($items) - 1;

            if ($prevItem && $items[$last]['ean'] === null && preg_match(self::EAN_RE, $line, $e)) {
                $items[$last]['ean'] = $e['ean'];
                // The EAN line can carry the rest of the description
                $more = trim(mb_substr($line, $nameCols[0], $nameCols[1] - $nameCols[0]));
                if ($more !== '') {
                    $items[$last]['product'] .= ' '.$more;
                }

                continue;
            }

            $stripped = trim($line);

            // Wrapped description, e.g. "CLASS II" on the line below the item
            if ($prevItem && $stripped !== '' && ! str_contains($stripped, '  ') && str_starts_with($line, str_repeat(' ', 15))) {
                $items[$last]['product'] .= ' '.$stripped;

                continue;
            }

            $prevItem = false;

            if (str_contains($line, 'Gross Total Excl.') && str_contains($line, 'Shipping Fees')) {
                $totals = $this->readTotals($line, array_slice($lines, $i + 1));
            }
        }

        if (empty($items)) {
            return $this->failure('No invoice lines found. This does not look like a Dynamis invoice, or their layout has changed.');
        }

        $gross = $totals['Gross Total Excl.'] ?? null;
        $shipping = $totals['Shipping Fees'] ?? 0.0;
        $linesSum = round(array_sum(array_column($items, 'line_total')), 2);
        $warnings = [];

        if ($gross === null) {
            $warnings[] = 'The invoice totals could not be read, so the lines could not be checked against the goods total.';
        } elseif (abs($linesSum - $gross) > 0.01) {
            $warnings[] = sprintf(
                'The lines add up to €%s but the invoice goods total is €%s. A line was probably missed; check the page against the PDF.',
                number_format($linesSum, 2),
                number_format($gross, 2)
            );
        }

        return [
            'success' => true,
            'errors' => [],
            'warnings' => $warnings,
            'data' => [
                'metadata' => $this->readMetadata($text),
                'items' => $items,
                'totals' => [
                    'gross' => $gross,
                    'shipping' => $shipping,
                    'lines_sum' => $linesSum,
                    'totals_match' => $gross !== null && abs($linesSum - $gross) <= 0.01,
                ],
            ],
        ];
    }

    /**
     * Rows for DeliveryService::importFromPdfData(), one per invoice line and in
     * invoice order. A per-kg line becomes boxes of a given weight, like the
     * XLSX import; box and piece lines are counted in their own unit.
     */
    public function convertToDeliveryItems(array $parsed): array
    {
        $rows = [];

        foreach ($parsed['data']['items'] ?? [] as $item) {
            $perKg = $item['unit'] === 'KG';
            $boxes = max(1, (int) $item['packages']);
            $units = $perKg ? $boxes : max(1, (int) round($item['quantity']));

            $rows[] = [
                'Code' => $item['code'],
                'Product' => $item['product'],
                'Ordered' => (string) $units,
                'Qty' => (string) $units,
                'Case_Size' => 1,
                'Total_Ordered_Units' => $units,
                'Total_Delivered_Units' => $units,
                'RSP' => 0,
                'Price' => $item['net_unit_price'],
                'Unit_Cost' => $item['net_unit_price'],
                'Tax' => 0,
                'Value' => $item['line_total'],
                'price_valid' => true,
                'is_weight_based' => $perKg,
                'weight_per_unit' => $perKg ? round($item['quantity'] / $boxes, 3) : null,
                'weight_unit' => $perKg ? 'kg' : null,
                'total_weight' => $perKg ? round($item['quantity'], 3) : null,
                'order_number' => $item['delivery_note'],
                'skip_barcode_retrieval' => true,
            ];
        }

        return $rows;
    }

    /**
     * Each amount on the line under the totals header belongs to the header it
     * sits under; blank columns (no rebate, no shipping) are simply absent.
     *
     * @param  string[]  $following
     * @return array<string, float>
     */
    private function readTotals(string $headerLine, array $following): array
    {
        preg_match_all('/\S+(?: \S+)*/u', $headerLine, $found, PREG_OFFSET_CAPTURE);
        $headers = [];
        foreach ($found[0] as [$label, $offset]) {
            $start = $this->charOffset($headerLine, $offset);
            $headers[] = [trim($label), $start + mb_strlen($label) / 2];
        }

        $values = null;
        foreach ($following as $line) {
            if (trim($line) !== '') {
                $values = $line;
                break;
            }
        }

        $totals = [];
        if ($values === null || ! preg_match_all(self::AMOUNT_RE, $values, $amounts, PREG_OFFSET_CAPTURE)) {
            return $totals;
        }

        foreach ($amounts[0] as [$amount, $offset]) {
            $centre = $this->charOffset($values, $offset) + mb_strlen($amount) / 2;
            usort($headers, fn ($a, $b) => abs($a[1] - $centre) <=> abs($b[1] - $centre));
            $totals[$headers[0][0]] = $this->num($amount);
        }

        return $totals;
    }

    /**
     * The payment slip at the foot repeats the reference and date without the
     * header's letter spacing, so it is tried first.
     *
     * @return array{invoice_number: ?string, invoice_date: ?string}
     */
    private function readMetadata(string $text): array
    {
        $number = null;
        $date = null;

        if (preg_match('/Facture\s*:\s*(\S+)/u', $text, $m)) {
            $number = $m[1];
        }
        if (preg_match('/Facture\s*:.*?Date\s*:\s*(\d{2})\/(\d{2})\/(\d{4})/u', $text, $m)) {
            $date = "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        if (preg_match('/INVOICE\s+((?:[A-Z0-9]\s*)+?)\s*dated\s*(\d{2})\/(\d{2})\/(\d{4})/u', $text, $m)) {
            $number ??= preg_replace('/\s+/', '', $m[1]);
            $date ??= "{$m[4]}-{$m[3]}-{$m[2]}";
        }

        return ['invoice_number' => $number, 'invoice_date' => $date];
    }

    /** "1 022,44 €" → 1022.44 (space or dot for thousands, comma for decimals). */
    private function num(string $value): float
    {
        $value = preg_replace('/[\s€]/u', '', $value);

        return (float) str_replace(',', '.', str_replace('.', '', $value));
    }

    private function charOffset(string $line, int $byteOffset): int
    {
        return mb_strlen(substr($line, 0, $byteOffset));
    }

    private function failure(string $message): array
    {
        return ['success' => false, 'errors' => [$message], 'warnings' => [], 'data' => []];
    }
}
