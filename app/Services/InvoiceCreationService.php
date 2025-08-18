<?php

namespace App\Services;

use App\Models\AccountingSupplier;
use App\Models\Invoice;
use App\Models\InvoiceUploadFile;
use App\Models\InvoiceVatLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InvoiceCreationService
{
    /**
     * Create an invoice from a parsed upload file
     * @param InvoiceUploadFile $file
     * @param bool $skipDuplicateCheck Whether to skip duplicate checking (for manual review override)
     */
    public function createFromParsedFile(InvoiceUploadFile $file, bool $skipDuplicateCheck = false): ?Invoice
    {
        try {
            return DB::transaction(function () use ($file, $skipDuplicateCheck) {
                // Find or create supplier
                $supplier = $this->findOrCreateSupplier($file->supplier_detected);
                
                // Parse VAT data from JSON
                $vatData = is_string($file->parsed_vat_data) 
                    ? json_decode($file->parsed_vat_data, true) 
                    : $file->parsed_vat_data;
                
                // Handle missing VAT data
                if (empty($vatData)) {
                    $vatData = [
                        'vat_23' => ['net' => 0, 'vat' => 0],
                        'vat_13_5' => ['net' => 0, 'vat' => 0],
                        'vat_9' => ['net' => 0, 'vat' => 0],
                        'vat_0' => ['net' => 0, 'vat' => 0],
                    ];
                }
                
                // Handle both old format (simple floats) and new format (net/vat objects)
                // Check if we have the new format (with 'net' and 'vat' keys)
                if (isset($vatData['vat_0']) && is_array($vatData['vat_0']) && isset($vatData['vat_0']['net'])) {
                    // New format with net and vat amounts
                    $standardNet = $vatData['vat_23']['net'] ?? 0;
                    $standardVat = $vatData['vat_23']['vat'] ?? 0;
                    $reducedNet = $vatData['vat_13_5']['net'] ?? 0;
                    $reducedVat = $vatData['vat_13_5']['vat'] ?? 0;
                    $secondReducedNet = $vatData['vat_9']['net'] ?? 0;
                    $secondReducedVat = $vatData['vat_9']['vat'] ?? 0;
                    $zeroNet = $vatData['vat_0']['net'] ?? 0;
                    $zeroVat = 0; // Always 0 for zero-rated
                } else {
                    // Old format with simple floats (net amounts only)
                    // Calculate VAT amounts based on rates
                    $zeroNet = $vatData['vat_0'] ?? 0;
                    $zeroVat = 0;
                    $secondReducedNet = $vatData['vat_9'] ?? 0;
                    $secondReducedVat = $secondReducedNet * 0.09;
                    $reducedNet = $vatData['vat_13_5'] ?? 0;
                    $reducedVat = $reducedNet * 0.135;
                    $standardNet = $vatData['vat_23'] ?? 0;
                    $standardVat = $standardNet * 0.23;
                }
                
                // Calculate totals
                $subtotal = $standardNet + $reducedNet + $secondReducedNet + $zeroNet;
                $vatAmount = $standardVat + $reducedVat + $secondReducedVat + $zeroVat;
                // Always calculate total from subtotal + VAT for accuracy
                $totalAmount = $subtotal + $vatAmount;
                
                // Check for duplicate invoices (unless explicitly skipped)
                if (!$skipDuplicateCheck) {
                    $duplicateCheck = $this->checkForDuplicateInvoice(
                        $supplier->id,
                        $file->parsed_invoice_date,
                        $totalAmount
                    );
                    
                    if ($duplicateCheck) {
                        // Mark as review if potential duplicate found
                        $file->status = 'review';
                        $file->error_message = 'Potential duplicate invoice found: ' . $duplicateCheck->invoice_number . 
                                              ' (ID: ' . $duplicateCheck->id . ')';
                        $file->save();
                        
                        Log::warning('Potential duplicate invoice detected', [
                            'file_id' => $file->id,
                            'existing_invoice_id' => $duplicateCheck->id,
                            'existing_invoice_number' => $duplicateCheck->invoice_number,
                            'supplier' => $supplier->name,
                            'date' => $file->parsed_invoice_date,
                            'amount' => $totalAmount
                        ]);
                        
                        return null;
                    }
                } else {
                    // Log that we're creating despite potential duplicate
                    Log::info('Creating invoice with duplicate check skipped (manual override)', [
                        'file_id' => $file->id,
                        'supplier' => $supplier->name,
                        'date' => $file->parsed_invoice_date,
                        'amount' => $totalAmount
                    ]);
                }
                
                // Generate invoice number if not parsed
                $invoiceNumber = $file->parsed_invoice_number;
                if (empty($invoiceNumber)) {
                    // Generate a unique invoice number
                    $invoiceNumber = 'BU-' . date('Y') . '-' . str_pad($file->id, 6, '0', STR_PAD_LEFT);
                }
                
                // Create invoice
                $invoice = Invoice::create([
                    'invoice_number' => $invoiceNumber,
                    'supplier_id' => $supplier->id,
                    'supplier_name' => $supplier->name,
                    'invoice_date' => $file->parsed_invoice_date ?: now(),
                    'due_date' => $file->parsed_invoice_date ? 
                        \Carbon\Carbon::parse($file->parsed_invoice_date)->addDays(30) : 
                        now()->addDays(30),
                    'subtotal' => $subtotal,
                    'vat_amount' => $vatAmount,
                    'total_amount' => $totalAmount,
                    
                    // VAT breakdown
                    'standard_net' => $standardNet,
                    'standard_vat' => $standardVat,
                    'reduced_net' => $reducedNet,
                    'reduced_vat' => $reducedVat,
                    'second_reduced_net' => $secondReducedNet,
                    'second_reduced_vat' => $secondReducedVat,
                    'zero_net' => $zeroNet,
                    'zero_vat' => $zeroVat,
                    
                    // Status and metadata
                    'payment_status' => 'pending',
                    'expense_category' => 'bulk_upload',
                    'notes' => "Imported from bulk upload batch: {$file->bulk_upload_id}",
                    
                    // Audit fields
                    'created_by' => auth()->id() ?: $file->created_by,
                    'updated_by' => auth()->id() ?: $file->created_by,
                ]);
                
                // Create VAT lines for better detail tracking
                $this->createVatLines($invoice, $vatData);
                
                // Link the uploaded file to the created invoice
                $file->invoice_id = $invoice->id;
                $file->status = 'completed';
                $file->save();
                
                // Move uploaded file to invoice attachments
                $this->createInvoiceAttachment($invoice, $file);
                
                Log::info('Invoice created from bulk upload', [
                    'invoice_id' => $invoice->id,
                    'file_id' => $file->id,
                    'supplier' => $supplier->name,
                    'total' => $totalAmount,
                ]);
                
                return $invoice;
            });
        } catch (\Exception $e) {
            Log::error('Failed to create invoice from parsed file', [
                'file_id' => $file->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Mark file as failed
            $file->status = 'failed';
            $file->error_message = 'Failed to create invoice: ' . $e->getMessage();
            $file->save();
            
            return null;
        }
    }
    
    /**
     * Find or create supplier based on detected name
     */
    private function findOrCreateSupplier(string $supplierName): AccountingSupplier
    {
        // Clean the supplier name
        $supplierName = trim($supplierName);
        
        // Try exact match first
        $supplier = AccountingSupplier::where('name', $supplierName)->first();
        
        // Try case-insensitive match
        if (!$supplier) {
            $supplier = AccountingSupplier::whereRaw('LOWER(name) = ?', [strtolower($supplierName)])->first();
        }
        
        // Try partial match for common variations
        if (!$supplier) {
            // Remove common suffixes like Ltd, Limited, etc.
            $cleanName = preg_replace('/\s+(ltd|limited|inc|corp|plc|co\.|company)\.?$/i', '', $supplierName);
            $supplier = AccountingSupplier::whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($cleanName) . '%'])->first();
        }
        
        // Create new supplier if not found
        if (!$supplier) {
            $supplier = AccountingSupplier::create([
                'code' => $this->generateSupplierCode($supplierName),
                'name' => $supplierName,
                'supplier_type' => 'other',
                'status' => 'active',
                'is_active' => true,
                'is_bulk_upload_created' => true,
                'created_by' => auth()->id() ?: 1,
                'updated_by' => auth()->id() ?: 1,
            ]);
            
            Log::info('Created new supplier from bulk upload', [
                'supplier_id' => $supplier->id,
                'name' => $supplierName,
            ]);
        }
        
        return $supplier;
    }
    
    /**
     * Generate a unique supplier code
     */
    private function generateSupplierCode(string $name): string
    {
        // Create code from name (first 3-4 letters)
        $nameCode = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 4));
        
        // Add timestamp for uniqueness
        $timestamp = now()->format('ymd');
        $code = $nameCode . $timestamp;
        
        // Ensure uniqueness
        $counter = 1;
        $originalCode = $code;
        while (AccountingSupplier::where('code', $code)->exists()) {
            $code = $originalCode . $counter;
            $counter++;
        }
        
        return $code;
    }
    
    /**
     * Create VAT lines for the invoice
     */
    private function createVatLines(Invoice $invoice, array $vatData): void
    {
        $lineNumber = 1;
        
        // Standard rate (23%)
        if (($vatData['vat_23']['net'] ?? 0) > 0) {
            InvoiceVatLine::create([
                'invoice_id' => $invoice->id,
                'vat_category' => 'STANDARD',
                'net_amount' => $vatData['vat_23']['net'],
                'line_number' => $lineNumber++,
                'created_by' => $invoice->created_by,
            ]);
        }
        
        // Reduced rate (13.5%)
        if (($vatData['vat_13_5']['net'] ?? 0) > 0) {
            InvoiceVatLine::create([
                'invoice_id' => $invoice->id,
                'vat_category' => 'REDUCED',
                'net_amount' => $vatData['vat_13_5']['net'],
                'line_number' => $lineNumber++,
                'created_by' => $invoice->created_by,
            ]);
        }
        
        // Second reduced rate (9%)
        if (($vatData['vat_9']['net'] ?? 0) > 0) {
            InvoiceVatLine::create([
                'invoice_id' => $invoice->id,
                'vat_category' => 'SECOND_REDUCED',
                'net_amount' => $vatData['vat_9']['net'],
                'line_number' => $lineNumber++,
                'created_by' => $invoice->created_by,
            ]);
        }
        
        // Zero rate (0%)
        if (($vatData['vat_0']['net'] ?? 0) > 0) {
            InvoiceVatLine::create([
                'invoice_id' => $invoice->id,
                'vat_category' => 'ZERO',
                'net_amount' => $vatData['vat_0']['net'],
                'line_number' => $lineNumber++,
                'created_by' => $invoice->created_by,
            ]);
        }
    }
    
    /**
     * Create invoice attachment from uploaded file
     */
    private function createInvoiceAttachment(Invoice $invoice, InvoiceUploadFile $file): void
    {
        // Check if temp file exists
        if (!$file->temp_path || !$file->tempFileExists()) {
            Log::warning('Temp file not found for attachment creation', [
                'invoice_id' => $invoice->id,
                'file_id' => $file->id,
                'temp_path' => $file->temp_path
            ]);
            return;
        }
        
        try {
            // Get the full path to the temp file
            $tempFilePath = $file->temp_file_path;
            
            // Generate filename for permanent storage
            $filename = $file->stored_filename ?: ($invoice->id . '_' . \Str::slug($file->original_filename))
                . '.' . pathinfo($file->original_filename, PATHINFO_EXTENSION);
            
            // Create a permanent path for the attachment
            $permanentPath = 'invoices/attachments/' . $invoice->id . '/' . $filename;
            
            // Copy file to permanent location
            $tempFileContent = file_get_contents($tempFilePath);
            Storage::disk('local')->put($permanentPath, $tempFileContent);
            
            // Create attachment record
            $invoice->attachments()->create([
                'original_filename' => $file->original_filename,
                'stored_filename' => $filename,
                'file_path' => $permanentPath,
                'file_size' => $file->file_size,
                'mime_type' => $file->mime_type,
                'file_hash' => $file->file_hash,
                'attachment_type' => 'invoice_scan',  // Changed to valid enum value
                'is_primary' => true,
                'uploaded_by' => $invoice->created_by,
                'uploaded_at' => now(),
            ]);
            
            Log::info('Invoice attachment created successfully', [
                'invoice_id' => $invoice->id,
                'file_id' => $file->id,
                'attachment_path' => $permanentPath
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create invoice attachment', [
                'invoice_id' => $invoice->id,
                'file_id' => $file->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * Check for potential duplicate invoices
     */
    private function checkForDuplicateInvoice(int $supplierId, ?string $invoiceDate, float $totalAmount): ?Invoice
    {
        if (!$invoiceDate) {
            return null;
        }
        
        $dateCarbon = \Carbon\Carbon::parse($invoiceDate);
        
        // Look for invoices from the same supplier within 1 day and similar amount
        $query = Invoice::where('supplier_id', $supplierId)
            ->whereBetween('invoice_date', [
                $dateCarbon->copy()->subDay()->format('Y-m-d'),
                $dateCarbon->copy()->addDay()->format('Y-m-d')
            ]);
            
        // Check for similar amounts (within €0.50)
        $potentialDuplicates = $query->get()->filter(function ($invoice) use ($totalAmount) {
            return abs($invoice->total_amount - $totalAmount) <= 0.50;
        });
        
        return $potentialDuplicates->first();
    }
}