<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class InvoiceAttachmentController extends Controller
{
    /**
     * Store a newly uploaded attachment.
     */
    public function store(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'attachments' => 'required|array|min:1|max:5',
            'attachments.*' => [
                'required',
                'file',
                'max:10240', // 10MB max
                'mimes:pdf,jpg,jpeg,png,gif,webp,txt,doc,docx,xls,xlsx',
            ],
            'descriptions' => 'nullable|array',
            'descriptions.*' => 'nullable|string|max:255',
            'attachment_types' => 'nullable|array',
            'attachment_types.*' => [
                'nullable',
                Rule::in(['invoice_scan', 'receipt', 'delivery_note', 'other'])
            ],
        ]);

        $uploadedAttachments = [];

        try {
            foreach ($validated['attachments'] as $index => $file) {
                $originalFilename = $file->getClientOriginalName();
                $storedFilename = InvoiceAttachment::generateStoredFilename($originalFilename);
                $filePath = InvoiceAttachment::generateFilePath($invoice->id, $storedFilename);
                $mimeType = $file->getMimeType();
                $fileSize = $file->getSize();
                $fileHash = hash_file('sha256', $file->getPathname());

                // Ensure directory exists with proper permissions
                $directory = dirname($filePath);
                if (!Storage::disk('private')->exists($directory)) {
                    Storage::disk('private')->makeDirectory($directory, 0775, true);
                }
                
                // Store the file
                $file->storeAs($directory, basename($filePath), 'private');
                
                // Ensure proper permissions for web server access
                $fullPath = Storage::disk('private')->path($filePath);
                chmod($fullPath, 0644);

                // Create attachment record
                $attachment = InvoiceAttachment::create([
                    'invoice_id' => $invoice->id,
                    'original_filename' => $originalFilename,
                    'stored_filename' => $storedFilename,
                    'file_path' => $filePath,
                    'mime_type' => $mimeType,
                    'file_size' => $fileSize,
                    'file_hash' => $fileHash,
                    'description' => $validated['descriptions'][$index] ?? null,
                    'attachment_type' => $validated['attachment_types'][$index] ?? 'invoice_scan',
                    'is_primary' => $index === 0 && !$invoice->hasAttachments(), // First upload becomes primary if none exist
                    'uploaded_by' => auth()->id(),
                ]);

                $uploadedAttachments[] = $attachment;
            }

            return response()->json([
                'success' => true,
                'message' => count($uploadedAttachments) === 1 ? 
                    'File uploaded successfully' : 
                    count($uploadedAttachments) . ' files uploaded successfully',
                'attachments' => collect($uploadedAttachments)->map(function ($attachment) {
                    return [
                        'id' => $attachment->id,
                        'original_filename' => $attachment->original_filename,
                        'formatted_file_size' => $attachment->formatted_file_size,
                        'attachment_type_label' => $attachment->attachment_type_label,
                        'is_viewable' => $attachment->isViewable(),
                        'view_url' => $attachment->view_url,
                        'viewer_url' => $attachment->viewer_url,
                        'download_url' => $attachment->download_url,
                    ];
                })
            ]);

        } catch (\Exception $e) {
            // Clean up any uploaded files if there was an error
            foreach ($uploadedAttachments as $attachment) {
                $attachment->deleteFile();
                $attachment->delete();
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload files: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified attachment in browser.
     */
    public function view(InvoiceAttachment $attachment)
    {
        if (!$attachment->exists()) {
            abort(404, 'File not found');
        }

        if (!$attachment->isViewable()) {
            return $this->download($attachment);
        }

        $filePath = $attachment->full_storage_path;
        $extension = strtolower(pathinfo($attachment->original_filename, PATHINFO_EXTENSION));
        
        // Force correct MIME type based on file extension
        $mimeType = $attachment->mime_type;
        if ($extension === 'pdf') {
            $mimeType = 'application/pdf';
        } elseif (in_array($extension, ['jpg', 'jpeg'])) {
            $mimeType = 'image/jpeg';
        } elseif ($extension === 'png') {
            $mimeType = 'image/png';
        } elseif ($extension === 'gif') {
            $mimeType = 'image/gif';
        } elseif ($extension === 'webp') {
            $mimeType = 'image/webp';
        }
        
        $headers = [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . $attachment->original_filename . '"',
            'Cache-Control' => 'public, max-age=3600', // Cache for 1 hour
            'Pragma' => 'public',
            'X-Content-Type-Options' => 'nosniff',
        ];
        
        // For PDFs, add additional headers to encourage inline viewing
        if ($extension === 'pdf') {
            $headers['Content-Transfer-Encoding'] = 'binary';
            $headers['Accept-Ranges'] = 'bytes';
        }
        
        return response()->file($filePath, $headers);
    }

    /**
     * Display attachment in embedded viewer page.
     */
    public function viewEmbedded(InvoiceAttachment $attachment)
    {
        if (!$attachment->exists()) {
            abort(404, 'File not found');
        }

        if (!$attachment->isViewable()) {
            return $this->download($attachment);
        }

        $viewUrl = route('invoices.attachments.view', $attachment);
        $downloadUrl = route('invoices.attachments.download', $attachment);
        
        return view('invoices.attachment-viewer', compact('attachment', 'viewUrl', 'downloadUrl'));
    }

    /**
     * Download the specified attachment.
     */
    public function download(InvoiceAttachment $attachment)
    {
        if (!$attachment->exists()) {
            abort(404, 'File not found');
        }

        $filePath = $attachment->full_storage_path;
        
        return response()->download($filePath, $attachment->original_filename, [
            'Content-Type' => $attachment->mime_type,
        ]);
    }

    /**
     * Update attachment details.
     */
    public function update(Request $request, InvoiceAttachment $attachment)
    {
        $validated = $request->validate([
            'description' => 'nullable|string|max:255',
            'attachment_type' => [
                'required',
                Rule::in(['invoice_scan', 'receipt', 'delivery_note', 'other'])
            ],
            'is_primary' => 'boolean',
        ]);

        // If setting as primary, remove primary from other attachments
        if ($validated['is_primary'] ?? false) {
            $attachment->invoice->attachments()
                ->where('id', '!=', $attachment->id)
                ->update(['is_primary' => false]);
        }

        $attachment->update($validated);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Attachment updated successfully',
                'attachment' => [
                    'id' => $attachment->id,
                    'description' => $attachment->description,
                    'attachment_type_label' => $attachment->attachment_type_label,
                    'is_primary' => $attachment->is_primary,
                ]
            ]);
        }

        return back()->with('success', 'Attachment updated successfully');
    }

    /**
     * Remove the specified attachment.
     */
    public function destroy(InvoiceAttachment $attachment)
    {
        try {
            $filename = $attachment->original_filename;
            
            // Delete the file and record
            $attachment->delete(); // Model boot method handles file deletion

            if (request()->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Attachment '{$filename}' deleted successfully"
                ]);
            }

            return back()->with('success', "Attachment '{$filename}' deleted successfully");

        } catch (\Exception $e) {
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete attachment: ' . $e->getMessage()
                ], 500);
            }

            return back()->with('error', 'Failed to delete attachment: ' . $e->getMessage());
        }
    }

    /**
     * Get attachments for an invoice (AJAX endpoint).
     */
    public function index(Invoice $invoice)
    {
        $attachments = $invoice->attachments()
            ->with('uploader')
            ->orderBy('is_primary', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'attachments' => $attachments->map(function ($attachment) {
                return [
                    'id' => $attachment->id,
                    'original_filename' => $attachment->original_filename,
                    'formatted_file_size' => $attachment->formatted_file_size,
                    'attachment_type_label' => $attachment->attachment_type_label,
                    'description' => $attachment->description,
                    'is_primary' => $attachment->is_primary,
                    'is_viewable' => $attachment->isViewable(),
                    'view_url' => $attachment->view_url,
                    'viewer_url' => $attachment->viewer_url,
                    'download_url' => $attachment->download_url,
                    'uploaded_by' => $attachment->uploader?->name ?? 'System',
                    'uploaded_at' => $attachment->uploaded_at->format('d/m/Y H:i'),
                ];
            })
        ]);
    }

    /**
     * Get allowed file types and size limits.
     */
    public function getUploadConfig()
    {
        return response()->json([
            'max_files' => 5,
            'max_size_mb' => 10,
            'allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'txt', 'doc', 'docx', 'xls', 'xlsx'],
            'allowed_types' => [
                'invoice_scan' => 'Invoice Scan',
                'receipt' => 'Receipt', 
                'delivery_note' => 'Delivery Note',
                'other' => 'Other',
            ]
        ]);
    }
}