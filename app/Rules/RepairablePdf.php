<?php

namespace App\Rules;

use App\Services\PdfRepairService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class RepairablePdf implements ValidationRule
{
    protected array $allowedMimeTypes;

    protected ?string $errorMessage = null;

    public function __construct()
    {
        $this->allowedMimeTypes = config('invoices.bulk_upload.allowed_mime_types', [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/tiff',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.oasis.opendocument.text',
            'application/vnd.oasis.opendocument.spreadsheet',
        ]);
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            $fail('The file upload failed.');

            return;
        }

        // First check if it's already a valid file type
        $mimeType = $value->getMimeType();
        if (in_array($mimeType, $this->allowedMimeTypes)) {
            return; // File is already valid
        }

        // Check if this might be a corrupted PDF based on extension
        $extension = strtolower($value->getClientOriginalExtension());
        if ($extension !== 'pdf') {
            // Not a PDF extension, and not a valid MIME type
            $fail('Only PDF, JPG, PNG, TIFF, DOC, DOCX, XLS, XLSX, ODT, and ODS files are allowed.');

            return;
        }

        // This has a PDF extension but wrong MIME type - might be corrupted
        // Try to repair it if PDF repair is enabled
        if (! config('invoices.pdf_repair.enabled', true)) {
            $fail('The PDF file appears to be corrupted and automatic repair is disabled.');

            return;
        }

        // Create a temporary file to test repair
        $tempPath = sys_get_temp_dir().'/'.uniqid('pdf_test_').'.pdf';

        try {
            // Copy uploaded file to temp location
            if (! copy($value->getPathname(), $tempPath)) {
                $fail('Failed to process the PDF file.');

                return;
            }

            $pdfRepairService = new PdfRepairService;

            // Check if it needs repair
            if (! $pdfRepairService->needsRepair($tempPath)) {
                // Doesn't need repair but has wrong MIME type
                @unlink($tempPath);
                $fail('The file type could not be determined. Please ensure it is a valid PDF.');

                return;
            }

            // Log repair attempt
            if (config('invoices.pdf_repair.log_repairs', true)) {
                Log::info('Attempting PDF repair during validation', [
                    'original_name' => $value->getClientOriginalName(),
                    'original_mime' => $mimeType,
                    'repair_stats' => $pdfRepairService->getRepairStats($tempPath),
                ]);
            }

            // Attempt repair
            if (! $pdfRepairService->repair($tempPath)) {
                @unlink($tempPath);
                $fail('The PDF file appears to be corrupted and could not be repaired.');

                return;
            }

            // Check if repaired file is now a valid PDF
            $repairedMimeType = mime_content_type($tempPath);
            if ($repairedMimeType !== 'application/pdf') {
                @unlink($tempPath);
                $fail('The file could not be processed as a valid PDF.');

                return;
            }

            // Copy repaired content back to the uploaded file
            // This allows the repaired version to be used in subsequent processing
            $repairedContent = file_get_contents($tempPath);
            file_put_contents($value->getPathname(), $repairedContent);

            if (config('invoices.pdf_repair.log_repairs', true)) {
                Log::info('PDF repaired successfully during validation', [
                    'original_name' => $value->getClientOriginalName(),
                    'supplier' => $pdfRepairService->detectSupplier($tempPath),
                ]);
            }

            @unlink($tempPath);

        } catch (\Exception $e) {
            @unlink($tempPath);
            Log::error('PDF repair failed during validation', [
                'file' => $value->getClientOriginalName(),
                'error' => $e->getMessage(),
            ]);
            $fail('The PDF file could not be processed: '.$e->getMessage());
        }
    }
}
