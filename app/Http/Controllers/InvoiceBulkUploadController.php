<?php

namespace App\Http\Controllers;

use App\Jobs\ParseInvoiceCameraImage;
use App\Jobs\ParseInvoiceFile;
use App\Models\InvoiceBulkUpload;
use App\Models\InvoiceUploadFile;
use App\Rules\RepairablePdf;
use App\Services\AmazonPaymentAdjustmentService;
use App\Services\InvoiceParsingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InvoiceBulkUploadController extends Controller
{
    /**
     * Display the bulk upload interface.
     */
    public function index()
    {
        $recentUploads = InvoiceBulkUpload::where('user_id', auth()->id())
            ->with(['files' => function ($query) {
                $query->select('id', 'bulk_upload_id', 'status', 'original_filename');
            }])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        return view('invoices.bulk-upload', [
            'recentUploads' => $recentUploads,
            'maxFiles' => config('invoices.bulk_upload.max_files_per_batch'),
            'maxFileSize' => config('invoices.bulk_upload.max_file_size_mb'),
            'allowedExtensions' => config('invoices.bulk_upload.allowed_extensions'),
        ]);
    }

    /**
     * Handle the bulk file upload.
     */
    public function upload(Request $request)
    {
        // Validate the uploaded files
        $maxFiles = config('invoices.bulk_upload.max_files_per_batch');
        $maxSizeMB = config('invoices.bulk_upload.max_file_size_mb');
        $maxSizeKB = $maxSizeMB * 1024;
        $allowedMimes = implode(',', config('invoices.bulk_upload.allowed_mime_types'));

        $request->validate([
            'files' => "required|array|min:1|max:{$maxFiles}",
            'files.*' => [
                'required',
                'file',
                "max:{$maxSizeKB}",
                new RepairablePdf,
            ],
        ], [
            'files.max' => "You can upload a maximum of {$maxFiles} files at once.",
            'files.*.max' => "Each file must be less than {$maxSizeMB}MB.",
        ]);

        DB::beginTransaction();

        try {
            // Create bulk upload batch
            $batch = InvoiceBulkUpload::create([
                'batch_id' => InvoiceBulkUpload::generateBatchId(),
                'user_id' => auth()->id(),
                'total_files' => count($request->file('files')),
                'status' => 'uploading',
                'metadata' => [
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ],
            ]);

            $uploadedFiles = [];
            $tempPath = config('invoices.bulk_upload.temp_path');
            $batchFolder = $tempPath.'/'.$batch->batch_id;

            // Ensure the temp directory exists
            Storage::disk('local')->makeDirectory($batchFolder);

            // Fix permissions for the batch directory so queue worker can access it
            $fullBatchPath = Storage::disk('local')->path($batchFolder);
            chmod($fullBatchPath, 0775); // rwxrwxr-x

            foreach ($request->file('files') as $index => $file) {
                $originalName = $file->getClientOriginalName();
                $extension = $file->getClientOriginalExtension();
                // Re-detect MIME type in case file was repaired during validation
                $mimeType = mime_content_type($file->getPathname()) ?: $file->getMimeType();
                $fileSize = $file->getSize();

                // Generate unique filename
                $storedName = Str::uuid().'.'.$extension;
                $filePath = $batchFolder.'/'.$storedName;

                // Store the file temporarily - use 'local' disk which is now storage/app/private
                $storedPath = $file->storeAs($batchFolder, $storedName, 'local');

                // Debug logging to track file creation
                $fullPath = Storage::disk('local')->path($filePath);
                \Log::info('File upload debug', [
                    'original_name' => $originalName,
                    'stored_name' => $storedName,
                    'batch_folder' => $batchFolder,
                    'file_path' => $filePath,
                    'full_path' => $fullPath,
                    'stored_path' => $storedPath,
                    'file_exists_after_store' => file_exists($fullPath),
                    'file_size' => file_exists($fullPath) ? filesize($fullPath) : 0,
                ]);

                // Calculate file hash and fix file permissions
                $fullPath = Storage::disk('local')->path($filePath);
                chmod($fullPath, 0664); // rw-rw-r--
                $fileHash = hash_file('sha256', $fullPath);

                // Create file record
                $uploadFile = InvoiceUploadFile::create([
                    'bulk_upload_id' => $batch->id,
                    'original_filename' => $originalName,
                    'stored_filename' => $storedName,
                    'temp_path' => $storedPath,
                    'mime_type' => $mimeType,
                    'file_size' => $fileSize,
                    'file_hash' => $fileHash,
                    'status' => 'uploaded',
                    'upload_progress' => 100,
                    'uploaded_at' => now(),
                ]);

                $uploadedFiles[] = $uploadFile;
            }

            // Update batch status
            $batch->update([
                'status' => 'uploaded',
                'started_at' => now(),
            ]);

            DB::commit();

            // Get page count for PDFs after successful upload
            $pdfSplittingService = new \App\Services\PdfSplittingService;
            foreach ($uploadedFiles as $uploadFile) {
                $exists = file_exists($uploadFile->temp_file_path);
                \Log::info('File status after DB commit', [
                    'file_id' => $uploadFile->id,
                    'filename' => $uploadFile->original_filename,
                    'temp_path' => $uploadFile->temp_file_path,
                    'exists_after_commit' => $exists,
                    'file_size' => $exists ? filesize($uploadFile->temp_file_path) : 0,
                ]);

                // Get page count for PDFs
                if ($uploadFile->isPdf() && $exists) {
                    try {
                        $pageCount = $pdfSplittingService->getPageCount($uploadFile);
                        if ($pageCount > 0) {
                            $uploadFile->update(['page_count' => $pageCount]);
                            \Log::info('Updated PDF page count', [
                                'file_id' => $uploadFile->id,
                                'page_count' => $pageCount,
                            ]);
                        }
                    } catch (\Exception $e) {
                        \Log::warning('Failed to get PDF page count', [
                            'file_id' => $uploadFile->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            // Return response with batch info
            return response()->json([
                'success' => true,
                'batch_id' => $batch->batch_id,
                'total_files' => count($uploadedFiles),
                'message' => count($uploadedFiles).' file(s) uploaded successfully. Ready for processing.',
                'redirect_url' => route('invoices.bulk-upload.preview', $batch->batch_id),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            // Clean up any uploaded files
            if (isset($batchFolder)) {
                Storage::disk('local')->deleteDirectory($batchFolder);
            }

            return response()->json([
                'success' => false,
                'error' => 'Upload failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check the status of a bulk upload batch.
     */
    public function status($batchId)
    {
        $batch = InvoiceBulkUpload::where('batch_id', $batchId)
            ->where('user_id', auth()->id())
            ->with(['files' => function ($query) {
                $query->select('id', 'bulk_upload_id', 'original_filename', 'status',
                    'upload_progress', 'error_message', 'parsing_confidence', 'supplier_detected');
            }])
            ->firstOrFail();

        return response()->json([
            'batch_id' => $batch->batch_id,
            'status' => $batch->status,
            'total_files' => $batch->total_files,
            'processed_files' => $batch->processed_files,
            'successful_files' => $batch->successful_files,
            'failed_files' => $batch->failed_files,
            'progress_percentage' => $batch->progress_percentage,
            'files' => $batch->files->map(function ($file) {
                return [
                    'id' => $file->id,
                    'filename' => $file->original_filename,
                    'status' => $file->status,
                    'status_label' => $file->status_label,
                    'status_color' => $file->status_color,
                    'progress' => $file->upload_progress,
                    'confidence' => $file->parsing_confidence,
                    'supplier_detected' => $file->supplier_detected,
                    'error' => $file->error_message,
                ];
            }),
        ]);
    }

    /**
     * Preview uploaded files before processing.
     */
    public function preview($batchId, Request $request)
    {
        $batch = InvoiceBulkUpload::where('batch_id', $batchId)
            ->where('user_id', auth()->id())
            ->with(['files', 'files.parentFile'])
            ->firstOrFail();

        // Apply filters if provided
        $files = $batch->files;

        // Filter by supplier if specified
        if ($request->has('supplier') && $request->supplier) {
            $files = $files->where('supplier_detected', $request->supplier);
        }

        // Filter by status if specified
        if ($request->has('status') && $request->status) {
            $files = $files->where('status', $request->status);
        }

        // For Amazon pending view, show specific messaging
        $isAmazonPendingView = $request->has('amazon_pending') && $request->amazon_pending == '1';

        // Get all suppliers for dropdown (from accounting_suppliers table)
        $suppliers = \App\Models\AccountingSupplier::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('invoices.bulk-upload-preview', [
            'batch' => $batch,
            'files' => $files,
            'isAmazonPendingView' => $isAmazonPendingView,
            'suppliers' => $suppliers,
            'filters' => [
                'supplier' => $request->supplier,
                'status' => $request->status,
            ],
        ]);
    }

    /**
     * Show Amazon pending invoices across all batches (unified view).
     */
    public function amazonPending(Request $request)
    {
        // Find all files with Amazon pending status across all batches for the current user
        $files = InvoiceUploadFile::whereHas('bulkUpload', function ($query) {
            $query->where('user_id', auth()->id());
        })
            ->where(function ($query) {
                $query->where('status', 'amazon_pending')
                    ->orWhere(function ($subQuery) {
                        $subQuery->where('supplier_detected', 'Amazon')
                            ->whereIn('status', ['review', 'parsed']);
                    });
            })
            ->with(['bulkUpload'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Group files by batch for better organization
        $batches = $files->groupBy('bulk_upload_id')->map(function ($batchFiles) {
            $firstFile = $batchFiles->first();

            return [
                'batch' => $firstFile->bulkUpload,
                'files' => $batchFiles,
            ];
        });

        return view('invoices.bulk-upload-amazon-pending', [
            'batches' => $batches,
            'totalFiles' => $files->count(),
        ]);
    }

    /**
     * Delete Amazon pending invoice files (bulk operation).
     */
    public function deleteAmazonPendingFiles(Request $request)
    {
        $validated = $request->validate([
            'file_ids' => 'required|array|min:1',
            'file_ids.*' => 'exists:invoice_upload_files,id',
        ]);

        try {
            DB::transaction(function () use ($validated) {
                $files = InvoiceUploadFile::whereIn('id', $validated['file_ids'])
                    ->whereHas('bulkUpload', function ($query) {
                        $query->where('user_id', auth()->id());
                    })
                    ->get();

                $deletedCount = 0;
                foreach ($files as $file) {
                    // Only delete Amazon files that are pending/review
                    if ($file->supplier_detected === 'Amazon' &&
                        in_array($file->status, ['amazon_pending', 'review', 'parsed'])) {

                        // Delete temp file if exists
                        $file->deleteTempFile();

                        // Delete the upload file record
                        $file->delete();
                        $deletedCount++;

                        Log::info('Deleted Amazon pending file', [
                            'file_id' => $file->id,
                            'filename' => $file->original_filename,
                            'batch_id' => $file->bulk_upload_id,
                            'deleted_by' => auth()->id(),
                        ]);
                    }
                }

                if ($deletedCount === 0) {
                    throw new \Exception('No valid Amazon pending files found to delete.');
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Amazon pending files deleted successfully.',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete Amazon pending files', [
                'error' => $e->getMessage(),
                'file_ids' => $validated['file_ids'],
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to delete files: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel a bulk upload batch.
     */
    public function cancel($batchId)
    {
        $batch = InvoiceBulkUpload::where('batch_id', $batchId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        if (! $batch->canBeCancelled()) {
            return response()->json([
                'success' => false,
                'error' => 'This batch cannot be cancelled.',
            ], 400);
        }

        $batch->cancel();

        // Clean up temporary files
        $tempPath = config('invoices.bulk_upload.temp_path').'/'.$batch->batch_id;
        Storage::disk('local')->deleteDirectory($tempPath);

        return response()->json([
            'success' => true,
            'message' => 'Batch upload cancelled successfully.',
        ]);
    }

    /**
     * Start processing uploaded files.
     */
    public function startProcessing(Request $request, $batchId)
    {
        $batch = InvoiceBulkUpload::where('batch_id', $batchId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        if (! in_array($batch->status, ['uploaded', 'failed'])) {
            return response()->json([
                'success' => false,
                'error' => 'Batch is not ready for processing.',
            ], 400);
        }

        // Handle payment adjustments for Amazon invoices
        $this->processPaymentAdjustments($request, $batch);

        // Mark batch as started
        $batch->markAsStarted();

        // Queue parsing jobs for each uploaded file
        $queuedCount = 0;
        foreach ($batch->files as $file) {
            if (in_array($file->status, ['uploaded', 'failed'])) {
                if ($file->parsing_source === 'gemini') {
                    ParseInvoiceCameraImage::dispatch($file);
                } else {
                    ParseInvoiceFile::dispatch($file);
                }
                $queuedCount++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Processing started for {$queuedCount} file(s).",
            'redirect_url' => route('invoices.bulk-upload.preview', $batch->batch_id),
        ]);
    }

    /**
     * Process payment adjustments for Amazon invoices
     */
    protected function processPaymentAdjustments(Request $request, InvoiceBulkUpload $batch): void
    {
        $paymentAdjustments = $request->input('actual_payment', []);
        $adjustmentService = new AmazonPaymentAdjustmentService;

        foreach ($batch->files as $file) {
            $fileId = $file->id;

            if (isset($paymentAdjustments[$fileId]) && $adjustmentService->needsPaymentAdjustment($file)) {
                $actualPaid = (float) $paymentAdjustments[$fileId];

                // Validate payment amount
                $errors = $adjustmentService->validatePaymentAmount($file, $actualPaid);
                if (! empty($errors)) {
                    // Log validation errors but continue processing
                    Log::warning('Payment adjustment validation failed', [
                        'file_id' => $fileId,
                        'actual_paid' => $actualPaid,
                        'errors' => $errors,
                    ]);

                    continue;
                }

                // Calculate adjusted VAT breakdown
                $adjustmentData = $adjustmentService->adjustPayment($file, $actualPaid);

                // Store adjustment data in parsed_data for later use
                $parsedData = $file->parsed_data ?? [];
                $parsedData['payment_adjusted'] = true;
                $parsedData['actual_payment'] = $actualPaid;
                $parsedData['adjustment_data'] = $adjustmentData;

                $file->parsed_data = $parsedData;
                $file->save();

                Log::info('Payment adjustment stored for Amazon invoice', [
                    'file_id' => $fileId,
                    'original_total' => $adjustmentData['original_total'],
                    'actual_paid' => $actualPaid,
                    'payment_difference' => $adjustmentData['payment_difference'],
                ]);
            }
        }
    }

    /**
     * Check parser configuration.
     */
    public function checkParserConfiguration()
    {
        $parsingService = new InvoiceParsingService;
        $checks = $parsingService->checkConfiguration();

        return response()->json($checks);
    }

    /**
     * Create invoices from files marked for review.
     */
    public function createFromReview($batchId, Request $request)
    {
        $batch = InvoiceBulkUpload::where('batch_id', $batchId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        // Find all files with 'review', 'parsed', or 'amazon_pending' status
        $reviewFiles = InvoiceUploadFile::where('bulk_upload_id', $batch->id)
            ->whereIn('status', ['review', 'parsed', 'amazon_pending'])
            ->get();

        if ($reviewFiles->isEmpty()) {
            return response()->json([
                'success' => false,
                'error' => 'No files ready for invoice creation.',
            ], 400);
        }

        $createdCount = 0;
        $failedCount = 0;
        $errors = [];

        // RTD statistics for Udea invoices
        $rtdStats = [
            'udea_invoices' => 0,
            'resolved_lines' => 0,
            'unresolved_lines' => 0,
            'unresolved_value' => 0.0,
        ];

        // Get payment adjustments from request
        $paymentAdjustments = $request->input('payment_adjustments', []);

        $creationService = new \App\Services\InvoiceCreationService;
        $amazonService = new \App\Services\AmazonInvoiceProcessingService($creationService);

        foreach ($reviewFiles as $file) {
            try {
                // Handle Amazon pending invoices with payment adjustments
                if ($file->status === 'amazon_pending') {
                    $actualPayment = $paymentAdjustments[$file->id] ?? null;

                    if (! $actualPayment) {
                        throw new \Exception('Amazon invoice requires payment amount');
                    }

                    // Create invoice with payment adjustment
                    $invoice = $amazonService->createFromPendingWithPayment($file, (float) $actualPayment);

                    if ($invoice) {
                        $createdCount++;
                        \Log::info('Amazon invoice created with payment adjustment', [
                            'file_id' => $file->id,
                            'invoice_id' => $invoice->id,
                            'actual_payment' => $actualPayment,
                        ]);
                    } else {
                        $failedCount++;
                        $errors[] = "File {$file->original_filename}: Failed to create Amazon invoice";
                    }
                } else {
                    // Handle regular review and parsed files
                    // Check if this is a duplicate that user is intentionally overriding
                    $isDuplicateOverride = $file->error_message && str_contains(strtolower($file->error_message), 'duplicate');

                    if ($isDuplicateOverride) {
                        \Log::info('Creating invoice despite duplicate warning (user override)', [
                            'file_id' => $file->id,
                            'warning' => $file->error_message,
                        ]);
                    }

                    // Create invoice, skipping duplicate check if it's an override
                    $invoice = $creationService->createFromParsedFile($file, $isDuplicateOverride);
                    if ($invoice) {
                        $createdCount++;

                        // Collect RTD stats for Udea invoices
                        if ($invoice->rtd_breakdown && isset($invoice->rtd_breakdown['stats'])) {
                            $rtdStats['udea_invoices']++;
                            $rtdStats['resolved_lines'] += $invoice->rtd_breakdown['stats']['resolved_lines'] ?? 0;
                            $rtdStats['unresolved_lines'] += $invoice->rtd_breakdown['unresolved']['count'] ?? 0;
                            $rtdStats['unresolved_value'] += $invoice->rtd_breakdown['unresolved']['net_total'] ?? 0.0;
                        }
                    } else {
                        $failedCount++;
                        $errors[] = "File {$file->original_filename}: Failed to create invoice";
                    }
                }
            } catch (\Exception $e) {
                $failedCount++;
                $errors[] = "File {$file->original_filename}: ".$e->getMessage();
                \Log::error('Failed to create invoice from review', [
                    'file_id' => $file->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Update batch statistics
        $batch->updateStatistics();

        $message = "$createdCount invoice(s) created successfully.";
        if ($failedCount > 0) {
            $message .= " $failedCount file(s) failed.";
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'created' => $createdCount,
            'failed' => $failedCount,
            'errors' => $errors,
            'rtd_stats' => $rtdStats,
        ]);
    }

    /**
     * Delete a single file from the batch.
     */
    public function deleteFile($batchId, $fileId)
    {
        $batch = InvoiceBulkUpload::where('batch_id', $batchId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $file = InvoiceUploadFile::where('id', $fileId)
            ->where('bulk_upload_id', $batch->id)
            ->firstOrFail();

        // Check if file can be deleted
        $canDelete = in_array($file->status, ['pending', 'uploaded', 'failed']);

        // Allow deletion of 'review' status files only if they have duplicate warnings
        if ($file->status === 'review') {
            $isDuplicate = $file->error_message && str_contains(strtolower($file->error_message), 'duplicate');
            $canDelete = $isDuplicate;
        }

        if (! $canDelete) {
            return response()->json([
                'success' => false,
                'error' => 'Cannot delete a file that has been processed. Only duplicates can be removed from review.',
            ], 400);
        }

        // Delete temp file
        $file->deleteTempFile();

        // Delete record
        $file->delete();

        // Update batch totals
        $batch->total_files--;
        $batch->save();
        $batch->updateStatistics();

        return response()->json([
            'success' => true,
            'message' => 'File removed successfully.',
        ]);
    }

    /**
     * Retry parsing a failed file.
     */
    public function retryFile($batchId, $fileId)
    {
        $batch = InvoiceBulkUpload::where('batch_id', $batchId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $file = InvoiceUploadFile::where('id', $fileId)
            ->where('bulk_upload_id', $batch->id)
            ->firstOrFail();

        // Check if file can be retried
        if ($file->status !== 'failed') {
            return response()->json([
                'success' => false,
                'error' => 'Only failed files can be retried. Current status: '.$file->status,
            ], 400);
        }

        try {
            // Reset file status for retry
            $file->status = 'uploaded';
            $file->error_message = null;
            $file->parsing_confidence = null;
            $file->anomaly_warnings = null;
            $file->supplier_detected = null;
            $file->parsed_data = null;
            $file->parsed_invoice_date = null;
            $file->parsed_invoice_number = null;
            $file->parsed_total_amount = null;
            $file->parsed_vat_data = null;
            $file->is_tax_free = false;
            $file->is_credit_note = false;
            $file->save();

            // Queue new parsing job based on parsing source
            if ($file->parsing_source === 'gemini') {
                ParseInvoiceCameraImage::dispatch($file);
            } else {
                ParseInvoiceFile::dispatch($file);
            }

            Log::info('File queued for retry parsing', [
                'file_id' => $file->id,
                'filename' => $file->original_filename,
                'batch_id' => $batch->batch_id,
                'retried_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'File queued for retry parsing. The page will refresh automatically to show progress.',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retry file parsing', [
                'file_id' => $file->id,
                'error' => $e->getMessage(),
                'batch_id' => $batch->batch_id,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retry file: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display embedded file viewer page.
     */
    public function fileViewer($batchId, $fileId)
    {
        $batch = InvoiceBulkUpload::where('batch_id', $batchId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $file = InvoiceUploadFile::where('id', $fileId)
            ->where('bulk_upload_id', $batch->id)
            ->firstOrFail();

        if (! $file->tempFileExists() || ! $file->isViewable()) {
            abort(404, 'File not found or not viewable');
        }

        $viewUrl = route('invoices.bulk-upload.view-file', [$batchId, $fileId]);
        $downloadUrl = route('invoices.bulk-upload.view-file', [$batchId, $fileId]).'?download=1';

        return view('invoices.bulk-upload-file-viewer', compact('batch', 'file', 'viewUrl', 'downloadUrl'));
    }

    /**
     * Serve the actual file content.
     */
    public function viewFile($batchId, $fileId)
    {
        $batch = InvoiceBulkUpload::where('batch_id', $batchId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $file = InvoiceUploadFile::where('id', $fileId)
            ->where('bulk_upload_id', $batch->id)
            ->firstOrFail();

        if (! $file->tempFileExists() || ! $file->isViewable()) {
            abort(404, 'File not found or not viewable');
        }

        // Check if download is requested
        $isDownload = request()->has('download');
        $disposition = $isDownload ? 'attachment' : 'inline';

        // Handle document conversion for viewing
        if ($file->isDocument()) {
            // Try to get existing converted PDF first
            $convertedPdfPath = $file->getConvertedPdfPath();

            // If no converted PDF exists, convert on-the-fly
            if (! $convertedPdfPath) {
                $conversionService = new \App\Services\DocumentConversionService;
                $tempPath = $file->temp_file_path;
                $outputDir = dirname($tempPath);

                $convertedPdfPath = $conversionService->convertToPdf($tempPath, $outputDir);

                if (! $convertedPdfPath) {
                    abort(500, 'Unable to convert document for viewing');
                }
            }

            $filePath = $convertedPdfPath;
            $contentType = 'application/pdf';
            $displayFilename = pathinfo($file->original_filename, PATHINFO_FILENAME).'.pdf';
        } else {
            // Handle regular files (PDFs and images)
            $filePath = $file->temp_file_path;
            $contentType = $file->mime_type;
            $displayFilename = $file->original_filename;
        }

        if (! file_exists($filePath)) {
            abort(404, 'File not found on disk');
        }

        // Return file response
        $fileContent = file_get_contents($filePath);

        return response($fileContent, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => $disposition.'; filename="'.$displayFilename.'"',
            'Cache-Control' => 'private, max-age=600',
        ]);
    }

    /**
     * Get thumbnails for PDF pages.
     */
    public function getThumbnails($batchId, $fileId)
    {
        $batch = InvoiceBulkUpload::where('batch_id', $batchId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $file = InvoiceUploadFile::where('id', $fileId)
            ->where('bulk_upload_id', $batch->id)
            ->firstOrFail();

        if (! $file->isPdf()) {
            return response()->json([
                'success' => false,
                'error' => 'File is not a PDF',
            ], 400);
        }

        try {
            $pdfSplittingService = new \App\Services\PdfSplittingService;
            $thumbnails = $pdfSplittingService->generateThumbnails($file);

            return response()->json([
                'success' => true,
                'thumbnails' => $thumbnails,
                'total_pages' => count($thumbnails),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate PDF thumbnails', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to generate thumbnails: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Split a PDF file by page ranges.
     */
    public function splitPdf($batchId, $fileId, Request $request)
    {
        $batch = InvoiceBulkUpload::where('batch_id', $batchId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $file = InvoiceUploadFile::where('id', $fileId)
            ->where('bulk_upload_id', $batch->id)
            ->firstOrFail();

        if (! $file->canBeSplit()) {
            return response()->json([
                'success' => false,
                'error' => 'File cannot be split (must be multi-page PDF in uploaded status)',
            ], 400);
        }

        $request->validate([
            'page_ranges' => 'required|array|min:1',
            'page_ranges.*' => 'required|string|regex:/^\d+(-\d+)?$/',
        ], [
            'page_ranges.required' => 'Page ranges are required',
            'page_ranges.*.regex' => 'Page ranges must be in format "1" or "1-3"',
        ]);

        $pageRanges = $request->input('page_ranges');

        // Validate page ranges don't exceed file page count
        foreach ($pageRanges as $range) {
            if (str_contains($range, '-')) {
                [$start, $end] = explode('-', $range, 2);
                $maxPage = max(intval($start), intval($end));
            } else {
                $maxPage = intval($range);
            }

            if ($maxPage > $file->page_count) {
                return response()->json([
                    'success' => false,
                    'error' => "Page range '{$range}' exceeds PDF page count ({$file->page_count})",
                ], 400);
            }
        }

        DB::beginTransaction();

        try {
            $pdfSplittingService = new \App\Services\PdfSplittingService;
            $splitFiles = $pdfSplittingService->splitPdf($file, $pageRanges);

            DB::commit();

            Log::info('PDF split successfully', [
                'original_file_id' => $fileId,
                'split_count' => count($splitFiles),
                'page_ranges' => $pageRanges,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'PDF split successfully',
                'split_count' => count($splitFiles),
                'split_files' => collect($splitFiles)->map(function ($splitFile) {
                    return [
                        'id' => $splitFile->id,
                        'filename' => $splitFile->original_filename,
                        'page_range' => $splitFile->page_range,
                    ];
                })->toArray(),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('PDF splitting failed', [
                'file_id' => $fileId,
                'page_ranges' => $pageRanges,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'PDF splitting failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update parsed data for an upload file
     */
    public function updateParsedData($batchId, $fileId, Request $request)
    {
        // Verify batch belongs to user
        $batch = InvoiceBulkUpload::where('batch_id', $batchId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        // Get the file
        $file = InvoiceUploadFile::where('id', $fileId)
            ->where('bulk_upload_id', $batch->id)
            ->firstOrFail();

        // Validate input
        $validated = $request->validate([
            'supplier_invoice_reference' => 'nullable|string|max:255',
            'invoice_date' => 'nullable|date_format:Y-m-d',
            'supplier_name' => 'nullable|string|max:255',
            'is_tax_free' => 'boolean',
            'is_credit_note' => 'boolean',
            'vat_0_net' => 'nullable|numeric|min:0',
            'vat_9_net' => 'nullable|numeric|min:0',
            'vat_13_5_net' => 'nullable|numeric|min:0',
            'vat_23_net' => 'nullable|numeric|min:0',
        ]);

        try {
            // Update parsed_data JSON field
            $parsedData = $file->parsed_data ?? [];

            // Only update fields that were provided (allows partial updates like VAT-only fix)
            if ($request->has('supplier_invoice_reference')) {
                $parsedData['supplier_invoice_reference'] = $validated['supplier_invoice_reference'] ?? null;
            }
            if ($request->has('invoice_date')) {
                $parsedData['invoice_date'] = $validated['invoice_date'] ?? null;
            }
            if ($request->filled('supplier_name')) {
                $parsedData['supplier_name'] = $validated['supplier_name'];
            }
            $parsedData['is_tax_free'] = $validated['is_tax_free'] ?? ($parsedData['is_tax_free'] ?? false);
            $parsedData['is_credit_note'] = $validated['is_credit_note'] ?? ($parsedData['is_credit_note'] ?? false);

            // Update VAT breakdown
            $vatBreakdown = [
                'vat_0' => [
                    'net' => floatval($validated['vat_0_net'] ?? 0),
                    'vat' => 0.00,
                ],
                'vat_9' => [
                    'net' => floatval($validated['vat_9_net'] ?? 0),
                    'vat' => floatval($validated['vat_9_net'] ?? 0) * 0.09,
                ],
                'vat_13_5' => [
                    'net' => floatval($validated['vat_13_5_net'] ?? 0),
                    'vat' => floatval($validated['vat_13_5_net'] ?? 0) * 0.135,
                ],
                'vat_23' => [
                    'net' => floatval($validated['vat_23_net'] ?? 0),
                    'vat' => floatval($validated['vat_23_net'] ?? 0) * 0.23,
                ],
            ];

            $parsedData['vat_breakdown'] = $vatBreakdown;

            // Calculate total amount
            $totalAmount =
                floatval($validated['vat_0_net'] ?? 0) +
                floatval($validated['vat_9_net'] ?? 0) * 1.09 +
                floatval($validated['vat_13_5_net'] ?? 0) * 1.135 +
                floatval($validated['vat_23_net'] ?? 0) * 1.23;

            $parsedData['total_amount'] = $totalAmount;

            // Save to database
            $file->parsed_data = $parsedData;
            $file->parsed_vat_data = $vatBreakdown;
            if ($request->has('supplier_invoice_reference')) {
                $file->parsed_invoice_number = $validated['supplier_invoice_reference'] ?? null;
            }
            if ($request->has('invoice_date')) {
                $file->parsed_invoice_date = $validated['invoice_date'] ?? null;
            }
            $file->parsed_total_amount = $totalAmount;
            if ($request->filled('supplier_name')) {
                $file->supplier_detected = $validated['supplier_name'];
            }
            $file->is_tax_free = $validated['is_tax_free'] ?? $file->is_tax_free;
            $file->is_credit_note = $validated['is_credit_note'] ?? $file->is_credit_note;

            // Update status to 'review' if it was in failed/uploaded state
            if (in_array($file->status, ['uploaded', 'failed', 'parsing'])) {
                $file->status = 'review';
            }

            $file->save();

            return response()->json([
                'success' => true,
                'message' => 'Parsed data updated successfully',
                'data' => $parsedData,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update parsed data', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to update parsed data: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Parse a Udea invoice file and return debug output
     */
    public function parseUdeaInvoice($batchId, $fileId)
    {
        // Verify batch belongs to user
        $batch = InvoiceBulkUpload::where('batch_id', $batchId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        // Get the file
        $file = InvoiceUploadFile::where('id', $fileId)
            ->where('bulk_upload_id', $batch->id)
            ->firstOrFail();

        // Check if file exists
        if (! $file->tempFileExists()) {
            return response()->json([
                'success' => false,
                'error' => 'File not found on disk',
            ], 404);
        }

        // Check if it's a PDF
        if (! $file->isPdf()) {
            return response()->json([
                'success' => false,
                'error' => 'Only PDF files can be parsed with the Udea invoice parser',
            ], 400);
        }

        try {
            $pdfPath = $file->temp_file_path;
            $parserScript = base_path('scripts/invoice-parser/parsers/invoice_udea.py');
            $venvPython = base_path('scripts/invoice-parser/venv/bin/python');

            // Check if parser script exists
            if (! file_exists($parserScript)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Udea invoice parser script not found',
                ], 500);
            }

            // Use venv Python if available, otherwise fall back to system Python
            $pythonExecutable = file_exists($venvPython) ? $venvPython : 'python3';

            // Log the paths for debugging
            Log::debug('Udea parser paths', [
                'python' => $pythonExecutable,
                'python_exists' => file_exists($pythonExecutable),
                'script' => $parserScript,
                'script_exists' => file_exists($parserScript),
                'pdf_path' => $pdfPath,
                'pdf_exists' => file_exists($pdfPath),
            ]);

            // Run the parser - capture stdout (JSON) and stderr separately
            // Use --verbose flag to get debug info in the JSON metadata, not stderr
            $command = sprintf(
                '%s %s %s 2>/dev/null',
                escapeshellarg($pythonExecutable),
                escapeshellarg($parserScript),
                escapeshellarg($pdfPath)
            );

            $output = shell_exec($command);

            // If output is empty, try again with stderr captured for error diagnosis
            if (empty($output)) {
                $commandWithStderr = sprintf(
                    '%s %s %s 2>&1',
                    escapeshellarg($pythonExecutable),
                    escapeshellarg($parserScript),
                    escapeshellarg($pdfPath)
                );
                $errorOutput = shell_exec($commandWithStderr);

                return response()->json([
                    'success' => false,
                    'error' => 'Parser returned no output',
                    'raw_output' => $errorOutput,
                    'command' => $command,
                ]);
            }

            // Try to parse JSON output
            $result = json_decode($output, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                // If not valid JSON, return raw output for debugging
                return response()->json([
                    'success' => false,
                    'error' => 'Parser did not return valid JSON',
                    'raw_output' => substr($output, 0, 5000),  // Limit output size
                    'json_error' => json_last_error_msg(),
                ]);
            }

            // Log the parsing attempt
            Log::info('Udea invoice parser executed', [
                'file_id' => $fileId,
                'filename' => $file->original_filename,
                'success' => $result['success'] ?? false,
                'invoice_number' => $result['header']['invoice_number'] ?? null,
                'lines_count' => count($result['lines'] ?? []),
                'user_id' => auth()->id(),
            ]);

            // Compute RTD preview from parsed lines
            $rtdPreview = null;
            if (! empty($result['lines'])) {
                $rtdPreview = $this->computeRtdPreview($result);
            }

            return response()->json([
                'success' => true,
                'filename' => $file->original_filename,
                'parser_result' => $result,
                'rtd_preview' => $rtdPreview,
            ]);

        } catch (\Exception $e) {
            Log::error('Udea invoice parser failed', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Parser execution failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Compute RTD preview from parsed Udea data without needing an Invoice record.
     * This is used to give users feedback before the invoice is created.
     */
    private function computeRtdPreview(array $parsedData): array
    {
        $rtdService = app(\App\Services\RtdResolutionService::class);

        // Initialize breakdown structure
        $breakdown = [
            'goods_for_resale' => ['0' => 0, '9' => 0, '13.5' => 0, '23' => 0],
            'excluded' => ['freight' => 0, 'deposits' => 0],
            'unresolved' => ['count' => 0, 'net_total' => 0],
            'stats' => ['total_lines' => 0, 'resolved_lines' => 0, 'excluded_lines' => 0],
        ];
        $issues = [];

        $lines = $parsedData['lines'] ?? [];
        $barrels = $parsedData['barrels'] ?? ['total' => 0];
        $costs = $parsedData['costs'] ?? ['total' => 0];

        // Add excluded totals
        $breakdown['excluded']['freight'] = round($rtdService->parseMonetaryValue($costs['total'] ?? 0), 2);
        $breakdown['excluded']['deposits'] = round($rtdService->parseMonetaryValue($barrels['total'] ?? 0), 2);
        $breakdown['stats']['total_lines'] = count($lines);

        // Process product lines
        foreach ($lines as $line) {
            $lineType = $line['line_type'] ?? 'unknown';
            $articleCode = $line['article_code'] ?? null;
            $lineTotal = $rtdService->parseMonetaryValue($line['line_total'] ?? 0);
            $description = $line['description'] ?? '';

            // Skip non-resale lines
            if ($lineType !== 'product_for_resale') {
                $breakdown['stats']['excluded_lines']++;

                continue;
            }

            // Resolve article code to product and VAT rate
            $resolution = $rtdService->resolveArticleCode($articleCode);

            if ($resolution['status'] === 'no_product_match') {
                $issues[] = [
                    'article_code' => $articleCode,
                    'line_total' => round($lineTotal, 2),
                    'description' => substr($description, 0, 50),
                    'reason' => 'no_product_match',
                    'reason_text' => 'No product linked',
                ];
                $breakdown['unresolved']['count']++;
                $breakdown['unresolved']['net_total'] += $lineTotal;

                continue;
            }

            if ($resolution['status'] === 'no_tax_category') {
                $issues[] = [
                    'article_code' => $articleCode,
                    'line_total' => round($lineTotal, 2),
                    'description' => substr($description, 0, 50),
                    'reason' => 'no_tax_category',
                    'reason_text' => 'No VAT rate set',
                    'product_code' => $resolution['product_code'],
                    'product_name' => $resolution['product_name'] ?? null,
                ];
                $breakdown['unresolved']['count']++;
                $breakdown['unresolved']['net_total'] += $lineTotal;

                continue;
            }

            $vatRate = $resolution['vat_rate'];

            // Check if VAT rate is valid Irish rate
            if (! $rtdService->isValidIrishVatRate($vatRate)) {
                $issues[] = [
                    'article_code' => $articleCode,
                    'line_total' => round($lineTotal, 2),
                    'description' => substr($description, 0, 50),
                    'reason' => 'invalid_vat_rate',
                    'reason_text' => "Invalid rate: {$vatRate}%",
                    'vat_rate_found' => $vatRate,
                    'product_code' => $resolution['product_code'],
                    'product_name' => $resolution['product_name'] ?? null,
                ];
                $breakdown['unresolved']['count']++;
                $breakdown['unresolved']['net_total'] += $lineTotal;

                continue;
            }

            // Add to correct VAT bucket
            $bucketKey = $rtdService->vatRateToBucketKey($vatRate);
            $breakdown['goods_for_resale'][$bucketKey] += $lineTotal;
            $breakdown['stats']['resolved_lines']++;
        }

        // Round all values
        foreach ($breakdown['goods_for_resale'] as $key => $value) {
            $breakdown['goods_for_resale'][$key] = round($value, 2);
        }
        $breakdown['unresolved']['net_total'] = round($breakdown['unresolved']['net_total'], 2);

        // Calculate totals
        $totalResolved = array_sum($breakdown['goods_for_resale']);
        $totalUnresolved = $breakdown['unresolved']['net_total'];
        $resolvedPercentage = $breakdown['stats']['total_lines'] > 0
            ? round(($breakdown['stats']['resolved_lines'] / $breakdown['stats']['total_lines']) * 100, 1)
            : 0;

        return [
            'breakdown' => $breakdown,
            'issues' => $issues,
            'summary' => [
                'total_resolved' => round($totalResolved, 2),
                'total_unresolved' => round($totalUnresolved, 2),
                'resolved_lines' => $breakdown['stats']['resolved_lines'],
                'unresolved_lines' => $breakdown['unresolved']['count'],
                'excluded_lines' => $breakdown['stats']['excluded_lines'],
                'total_lines' => $breakdown['stats']['total_lines'],
                'resolved_percentage' => $resolvedPercentage,
                'is_complete' => $breakdown['unresolved']['count'] === 0,
            ],
        ];
    }

    /**
     * Handle a single camera-captured invoice image upload.
     *
     * Supports lazy batch creation: first photo creates the batch,
     * subsequent photos add to it via the returned batch_id.
     * Each image is immediately dispatched for Gemini AI parsing.
     */
    public function cameraUpload(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:10240',
            'batch_id' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            // Find or create batch
            $batch = null;
            if ($request->filled('batch_id')) {
                $batch = InvoiceBulkUpload::where('batch_id', $request->input('batch_id'))
                    ->where('user_id', auth()->id())
                    ->first();
            }

            if (! $batch) {
                $batch = InvoiceBulkUpload::create([
                    'batch_id' => InvoiceBulkUpload::generateBatchId(),
                    'user_id' => auth()->id(),
                    'total_files' => 0,
                    'status' => 'processing',
                    'metadata' => [
                        'source' => 'camera',
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                    ],
                ]);
            }

            $tempPath = config('invoices.bulk_upload.temp_path');
            $batchFolder = $tempPath.'/'.$batch->batch_id;

            Storage::disk('local')->makeDirectory($batchFolder);
            $fullBatchPath = Storage::disk('local')->path($batchFolder);
            if (! is_writable($fullBatchPath)) {
                chmod($fullBatchPath, 0775);
            }

            $file = $request->file('image');
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension() ?: 'jpg';
            $mimeType = $file->getMimeType();
            $fileSize = $file->getSize();

            $storedName = Str::uuid().'.'.$extension;
            $storedPath = $file->storeAs($batchFolder, $storedName, 'local');

            $fullPath = Storage::disk('local')->path($batchFolder.'/'.$storedName);
            chmod($fullPath, 0664);
            $fileHash = hash_file('sha256', $fullPath);

            $uploadFile = InvoiceUploadFile::create([
                'bulk_upload_id' => $batch->id,
                'original_filename' => $originalName,
                'stored_filename' => $storedName,
                'temp_path' => $storedPath,
                'mime_type' => $mimeType,
                'file_size' => $fileSize,
                'file_hash' => $fileHash,
                'status' => 'uploaded',
                'parsing_source' => 'gemini',
                'upload_progress' => 100,
                'uploaded_at' => now(),
            ]);

            // Update batch file count
            $batch->update([
                'total_files' => $batch->files()->count(),
            ]);

            DB::commit();

            // Dispatch AI parsing job immediately
            ParseInvoiceCameraImage::dispatch($uploadFile);

            Log::info('Camera invoice image uploaded and queued for AI parsing', [
                'file_id' => $uploadFile->id,
                'batch_id' => $batch->batch_id,
                'filename' => $originalName,
            ]);

            return response()->json([
                'success' => true,
                'batch_id' => $batch->batch_id,
                'file_id' => $uploadFile->id,
                'filename' => $originalName,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Camera upload failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to upload image: '.$e->getMessage(),
            ], 500);
        }
    }
}
