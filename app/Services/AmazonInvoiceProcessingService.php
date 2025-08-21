<?php

namespace App\Services;

use App\Models\AmazonInvoicePending;
use App\Models\Invoice;
use App\Models\InvoiceUploadFile;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AmazonInvoiceProcessingService
{
    protected InvoiceCreationService $invoiceCreationService;

    public function __construct(InvoiceCreationService $invoiceCreationService)
    {
        $this->invoiceCreationService = $invoiceCreationService;
    }

    /**
     * Create a pending Amazon invoice from a parsed upload file
     */
    public function createPendingFromParsedFile(InvoiceUploadFile $file): AmazonInvoicePending
    {
        if ($file->supplier_detected !== 'Amazon') {
            throw new Exception('File is not detected as Amazon invoice');
        }

        if ($file->status !== 'parsed') {
            throw new Exception('File must be in parsed status');
        }

        // Extract data from parsed file
        $parsedData = $file->parsed_data ?? [];
        $batch = $file->bulkUpload;

        // Create pending invoice record
        $pending = AmazonInvoicePending::create([
            'invoice_upload_file_id' => $file->id,
            'batch_id' => $batch->batch_id,
            'user_id' => $file->bulk_upload_id ? $batch->user_id : auth()->id(),
            'invoice_date' => $file->parsed_invoice_date,
            'invoice_number' => $file->parsed_invoice_number,
            'gbp_amount' => $this->extractGbpAmount($parsedData),
            'parsed_data' => $parsedData,
            'status' => 'pending',
        ]);

        // Update the upload file status
        $file->update(['status' => 'amazon_pending']);

        Log::info('Amazon pending invoice created', [
            'pending_id' => $pending->id,
            'file_id' => $file->id,
            'gbp_amount' => $pending->gbp_amount,
        ]);

        return $pending;
    }

    /**
     * Process a pending invoice by creating the actual invoice
     */
    public function processPendingInvoice(AmazonInvoicePending $pending): Invoice
    {
        if (! $pending->canBeProcessed()) {
            throw new Exception('Pending invoice cannot be processed: payment not entered or invalid status');
        }

        return DB::transaction(function () use ($pending) {
            // Mark as processing
            $pending->markAsProcessing();

            try {
                // Calculate VAT breakdown based on EUR payment
                $vatBreakdown = $this->calculateEurVatBreakdown(
                    $pending->actual_payment_eur,
                    $pending->parsed_data,
                    $pending->gbp_amount
                );

                // Create modified parsed data with EUR amounts
                $modifiedParsedData = $this->createEurParsedData($pending, $vatBreakdown);

                // Temporarily update the upload file with new data
                $uploadFile = $pending->uploadFile;
                $originalParsedData = $uploadFile->parsed_data;
                $originalVatData = $uploadFile->parsed_vat_data;

                $uploadFile->update([
                    'parsed_data' => $modifiedParsedData,
                    'parsed_vat_data' => $vatBreakdown,
                    'parsed_total_amount' => $pending->actual_payment_eur,
                ]);

                // Create the invoice using existing service
                $invoice = $this->invoiceCreationService->createFromParsedFile($uploadFile, true);

                if (! $invoice) {
                    throw new Exception('Failed to create invoice from pending data');
                }

                // Add notes about the conversion
                $conversionNote = $this->buildConversionNote($pending);
                if ($invoice->notes) {
                    $invoice->notes .= "\n\n".$conversionNote;
                } else {
                    $invoice->notes = $conversionNote;
                }
                $invoice->save();

                // Mark pending as completed
                $pending->markAsCompleted();

                Log::info('Amazon pending invoice processed successfully', [
                    'pending_id' => $pending->id,
                    'invoice_id' => $invoice->id,
                    'eur_amount' => $pending->actual_payment_eur,
                    'gbp_amount' => $pending->gbp_amount,
                ]);

                return $invoice;

            } catch (Exception $e) {
                // Restore original data
                if (isset($uploadFile, $originalParsedData)) {
                    $uploadFile->update([
                        'parsed_data' => $originalParsedData,
                        'parsed_vat_data' => $originalVatData,
                    ]);
                }

                // Reset pending status
                $pending->update(['status' => 'pending']);

                Log::error('Failed to process Amazon pending invoice', [
                    'pending_id' => $pending->id,
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }
        });
    }

    /**
     * Calculate VAT breakdown from EUR payment amount and parsed data
     */
    public function calculateEurVatBreakdown(float $eurAmount, ?array $parsedData = null, ?float $gbpAmount = null): array
    {
        // Check if we have EUR VAT detected from the parsed data
        $eurVatAmount = 0.0;
        if ($parsedData && isset($parsedData['EUR_VAT_Amount'])) {
            $eurVatAmount = (float) $parsedData['EUR_VAT_Amount'];
        }

        if ($eurVatAmount > 0) {
            // Calculate proper 23% VAT breakdown
            $netAt23 = $eurVatAmount / 0.23;
            $expectedTotal = $netAt23 + $eurVatAmount;
            $exchangeDifference = max(0, $eurAmount - $expectedTotal);

            return [
                'vat_0' => [
                    'net' => $exchangeDifference,
                    'vat' => 0.00,
                ],
                'vat_9' => [
                    'net' => 0.00,
                    'vat' => 0.00,
                ],
                'vat_13_5' => [
                    'net' => 0.00,
                    'vat' => 0.00,
                ],
                'vat_23' => [
                    'net' => $netAt23,
                    'vat' => $eurVatAmount,
                ],
            ];
        }

        // Fallback: if no EUR VAT detected, treat entire amount as 0% VAT
        return [
            'vat_0' => [
                'net' => $eurAmount,
                'vat' => 0.00,
            ],
            'vat_9' => [
                'net' => 0.00,
                'vat' => 0.00,
            ],
            'vat_13_5' => [
                'net' => 0.00,
                'vat' => 0.00,
            ],
            'vat_23' => [
                'net' => 0.00,
                'vat' => 0.00,
            ],
        ];
    }

    /**
     * Validate EUR payment amount
     */
    public function validateEurPayment(float $eurAmount, ?float $gbpAmount = null): array
    {
        $errors = [];

        if ($eurAmount <= 0) {
            $errors[] = 'EUR payment amount must be positive';
        }

        if ($eurAmount > 10000) {
            $errors[] = 'EUR payment amount seems unusually high (>€10,000)';
        }

        // If we have GBP amount, check exchange rate is reasonable
        if ($gbpAmount && $gbpAmount > 0) {
            $exchangeRate = $eurAmount / $gbpAmount;

            // Reasonable EUR/GBP range: 1.10 to 1.30 (as of 2025)
            if ($exchangeRate < 1.05) {
                $errors[] = 'Exchange rate seems too low (EUR/GBP < 1.05)';
            } elseif ($exchangeRate > 1.35) {
                $errors[] = 'Exchange rate seems too high (EUR/GBP > 1.35)';
            }
        }

        return $errors;
    }

    /**
     * Extract GBP amount from parsed data
     */
    protected function extractGbpAmount(array $parsedData): ?float
    {
        // Try different possible fields
        $possibleFields = [
            'total_amount',
            'GBP_Amount',
            'Total',
        ];

        foreach ($possibleFields as $field) {
            if (isset($parsedData[$field]) && is_numeric($parsedData[$field])) {
                return (float) $parsedData[$field];
            }
        }

        // If no direct amount, try to calculate from VAT breakdown
        if (isset($parsedData['vat_breakdown']) && is_array($parsedData['vat_breakdown'])) {
            $total = 0;
            foreach ($parsedData['vat_breakdown'] as $rate => $amounts) {
                if (is_array($amounts) && isset($amounts['net'])) {
                    $total += (float) $amounts['net'];
                    if (isset($amounts['vat'])) {
                        $total += (float) $amounts['vat'];
                    }
                }
            }

            return $total > 0 ? $total : null;
        }

        return null;
    }

    /**
     * Create EUR-based parsed data for invoice creation
     */
    protected function createEurParsedData(AmazonInvoicePending $pending, array $vatBreakdown): array
    {
        $originalData = $pending->parsed_data;

        // Create new data with EUR amounts
        $eurData = array_merge($originalData, [
            'total_amount' => $pending->actual_payment_eur,
            'Currency' => 'EUR',
            'Currency Note' => sprintf(
                'Converted from GBP £%.2f to EUR €%.2f (rate: %.4f)',
                $pending->gbp_amount,
                $pending->actual_payment_eur,
                $pending->exchange_rate ?? 0
            ),
            'vat_breakdown' => $vatBreakdown,
        ]);

        return $eurData;
    }

    /**
     * Build conversion note for invoice
     */
    protected function buildConversionNote(AmazonInvoicePending $pending): string
    {
        $note = "Amazon Invoice Currency Conversion:\n";
        $note .= "Original GBP Amount: {$pending->formatted_gbp_amount}\n";
        $note .= "Actual EUR Payment: {$pending->formatted_eur_amount}\n";

        if ($pending->exchange_rate) {
            $note .= "Exchange Rate: {$pending->exchange_rate}\n";
        }

        $userName = $pending->paymentEnteredBy ? $pending->paymentEnteredBy->name : 'Unknown';
        $note .= "Converted by: {$userName}\n";

        $convertedDate = $pending->payment_entered_at ? $pending->payment_entered_at->format('d/m/Y H:i') : 'Not set';
        $note .= "Converted on: {$convertedDate}";

        if ($pending->notes) {
            $note .= "\nNotes: {$pending->notes}";
        }

        return $note;
    }

    /**
     * Build conversion note for invoice from pending record and actual payment
     */
    protected function buildConversionNoteFromPending(AmazonInvoicePending $pending, float $actualPayment): string
    {
        $note = "Amazon Invoice Payment Adjustment:\n";

        if ($pending->gbp_amount) {
            $note .= 'Original GBP Amount: £'.number_format($pending->gbp_amount, 2)."\n";
        }

        $note .= 'Actual EUR Payment: €'.number_format($actualPayment, 2)."\n";

        if ($pending->gbp_amount && $actualPayment > 0) {
            $exchangeRate = $actualPayment / $pending->gbp_amount;
            $note .= 'Exchange Rate: '.number_format($exchangeRate, 4)."\n";
        }

        $userName = auth()->user() ? auth()->user()->name : 'System';
        $note .= "Adjusted by: {$userName}\n";

        $convertedDate = now()->format('d/m/Y H:i');
        $note .= "Adjusted on: {$convertedDate}";

        return $note;
    }

    /**
     * Get pending invoices summary for dashboard
     */
    public function getPendingSummary(?int $userId = null): array
    {
        $query = AmazonInvoicePending::pending();

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $pending = $query->get();

        return [
            'total_count' => $pending->count(),
            'total_gbp_amount' => $pending->sum('gbp_amount'),
            'oldest_days' => $pending->max('days_pending') ?? 0,
            'recent_count' => $pending->where('created_at', '>=', now()->subDays(7))->count(),
        ];
    }

    /**
     * Create an invoice from Amazon pending with payment adjustment
     */
    public function createFromPendingWithPayment(InvoiceUploadFile $file, float $actualPayment): ?Invoice
    {
        if ($file->status !== 'amazon_pending') {
            throw new Exception('File must be in amazon_pending status');
        }

        // Get the pending record
        $pending = AmazonInvoicePending::where('invoice_upload_file_id', $file->id)->first();
        if (! $pending) {
            throw new Exception('Amazon pending record not found');
        }

        return DB::transaction(function () use ($file, $pending, $actualPayment) {
            // Calculate VAT breakdown using the corrected method
            $vatBreakdown = $this->calculateEurVatBreakdown($actualPayment, $pending->parsed_data, $pending->gbp_amount);

            // Create modified parsed data with adjusted amounts
            $modifiedParsedData = array_merge($file->parsed_data ?? [], [
                'total_amount' => $actualPayment,
                'vat_breakdown' => $vatBreakdown,
                'actual_payment_eur' => $actualPayment,
                'original_gbp_total' => $pending->gbp_amount,
                'amazon_payment_adjusted' => true,
            ]);

            // Temporarily update the upload file for invoice creation
            $originalParsedData = $file->parsed_data;
            $originalVatData = $file->parsed_vat_data;

            $file->update([
                'parsed_data' => $modifiedParsedData,
                'parsed_vat_data' => $vatBreakdown,
                'parsed_total_amount' => $actualPayment,
            ]);

            try {
                // Create the invoice using the creation service
                $invoice = $this->invoiceCreationService->createFromParsedFile($file, false);

                if ($invoice) {
                    // Add conversion note to the invoice
                    $conversionNote = $this->buildConversionNoteFromPending($pending, $actualPayment);
                    if ($invoice->notes) {
                        $invoice->notes .= "\n\n".$conversionNote;
                    } else {
                        $invoice->notes = $conversionNote;
                    }
                    $invoice->save();

                    // Update file status to completed
                    $file->update([
                        'status' => 'completed',
                        'invoice_id' => $invoice->id,
                    ]);

                    // Update pending record
                    $pending->update([
                        'status' => 'completed',
                        'invoice_id' => $invoice->id,
                        'actual_payment' => $actualPayment,
                        'processed_at' => now(),
                    ]);

                    Log::info('Amazon invoice created with payment adjustment', [
                        'file_id' => $file->id,
                        'pending_id' => $pending->id,
                        'invoice_id' => $invoice->id,
                        'actual_payment' => $actualPayment,
                        'original_gbp' => $pending->gbp_amount,
                    ]);
                } else {
                    // Restore original data if invoice creation failed
                    $file->update([
                        'parsed_data' => $originalParsedData,
                        'parsed_vat_data' => $originalVatData,
                    ]);
                    throw new Exception('Failed to create invoice');
                }

                return $invoice;
            } catch (Exception $e) {
                // Restore original data if anything failed
                $file->update([
                    'parsed_data' => $originalParsedData,
                    'parsed_vat_data' => $originalVatData,
                ]);
                throw $e;
            }
        });
    }
}
