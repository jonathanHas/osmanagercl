<?php

namespace App\Services;

use setasign\Fpdi\Fpdi;

/**
 * Generates a filled-in Organic Trust "Multi-Ingredient Product Registration Form" by overlaying
 * text onto the official PDF form.
 *
 * The official form is a flat PDF (no fillable fields) using compressed object streams that the
 * free FPDI parser cannot read, so a PDF-1.4 copy is kept at the template path below (produced once
 * with Ghostscript). We import its pages and stamp text at calibrated coordinates.
 *
 * Coordinates are in PDF points (pt) with a top-left origin, matching FPDF's coordinate system and
 * the values reported by `pdftotext -bbox` on the template.
 */
class OrganicRegistrationPdfService
{
    private const TEMPLATE = 'downloads/organic-multi-ingredient-registration-template.pdf';

    /** Official form has 12 printed ingredient rows per page. */
    private const MAX_ROWS = 12;

    /** Baseline Y of the first ingredient row, and the gap between rows. */
    private const ROW0_BASELINE = 394.5;

    private const ROW_STEP = 20.35;

    /** Per-column X anchor, width, alignment and font size (pt). */
    private const COL = [
        'ingredient' => ['x' => 36, 'w' => 118, 'align' => 'L', 'size' => 7],
        'quantity' => ['x' => 157, 'w' => 44, 'align' => 'C', 'size' => 7],
        'percent' => ['x' => 207, 'w' => 54, 'align' => 'C', 'size' => 7],
        'organic' => ['x' => 268, 'w' => 59, 'align' => 'C', 'size' => 7],
        'certification_body' => ['x' => 330, 'w' => 107, 'align' => 'C', 'size' => 7],
    ];

    /** Centre X of the "up to date organic certificate on file" tick box. */
    private const CERT_ON_FILE_X = 475.8;

    /**
     * Header fields stamped at the top of every page: [x, baseline, width, align].
     * The value boxes start at x=180; the licence box sits to the right at 481-560.
     */
    private const HEADER = [
        'company' => [185, 199, 210, 'L'],
        'licence' => [481, 199, 79, 'C'],
        'responsible_person' => [185, 227, 340, 'L'],
        'product_name' => [185, 255, 340, 'L'],
    ];

    /**
     * Build the filled PDF and return the raw bytes.
     *
     * @param  array<int,array{ingredient?:string,quantity?:string,percent?:float|null,organic?:bool,certification_body?:string}>  $rows
     */
    public function generate(string $productName, array $rows): string
    {
        $pdf = new Fpdi('P', 'pt', 'A4');
        $pdf->SetAutoPageBreak(false);
        $template = public_path(self::TEMPLATE);

        $business = config('app.business', []);
        $company = $business['legal_name'] ?? $business['name'] ?? '';
        $licence = $business['organic_licence_no'] ?? '';
        $responsible = $business['organic_responsible_person'] ?? '';

        // More than 12 ingredients spills onto a second copy of page 1, with its header repeated.
        $chunks = array_chunk($rows, self::MAX_ROWS) ?: [[]];

        foreach ($chunks as $chunk) {
            $pdf->AddPage();
            $pdf->setSourceFile($template);
            $pdf->useTemplate($pdf->importPage(1), ['adjustPageSize' => true]);
            $pdf->SetTextColor(0, 0, 0);

            $this->header($pdf, 'company', $company);
            $this->header($pdf, 'licence', $licence);
            $this->header($pdf, 'responsible_person', $responsible);
            $this->header($pdf, 'product_name', $productName);

            foreach (array_values($chunk) as $i => $row) {
                $baseline = self::ROW0_BASELINE + $i * self::ROW_STEP;
                $isOrganic = (bool) ($row['organic'] ?? true);

                $this->cell($pdf, 'ingredient', $baseline, (string) ($row['ingredient'] ?? ''));
                $this->cell($pdf, 'quantity', $baseline, (string) ($row['quantity'] ?? ''));
                $this->cell($pdf, 'percent', $baseline, $this->percent($row['percent'] ?? null));
                $this->cell($pdf, 'organic', $baseline, $isOrganic ? 'Organic' : 'Non-Organic');
                $this->cell($pdf, 'certification_body', $baseline, (string) ($row['certification_body'] ?? ''));

                // The certificate-on-file column applies to organic ingredients only.
                if ($isOrganic) {
                    $pdf->SetFont('Helvetica', 'B', 10);
                    $pdf->Text(self::CERT_ON_FILE_X - $pdf->GetStringWidth('X') / 2, $baseline, 'X');
                }
            }
        }

        // Page 2 carries the documentation checklist and signature blocks, completed by hand.
        $pdf->AddPage();
        $pdf->setSourceFile($template);
        $pdf->useTemplate($pdf->importPage(2), ['adjustPageSize' => true]);

        return $pdf->Output('S');
    }

    /**
     * Write one header field into its box on the current page.
     */
    private function header(Fpdi $pdf, string $key, string $text): void
    {
        if ($text === '') {
            return;
        }

        [$x, $baseline, $width, $align] = self::HEADER[$key];

        $pdf->SetFont('Helvetica', '', 9);
        $text = $this->fit($pdf, $this->latin1($text), (float) $width);

        if ($align === 'C') {
            $x += ($width - $pdf->GetStringWidth($text)) / 2;
        }

        $pdf->Text($x, $baseline, $text);
    }

    /**
     * Format a share of the overall product. Unknown weights print blank rather than a guess.
     */
    private function percent(?float $percent): string
    {
        return $percent === null ? '' : number_format($percent, 1).'%';
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
     * FPDF core fonts use Windows-1252; convert UTF-8 so accents/symbols render.
     */
    private function latin1(string $text): string
    {
        return mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
    }
}
