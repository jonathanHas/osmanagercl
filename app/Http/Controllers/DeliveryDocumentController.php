<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\DeliveryDocument;

class DeliveryDocumentController extends Controller
{
    /**
     * Get documents for a delivery (AJAX endpoint).
     */
    public function index(Delivery $delivery)
    {
        $documents = $delivery->documents()
            ->with('uploader')
            ->orderBy('is_primary', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'documents' => $documents->map(function ($document) {
                return [
                    'id' => $document->id,
                    'original_filename' => $document->original_filename,
                    'formatted_file_size' => $document->formatted_file_size,
                    'document_type_label' => $document->document_type_label,
                    'is_primary' => $document->is_primary,
                    'is_viewable' => $document->isViewable(),
                    'view_url' => $document->view_url,
                    'viewer_url' => $document->viewer_url,
                    'download_url' => $document->download_url,
                    'uploaded_by' => $document->uploader?->name ?? 'System',
                    'uploaded_at' => $document->uploaded_at->format('d/m/Y H:i'),
                ];
            }),
        ]);
    }

    /**
     * Display the specified document in browser.
     */
    public function view(DeliveryDocument $document)
    {
        if (! $document->exists()) {
            abort(404, 'File not found');
        }

        if (! $document->isViewable()) {
            return $this->download($document);
        }

        $filePath = $document->full_storage_path;
        $extension = strtolower(pathinfo($document->original_filename, PATHINFO_EXTENSION));

        // Force correct MIME type based on file extension
        $mimeType = $document->mime_type;
        if ($extension === 'pdf') {
            $mimeType = 'application/pdf';
        } elseif (in_array($extension, ['jpg', 'jpeg'])) {
            $mimeType = 'image/jpeg';
        } elseif ($extension === 'png') {
            $mimeType = 'image/png';
        } elseif ($extension === 'csv') {
            $mimeType = 'text/csv';
        }

        $headers = [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="'.$document->original_filename.'"',
            'Cache-Control' => 'public, max-age=3600',
            'Pragma' => 'public',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Content-Security-Policy' => "default-src 'none'; object-src 'none'; script-src 'none'; style-src 'unsafe-inline'; frame-ancestors 'self';",
        ];

        if ($extension === 'pdf') {
            $headers['Content-Transfer-Encoding'] = 'binary';
            $headers['Accept-Ranges'] = 'bytes';
        }

        return response()->file($filePath, $headers);
    }

    /**
     * Display document in embedded viewer page.
     */
    public function viewEmbedded(DeliveryDocument $document)
    {
        if (! $document->exists()) {
            abort(404, 'File not found');
        }

        if (! $document->isViewable()) {
            return $this->download($document);
        }

        $viewUrl = route('delivery-documents.view', $document);
        $downloadUrl = route('delivery-documents.download', $document);

        return view('deliveries.document-viewer', compact('document', 'viewUrl', 'downloadUrl'));
    }

    /**
     * Display document in minimal embedded viewer (clean fullscreen view).
     */
    public function viewEmbeddedMinimal(DeliveryDocument $document)
    {
        if (! $document->exists()) {
            abort(404, 'File not found');
        }

        if (! $document->isViewable()) {
            return $this->download($document);
        }

        $viewUrl = route('delivery-documents.view', $document);
        $downloadUrl = route('delivery-documents.download', $document);

        return view('deliveries.document-viewer-minimal', compact('document', 'viewUrl', 'downloadUrl'));
    }

    /**
     * Download the specified document.
     */
    public function download(DeliveryDocument $document)
    {
        if (! $document->exists()) {
            abort(404, 'File not found');
        }

        $filePath = $document->full_storage_path;

        return response()->download($filePath, $document->original_filename, [
            'Content-Type' => $document->mime_type,
        ]);
    }

    /**
     * Remove the specified document.
     */
    public function destroy(DeliveryDocument $document)
    {
        try {
            $filename = $document->original_filename;

            // Delete the file and record (model boot method handles file deletion)
            $document->delete();

            if (request()->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Document '{$filename}' deleted successfully",
                ]);
            }

            return back()->with('success', "Document '{$filename}' deleted successfully");

        } catch (\Exception $e) {
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete document: '.$e->getMessage(),
                ], 500);
            }

            return back()->with('error', 'Failed to delete document: '.$e->getMessage());
        }
    }
}
