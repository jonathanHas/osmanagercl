<?php

namespace App\Services;

use App\Models\InvoiceUploadFile;
use Illuminate\Support\Facades\Log;

class AmazonPaymentAdjustmentService
{
    /**
     * Check if an invoice file needs payment adjustment
     */
    public function needsPaymentAdjustment(InvoiceUploadFile $file): bool
    {
        // Check if it's Amazon - all Amazon invoices need payment adjustment
        // regardless of whether EUR VAT was detected by the parser
        if ($file->supplier_detected === 'Amazon' || $file->status === 'amazon_pending') {
            return true;
        }

        // Also check filename for Amazon indicators (for pre-parsing detection)
        if (in_array($file->status, ['uploaded', 'parsing'])) {
            $filename = strtolower($file->original_filename);

            return stripos($filename, 'amazon') !== false;
        }

        return false;
    }

    /**
     * Get adjustment data for display
     */
    public function getAdjustmentData(InvoiceUploadFile $file): array
    {
        if (! $this->needsPaymentAdjustment($file)) {
            return [];
        }

        $parsed = $file->parsed_data ?? [];

        // Handle cases where file hasn't been parsed yet
        if (in_array($file->status, ['uploaded', 'parsing'])) {
            return [
                'eur_vat_amount' => 0,
                'invoice_total' => 0,
                'vat_net_calculated' => 0,
                'needs_adjustment' => true,
                'currency' => 'EUR',
                'currency_note' => '',
                'eur_vat_detected' => false,
                'is_pre_parsing' => true, // Flag to show different UI
            ];
        }

        // Extract all relevant amounts from parsed data
        $eurVatAmount = (float) ($parsed['EUR_VAT_Amount'] ?? 0);
        $gbpVatAmount = (float) ($parsed['GBP_VAT_Amount'] ?? 0);
        $gbpTotal = (float) ($parsed['GBP_Total'] ?? $parsed['total_amount'] ?? 0);
        $eurTotal = (float) ($parsed['EUR_Total_Found'] ?? 0);
        $vatRate = $parsed['VAT_Rate'] ?? '23%';

        return [
            'eur_vat_amount' => $eurVatAmount,
            'gbp_vat_amount' => $gbpVatAmount,
            'gbp_total' => $gbpTotal,
            'eur_total' => $eurTotal,
            'vat_rate' => $vatRate,
            'vat_net_calculated' => $eurVatAmount > 0 ? $eurVatAmount / 0.23 : 0,
            'needs_adjustment' => true,
            'currency' => $parsed['Currency'] ?? 'EUR',
            'currency_note' => $parsed['Currency Note'] ?? '',
            'eur_vat_detected' => $eurVatAmount > 0,
            'gbp_amounts_detected' => $gbpVatAmount > 0 || $gbpTotal > 0,
            'is_pre_parsing' => false,
            'invoice_date' => $parsed['Invoice Date'] ?? ($parsed['invoice_date'] ? date('d/m/Y', strtotime($parsed['invoice_date'])) : null),
            'invoice_number' => $parsed['Invoice Number'] ?? $parsed['invoice_number'] ?? null,
        ];
    }

    /**
     * Calculate adjusted VAT breakdown based on actual payment
     */
    public function adjustPayment(InvoiceUploadFile $file, float $actualPaid): array
    {
        if (! $this->needsPaymentAdjustment($file)) {
            throw new \InvalidArgumentException('File does not need payment adjustment');
        }

        $parsed = $file->parsed_data;
        $vatAmount = (float) ($parsed['EUR_VAT_Amount'] ?? 0);
        $invoiceTotal = (float) ($parsed['EUR_Total_Found'] ?? 0);

        // Calculate correct VAT breakdown
        $netAt23 = $vatAmount / 0.23;
        $expectedTotal = $netAt23 + $vatAmount;
        $paymentDifference = $actualPaid - $expectedTotal;

        Log::info('Amazon payment adjustment calculated', [
            'file_id' => $file->id,
            'invoice_total' => $invoiceTotal,
            'actual_paid' => $actualPaid,
            'vat_amount' => $vatAmount,
            'net_at_23' => $netAt23,
            'payment_difference' => $paymentDifference,
        ]);

        return [
            'vat_breakdown' => [
                'vat_0' => [
                    'net' => max(0, $paymentDifference),
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
                    'vat' => $vatAmount,
                ],
            ],
            'total_amount' => $actualPaid,
            'original_total' => $invoiceTotal,
            'vat_amount' => $vatAmount,
            'payment_difference' => $paymentDifference,
            'adjustment_note' => sprintf(
                'Payment adjusted from €%.2f to €%.2f (€%.2f %s difference)',
                $invoiceTotal,
                $actualPaid,
                abs($paymentDifference),
                $paymentDifference >= 0 ? 'exchange rate' : 'discount/credit'
            ),
        ];
    }

    /**
     * Validate actual payment amount
     */
    public function validatePaymentAmount(InvoiceUploadFile $file, float $actualPaid): array
    {
        $errors = [];

        if ($actualPaid <= 0) {
            $errors[] = 'Payment amount must be positive';
        }

        if ($this->needsPaymentAdjustment($file)) {
            $invoiceTotal = (float) ($file->parsed_data['EUR_Total_Found'] ?? 0);

            // Check if payment is within reasonable range (±50% of invoice total)
            $maxExpected = $invoiceTotal * 1.5;
            $minExpected = $invoiceTotal * 0.5;

            if ($actualPaid > $maxExpected) {
                $errors[] = sprintf(
                    'Payment amount (€%.2f) seems too high compared to invoice total (€%.2f)',
                    $actualPaid,
                    $invoiceTotal
                );
            } elseif ($actualPaid < $minExpected) {
                $errors[] = sprintf(
                    'Payment amount (€%.2f) seems too low compared to invoice total (€%.2f)',
                    $actualPaid,
                    $invoiceTotal
                );
            }
        }

        return $errors;
    }

    /**
     * Preview calculation for UI display
     */
    public function previewCalculation(float $vatAmount, float $actualPaid): array
    {
        $netAt23 = $vatAmount / 0.23;
        $expectedTotal = $netAt23 + $vatAmount;
        $paymentDifference = $actualPaid - $expectedTotal;

        return [
            'vat_23_net' => round($netAt23, 2),
            'vat_0_net' => round(max(0, $paymentDifference), 2),
            'total_calculated' => round($netAt23 + max(0, $paymentDifference), 2),
            'vat_amount' => round($vatAmount, 2),
            'final_total' => round($actualPaid, 2),
            'difference' => round($paymentDifference, 2),
        ];
    }
}
