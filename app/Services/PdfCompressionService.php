<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Shrinks image-heavy invoice PDFs after upload (e.g. a 4 MB electricity bill
 * whose third page is a full-page marketing photo).
 *
 * Ghostscript rewrites the file, downsampling colour and grey pictures above the
 * configured resolution; text, fonts and black-and-white images are left alone.
 * The original is only replaced when all of these hold, otherwise it is kept:
 *  - the PDF has a real text layer (a scan, where the picture IS the invoice,
 *    is never touched);
 *  - the page count is unchanged;
 *  - every page yields exactly the same words afterwards;
 *  - the file got meaningfully smaller.
 */
class PdfCompressionService
{
    /**
     * Compress the PDF at $path in place when it is safe and worthwhile.
     *
     * @return array{compressed: bool, reason: string, original_size: int, new_size: int}
     */
    public function compress(string $path): array
    {
        $originalSize = is_file($path) ? (int) filesize($path) : 0;
        $result = fn (bool $done, string $reason, ?int $newSize = null) => [
            'compressed' => $done,
            'reason' => $reason,
            'original_size' => $originalSize,
            'new_size' => $newSize ?? $originalSize,
        ];

        if (! config('invoices.pdf_compression.enabled', true)) {
            return $result(false, 'disabled');
        }

        if ($originalSize < config('invoices.pdf_compression.min_size_kb', 500) * 1024) {
            return $result(false, 'small enough already');
        }

        // Ghostscript is sandboxed on some systems (AppArmor on Ubuntu 26.04) and
        // may only read and write under the temp directory, so work on copies there.
        $workDir = sys_get_temp_dir().'/pdfcompress_'.bin2hex(random_bytes(8));

        if (! @mkdir($workDir, 0700)) {
            return $result(false, 'could not create a working folder');
        }

        $in = $workDir.'/in.pdf';
        $out = $workDir.'/out.pdf';

        try {
            if (! copy($path, $in)) {
                return $result(false, 'could not copy the file');
            }

            $before = $this->pageWords($in);

            if ($before === null) {
                return $result(false, 'pdftotext not available or failed');
            }

            $wordCount = array_sum(array_map('count', $before));

            if ($wordCount < config('invoices.pdf_compression.min_words', 50)) {
                return $result(false, 'no text layer (scanned document), left untouched');
            }

            $dpi = (int) config('invoices.pdf_compression.image_dpi', 200);

            $gs = Process::timeout((int) config('invoices.pdf_compression.timeout', 60))->run([
                config('invoices.pdf_compression.ghostscript', 'gs'),
                '-q', '-dNOPAUSE', '-dBATCH', '-dSAFER',
                '-sDEVICE=pdfwrite',
                '-dDownsampleColorImages=true',
                '-dColorImageDownsampleType=/Bicubic',
                "-dColorImageResolution={$dpi}",
                '-dColorImageDownsampleThreshold=1.0',
                '-dDownsampleGrayImages=true',
                '-dGrayImageDownsampleType=/Bicubic',
                "-dGrayImageResolution={$dpi}",
                '-dGrayImageDownsampleThreshold=1.0',
                '-dDownsampleMonoImages=false',
                "-sOutputFile={$out}",
                $in,
            ]);

            if (! $gs->successful() || ! is_file($out) || filesize($out) === 0) {
                return $result(false, 'ghostscript not available or failed');
            }

            $newSize = (int) filesize($out);
            $maxRatio = (float) config('invoices.pdf_compression.max_size_ratio', 0.8);

            if ($newSize > $originalSize * $maxRatio) {
                return $result(false, 'saving too small');
            }

            $after = $this->pageWords($out);

            if ($after === null || count($after) !== count($before)) {
                return $result(false, 'page count changed, original kept');
            }

            if ($after !== $before) {
                return $result(false, 'text changed, original kept');
            }

            // Write beside the target and rename, so a failure cannot leave half a file.
            $swap = $path.'.compressing';

            if (! copy($out, $swap) || ! rename($swap, $path)) {
                @unlink($swap);

                return $result(false, 'could not replace the file');
            }

            @chmod($path, 0664);
            clearstatcache(true, $path);

            return $result(true, 'compressed', $newSize);
        } catch (\Throwable $e) {
            Log::warning('PDF compression failed, original kept', ['path' => $path, 'error' => $e->getMessage()]);

            return $result(false, 'error: '.$e->getMessage());
        } finally {
            @unlink($in);
            @unlink($out);
            @rmdir($workDir);
        }
    }

    /**
     * The words on each page, sorted, one list per page. Sorting makes the comparison
     * independent of reading order, which a rewrite can change without changing content.
     *
     * @return array<int, array<int, string>>|null null when pdftotext could not run
     */
    private function pageWords(string $pdf): ?array
    {
        $run = Process::timeout(30)->run([config('invoices.pdf_compression.pdftotext', 'pdftotext'), $pdf, '-']);

        if (! $run->successful()) {
            return null;
        }

        // pdftotext ends every page with a form feed.
        $pages = explode("\f", $run->output());
        array_pop($pages);

        return array_map(function (string $page) {
            $words = preg_split('/\s+/u', $page, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            sort($words, SORT_STRING);

            return $words;
        }, $pages);
    }
}
