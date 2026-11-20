<?php

namespace App\Services\Electricity;

use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Text of a bill PDF via poppler's pdftotext, whole page or the left part only.
 */
class BillTextExtractor
{
    /**
     * @param  int|null  $cropWidth  points from the left edge to keep, null for the whole page
     */
    public function text(string $pdfPath, ?int $cropWidth = null): string
    {
        $command = [config('invoices.pdf_compression.pdftotext') ?: 'pdftotext', '-layout'];

        if ($cropWidth !== null) {
            array_push($command, '-x', '0', '-y', '0', '-W', (string) $cropWidth, '-H', '3000');
        }

        $result = Process::timeout(30)->run([...$command, $pdfPath, '-']);

        if (! $result->successful()) {
            throw new RuntimeException('Could not read the PDF (pdftotext failed): '.trim($result->errorOutput()));
        }

        return $result->output();
    }
}
