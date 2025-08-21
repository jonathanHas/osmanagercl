<?php

namespace App\Http\Controllers;

use App\Models\AmazonInvoicePending;
use App\Services\AmazonInvoiceProcessingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;

class AmazonPendingInvoiceController extends Controller
{
    protected AmazonInvoiceProcessingService $amazonService;

    public function __construct(AmazonInvoiceProcessingService $amazonService)
    {
        $this->amazonService = $amazonService;
    }

    /**
     * Display a listing of pending Amazon invoices
     * Redirects to the unified bulk-upload Amazon pending view
     */
    public function index()
    {
        // Redirect to the unified bulk-upload Amazon pending view
        return redirect()->route('invoices.bulk-upload.amazon-pending');
    }

    /**
     * Display the specified pending invoice for payment entry
     */
    public function show(AmazonInvoicePending $pending)
    {
        $pending->load(['user', 'paymentEnteredBy', 'uploadFile']);

        // Get some context from parsed data
        $parsedData = $pending->parsed_data ?? [];

        return view('invoices.amazon-pending.show', [
            'pending' => $pending,
            'parsedData' => $parsedData,
        ]);
    }

    /**
     * Display the embedded invoice viewer
     */
    public function viewer(AmazonInvoicePending $pending)
    {
        $pending->load(['user', 'paymentEnteredBy', 'uploadFile.bulkUpload']);

        $viewUrl = route('amazon-pending.view-invoice', $pending);
        $downloadUrl = route('amazon-pending.view-invoice', $pending).'?download=1';

        return view('invoices.amazon-pending.viewer', compact('pending', 'viewUrl', 'downloadUrl'));
    }

