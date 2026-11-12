<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\DeliveryDocument;
use Illuminate\Support\Facades\Storage;

/**
 * Files the source document (invoice PDF, XLSX) of a delivery docket on the
 * private disk and records it against the docket.
 */
class DeliveryDocumentService
{
    public function store(
        Delivery $delivery,
        string $tempFilePath,
        string $originalFilename,
        string $documentType = 'invoice_pdf',
        ?array $parsingMetadata = null,
        ?string $orderNumber = null
    ): DeliveryDocument {
        $storedFilename = DeliveryDocument::generateStoredFilename($originalFilename);
        $filePath = DeliveryDocument::generateFilePath($delivery->id, $storedFilename);

        // Get file info before moving
        $mimeType = mime_content_type($tempFilePath);
        $fileSize = filesize($tempFilePath);
        $fileHash = hash_file('sha256', $tempFilePath);

        // Ensure directory exists
        $directory = dirname($filePath);
        if (! Storage::disk('private')->exists($directory)) {
            Storage::disk('private')->makeDirectory($directory, 0775, true);
        }

        // Copy file to permanent storage
        $fileContents = file_get_contents($tempFilePath);
        Storage::disk('private')->put($filePath, $fileContents);

        // Set proper permissions
        $fullPath = Storage::disk('private')->path($filePath);
        @chmod($fullPath, 0644);

        // Create document record
        return DeliveryDocument::create([
            'delivery_id' => $delivery->id,
            'original_filename' => $originalFilename,
            'order_number' => $orderNumber,
            'stored_filename' => $storedFilename,
            'file_path' => $filePath,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'file_hash' => $fileHash,
            'document_type' => $documentType,
            'is_primary' => $delivery->documents()->count() === 0,
            'uploaded_by' => auth()->id(),
            'parsing_metadata' => $parsingMetadata,
        ]);
    }
}
