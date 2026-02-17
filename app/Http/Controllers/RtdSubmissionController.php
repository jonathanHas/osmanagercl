<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\RtdSubmission;
use App\Models\VatReturn;
use App\Services\SalesAccountingImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $availableInvoices = collect();
        if ($submission->isDraft()) {
            $availableInvoices = Invoice::with('supplier')
                ->rtdUnsubmitted()
                ->whereNotNull('rtd_snapshot')
                ->orderBy('invoice_date')
                ->get();
        }

        return view('rtd.submissions.show', [
            'submission' => $submission,
            'availableInvoices' => $availableInvoices,
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

        // Sales data for ROS Section 1 (from VAT returns)
        $sales = $totals['sales'] ?? $defaultRates;
        $salesTotal = $totals['sales_total'] ?? 0;
        $salesSource = $totals['sales_source'] ?? null;
        $vatReturnsCount = $totals['vat_returns_count'] ?? 0;

        // Combined acquisitions for ROS Section 2 (EU + Non-EU)
        $combinedAcquisitions = [
            '0' => ($euAcquisitions['0'] ?? 0) + ($nonEuAcquisitions['0'] ?? 0),
            '9' => ($euAcquisitions['9'] ?? 0) + ($nonEuAcquisitions['9'] ?? 0),
            '13.5' => ($euAcquisitions['13.5'] ?? 0) + ($nonEuAcquisitions['13.5'] ?? 0),
            '23' => ($euAcquisitions['23'] ?? 0) + ($nonEuAcquisitions['23'] ?? 0),
        ];
        $combinedAcquisitionsTotal = $euAcquisitionsTotal + $nonEuAcquisitionsTotal;

        // Split VAT out from other excluded items for reconciliation display
        $vatTotal = floatval($excluded['vat'] ?? 0);
        $excludedNonVat = round($excludedTotal - $vatTotal, 2);

        // Reconciliation: sum of all invoice total_amount vs breakdown totals
        $invoiceTotalSum = $submission->invoices->sum('total_amount');
        $breakdownSum = round($goodsTotal + $serviceTotal + $excludedTotal, 2);

        return view('rtd.submissions.report', [
            'submission' => $submission,
            'sales' => $sales,
            'salesTotal' => $salesTotal,
            'salesSource' => $salesSource,
            'vatReturnsCount' => $vatReturnsCount,
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
            'combinedAcquisitions' => $combinedAcquisitions,
            'combinedAcquisitionsTotal' => $combinedAcquisitionsTotal,
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

        // Sales data for Section 1
        $sales = $totals['sales'] ?? $defaultRates;
        $salesTotal = $totals['sales_total'] ?? 0;

        $periodStart = $submission->period_start->format('d/m/Y');
        $periodEnd = $submission->period_end->format('d/m/Y');
        $status = $submission->isSubmitted() ? 'Submitted' : 'Draft';
        $date = $submission->submitted_date ? $submission->submitted_date->format('d/m/Y') : $submission->created_at->format('d/m/Y');
        $reference = $submission->reference_number ?? 'N/A';

        $filename = 'RTD-'.$submission->period_start->format('Y').'-submission-'.$submission->id.'.csv';

        return new StreamedResponse(function () use ($submission, $goods, $service, $excluded, $excludedTotal, $goodsTotal, $serviceTotal, $euAcquisitions, $euAcquisitionsTotal, $nonEuAcquisitions, $nonEuAcquisitionsTotal, $postponedAccounting, $sales, $salesTotal, $periodStart, $periodEnd, $status, $date, $reference) {
            $handle = fopen('php://output', 'w');

            // Header
            fputcsv($handle, ['RTD Submission Report']);
            fputcsv($handle, ["Period: {$periodStart} - {$periodEnd}"]);
            fputcsv($handle, ["Status: {$status}", "Date: {$date}", "Reference: {$reference}"]);
            fputcsv($handle, ["Invoices: {$submission->invoices->count()}"]);
            fputcsv($handle, []);

            // Combined acquisitions for Section 2
            $combinedAcquisitions = [
                '0' => ($euAcquisitions['0'] ?? 0) + ($nonEuAcquisitions['0'] ?? 0),
                '9' => ($euAcquisitions['9'] ?? 0) + ($nonEuAcquisitions['9'] ?? 0),
                '13.5' => ($euAcquisitions['13.5'] ?? 0) + ($nonEuAcquisitions['13.5'] ?? 0),
                '23' => ($euAcquisitions['23'] ?? 0) + ($nonEuAcquisitions['23'] ?? 0),
            ];
            $combinedAcquisitionsTotal = $euAcquisitionsTotal + $nonEuAcquisitionsTotal;

            // Section 1: Goods and/or Services (Sales)
            fputcsv($handle, ['SECTION 1: GOODS AND/OR SERVICES (SALES)']);
            fputcsv($handle, ['ROS Box', 'Description', 'Net Amount']);
            fputcsv($handle, ['E3', 'Exempt', '0.00']);
            fputcsv($handle, ['D4', '0% Exp', '0.00']);
            fputcsv($handle, ['D1', '0% Home', number_format($sales['0'] ?? 0, 2)]);
            fputcsv($handle, ['C5', '4.8%', '0.00']);
            fputcsv($handle, ['BC5', '9%', number_format($sales['9'] ?? 0, 2)]);
            fputcsv($handle, ['AC5', '13.5%', number_format($sales['13.5'] ?? 0, 2)]);
            fputcsv($handle, ['B5', 'FlatFarm', '0.00']);
            fputcsv($handle, ['P1', 'Std Rate', number_format($sales['23'] ?? 0, 2)]);
            fputcsv($handle, ['Z1', 'Total', number_format($salesTotal, 2)]);
            fputcsv($handle, []);

            // Section 2: Acquisitions from the EU and Non-EU
            fputcsv($handle, ['SECTION 2: ACQUISITIONS FROM THE EU AND NON-EU']);
            fputcsv($handle, ['ROS Box', 'Description', 'Net Amount']);
            fputcsv($handle, ['E4', 'Exempt', '0.00']);
            fputcsv($handle, ['D2', '0% Home', number_format($combinedAcquisitions['0'], 2)]);
            fputcsv($handle, ['C6', '4.8%', '0.00']);
            fputcsv($handle, ['BC6', '9%', number_format($combinedAcquisitions['9'], 2)]);
            fputcsv($handle, ['AC6', '13.5%', number_format($combinedAcquisitions['13.5'], 2)]);
            fputcsv($handle, ['B6', 'FlatFarm', '0.00']);
            fputcsv($handle, ['P2', 'Std Rate', number_format($combinedAcquisitions['23'], 2)]);
            fputcsv($handle, ['Z2', 'Total', number_format($combinedAcquisitionsTotal, 2)]);
            fputcsv($handle, ['PA2', 'Postponed Accounting', number_format($postponedAccounting, 2)]);
            fputcsv($handle, ['', '', 'Figures already included in T1/T2 totals below']);
            fputcsv($handle, []);

            // Section 3: Goods or Services Purchased for Resale
            fputcsv($handle, ['SECTION 3: GOODS OR SERVICES PURCHASED FOR RESALE']);
            fputcsv($handle, ['ROS Box', 'Description', 'Net Amount']);
            fputcsv($handle, ['E5', 'Exempt', '0.00']);
            fputcsv($handle, ['J1', '0% Home', number_format($goods['0'] ?? 0, 2)]);
            fputcsv($handle, ['H5', '4.8%', '0.00']);
            fputcsv($handle, ['BH5', '9%', number_format($goods['9'] ?? 0, 2)]);
            fputcsv($handle, ['AH5', '13.5%', number_format($goods['13.5'] ?? 0, 2)]);
            fputcsv($handle, ['G5', 'FlatFarm', '0.00']);
            fputcsv($handle, ['R1', 'Std Rate', number_format($goods['23'] ?? 0, 2)]);
            fputcsv($handle, ['Z3', 'Total', number_format($goodsTotal, 2)]);
            fputcsv($handle, []);

            // Section 4: Other Deductible Goods & Services (Not for Resale)
            fputcsv($handle, ['SECTION 4: OTHER DEDUCTIBLE GOODS & SERVICES (NOT FOR RESALE)']);
            fputcsv($handle, ['ROS Box', 'Description', 'Net Amount']);
            fputcsv($handle, ['E6', 'Exempt', '0.00']);
            fputcsv($handle, ['J2', '0% Home', number_format($service['0'] ?? 0, 2)]);
            fputcsv($handle, ['H6', '4.8%', '0.00']);
            fputcsv($handle, ['BH6', '9%', number_format($service['9'] ?? 0, 2)]);
            fputcsv($handle, ['AH6', '13.5%', number_format($service['13.5'] ?? 0, 2)]);
            fputcsv($handle, ['G6', 'FlatFarm', '0.00']);
            fputcsv($handle, ['R2', 'Std Rate', number_format($service['23'] ?? 0, 2)]);
            fputcsv($handle, ['Z5', 'Total', number_format($serviceTotal, 2)]);
            fputcsv($handle, ['PA4', 'Postponed Accounting', number_format($postponedAccounting, 2)]);
            fputcsv($handle, []);

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
            fputcsv($handle, ['T2 Other (Z5)', number_format($serviceTotal, 2)]);
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
     * Add frozen invoices to a draft submission.
     */
    public function addInvoices(Request $request, RtdSubmission $submission)
    {
        if (! $submission->isDraft()) {
            return response()->json(['success' => false, 'message' => 'Cannot modify a submitted submission.'], 422);
        }

        $validated = $request->validate([
            'invoice_ids' => 'required|array|min:1',
            'invoice_ids.*' => 'exists:App\Models\Invoice,id',
        ]);

        // Verify all selected invoices are frozen and unsubmitted
        $invoices = Invoice::whereIn('id', $validated['invoice_ids'])
            ->where('rtd_status', 'frozen')
            ->whereNull('rtd_submission_id')
            ->whereNotNull('rtd_snapshot')
            ->get();

        if ($invoices->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No eligible invoices found.'], 422);
        }

        // Link invoices to submission
        Invoice::whereIn('id', $invoices->pluck('id'))
            ->update(['rtd_submission_id' => $submission->id]);

        // Recalculate totals
        $submission->calculateTotalsSnapshot();

        return response()->json([
            'success' => true,
            'message' => "{$invoices->count()} invoice(s) added to submission.",
            'added_count' => $invoices->count(),
        ]);
    }

    /**
     * Delete a draft submission and unlink its invoices.
     */
    public function destroy(RtdSubmission $submission)
    {
        if (! $submission->isDraft()) {
            return back()->with('error', 'Only draft submissions can be deleted.');
        }

        // Unlink all associated invoices so they become available again
        Invoice::where('rtd_submission_id', $submission->id)
            ->update(['rtd_submission_id' => null]);

        $submission->delete();

        return redirect()->route('rtd.submissions.index')
            ->with('success', 'Draft submission deleted and invoices released.');
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

    /**
     * Debug diagnostic page for Section 1 sales data calculation.
     * Replays the aggregateSalesFromVatReturns() logic with full transparency.
     */
    public function debugSales(RtdSubmission $submission)
    {
        $periodStart = $submission->period_start;
        $periodEnd = $submission->period_end;

        // Ensure sales_accounting_daily is populated
        app(SalesAccountingImportService::class)->ensureDataExists($periodStart, $periodEnd);

        // VAT rate mapping (same as RtdSubmission::aggregateSalesFromVatReturns)
        $rateToKey = [
            '0' => '0', '0.0' => '0', '0.0000' => '0',
            '0.09' => '9', '0.0900' => '9',
            '0.135' => '13.5', '0.1350' => '13.5',
            '0.23' => '23', '0.2300' => '23',
        ];
        $defaultRates = ['0' => 0, '9' => 0, '13.5' => 0, '23' => 0];

        // 1. Find VAT returns in period
        $vatReturns = VatReturn::where('period_start', '>=', $periodStart)
            ->where('period_end', '<=', $periodEnd)
            ->orderBy('period_start')
            ->get();

        // 2. Process each VAT return (replay Tier 1 / Tier 2 logic)
        $vatReturnDetails = [];
        $aggregatedSales = $defaultRates;

        foreach ($vatReturns as $vatReturn) {
            $detail = [
                'id' => $vatReturn->id,
                'return_period' => $vatReturn->return_period,
                'period_start' => $vatReturn->period_start->format('Y-m-d'),
                'period_end' => $vatReturn->period_end->format('Y-m-d'),
                'status' => $vatReturn->status,
                'has_sales_vat_data' => ! empty($vatReturn->sales_vat_data),
                'has_by_rate' => ! empty($vatReturn->sales_vat_data['by_rate'] ?? null),
                'tier_used' => null,
                'sales_by_rate' => $defaultRates,
                'paperin_gross' => 0,
                'raw_sales_vat_data' => $vatReturn->sales_vat_data,
            ];

            $salesVatData = $vatReturn->sales_vat_data;

            if ($salesVatData && ! empty($salesVatData['by_rate'])) {
                // Tier 1: persisted VAT3 data
                $detail['tier_used'] = 1;
                foreach ($salesVatData['by_rate'] as $rateData) {
                    $vatRate = is_array($rateData) ? ($rateData['vat_rate'] ?? null) : (is_object($rateData) ? $rateData->vat_rate : null);
                    $totalNet = is_array($rateData) ? ($rateData['total_net'] ?? 0) : (is_object($rateData) ? ($rateData->total_net ?? 0) : 0);

                    if ($vatRate === null) {
                        continue;
                    }

                    $key = $rateToKey[(string) $vatRate] ?? null;
                    if ($key !== null) {
                        $detail['sales_by_rate'][$key] += (float) $totalNet;
                        $aggregatedSales[$key] += (float) $totalNet;
                    }
                }

                // If this VAT return pre-dates the paperin fix, apply the deduction now
                $detail['has_paperin_adjustment'] = isset($salesVatData['paperin_adjustment']);
                if (! isset($salesVatData['paperin_adjustment'])) {
                    $paperinGross = (float) DB::table('sales_accounting_daily')
                        ->where('payment_type', 'paperin')
                        ->whereBetween('sale_date', [
                            $vatReturn->period_start->format('Y-m-d'),
                            $vatReturn->period_end->format('Y-m-d'),
                        ])
                        ->sum('gross_amount');

                    $detail['paperin_gross'] = $paperinGross;
                    $detail['paperin_retrofix'] = true;
                    if ($paperinGross > 0) {
                        $detail['sales_by_rate']['0'] -= $paperinGross;
                        $aggregatedSales['0'] -= $paperinGross;
                    }
                }
            } else {
                // Tier 2: fallback to sales_accounting_daily for this VAT return's period
                $detail['tier_used'] = 2;
                $periodSales = DB::table('sales_accounting_daily')
                    ->select('vat_rate', DB::raw('SUM(net_amount) as total_net'))
                    ->whereBetween('sale_date', [
                        $vatReturn->period_start->format('Y-m-d'),
                        $vatReturn->period_end->format('Y-m-d'),
                    ])
                    ->groupBy('vat_rate')
                    ->get();

                foreach ($periodSales as $row) {
                    $key = $rateToKey[(string) $row->vat_rate] ?? null;
                    if ($key !== null) {
                        $detail['sales_by_rate'][$key] += (float) $row->total_net;
                        $aggregatedSales[$key] += (float) $row->total_net;
                    }
                }

                $paperinGross = (float) DB::table('sales_accounting_daily')
                    ->where('payment_type', 'paperin')
                    ->whereBetween('sale_date', [
                        $vatReturn->period_start->format('Y-m-d'),
                        $vatReturn->period_end->format('Y-m-d'),
                    ])
                    ->sum('gross_amount');

                $detail['paperin_gross'] = $paperinGross;
                if ($paperinGross > 0) {
                    $detail['sales_by_rate']['0'] -= $paperinGross;
                    $aggregatedSales['0'] -= $paperinGross;
                }
            }

            $vatReturnDetails[] = $detail;
        }

        // 3. Detect coverage gaps
        $coveredRanges = $vatReturns->map(fn ($vr) => [
            'start' => $vr->period_start,
            'end' => $vr->period_end,
        ])->sortBy('start')->values();

        $gaps = [];
        $cursor = $periodStart->copy();

        foreach ($coveredRanges as $range) {
            if ($cursor->lt($range['start'])) {
                $gaps[] = [
                    'start' => $cursor->format('Y-m-d'),
                    'end' => $range['start']->copy()->subDay()->format('Y-m-d'),
                ];
            }
            if ($range['end']->gte($cursor)) {
                $cursor = $range['end']->copy()->addDay();
            }
        }

        if ($cursor->lte($periodEnd)) {
            $gaps[] = [
                'start' => $cursor->format('Y-m-d'),
                'end' => $periodEnd->format('Y-m-d'),
            ];
        }

        // 4. Calculate what each gap would contribute from sales_accounting_daily
        $gapDetails = [];
        foreach ($gaps as $gap) {
            $gapSales = DB::table('sales_accounting_daily')
                ->select('vat_rate', DB::raw('SUM(net_amount) as total_net'))
                ->whereBetween('sale_date', [$gap['start'], $gap['end']])
                ->groupBy('vat_rate')
                ->get();

            $gapRates = $defaultRates;
            foreach ($gapSales as $row) {
                $key = $rateToKey[(string) $row->vat_rate] ?? null;
                if ($key !== null) {
                    $gapRates[$key] += (float) $row->total_net;
                }
            }

            $paperinGross = (float) DB::table('sales_accounting_daily')
                ->where('payment_type', 'paperin')
                ->whereBetween('sale_date', [$gap['start'], $gap['end']])
                ->sum('gross_amount');

            if ($paperinGross > 0) {
                $gapRates['0'] -= $paperinGross;
            }

            $gapDetails[] = [
                'start' => $gap['start'],
                'end' => $gap['end'],
                'sales_by_rate' => $gapRates,
                'paperin_gross' => $paperinGross,
                'total' => round(array_sum($gapRates), 2),
            ];
        }

        // 5. Reference: full-period sales_accounting_daily query (what sales accounting report uses)
        $referenceSales = $defaultRates;
        $refData = DB::table('sales_accounting_daily')
            ->select('vat_rate', DB::raw('SUM(net_amount) as total_net'))
            ->whereBetween('sale_date', [$periodStart->format('Y-m-d'), $periodEnd->format('Y-m-d')])
            ->groupBy('vat_rate')
            ->get();

        foreach ($refData as $row) {
            $key = $rateToKey[(string) $row->vat_rate] ?? null;
            if ($key !== null) {
                $referenceSales[$key] += (float) $row->total_net;
            }
        }

        $refPaperin = (float) DB::table('sales_accounting_daily')
            ->where('payment_type', 'paperin')
            ->whereBetween('sale_date', [$periodStart->format('Y-m-d'), $periodEnd->format('Y-m-d')])
            ->sum('gross_amount');

        if ($refPaperin > 0) {
            $referenceSales['0'] -= $refPaperin;
        }

        // Round everything
        foreach ($referenceSales as $k => $v) {
            $referenceSales[$k] = round($v, 2);
        }
        foreach ($aggregatedSales as $k => $v) {
            $aggregatedSales[$k] = round($v, 2);
        }

        // 6. Current snapshot values
        $snapshotSales = $submission->totals_snapshot['sales'] ?? $defaultRates;
        $snapshotSource = $submission->totals_snapshot['sales_source'] ?? 'unknown';
        $snapshotVatCount = $submission->totals_snapshot['vat_returns_count'] ?? 0;

        // 7. What the "fixed" total would be (aggregated + gaps)
        $fixedSales = $aggregatedSales;
        foreach ($gapDetails as $gap) {
            foreach ($gap['sales_by_rate'] as $rate => $amount) {
                $fixedSales[$rate] = round(($fixedSales[$rate] ?? 0) + $amount, 2);
            }
        }

        return view('rtd.submissions.debug-sales', [
            'submission' => $submission,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'vatReturns' => $vatReturnDetails,
            'vatReturnCount' => $vatReturns->count(),
            'aggregatedSales' => $aggregatedSales,
            'gaps' => $gapDetails,
            'hasGaps' => ! empty($gapDetails),
            'referenceSales' => $referenceSales,
            'referencePaperin' => $refPaperin,
            'snapshotSales' => $snapshotSales,
            'snapshotSource' => $snapshotSource,
            'snapshotVatCount' => $snapshotVatCount,
            'fixedSales' => $fixedSales,
        ]);
    }
}