    /**
     * View the original invoice file
     */
    public function viewInvoice(AmazonInvoicePending $pending)
    {
        $uploadFile = $pending->uploadFile;

        if (! $uploadFile) {
            abort(404, 'Invoice file not found');
        }

        // Try to find the file in various possible locations
        $possiblePaths = [
            // Current system path
            $uploadFile->file_path,
            // Most common temp upload path (batch_id already includes BATCH- prefix)
            'temp/invoices/'.$uploadFile->bulkUpload->batch_id.'/'.$uploadFile->stored_filename,
            // Legacy path with private prefix (just in case)
            'private/temp/invoices/'.$uploadFile->bulkUpload->batch_id.'/'.$uploadFile->stored_filename,
        ];

        $filePath = null;
        foreach ($possiblePaths as $path) {
            if ($path && Storage::exists($path)) {
                $filePath = $path;
                break;
            }
            // Fallback to file_exists since Storage::exists sometimes fails
            if ($path) {
                $fullPath = Storage::path($path);
                if (file_exists($fullPath)) {
                    $filePath = $path;
                    break;
                }
            }
        }

        // Last resort: search through all invoice files
        if (! $filePath) {
            $searchPaths = Storage::allFiles('temp/invoices');
            foreach ($searchPaths as $searchPath) {
                if (basename($searchPath) === $uploadFile->stored_filename) {
                    $filePath = $searchPath;
                    break;
                }
            }
        }

        if (! $filePath) {
            abort(404, 'Invoice file not found on disk');
        }

        // Double check the file exists using both methods
        $fullPath = Storage::path($filePath);
        if (! Storage::exists($filePath) && ! file_exists($fullPath)) {
            abort(404, 'Invoice file not accessible');
        }

        // Get the file content and return as response
        $fileContent = Storage::get($filePath);
        $fileName = $uploadFile->original_filename;

        // Check if download is requested
        $isDownload = request()->has('download');
        $disposition = $isDownload ? 'attachment' : 'inline';

        return Response::make($fileContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$fileName.'"',
            'Cache-Control' => 'private, max-age=600',
        ]);
    }

    /**
     * Update the payment information for a pending invoice
     */
    public function updatePayment(Request $request, AmazonInvoicePending $pending)
    {
        // Validate the EUR payment
        $request->validate([
            'actual_payment_eur' => [
                'required',
                'numeric',
                'min:0.01',
                'max:50000', // Reasonable maximum
            ],
            'notes' => 'nullable|string|max:1000',
        ]);

        $eurAmount = (float) $request->actual_payment_eur;

        // Validate payment amount using the service
        $validationErrors = $this->amazonService->validateEurPayment($eurAmount, $pending->gbp_amount);

        if (! empty($validationErrors)) {
            return back()
                ->withErrors(['actual_payment_eur' => $validationErrors])
                ->withInput();
        }

        // Save payment information
        $pending->markPaymentEntered($eurAmount, $request->notes);

        Log::info('Amazon pending payment entered', [
            'pending_id' => $pending->id,
            'eur_amount' => $eurAmount,
            'user_id' => auth()->id(),
        ]);

        return redirect()
            ->route('amazon-pending.show', $pending)
            ->with('success', 'Payment information saved successfully! You can now process this invoice.');
    }

    /**
     * Process the pending invoice and create the actual invoice
     */
    public function process(AmazonInvoicePending $pending)
    {
        if (! $pending->canBeProcessed()) {
            return back()
                ->with('error', 'This invoice cannot be processed yet. Please enter payment information first.');
        }

        try {
            $invoice = $this->amazonService->processPendingInvoice($pending);

            return redirect()
                ->route('invoices.show', $invoice)
                ->with('success', "Invoice #{$invoice->invoice_number} created successfully from Amazon payment!");

        } catch (\Exception $e) {
            Log::error('Failed to process Amazon pending invoice', [
                'pending_id' => $pending->id,
                'error' => $e->getMessage(),
            ]);

            return back()
                ->with('error', 'Failed to create invoice: '.$e->getMessage());
        }
    }

    /**
     * Cancel a pending invoice
     */
    public function cancel(AmazonInvoicePending $pending)
    {
        $pending->markAsCancelled();

        // Update the upload file status back to review so user can handle manually
        $pending->uploadFile->update(['status' => 'review']);

        return redirect()
            ->route('amazon-pending.index')
            ->with('success', 'Pending invoice cancelled. File moved back to review status.');
    }

    /**
     * Bulk process multiple pending invoices that have payment entered
     */
    public function bulkProcess(Request $request)
    {
        $pendingIds = $request->input('pending_ids', []);

        if (empty($pendingIds)) {
            return back()->with('error', 'No invoices selected for processing.');
        }

        $pendingInvoices = AmazonInvoicePending::whereIn('id', $pendingIds)
            ->where('status', 'pending')
            ->whereNotNull('actual_payment_eur')
            ->get();

        if ($pendingInvoices->isEmpty()) {
            return back()->with('error', 'No valid pending invoices found for processing.');
        }

        $processed = 0;
        $failed = 0;
        $errors = [];

        foreach ($pendingInvoices as $pending) {
            try {
                $this->amazonService->processPendingInvoice($pending);
                $processed++;
            } catch (\Exception $e) {
                $failed++;
                $errors[] = "Invoice {$pending->id}: ".$e->getMessage();

                Log::error('Bulk process failed for pending invoice', [
                    'pending_id' => $pending->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $message = "{$processed} invoice(s) processed successfully.";
        if ($failed > 0) {
            $message .= " {$failed} failed.";
        }

        $flashType = $failed > 0 ? 'warning' : 'success';

        return redirect()
            ->route('amazon-pending.index')
            ->with($flashType, $message)
            ->with('bulk_errors', $errors);
    }

    /**
     * Get pending invoices summary for AJAX requests
     */
    public function summary()
    {
        $summary = $this->amazonService->getPendingSummary(auth()->id());

        return response()->json($summary);
    }

    /**
     * Preview VAT calculation for given payment amount (AJAX)
     */
    public function previewCalculation(Request $request, AmazonInvoicePending $pending)
    {
        $request->validate([
            'eur_amount' => 'required|numeric|min:0.01',
        ]);

        $eurAmount = (float) $request->eur_amount;

        // Calculate VAT breakdown
        $vatBreakdown = $this->amazonService->calculateEurVatBreakdown($eurAmount, $pending->parsed_data, $pending->gbp_amount);

        // Calculate totals
        $totalNet = array_sum(array_column($vatBreakdown, 'net'));
        $totalVat = array_sum(array_column($vatBreakdown, 'vat'));

        return response()->json([
            'vat_breakdown' => $vatBreakdown,
            'total_net' => round($totalNet, 2),
            'total_vat' => round($totalVat, 2),
            'total_amount' => round($totalNet + $totalVat, 2),
            'exchange_rate' => $pending->gbp_amount ? round($eurAmount / $pending->gbp_amount, 4) : null,
        ]);
    }
}
