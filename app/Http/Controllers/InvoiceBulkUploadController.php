<?php

namespace App\Http\Controllers;

use App\Jobs\ParseInvoiceFile;
use App\Models\InvoiceBulkUpload;
use App\Models\InvoiceUploadFile;
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
                "mimetypes:{$allowedMimes}",
            ],
        ], [
            'files.max' => "You can upload a maximum of {$maxFiles} files at once.",
            'files.*.max' => "Each file must be less than {$maxSizeMB}MB.",
            'files.*.mimetypes' => 'Only PDF, JPG, PNG, and TIFF files are allowed.',
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
                $mimeType = $file->getMimeType();
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
            $pdfSplittingService = new \App\Services\PdfSplittingService();
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
                    'upload_progress', 'error_message', 'parsing_confidence');
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

        return view('invoices.bulk-upload-preview', [
            'batch' => $batch,
            'files' => $files,
            'isAmazonPendingView' => $isAmazonPendingView,
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
                'error' => 'Failed to delete files: ' . $e->getMessage(),
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
                ParseInvoiceFile::dispatch($file);
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

        // Get the full path to the temp file
        $filePath = $file->temp_file_path;

        if (! file_exists($filePath)) {
            abort(404, 'File not found on disk');
        }

        // Check if download is requested
        $isDownload = request()->has('download');
        $disposition = $isDownload ? 'attachment' : 'inline';

        // Return file response
        $fileContent = file_get_contents($filePath);

        return response($fileContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$file->original_filename.'"',
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

        if (!$file->isPdf()) {
            return response()->json([
                'success' => false,
                'error' => 'File is not a PDF',
            ], 400);
        }

        try {
            $pdfSplittingService = new \App\Services\PdfSplittingService();
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
                'error' => 'Failed to generate thumbnails: ' . $e->getMessage(),
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

        if (!$file->canBeSplit()) {
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
            $pdfSplittingService = new \App\Services\PdfSplittingService();
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
                'split_files' => $splitFiles->map(function ($splitFile) {
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
                'error' => 'PDF splitting failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
