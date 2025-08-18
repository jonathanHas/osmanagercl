<?php

namespace App\Http\Controllers;

use App\Models\InvoiceBulkUpload;
use App\Models\InvoiceUploadFile;
use App\Jobs\ParseInvoiceFile;
use App\Services\InvoiceParsingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
            $batchFolder = $tempPath . '/' . $batch->batch_id;

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
                $storedName = Str::uuid() . '.' . $extension;
                $filePath = $batchFolder . '/' . $storedName;
                
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
                    'file_size' => file_exists($fullPath) ? filesize($fullPath) : 0
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
                    'temp_path' => $filePath,
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
            
            // Debug: Check if files still exist after commit
            foreach ($uploadedFiles as $uploadFile) {
                $exists = file_exists($uploadFile->temp_file_path);
                \Log::info('File status after DB commit', [
                    'file_id' => $uploadFile->id,
                    'filename' => $uploadFile->original_filename,
                    'temp_path' => $uploadFile->temp_file_path,
                    'exists_after_commit' => $exists,
                    'file_size' => $exists ? filesize($uploadFile->temp_file_path) : 0
                ]);
            }
            
            // Return response with batch info
            return response()->json([
                'success' => true,
                'batch_id' => $batch->batch_id,
                'total_files' => count($uploadedFiles),
                'message' => count($uploadedFiles) . ' file(s) uploaded successfully. Ready for processing.',
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
                'error' => 'Upload failed: ' . $e->getMessage(),
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
    public function preview($batchId)
    {
        $batch = InvoiceBulkUpload::where('batch_id', $batchId)
            ->where('user_id', auth()->id())
            ->with('files')
            ->firstOrFail();

        return view('invoices.bulk-upload-preview', [
            'batch' => $batch,
            'files' => $batch->files,
        ]);
    }

    /**
     * Cancel a bulk upload batch.
     */
    public function cancel($batchId)
    {
        $batch = InvoiceBulkUpload::where('batch_id', $batchId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        if (!$batch->canBeCancelled()) {
            return response()->json([
                'success' => false,
                'error' => 'This batch cannot be cancelled.',
            ], 400);
        }

        $batch->cancel();

        // Clean up temporary files
        $tempPath = config('invoices.bulk_upload.temp_path') . '/' . $batch->batch_id;
        Storage::disk('local')->deleteDirectory($tempPath);

        return response()->json([
            'success' => true,
            'message' => 'Batch upload cancelled successfully.',
        ]);
    }

    /**
     * Start processing uploaded files.
     */
    public function startProcessing($batchId)
    {
        $batch = InvoiceBulkUpload::where('batch_id', $batchId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        if (!in_array($batch->status, ['uploaded', 'failed'])) {
            return response()->json([
                'success' => false,
                'error' => 'Batch is not ready for processing.',
            ], 400);
        }

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
     * Check parser configuration.
     */
    public function checkParserConfiguration()
    {
        $parsingService = new InvoiceParsingService();
        $checks = $parsingService->checkConfiguration();
        
        return response()->json($checks);
    }

    /**
     * Create invoices from files marked for review.
     */
    public function createFromReview($batchId)
    {
        $batch = InvoiceBulkUpload::where('batch_id', $batchId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        // Find all files with 'review' or 'parsed' status
        $reviewFiles = InvoiceUploadFile::where('bulk_upload_id', $batch->id)
            ->whereIn('status', ['review', 'parsed'])
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
        
        $creationService = new \App\Services\InvoiceCreationService();

        foreach ($reviewFiles as $file) {
            try {
                // Check if this is a duplicate that user is intentionally overriding
                $isDuplicateOverride = $file->error_message && str_contains(strtolower($file->error_message), 'duplicate');
                
                if ($isDuplicateOverride) {
                    \Log::info('Creating invoice despite duplicate warning (user override)', [
                        'file_id' => $file->id,
                        'warning' => $file->error_message
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
            } catch (\Exception $e) {
                $failedCount++;
                $errors[] = "File {$file->original_filename}: " . $e->getMessage();
                \Log::error('Failed to create invoice from review', [
                    'file_id' => $file->id,
                    'error' => $e->getMessage()
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

        // Only allow deletion if file is not processed
        if (!in_array($file->status, ['pending', 'uploaded', 'failed'])) {
            return response()->json([
                'success' => false,
                'error' => 'Cannot delete a file that has been processed.',
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
}