<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\AccountingSupplier;
use App\Models\Invoice;
use App\Models\VatReturn;
use App\Services\SalesAccountingImportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VatReturnController extends Controller
{
    /**
     * Display a listing of VAT returns.
     */
    public function index()
    {
        $vatReturns = VatReturn::with('creator', 'finalizer')
            ->orderBy('period_end', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('management.vat-returns.index', compact('vatReturns'));
    }

    /**
     * Show the form for creating a new VAT return.
     */
    public function create(Request $request)
    {
        $endDate = $request->get('end_date') ? Carbon::parse($request->get('end_date')) : null;
        // Irish VAT returns are bi-monthly (every 2 months)
        // Periods are: Jan-Feb, Mar-Apr, May-Jun, Jul-Aug, Sep-Oct, Nov-Dec
        if ($endDate) {
            // Determine which bi-monthly period the selected date falls into
            $month = $endDate->month;
            $year = $endDate->year;

            // Calculate bi-monthly period
            if ($month % 2 == 0) {
                // Even month (Feb, Apr, Jun, Aug, Oct, Dec)
                $periodEnd = Carbon::create($year, $month, 1)->endOfMonth();
                $startDate = Carbon::create($year, $month - 1, 1);
            } else {
                // Odd month (Jan, Mar, May, Jul, Sep, Nov)
                $periodEnd = Carbon::create($year, $month + 1, 1)->endOfMonth();
                $startDate = Carbon::create($year, $month, 1);
            }
        } else {
            $periodEnd = null;
            $startDate = null;
        }

        // Get unassigned invoices query
        $query = Invoice::with(['supplier', 'vatLines'])
            ->unassigned()
            ->orderBy('supplier_name')
            ->orderBy('invoice_date')
            ->orderBy('invoice_number');

        // Apply date filter if provided
        if ($periodEnd) {
            $query->upToDate($periodEnd);
        }

        $invoices = $query->get();

        // Group invoices by supplier
        $invoicesBySupplier = $invoices->groupBy('supplier_name');

        // Calculate totals by supplier
        $supplierTotals = [];
        foreach ($invoicesBySupplier as $supplierName => $supplierInvoices) {
            $supplierTotals[$supplierName] = [
                'zero_net' => $supplierInvoices->sum('zero_net'),
                'zero_vat' => $supplierInvoices->sum('zero_vat'),
                'second_reduced_net' => $supplierInvoices->sum('second_reduced_net'),
                'second_reduced_vat' => $supplierInvoices->sum('second_reduced_vat'),
                'reduced_net' => $supplierInvoices->sum('reduced_net'),
                'reduced_vat' => $supplierInvoices->sum('reduced_vat'),
                'standard_net' => $supplierInvoices->sum('standard_net'),
                'standard_vat' => $supplierInvoices->sum('standard_vat'),
                'total' => $supplierInvoices->sum('total_amount'),
            ];
        }

        // Calculate grand totals for purchases
        $purchaseTotals = [
            'zero_net' => $invoices->sum('zero_net'),
            'zero_vat' => $invoices->sum('zero_vat'),
            'second_reduced_net' => $invoices->sum('second_reduced_net'),
            'second_reduced_vat' => $invoices->sum('second_reduced_vat'),
            'reduced_net' => $invoices->sum('reduced_net'),
            'reduced_vat' => $invoices->sum('reduced_vat'),
            'standard_net' => $invoices->sum('standard_net'),
            'standard_vat' => $invoices->sum('standard_vat'),
            'total_net' => $invoices->sum('subtotal'),
            'total_vat' => $invoices->sum('vat_amount'),
            'total_gross' => $invoices->sum('total_amount'),
        ];

        // Get EU supplier invoices
        $euSupplierIds = AccountingSupplier::euSuppliers()->pluck('id');
        $euInvoices = $invoices->whereIn('supplier_id', $euSupplierIds);
        $euTotalAmount = $euInvoices->sum('subtotal'); // Net amount for EU goods

        // Initialize default values
        $salesData = null;
        $rosFields = [
            'T1' => 0,
            'T2' => $purchaseTotals['total_vat'],
            'T3' => 0,
            'T4' => 0,
            'E1' => 0,
            'E2' => $euTotalAmount,
        ];

        // Get Sales VAT data only if dates are provided
        if ($startDate && $periodEnd) {
            $salesData = $this->getSalesVatData($startDate, $periodEnd);

            // Calculate net payable/repayable
            $vatOnSales = $salesData['total_vat'] ?? 0;
            $vatOnPurchases = $purchaseTotals['total_vat'];
            $netPayable = $vatOnSales - $vatOnPurchases;

            // Update ROS VAT Return fields with actual values
            $rosFields = [
                'T1' => $vatOnSales, // VAT on Sales
                'T2' => $vatOnPurchases, // VAT on Purchases
                'T3' => max(0, $netPayable), // Net Payable (if positive)
                'T4' => max(0, -$netPayable), // Net Repayable (if negative)
                'E1' => 0, // Total goods to other EU countries (we don't export)
                'E2' => $euTotalAmount, // Total goods from other EU countries
            ];
        }

        return view('management.vat-returns.create', compact(
            'invoicesBySupplier',
            'supplierTotals',
            'purchaseTotals',
            'endDate',
            'periodEnd',
            'startDate',
            'invoices',
            'salesData',
            'rosFields',
            'euInvoices',
            'euTotalAmount',
            'euSupplierIds'
        ));
    }

    /**
     * Get sales VAT data for the period
     */
    private function getSalesVatData($startDate, $endDate)
    {
        // Auto-import missing data from POS on demand
        app(SalesAccountingImportService::class)->ensureDataExists($startDate, $endDate);

        // Check if we have pre-aggregated data
        $hasAggregatedData = DB::table('sales_accounting_daily')
            ->whereBetween('sale_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->exists();

        if ($hasAggregatedData) {
            // Use optimized pre-aggregated data
            $salesData = DB::table('sales_accounting_daily')
                ->select(
                    DB::raw('SUM(net_amount) as total_net'),
                    DB::raw('SUM(vat_amount) as total_vat'),
                    DB::raw('SUM(gross_amount) as total_gross'),
                    'vat_rate'
                )
                ->whereBetween('sale_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->groupBy('vat_rate')
                ->get();

            // Calculate paperin (gift voucher redemption) total to prevent double-counting.
            // Vouchers are counted as revenue when sold; when redeemed (paperin), the gross
            // amount must be deducted from net/gross totals. VAT is unaffected since the
            // original voucher sale is at 0% VAT.
            $paperinTotal = (float) DB::table('sales_accounting_daily')
                ->where('payment_type', 'paperin')
                ->whereBetween('sale_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->sum('gross_amount');

            $totals = [
                'total_net' => $salesData->sum('total_net') - $paperinTotal,
                'total_vat' => $salesData->sum('total_vat'),
                'total_gross' => $salesData->sum('total_gross') - $paperinTotal,
                'by_rate' => $salesData->keyBy(fn ($item) => (string) (float) $item->vat_rate),
                'data_source' => 'optimized',
                'paperin_adjustment' => $paperinTotal,
            ];

            // Deduct paperin from the 0% rate entry (matching sales accounting behavior)
            if ($paperinTotal > 0 && isset($totals['by_rate']['0'])) {
                $totals['by_rate']['0']->total_net -= $paperinTotal;
                $totals['by_rate']['0']->total_gross -= $paperinTotal;
            }
        } else {
            // Fall back to real-time POS query
            $formattedStartDate = $startDate->format('Y m d');
            $formattedEndDate = $endDate->format('Y m d');

            // Sales totals do not need the PAYMENTS join at all — joining it would
            // repeat each ticket line once per payment row on split-payment receipts.
            $salesQuery = "
                SELECT
                    TAXES.RATE as vat_rate,
                    SUM(TICKETLINES.PRICE * TICKETLINES.UNITS) AS total_net,
                    SUM(TICKETLINES.PRICE * TICKETLINES.UNITS * TAXES.RATE) AS total_vat
                FROM TICKETLINES
                JOIN TICKETS ON TICKETLINES.TICKET = TICKETS.ID
                JOIN RECEIPTS ON TICKETS.ID = RECEIPTS.ID
                JOIN TAXES ON TICKETLINES.TAXID = TAXES.ID
                LEFT JOIN CUSTOMERS ON TICKETS.CUSTOMER = CUSTOMERS.ID
                WHERE DATE_FORMAT(RECEIPTS.DATENEW, '%Y %m %d') BETWEEN ? AND ?
                AND (CUSTOMERS.NAME IS NULL OR CUSTOMERS.NAME NOT IN ('Kitchen', 'Coffee'))
                GROUP BY TAXES.RATE
            ";

            $salesData = DB::connection('pos')
                ->select($salesQuery, [$formattedStartDate, $formattedEndDate]);

            // Calculate paperin (gift voucher redemption) total from POS.
            // The voucher's share of the receipt is apportioned by payment amount, so a
            // part-voucher/part-cash receipt only deducts the voucher portion, and a voucher
            // worth more than the goods only deducts the goods value (the rest is breakage,
            // never counted as revenue a second time).
            $paperinQuery = "
                SELECT COALESCE(SUM(
                    TICKETLINES.PRICE * TICKETLINES.UNITS * (pay.paid / tot.total) * (1 + TAXES.RATE)
                ), 0) AS total_gross
                FROM TICKETLINES
                JOIN TICKETS ON TICKETLINES.TICKET = TICKETS.ID
                JOIN RECEIPTS ON TICKETS.ID = RECEIPTS.ID
                JOIN TAXES ON TICKETLINES.TAXID = TAXES.ID
                JOIN (
                    SELECT p.RECEIPT, p.PAYMENT, SUM(p.TOTAL) AS paid
                    FROM PAYMENTS p
                    JOIN RECEIPTS r2 ON p.RECEIPT = r2.ID
                    WHERE DATE_FORMAT(r2.DATENEW, '%Y %m %d') BETWEEN ? AND ?
                    GROUP BY p.RECEIPT, p.PAYMENT
                ) pay ON pay.RECEIPT = RECEIPTS.ID
                JOIN (
                    SELECT p.RECEIPT, SUM(p.TOTAL) AS total
                    FROM PAYMENTS p
                    JOIN RECEIPTS r3 ON p.RECEIPT = r3.ID
                    WHERE DATE_FORMAT(r3.DATENEW, '%Y %m %d') BETWEEN ? AND ?
                    GROUP BY p.RECEIPT
                    HAVING SUM(p.TOTAL) <> 0
                ) tot ON tot.RECEIPT = RECEIPTS.ID
                LEFT JOIN CUSTOMERS ON TICKETS.CUSTOMER = CUSTOMERS.ID
                WHERE DATE_FORMAT(RECEIPTS.DATENEW, '%Y %m %d') BETWEEN ? AND ?
                AND pay.PAYMENT = 'paperin'
                AND (CUSTOMERS.NAME IS NULL OR CUSTOMERS.NAME NOT IN ('Kitchen', 'Coffee'))
            ";

            $paperinResult = DB::connection('pos')->select($paperinQuery, [
                $formattedStartDate, $formattedEndDate,
                $formattedStartDate, $formattedEndDate,
                $formattedStartDate, $formattedEndDate,
            ]);
            $paperinTotal = (float) ($paperinResult[0]->total_gross ?? 0);

            $totalNet = collect($salesData)->sum('total_net');
            $totalVat = collect($salesData)->sum('total_vat');

            $byRate = collect($salesData)->keyBy(fn ($item) => (string) (float) $item->vat_rate);

            // Deduct paperin from the 0% rate entry (matching sales accounting behavior)
            if ($paperinTotal > 0 && isset($byRate['0'])) {
                $byRate['0']->total_net -= $paperinTotal;
            }

            $totals = [
                'total_net' => $totalNet - $paperinTotal,
                'total_vat' => $totalVat,
                'total_gross' => ($totalNet + $totalVat) - $paperinTotal,
                'by_rate' => $byRate,
                'data_source' => 'real-time',
                'paperin_adjustment' => $paperinTotal,
            ];
        }

        return $totals;
    }

    /**
     * Store a newly created VAT return (assign invoices).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'return_period' => 'required|string|max:100|unique:vat_returns,return_period',
            'period_end' => 'required|date',
            'notes' => 'nullable|string',
            'invoice_ids' => 'required|array|min:1',
            'invoice_ids.*' => 'exists:invoices,id',
        ]);

        DB::beginTransaction();
        try {
            // Determine period start based on bi-monthly Irish VAT periods
            // Periods: Jan-Feb, Mar-Apr, May-Jun, Jul-Aug, Sep-Oct, Nov-Dec
            $periodEnd = Carbon::parse($validated['period_end']);
            $month = $periodEnd->month;
            $year = $periodEnd->year;
            if ($month % 2 == 0) {
                $periodStart = Carbon::create($year, $month - 1, 1);
            } else {
                $periodStart = Carbon::create($year, $month, 1);
            }

            // Create VAT return
            $vatReturn = VatReturn::create([
                'return_period' => $validated['return_period'],
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'status' => 'draft',
                'notes' => $validated['notes'],
                'created_by' => auth()->id(),
            ]);

            // Assign invoices to this VAT return
            Invoice::whereIn('id', $validated['invoice_ids'])
                ->unassigned()
                ->update(['vat_return_id' => $vatReturn->id]);

            // Calculate totals
            $vatReturn->calculateTotals();

            // Save sales VAT data and EU total
            $salesData = $this->getSalesVatData($periodStart, $periodEnd);
            $salesVatData = [
                'total_net' => $salesData['total_net'],
                'total_vat' => $salesData['total_vat'],
                'total_gross' => $salesData['total_gross'],
                'data_source' => $salesData['data_source'],
                'paperin_adjustment' => $salesData['paperin_adjustment'] ?? 0,
                'by_rate' => collect($salesData['by_rate'])->map(fn ($item) => [
                    'vat_rate' => is_object($item) ? $item->vat_rate : ($item['vat_rate'] ?? null),
                    'total_net' => is_object($item) ? ($item->total_net ?? 0) : ($item['total_net'] ?? 0),
                    'total_vat' => is_object($item) ? ($item->total_vat ?? 0) : ($item['total_vat'] ?? 0),
                ])->values()->toArray(),
            ];

            $euSupplierIds = AccountingSupplier::euSuppliers()->pluck('id');
            $euTotalAmount = $vatReturn->invoices()->whereIn('supplier_id', $euSupplierIds)->sum('subtotal');

            $vatReturn->update([
                'sales_vat_data' => $salesVatData,
                'eu_total_amount' => $euTotalAmount,
            ]);

            DB::commit();

            return redirect()->route('management.vat-returns.show', $vatReturn)
                ->with('success', 'VAT return created successfully with '.count($validated['invoice_ids']).' invoices assigned.')
                ->with('download_csv', true);

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withInput()
                ->with('error', 'Failed to create VAT return: '.$e->getMessage());
        }
    }

    /**
     * Display the specified VAT return.
     */
    public function show(VatReturn $vatReturn)
    {
        $vatReturn->load(['invoices.supplier', 'creator', 'finalizer']);

        // Group invoices by supplier
        $invoicesBySupplier = $vatReturn->invoices->groupBy('supplier_name');

        // Calculate totals by supplier
        $supplierTotals = [];
        foreach ($invoicesBySupplier as $supplierName => $supplierInvoices) {
            $supplierTotals[$supplierName] = [
                'zero_net' => $supplierInvoices->sum('zero_net'),
                'zero_vat' => $supplierInvoices->sum('zero_vat'),
                'second_reduced_net' => $supplierInvoices->sum('second_reduced_net'),
                'second_reduced_vat' => $supplierInvoices->sum('second_reduced_vat'),
                'reduced_net' => $supplierInvoices->sum('reduced_net'),
                'reduced_vat' => $supplierInvoices->sum('reduced_vat'),
                'standard_net' => $supplierInvoices->sum('standard_net'),
                'standard_vat' => $supplierInvoices->sum('standard_vat'),
                'total' => $supplierInvoices->sum('total_amount'),
            ];
        }

        $vatBreakdown = $vatReturn->getVatBreakdown();

        // Get EU supplier invoices
        $euSupplierIds = AccountingSupplier::euSuppliers()->pluck('id');
        $euInvoices = $vatReturn->invoices->whereIn('supplier_id', $euSupplierIds);
        $euTotalAmount = $vatReturn->eu_total_amount > 0 ? $vatReturn->eu_total_amount : $euInvoices->sum('subtotal');

        // Build ROS fields from persisted sales data
        // For draft returns missing the paperin adjustment, recalculate from live data
        $salesData = $vatReturn->sales_vat_data;
        $byRateNetSum = collect($salesData['by_rate'] ?? [])->sum('total_net');
        $needsRecalc = ! isset($salesData['paperin_adjustment'])
            || abs($byRateNetSum - ($salesData['total_net'] ?? 0)) > 0.01;
        if ($salesData && $needsRecalc && $vatReturn->canBeModified()) {
            $salesData = $this->getSalesVatData($vatReturn->period_start, $vatReturn->period_end);
            $salesVatData = [
                'total_net' => $salesData['total_net'],
                'total_vat' => $salesData['total_vat'],
                'total_gross' => $salesData['total_gross'],
                'data_source' => $salesData['data_source'],
                'paperin_adjustment' => $salesData['paperin_adjustment'] ?? 0,
                'by_rate' => collect($salesData['by_rate'])->map(fn ($item) => [
                    'vat_rate' => is_object($item) ? $item->vat_rate : ($item['vat_rate'] ?? null),
                    'total_net' => is_object($item) ? ($item->total_net ?? 0) : ($item['total_net'] ?? 0),
                    'total_vat' => is_object($item) ? ($item->total_vat ?? 0) : ($item['total_vat'] ?? 0),
                ])->values()->toArray(),
            ];
            $vatReturn->update(['sales_vat_data' => $salesVatData]);
            $salesData = $salesVatData;
        }
        $rosFields = null;
        if ($salesData) {
            $vatOnSales = $salesData['total_vat'] ?? 0;
            $vatOnPurchases = $vatReturn->total_vat;
            $netPayable = $vatOnSales - $vatOnPurchases;

            $rosFields = [
                'T1' => $vatOnSales,
                'T2' => $vatOnPurchases,
                'T3' => max(0, $netPayable),
                'T4' => max(0, -$netPayable),
                'E1' => 0,
                'E2' => $euTotalAmount,
            ];
        }

        return view('management.vat-returns.show', compact(
            'vatReturn',
            'invoicesBySupplier',
            'supplierTotals',
            'vatBreakdown',
            'euInvoices',
            'euTotalAmount',
            'salesData',
            'rosFields'
        ));
    }

    /**
     * Finalize a VAT return.
     */
    public function finalize(VatReturn $vatReturn)
    {
        try {
            $vatReturn->finalize();

            return redirect()->route('management.vat-returns.show', $vatReturn)
                ->with('success', 'VAT return has been finalized successfully.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Export VAT return preview to CSV (for testing before creation).
     */
    public function exportPreview(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'period' => 'required|string',
            'invoice_ids' => 'required|array|min:1',
            'invoice_ids.*' => 'exists:invoices,id',
        ]);

        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);
        $period = $validated['period'];

        // Get selected invoices
        $invoices = Invoice::with(['supplier', 'vatLines'])
            ->whereIn('id', $validated['invoice_ids'])
            ->orderBy('supplier_name')
            ->orderBy('invoice_date')
            ->get();

        // Get sales data
        $salesData = $this->getSalesVatData($startDate, $endDate);

        // Get EU supplier invoices
        $euSupplierIds = AccountingSupplier::euSuppliers()->pluck('id');
        $euInvoices = $invoices->whereIn('supplier_id', $euSupplierIds);
        $euTotalAmount = $euInvoices->sum('subtotal');

        // Calculate purchase totals
        $purchaseTotals = [
            'zero_net' => $invoices->sum('zero_net'),
            'zero_vat' => $invoices->sum('zero_vat'),
            'second_reduced_net' => $invoices->sum('second_reduced_net'),
            'second_reduced_vat' => $invoices->sum('second_reduced_vat'),
            'reduced_net' => $invoices->sum('reduced_net'),
            'reduced_vat' => $invoices->sum('reduced_vat'),
            'standard_net' => $invoices->sum('standard_net'),
            'standard_vat' => $invoices->sum('standard_vat'),
            'total_net' => $invoices->sum('subtotal'),
            'total_vat' => $invoices->sum('vat_amount'),
            'total_gross' => $invoices->sum('total_amount'),
        ];

        // Calculate ROS fields
        $vatOnSales = $salesData['total_vat'] ?? 0;
        $vatOnPurchases = $purchaseTotals['total_vat'];
        $netPayable = $vatOnSales - $vatOnPurchases;

        $rosFields = [
            'T1' => $vatOnSales,
            'T2' => $vatOnPurchases,
            'T3' => max(0, $netPayable),
            'T4' => max(0, -$netPayable),
            'E1' => 0,
            'E2' => $euTotalAmount,
        ];

        $filename = 'vat-return-preview-'.$period.'.csv';

        return $this->generateCsvExport($filename, $period, $startDate, $endDate, $invoices, $salesData, $rosFields, $purchaseTotals, $euInvoices);
    }

    /**
     * Export VAT return to CSV.
     */
    public function export(VatReturn $vatReturn)
    {
        $vatReturn->load('invoices.supplier');
        $filename = 'vat-return-'.$vatReturn->return_period.'.csv';

        // Get additional data for comprehensive export
        $startDate = $vatReturn->period_start;
        $endDate = $vatReturn->period_end;
        $salesData = $this->getSalesVatData($startDate, $endDate);

        // Get EU supplier invoices
        $euSupplierIds = AccountingSupplier::euSuppliers()->pluck('id');
        $euInvoices = $vatReturn->invoices->whereIn('supplier_id', $euSupplierIds);

        // Calculate ROS fields
        $vatOnSales = $salesData['total_vat'] ?? 0;
        $vatOnPurchases = $vatReturn->total_vat;
        $netPayable = $vatOnSales - $vatOnPurchases;

        $rosFields = [
            'T1' => $vatOnSales,
            'T2' => $vatOnPurchases,
            'T3' => max(0, $netPayable),
            'T4' => max(0, -$netPayable),
            'E1' => 0,
            'E2' => $euInvoices->sum('subtotal'),
        ];

        $purchaseTotals = [
            'zero_net' => $vatReturn->zero_net,
            'zero_vat' => $vatReturn->zero_vat,
            'second_reduced_net' => $vatReturn->second_reduced_net,
            'second_reduced_vat' => $vatReturn->second_reduced_vat,
            'reduced_net' => $vatReturn->reduced_net,
            'reduced_vat' => $vatReturn->reduced_vat,
            'standard_net' => $vatReturn->standard_net,
            'standard_vat' => $vatReturn->standard_vat,
            'total_net' => $vatReturn->total_net,
            'total_vat' => $vatReturn->total_vat,
            'total_gross' => $vatReturn->total_gross,
        ];

        return $this->generateCsvExport($filename, $vatReturn->return_period, $startDate, $endDate, $vatReturn->invoices, $salesData, $rosFields, $purchaseTotals, $euInvoices);
    }

    /**
     * Generate comprehensive CSV export for VAT return data.
     */
    private function generateCsvExport($filename, $period, $startDate, $endDate, $invoices, $salesData, $rosFields, $purchaseTotals, $euInvoices)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function () use ($period, $startDate, $endDate, $invoices, $salesData, $rosFields, $purchaseTotals, $euInvoices) {
            $file = fopen('php://output', 'w');

            // Write report header
            fputcsv($file, ['VAT Return Export']);
            fputcsv($file, ['Period:', $period]);
            fputcsv($file, ['Dates:', $startDate->format('M j, Y').' to '.$endDate->format('M j, Y')]);
            fputcsv($file, ['Generated:', now()->format('M j, Y H:i:s')]);
            fputcsv($file, ['Data Source:', ($salesData['data_source'] ?? 'real-time') === 'optimized' ? 'Optimized (Fast)' : 'Real-time']);
            fputcsv($file, []); // Empty row

            // Write ROS VAT Return Summary
            fputcsv($file, ['ROS VAT RETURN SUMMARY']);
            fputcsv($file, ['Field', 'Description', 'Amount EUR']);
            fputcsv($file, ['T1', 'VAT on Sales', number_format($rosFields['T1'], 2)]);
            fputcsv($file, ['T2', 'VAT on Purchases', number_format($rosFields['T2'], 2)]);
            fputcsv($file, ['T3', 'Net Payable', number_format($rosFields['T3'], 2)]);
            fputcsv($file, ['T4', 'Net Repayable', number_format($rosFields['T4'], 2)]);
            fputcsv($file, ['E1', 'Goods to other EU countries', number_format($rosFields['E1'], 2)]);
            fputcsv($file, ['E2', 'Goods from other EU countries', number_format($rosFields['E2'], 2)]);
            fputcsv($file, []); // Empty row

            // Write Sales VAT breakdown if available
            if (isset($salesData['by_rate']) && $salesData['by_rate']->count() > 0) {
                fputcsv($file, ['SALES VAT BREAKDOWN']);
                fputcsv($file, ['VAT Rate', 'Net Sales', 'VAT Amount', 'Gross Sales']);
                foreach ($salesData['by_rate'] as $rate => $data) {
                    fputcsv($file, [
                        number_format($rate * 100, 1).'%',
                        number_format($data->total_net ?? 0, 2),
                        number_format($data->total_vat ?? 0, 2),
                        number_format(($data->total_net ?? 0) + ($data->total_vat ?? 0), 2),
                    ]);
                }
                fputcsv($file, [
                    'TOTAL',
                    number_format($salesData['total_net'], 2),
                    number_format($salesData['total_vat'], 2),
                    number_format($salesData['total_gross'], 2),
                ]);
                fputcsv($file, []); // Empty row
            }

            // Write Purchase VAT breakdown
            fputcsv($file, ['PURCHASE VAT BREAKDOWN']);
            fputcsv($file, ['VAT Rate', 'Net Purchases', 'VAT Amount']);
            fputcsv($file, ['0%', number_format($purchaseTotals['zero_net'], 2), number_format($purchaseTotals['zero_vat'], 2)]);
            fputcsv($file, ['9%', number_format($purchaseTotals['second_reduced_net'], 2), number_format($purchaseTotals['second_reduced_vat'], 2)]);
            fputcsv($file, ['13.5%', number_format($purchaseTotals['reduced_net'], 2), number_format($purchaseTotals['reduced_vat'], 2)]);
            fputcsv($file, ['23%', number_format($purchaseTotals['standard_net'], 2), number_format($purchaseTotals['standard_vat'], 2)]);
            fputcsv($file, ['TOTAL', number_format($purchaseTotals['total_net'], 2), number_format($purchaseTotals['total_vat'], 2)]);
            fputcsv($file, []); // Empty row

            // Write EU Suppliers section if applicable
            if ($euInvoices->count() > 0) {
                fputcsv($file, ['EU SUPPLIERS (INTRASTAT E2)']);
                fputcsv($file, ['Supplier', 'Country', 'Net Amount']);
                $euInvoicesBySupplier = $euInvoices->groupBy('supplier_name');
                foreach ($euInvoicesBySupplier as $supplierName => $supplierInvoices) {
                    // Try to get country code from supplier
                    $supplier = $supplierInvoices->first()->supplier;
                    $countryCode = $supplier->country_code ?? 'EU';
                    fputcsv($file, [
                        $supplierName,
                        $countryCode,
                        number_format($supplierInvoices->sum('subtotal'), 2),
                    ]);
                }
                fputcsv($file, ['TOTAL EU GOODS', '', number_format($euInvoices->sum('subtotal'), 2)]);
                fputcsv($file, []); // Empty row
            }

            // Write detailed invoice data
            fputcsv($file, ['DETAILED INVOICE DATA']);
            fputcsv($file, [
                'Invoice Number',
                'Supplier',
                'Invoice Date',
                'Net 0%',
                'VAT 0%',
                'Net 9%',
                'VAT 9%',
                'Net 13.5%',
                'VAT 13.5%',
                'Net 23%',
                'VAT 23%',
                'Total Net',
                'Total VAT',
                'Total Gross',
                'EU Supplier',
            ]);

            // Get EU supplier IDs for flagging
            $euSupplierIds = \App\Models\AccountingSupplier::euSuppliers()->pluck('id')->toArray();

            // Write invoice data
            foreach ($invoices as $invoice) {
                $isEuSupplier = in_array($invoice->supplier_id, $euSupplierIds) ? 'Yes' : 'No';

                fputcsv($file, [
                    $invoice->invoice_number,
                    $invoice->supplier_name,
                    $invoice->invoice_date->format('Y-m-d'),
                    number_format($invoice->zero_net, 2, '.', ''),
                    number_format($invoice->zero_vat, 2, '.', ''),
                    number_format($invoice->second_reduced_net, 2, '.', ''),
                    number_format($invoice->second_reduced_vat, 2, '.', ''),
                    number_format($invoice->reduced_net, 2, '.', ''),
                    number_format($invoice->reduced_vat, 2, '.', ''),
                    number_format($invoice->standard_net, 2, '.', ''),
                    number_format($invoice->standard_vat, 2, '.', ''),
                    number_format($invoice->subtotal, 2, '.', ''),
                    number_format($invoice->vat_amount, 2, '.', ''),
                    number_format($invoice->total_amount, 2, '.', ''),
                    $isEuSupplier,
                ]);
            }

            // Write invoice totals
            fputcsv($file, [
                'TOTALS',
                '',
                '',
                number_format($purchaseTotals['zero_net'], 2, '.', ''),
                number_format($purchaseTotals['zero_vat'], 2, '.', ''),
                number_format($purchaseTotals['second_reduced_net'], 2, '.', ''),
                number_format($purchaseTotals['second_reduced_vat'], 2, '.', ''),
                number_format($purchaseTotals['reduced_net'], 2, '.', ''),
                number_format($purchaseTotals['reduced_vat'], 2, '.', ''),
                number_format($purchaseTotals['standard_net'], 2, '.', ''),
                number_format($purchaseTotals['standard_vat'], 2, '.', ''),
                number_format($purchaseTotals['total_net'], 2, '.', ''),
                number_format($purchaseTotals['total_vat'], 2, '.', ''),
                number_format($purchaseTotals['total_gross'], 2, '.', ''),
                '',
            ]);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Remove invoice from VAT return.
     */
    public function removeInvoice(VatReturn $vatReturn, Invoice $invoice)
    {
        if (! $vatReturn->canBeModified()) {
            return back()->with('error', 'Cannot modify a finalized VAT return.');
        }

        if ($invoice->vat_return_id !== $vatReturn->id) {
            return back()->with('error', 'Invoice does not belong to this VAT return.');
        }

        $invoice->update(['vat_return_id' => null]);
        $vatReturn->calculateTotals();

        return back()->with('success', 'Invoice removed from VAT return.');
    }

    /**
     * Delete a draft VAT return.
     */
    public function destroy(VatReturn $vatReturn)
    {
        if (! $vatReturn->canBeModified()) {
            return back()->with('error', 'Cannot delete a finalized VAT return.');
        }

        DB::beginTransaction();
        try {
            // Unassign all invoices
            $vatReturn->invoices()->update(['vat_return_id' => null]);

            // Delete the VAT return
            $vatReturn->delete();

            DB::commit();

            return redirect()->route('management.vat-returns.index')
                ->with('success', 'VAT return deleted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to delete VAT return: '.$e->getMessage());
        }
    }
}
