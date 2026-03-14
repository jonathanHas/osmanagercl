<?php

namespace App\Http\Controllers;

use App\Models\AccountingSupplier;
use Carbon\Carbon;
use Illuminate\Http\Request;

class OrganicTrustReportController extends Controller
{
    public function index(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfYear()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        if (! $request->filled('start_date')) {
            return view('suppliers.organic-trust-report', compact('startDate', 'endDate'));
        }

        $startDateTime = Carbon::parse($startDate)->startOfDay();
        $endDateTime = Carbon::parse($endDate)->endOfDay();

        $suppliers = $this->getSupplierSpend($startDateTime, $endDateTime);

        $totalSpend = $suppliers->sum('period_total');
        $organicSuppliers = $suppliers->where('is_organic', true);
        $organicSpend = $organicSuppliers->sum('period_total');
        $supplierCount = $suppliers->count();
        $organicCount = $organicSuppliers->count();

        return view('suppliers.organic-trust-report', compact(
            'startDate',
            'endDate',
            'suppliers',
            'totalSpend',
            'organicSpend',
            'supplierCount',
            'organicCount'
        ));
    }

    public function toggleOrganic(AccountingSupplier $supplier)
    {
        $supplier->update(['is_organic' => ! $supplier->is_organic]);

        return response()->json([
            'success' => true,
            'is_organic' => $supplier->is_organic,
        ]);
    }

    public function exportCsv(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfYear()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));
        $organicOnly = $request->boolean('organic_only');

        $startDateTime = Carbon::parse($startDate)->startOfDay();
        $endDateTime = Carbon::parse($endDate)->endOfDay();

        $suppliers = $this->getSupplierSpend($startDateTime, $endDateTime);

        if ($organicOnly) {
            $suppliers = $suppliers->where('is_organic', true);
        }

        $filename = 'organic-trust-report-'.$startDate.'-to-'.$endDate.'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function () use ($suppliers, $startDate, $endDate, $organicOnly) {
            $file = fopen('php://output', 'w');

            fputcsv($file, ['Organic Trust Supplier Report']);
            fputcsv($file, ['Date Range: '.$startDate.' to '.$endDate]);
            if ($organicOnly) {
                fputcsv($file, ['Filter: Organic suppliers only']);
            }
            fputcsv($file, []);

            fputcsv($file, ['Supplier Name', 'Total Amount (incl. VAT)', 'Invoice Count', 'Organic']);

            $totalAmount = 0;

            foreach ($suppliers as $supplier) {
                fputcsv($file, [
                    $supplier->name,
                    number_format($supplier->period_total, 2),
                    $supplier->period_invoice_count,
                    $supplier->is_organic ? 'Yes' : 'No',
                ]);

                $totalAmount += $supplier->period_total;
            }

            fputcsv($file, []);
            fputcsv($file, ['Summary']);
            fputcsv($file, ['Total:', '€'.number_format($totalAmount, 2)]);
            fputcsv($file, ['Supplier Count:', $suppliers->count()]);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function getSupplierSpend(Carbon $startDateTime, Carbon $endDateTime)
    {
        return AccountingSupplier::where('supplier_type', 'product')
            ->where('is_active', true)
            ->get()
            ->map(function ($supplier) use ($startDateTime, $endDateTime) {
                $invoiceStats = $supplier->invoices()
                    ->where('payment_status', '!=', 'cancelled')
                    ->whereBetween('invoice_date', [$startDateTime, $endDateTime])
                    ->selectRaw('COALESCE(SUM(total_amount), 0) as total, COUNT(*) as count')
                    ->first();

                $supplier->period_total = (float) $invoiceStats->total;
                $supplier->period_invoice_count = (int) $invoiceStats->count;

                return $supplier;
            })
            ->filter(fn ($supplier) => $supplier->period_total > 0)
            ->sortBy('name')
            ->values();
    }
}
