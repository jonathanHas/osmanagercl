<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\RtdSubmission;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RtdSubmissionController extends Controller
{
    /**
     * List all RTD submissions.
     */
    public function index()
    {
        $submissions = RtdSubmission::with('creator')
            ->withCount('invoices')
            ->orderByDesc('period_end')
            ->get();

        $unsubmittedCount = Invoice::rtdUnsubmitted()->count();

        return view('rtd.submissions.index', [
            'submissions' => $submissions,
            'unsubmittedCount' => $unsubmittedCount,
        ]);
    }

    /**
     * Show form to create a new submission.
     */
    public function create(Request $request)
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        $query = Invoice::with('supplier')
            ->rtdUnsubmitted()
            ->whereNotNull('rtd_snapshot')
            ->orderBy('invoice_date');

        if ($startDate && $endDate) {
            $query->whereBetween('invoice_date', [$startDate, $endDate]);
        }

        $invoices = $query->get();

        return view('rtd.submissions.create', [
            'invoices' => $invoices,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    /**
     * Store a new submission with selected invoices.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'invoice_ids' => 'required|array|min:1',
            'invoice_ids.*' => 'exists:invoices,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Verify all selected invoices are frozen and unsubmitted
        $invoices = Invoice::whereIn('id', $validated['invoice_ids'])
            ->where('rtd_status', 'frozen')
            ->whereNull('rtd_submission_id')
            ->get();

        if ($invoices->count() !== count($validated['invoice_ids'])) {
            return back()->withErrors(['invoice_ids' => 'Some selected invoices are not eligible for submission.'])->withInput();
        }

        $submission = RtdSubmission::create([
            'period_start' => $validated['period_start'],
            'period_end' => $validated['period_end'],
            'notes' => $validated['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);

        // Link invoices to submission
        Invoice::whereIn('id', $validated['invoice_ids'])
            ->update(['rtd_submission_id' => $submission->id]);

        // Calculate and save totals snapshot
        $submission->calculateTotalsSnapshot();

        return redirect()->route('rtd.submissions.show', $submission)
            ->with('success', "Submission created with {$invoices->count()} invoices.");
    }

    /**
     * Show a specific submission.
     */
    public function show(RtdSubmission $submission)
    {
        $submission->load(['creator', 'invoices.supplier']);

        return view('rtd.submissions.show', [
            'submission' => $submission,
        ]);
    }

    /**
     * Mark a submission as submitted to Revenue.
     */
    public function markSubmitted(Request $request, RtdSubmission $submission)
    {
        if (! $submission->isDraft()) {
            return back()->with('error', 'This submission has already been marked as submitted.');
        }

        $validated = $request->validate([
            'submitted_date' => 'required|date',
            'reference_number' => 'nullable|string|max:100',
        ]);

        $submission->update([
            'status' => 'submitted',
            'submitted_date' => $validated['submitted_date'],
            'reference_number' => $validated['reference_number'] ?? null,
            'updated_by' => auth()->id(),
        ]);

        // Recalculate snapshot to ensure it's current
        $submission->calculateTotalsSnapshot();

        return back()->with('success', 'Submission marked as submitted to Revenue.');
    }

    /**
     * On-screen Revenue-ready report for a submission.
     * All figures come from stored totals_snapshot for audit consistency.
     */
    public function report(RtdSubmission $submission)
    {
        $submission->load(['creator', 'invoices.supplier']);

        $totals = $submission->totals_snapshot ?? [];
        $defaultRates = ['0' => 0, '9' => 0, '13.5' => 0, '23' => 0];

        $goods = $totals['goods'] ?? $defaultRates;
        $service = $totals['service'] ?? $defaultRates;
        $excluded = $totals['excluded'] ?? ['freight' => 0, 'deposits' => 0, 'drs' => 0, 'vat' => 0, 'service_overhead' => 0];
        $goodsTotal = $totals['goods_total'] ?? 0;
        $serviceTotal = $totals['service_total'] ?? 0;
        $excludedTotal = array_sum(array_map('floatval', $excluded));

        // EU/Non-EU acquisition data (subset of T1/T2, not additional)
        $euAcquisitions = $totals['eu_acquisitions'] ?? $defaultRates;
        $euAcquisitionsTotal = $totals['eu_acquisitions_total'] ?? 0;
        $nonEuAcquisitions = $totals['non_eu_acquisitions'] ?? $defaultRates;
        $nonEuAcquisitionsTotal = $totals['non_eu_acquisitions_total'] ?? 0;
        $postponedAccounting = $totals['postponed_accounting'] ?? 0;

        // Split VAT out from other excluded items for reconciliation display
        $vatTotal = floatval($excluded['vat'] ?? 0);
        $excludedNonVat = round($excludedTotal - $vatTotal, 2);

        // Reconciliation: sum of all invoice total_amount vs breakdown totals
        $invoiceTotalSum = $submission->invoices->sum('total_amount');
        $breakdownSum = round($goodsTotal + $serviceTotal + $excludedTotal, 2);

        return view('rtd.submissions.report', [
            'submission' => $submission,
            'goods' => $goods,
            'service' => $service,
            'excluded' => $excluded,
            'goodsTotal' => $goodsTotal,
            'serviceTotal' => $serviceTotal,
            'excludedTotal' => $excludedTotal,
            'vatTotal' => $vatTotal,
            'excludedNonVat' => $excludedNonVat,
            'euAcquisitions' => $euAcquisitions,
            'euAcquisitionsTotal' => $euAcquisitionsTotal,
            'nonEuAcquisitions' => $nonEuAcquisitions,
            'nonEuAcquisitionsTotal' => $nonEuAcquisitionsTotal,
            'postponedAccounting' => $postponedAccounting,
            'invoiceTotalSum' => $invoiceTotalSum,
            'breakdownSum' => $breakdownSum,
        ]);
    }

    /**
     * Download CSV export for a submission with ROS box codes.
     */
    public function exportCsv(RtdSubmission $submission): StreamedResponse
    {
        $submission->load(['creator', 'invoices.supplier']);

        $totals = $submission->totals_snapshot ?? [];
        $defaultRates = ['0' => 0, '9' => 0, '13.5' => 0, '23' => 0];
        $goods = $totals['goods'] ?? $defaultRates;
        $service = $totals['service'] ?? $defaultRates;
        $excluded = $totals['excluded'] ?? ['freight' => 0, 'deposits' => 0, 'drs' => 0, 'vat' => 0, 'service_overhead' => 0];
        $goodsTotal = $totals['goods_total'] ?? 0;
        $serviceTotal = $totals['service_total'] ?? 0;
        $excludedTotal = array_sum(array_map('floatval', $excluded));

        $euAcquisitions = $totals['eu_acquisitions'] ?? $defaultRates;
        $euAcquisitionsTotal = $totals['eu_acquisitions_total'] ?? 0;
        $nonEuAcquisitions = $totals['non_eu_acquisitions'] ?? $defaultRates;
        $nonEuAcquisitionsTotal = $totals['non_eu_acquisitions_total'] ?? 0;
        $postponedAccounting = $totals['postponed_accounting'] ?? 0;

        $periodStart = $submission->period_start->format('d/m/Y');
        $periodEnd = $submission->period_end->format('d/m/Y');
        $status = $submission->isSubmitted() ? 'Submitted' : 'Draft';
        $date = $submission->submitted_date ? $submission->submitted_date->format('d/m/Y') : $submission->created_at->format('d/m/Y');
        $reference = $submission->reference_number ?? 'N/A';

        $filename = 'RTD-' . $submission->period_start->format('Y') . '-submission-' . $submission->id . '.csv';

        return new StreamedResponse(function () use ($submission, $goods, $service, $excluded, $excludedTotal, $goodsTotal, $serviceTotal, $euAcquisitions, $euAcquisitionsTotal, $nonEuAcquisitions, $nonEuAcquisitionsTotal, $postponedAccounting, $periodStart, $periodEnd, $status, $date, $reference) {
            $handle = fopen('php://output', 'w');

            // Header
            fputcsv($handle, ['RTD Submission Report']);
            fputcsv($handle, ["Period: {$periodStart} - {$periodEnd}"]);
            fputcsv($handle, ["Status: {$status}", "Date: {$date}", "Reference: {$reference}"]);
            fputcsv($handle, ["Invoices: {$submission->invoices->count()}"]);
            fputcsv($handle, []);

            // T1 Goods for Resale
            fputcsv($handle, ['GOODS FOR RESALE (T1)']);
            fputcsv($handle, ['ROS Box', 'VAT Rate', 'Net Amount']);
            fputcsv($handle, ['J1', '0%', number_format($goods['0'] ?? 0, 2)]);
            fputcsv($handle, ['BH5', '9%', number_format($goods['9'] ?? 0, 2)]);
            fputcsv($handle, ['AH5', '13.5%', number_format($goods['13.5'] ?? 0, 2)]);
            fputcsv($handle, ['R1', '23%', number_format($goods['23'] ?? 0, 2)]);
            fputcsv($handle, ['Z3', 'T1 Total', number_format($goodsTotal, 2)]);
            fputcsv($handle, []);

            // T2 Other Deductible
            fputcsv($handle, ['OTHER DEDUCTIBLE GOODS & SERVICES (T2)']);
            fputcsv($handle, ['ROS Box', 'VAT Rate', 'Net Amount']);
            fputcsv($handle, ['J2', '0%', number_format($service['0'] ?? 0, 2)]);
            fputcsv($handle, ['BH6', '9%', number_format($service['9'] ?? 0, 2)]);
            fputcsv($handle, ['AH6', '13.5%', number_format($service['13.5'] ?? 0, 2)]);
            fputcsv($handle, ['R2', '23%', number_format($service['23'] ?? 0, 2)]);
            fputcsv($handle, ['Z4', 'T2 Total', number_format($serviceTotal, 2)]);
            fputcsv($handle, []);

            // EU Acquisitions
            if ($euAcquisitionsTotal > 0 || $nonEuAcquisitionsTotal > 0) {
                fputcsv($handle, ['EU / NON-EU ACQUISITIONS (already included in T1/T2)']);
                fputcsv($handle, ['ROS Box', 'Rate', 'Net Amount']);
                if ($euAcquisitionsTotal > 0) {
                    $euBoxes = ['0' => 'ES1', '9' => 'ES2', '13.5' => 'ES3', '23' => 'ES4'];
                    foreach ($euBoxes as $rate => $box) {
                        if (($euAcquisitions[$rate] ?? 0) > 0) {
                            fputcsv($handle, [$box, "{$rate}% EU", number_format($euAcquisitions[$rate], 2)]);
                        }
                    }
                    fputcsv($handle, ['', 'EU Total', number_format($euAcquisitionsTotal, 2)]);
                }
                if ($nonEuAcquisitionsTotal > 0) {
                    $nonEuBoxes = ['0' => 'PA1', '9' => 'PA2', '13.5' => 'PA2', '23' => 'PA3'];
                    foreach (['0', '9', '13.5', '23'] as $rate) {
                        if (($nonEuAcquisitions[$rate] ?? 0) > 0) {
                            fputcsv($handle, [$nonEuBoxes[$rate], "{$rate}% Non-EU", number_format($nonEuAcquisitions[$rate], 2)]);
                        }
                    }
                    fputcsv($handle, ['', 'Non-EU Total', number_format($nonEuAcquisitionsTotal, 2)]);
                    if ($postponedAccounting > 0) {
                        fputcsv($handle, ['', 'Postponed Accounting', number_format($postponedAccounting, 2)]);
                    }
                }
                fputcsv($handle, []);
            }

            // Excluded
            fputcsv($handle, ['EXCLUDED FROM RTD']);
            fputcsv($handle, ['Category', 'Amount']);
            fputcsv($handle, ['Freight', number_format($excluded['freight'] ?? 0, 2)]);
            fputcsv($handle, ['Deposits', number_format($excluded['deposits'] ?? 0, 2)]);
            fputcsv($handle, ['DRS', number_format($excluded['drs'] ?? 0, 2)]);
            fputcsv($handle, ['Non-Retail / Service Overhead', number_format($excluded['service_overhead'] ?? 0, 2)]);
            fputcsv($handle, ['VAT', number_format($excluded['vat'] ?? 0, 2)]);
            fputcsv($handle, ['Excluded Total', number_format($excludedTotal, 2)]);
            fputcsv($handle, []);

            // Invoice Reconciliation (Informational)
            fputcsv($handle, ['INVOICE RECONCILIATION (Informational)']);
            fputcsv($handle, ['Note: Difference is expected - invoice totals include VAT and non-deductible charges excluded from RTD figures']);
            fputcsv($handle, ['T1 Goods (Z3)', number_format($goodsTotal, 2)]);
            fputcsv($handle, ['T2 Other (Z4)', number_format($serviceTotal, 2)]);
            fputcsv($handle, ['Excluded', number_format($excludedTotal, 2)]);
            $breakdownSum = round($goodsTotal + $serviceTotal + $excludedTotal, 2);
            fputcsv($handle, ['RTD Breakdown Total', number_format($breakdownSum, 2)]);
            $invoiceTotalSum = $submission->invoices->sum('total_amount');
            fputcsv($handle, ['Invoice Gross Total', number_format($invoiceTotalSum, 2)]);
            $difference = round($invoiceTotalSum - $breakdownSum, 2);
            fputcsv($handle, ['Difference', number_format($difference, 2)]);
            fputcsv($handle, []);

            // Invoices
            fputcsv($handle, ['INVOICES']);
            fputcsv($handle, ['Invoice #', 'Supplier', 'Date', 'Invoice Total', 'Type', 'Origin', 'RTD Amount']);
            foreach ($submission->invoices->sortBy('invoice_date') as $invoice) {
                $snapshot = $invoice->rtd_snapshot ?? [];
                $isService = ($snapshot['breakdown']['stats']['is_service'] ?? false)
                    || ($invoice->supplier && $invoice->supplier->rtd_classification === 'service_overhead');
                if ($isService) {
                    $breakdown = $snapshot['breakdown']['service_overhead'] ?? $snapshot['breakdown']['goods_for_resale'] ?? [];
                } else {
                    $breakdown = $snapshot['breakdown']['goods_for_resale'] ?? [];
                }
                $rtdTotal = array_sum(array_map('floatval', $breakdown));

                $vatTreatment = $invoice->supplier->vat_treatment ?? 'irish_vat';
                $origin = match ($vatTreatment) {
                    'eu_goods_zero_rated', 'eu_reverse_charge_services' => 'EU',
                    'postponed_import' => 'Non-EU (PA)',
                    'outside_scope_or_exempt' => 'Non-EU',
                    default => 'IE',
                };

                fputcsv($handle, [
                    $invoice->invoice_number,
                    $invoice->supplier_name,
                    $invoice->invoice_date->format('d/m/Y'),
                    number_format($invoice->total_amount, 2),
                    $isService ? 'T2 Service' : 'T1 Goods',
                    $origin,
                    number_format($rtdTotal, 2),
                ]);
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Recalculate totals snapshot for a draft submission.
     * Picks up any supplier metadata changes since the draft was created.
     */
    public function recalculate(RtdSubmission $submission)
    {
        if (! $submission->isDraft()) {
            return back()->with('error', 'Cannot recalculate a submitted submission.');
        }

        $submission->calculateTotalsSnapshot();

        return back()->with('success', 'Totals recalculated with latest supplier data.');
    }

    /**
     * Remove an invoice from a draft submission.
     */
    public function removeInvoice(RtdSubmission $submission, Invoice $invoice)
    {
        if (! $submission->isDraft()) {
            return response()->json(['success' => false, 'message' => 'Cannot modify a submitted submission.'], 422);
        }

        if ($invoice->rtd_submission_id !== $submission->id) {
            return response()->json(['success' => false, 'message' => 'Invoice is not in this submission.'], 422);
        }

        $invoice->update(['rtd_submission_id' => null]);

        // Recalculate totals
        $submission->calculateTotalsSnapshot();

        return response()->json([
            'success' => true,
            'message' => 'Invoice removed from submission.',
            'invoice_count' => $submission->invoices()->count(),
            'totals_snapshot' => $submission->totals_snapshot,
        ]);
    }
}
