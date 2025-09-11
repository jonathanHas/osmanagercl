<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class PdfRepairService
{
    /**
     * Maximum number of bytes to scan for PDF header
     */
    const MAX_HEADER_SCAN_BYTES = 1024;

    /**
     * PDF file signature
     */
    const PDF_SIGNATURE = '%PDF-';

    /**
     * Check if a file needs PDF repair
     */
    public function needsRepair(string $filePath): bool
    {
        if (! file_exists($filePath) || ! is_readable($filePath)) {
            return false;
        }

        // Read the first few bytes of the file
        $handle = fopen($filePath, 'rb');
        if (! $handle) {
            return false;
        }

        $header = fread($handle, 10);
        fclose($handle);

        // Check if file starts with PDF signature
        if (strpos($header, self::PDF_SIGNATURE) === 0) {
            return false; // File is already valid
        }

        // Check if PDF signature exists elsewhere in the first 1KB
        $offset = $this->detectPdfHeaderOffset($filePath);

        return $offset !== false && $offset > 0;
    }

    /**
     * Repair a corrupted PDF file
     *
     * @return bool True if repair was successful
     */
    public function repair(string $filePath): bool
    {
        try {
            // Find where the real PDF starts
            $offset = $this->detectPdfHeaderOffset($filePath);

            if ($offset === false || $offset === 0) {
                Log::warning('PDF repair: No valid PDF header found or file already valid', [
                    'file' => $filePath,
                    'offset' => $offset,
                ]);

                return $offset === 0; // Return true if already valid
            }

            Log::info('PDF repair: Found PDF header at offset', [
                'file' => $filePath,
                'offset' => $offset,
                'corrupted_data' => $this->getCorruptedDataPreview($filePath, $offset),
            ]);

            // Extract the clean PDF content
            $cleanPdfPath = $this->extractCleanPdf($filePath, $offset);

            if (! $cleanPdfPath) {
                Log::error('PDF repair: Failed to extract clean PDF', [
                    'file' => $filePath,
                ]);

                return false;
            }

            // Replace original file with cleaned version
            if (! rename($cleanPdfPath, $filePath)) {
                Log::error('PDF repair: Failed to replace original file', [
                    'original' => $filePath,
                    'clean' => $cleanPdfPath,
                ]);
                @unlink($cleanPdfPath);

                return false;
            }

            Log::info('PDF repair: Successfully repaired file', [
                'file' => $filePath,
                'bytes_removed' => $offset,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('PDF repair: Exception during repair', [
                'file' => $filePath,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Detect the offset where the PDF header starts
     *
     * @return int|false Offset in bytes, or false if not found
     */
    public function detectPdfHeaderOffset(string $filePath): int|false
    {
        $handle = fopen($filePath, 'rb');
        if (! $handle) {
            return false;
        }

        // Read first chunk to scan for PDF header
        $chunk = fread($handle, self::MAX_HEADER_SCAN_BYTES);
        fclose($handle);

        // Find PDF signature
        $position = strpos($chunk, self::PDF_SIGNATURE);

        return $position;
    }

    /**
     * Extract clean PDF content starting from the given offset
     *
     * @return string|false Path to cleaned file, or false on failure
     */
    protected function extractCleanPdf(string $filePath, int $offset): string|false
    {
        $tempPath = $filePath.'.clean.tmp';

        $sourceHandle = fopen($filePath, 'rb');
        if (! $sourceHandle) {
            return false;
        }

        $destHandle = fopen($tempPath, 'wb');
        if (! $destHandle) {
            fclose($sourceHandle);

            return false;
        }

        // Skip the corrupted bytes
        fseek($sourceHandle, $offset);

        // Copy the rest of the file
        while (! feof($sourceHandle)) {
            $chunk = fread($sourceHandle, 8192);
            if ($chunk === false) {
                break;
            }
            fwrite($destHandle, $chunk);
        }

        fclose($sourceHandle);
        fclose($destHandle);

        // Verify the cleaned file is valid
        if (! $this->isValidPdf($tempPath)) {
            @unlink($tempPath);

            return false;
        }

        return $tempPath;
    }

    /**
     * Check if a file is a valid PDF
     */
    protected function isValidPdf(string $filePath): bool
    {
        if (! file_exists($filePath) || filesize($filePath) < 10) {
            return false;
        }

        $handle = fopen($filePath, 'rb');
        if (! $handle) {
            return false;
        }

        $header = fread($handle, 10);
        fclose($handle);

        // Check for PDF signature at the beginning
        return strpos($header, self::PDF_SIGNATURE) === 0;
    }

    /**
     * Get a preview of the corrupted data for logging
     */
    protected function getCorruptedDataPreview(string $filePath, int $offset): string
    {
        $handle = fopen($filePath, 'rb');
        if (! $handle) {
            return '';
        }

        $preview = fread($handle, min($offset, 100));
        fclose($handle);

        // Convert to readable format
        $preview = preg_replace('/[\x00-\x1F\x7F-\xFF]/', '.', $preview);

        if (strlen($preview) > 50) {
            $preview = substr($preview, 0, 50).'...';
        }

        return $preview;
    }

    /**
     * Detect supplier from repaired PDF content
     */
    public function detectSupplier(string $filePath): ?string
    {
        // Quick text search in first few KB of file
        $handle = fopen($filePath, 'rb');
        if (! $handle) {
            return null;
        }

        $content = fread($handle, 8192);
        fclose($handle);

        // Check for known problematic suppliers
        if (stripos($content, 'Klee Paper') !== false) {
            return 'Klee Paper';
        }

        return null;
    }

    /**
     * Get detailed repair statistics for a file
     */
    public function getRepairStats(string $filePath): array
    {
        $stats = [
            'needs_repair' => false,
            'pdf_offset' => 0,
            'corrupted_bytes' => 0,
            'supplier_detected' => null,
            'file_size' => filesize($filePath),
        ];

        if ($this->needsRepair($filePath)) {
            $offset = $this->detectPdfHeaderOffset($filePath);
            $stats['needs_repair'] = true;
            $stats['pdf_offset'] = $offset;
            $stats['corrupted_bytes'] = $offset;
            $stats['corrupted_data_preview'] = $this->getCorruptedDataPreview($filePath, $offset);
        }

        $stats['supplier_detected'] = $this->detectSupplier($filePath);

        return $stats;
    }
}
