<?php

namespace App\Services;

use setasign\Fpdi\Fpdi;

/**
 * Generates a filled-in IIHF (Independent Irish Health Foods) "Goods Return Record" by
 * overlaying text onto the official PDF form.
 *
 * The official form is a flat PDF (no fillable fields) using compressed object streams that the
 * free FPDI parser cannot read, so a PDF-1.4 copy is kept at the template path below (produced once
 * with Ghostscript). We import that page and stamp text at calibrated coordinates.
 *
 * Coordinates are in PDF points (pt) with a top-left origin, matching FPDF's coordinate system and
 * the values reported by `pdftotext -bbox` on the template.
 */
class IihfGoodsReturnPdfService
{
    private const TEMPLATE = 'downloads/iihf-goods-return-template.pdf';

    /** Official form has 11 printed table rows per page. */
    private const MAX_ROWS = 11;

    /** Baseline Y of the first data row, and the gap between rows (from bbox of the A–F letters). */
    private const ROW0_BASELINE = 220.86;

    private const ROW_STEP = 18.84;

    /** Per-column X anchor, width, alignment and font size (pt). */
    private const COL = [
        'invoice' => ['x' => 85, 'w' => 36, 'align' => 'L', 'size' => 7],
        'code' => ['x' => 123, 'w' => 36, 'align' => 'L', 'size' => 7],
        'description' => ['x' => 164, 'w' => 182, 'align' => 'L', 'size' => 8],
        'qty' => ['x' => 352, 'w' => 44, 'align' => 'C', 'size' => 8],
        'value' => ['x' => 605, 'w' => 44, 'align' => 'C', 'size' => 8],
        'vat' => ['x' => 650, 'w' => 26, 'align' => 'C', 'size' => 8],
    ];

    /** Center X of each pre-printed reason-code letter (pt). */
    private const REASON_X = [
        'A' => 402.7, 'B' => 417.4, 'C' => 432.2, 'D' => 446.9, 'E' => 461.5, 'F' => 475.8,
    ];

    /**
     * Fixed account/contact details (the original template stores these as AcroForm field values,
     * which FPDI does not render, so we overlay them). Each entry: [text, x, baseline].
     */
    private const STATIC_FIELDS = [
        ['Mossfield Organic Store Ltd', 176, 138],  // ACCOUNT NAME
        ['20128', 658, 138],                        // IIHF ACCOUNT No.
        ['Jonathan Haslam', 236, 482],              // CUSTOMER CONTACT NAME
    ];

    /** DATE field (next to "Accepted by IIHF Driver"): [x, baseline]. */
    private const DATE_POS = [590, 530];

    /**
     * Build the filled PDF and return the raw bytes.
     *
     * @param  array<int,array{invoice?:string,code?:string,description?:string,qty?:string,value?:string,vat?:string,reason?:string}>  $rows
     * @param  string|null  $date  Pre-formatted date for the DATE field (e.g. "16/06/2026").
     */
    public function generate(array $rows, ?string $date = null): string
    {
        $pdf = new Fpdi('L', 'pt', 'A4');
        $pdf->SetAutoPageBreak(false);
        $template = public_path(self::TEMPLATE);

        $chunks = array_chunk($rows, self::MAX_ROWS) ?: [[]];

        foreach ($chunks as $chunk) {
            $pdf->AddPage();
            $pdf->setSourceFile($template);
            $tpl = $pdf->importPage(1);
            $pdf->useTemplate($tpl, ['adjustPageSize' => true]);
            $pdf->SetTextColor(0, 0, 0);

            // Fixed account/contact fields (one set per page).
            $pdf->SetFont('Helvetica', '', 9);
            foreach (self::STATIC_FIELDS as [$text, $x, $baseline]) {
                $pdf->Text($x, $baseline, $this->latin1($text));
            }
            if (! empty($date)) {
                $pdf->Text(self::DATE_POS[0], self::DATE_POS[1], $this->latin1($date));
            }

            $pdf->SetFont('Helvetica', '', 8);

            foreach (array_values($chunk) as $i => $row) {
                $baseline = self::ROW0_BASELINE + $i * self::ROW_STEP;

                $this->cell($pdf, 'invoice', $baseline, (string) ($row['invoice'] ?? ''));
                $this->cell($pdf, 'code', $baseline, (string) ($row['code'] ?? ''));
                $this->cell($pdf, 'description', $baseline, (string) ($row['description'] ?? ''));
                $this->cell($pdf, 'qty', $baseline, (string) ($row['qty'] ?? ''));
                $this->cell($pdf, 'value', $baseline, (string) ($row['value'] ?? ''));
                $this->cell($pdf, 'vat', $baseline, (string) ($row['vat'] ?? ''));

                $reason = strtoupper((string) ($row['reason'] ?? 'A'));
                if (isset(self::REASON_X[$reason])) {
                    $pdf->SetFont('Helvetica', 'B', 14);
                    $w = $pdf->GetStringWidth('X');
                    $pdf->Text(self::REASON_X[$reason] - $w / 2, $baseline, 'X');
                    $pdf->SetFont('Helvetica', '', 8);
                }
            }
        }

        return $pdf->Output('S');
    }

    /**
     * Write one cell's text at the given row baseline, honouring column alignment and width.
     */
    private function cell(Fpdi $pdf, string $key, float $baseline, string $text): void
    {
        if ($text === '') {
            return;
        }

        $col = self::COL[$key];
        $pdf->SetFont('Helvetica', '', $col['size']);
        $text = $this->fit($pdf, $this->latin1($text), (float) $col['w']);

        $x = $col['x'];
        if ($col['align'] === 'C') {
            $x = $col['x'] + ($col['w'] - $pdf->GetStringWidth($text)) / 2;
        }

        $pdf->Text($x, $baseline, $text);
    }

    /**
     * Truncate text with a trailing ".." so it fits the column width.
     */
    private function fit(Fpdi $pdf, string $text, float $maxWidth): string
    {
        if ($pdf->GetStringWidth($text) <= $maxWidth) {
            return $text;
        }

        while (strlen($text) > 1 && $pdf->GetStringWidth($text.'..') > $maxWidth) {
            $text = substr($text, 0, -1);
        }

        return rtrim($text).'..';
    }

    /**
     * FPDF core fonts use Windows-1252; convert UTF-8 so accents/symbols (e.g. €) render.
     */
    private function latin1(string $text): string
    {
        return mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
    }
}
