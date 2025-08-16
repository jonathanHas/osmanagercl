<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfitLossController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        // Default to current month if no dates provided
        $startDate = $request->get('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', Carbon::now()->endOfMonth()->format('Y-m-d'));

        $carbonStart = Carbon::parse($startDate);
        $carbonEnd = Carbon::parse($endDate);

        // Get revenue data from POS
        $revenueData = $this->getRevenueData($carbonStart, $carbonEnd);

        // Get cost data from invoices
        $costData = $this->getCostData($carbonStart, $carbonEnd);

        // Get cash payments to suppliers
        $supplierPayments = $this->getSupplierPayments($carbonStart, $carbonEnd);

        // Calculate profit/loss
        $totalRevenue = $revenueData['total_revenue'];
        $totalCosts = $costData['total_costs'] + $supplierPayments;
        $profitLoss = $totalRevenue - $totalCosts;
        $marginPercent = $totalRevenue > 0 ? ($profitLoss / $totalRevenue) * 100 : 0;

        // Get comparison data for previous period
        $previousPeriodData = $this->getPreviousPeriodComparison($carbonStart, $carbonEnd);

        return view('management.profit-loss.index', compact(
            'startDate',
            'endDate',
            'revenueData',
            'costData',
            'supplierPayments',
            'totalRevenue',
            'totalCosts',
            'profitLoss',
            'marginPercent',
            'previousPeriodData'
        ));
    }

    private function getRevenueData($startDate, $endDate)
    {
        // Check if we have pre-aggregated data for better performance
        $hasAggregatedData = DB::table('sales_accounting_daily')
            ->whereBetween('sale_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->exists();

        if ($hasAggregatedData) {
            // Use pre-aggregated data (100x faster)
            return $this->getAggregatedRevenueData($startDate, $endDate);
        }

        // Fall back to real-time POS queries - matching sales-accounting methodology exactly
        $formattedStartDate = $startDate->format('Y m d');
        $formattedEndDate = $endDate->format('Y m d');

        // Use TICKETLINES approach (same as sales-accounting) for accurate calculations
        $mainSalesQuery = "
            SELECT TAXES.RATE, SUM(PRICE * UNITS) AS Net, PAYMENTS.PAYMENT
            FROM TICKETLINES
            JOIN TICKETS ON TICKETLINES.TICKET = TICKETS.ID
            JOIN RECEIPTS ON TICKETS.ID = RECEIPTS.ID
            JOIN PAYMENTS ON RECEIPTS.ID = PAYMENTS.RECEIPT
            JOIN TAXES ON TICKETLINES.TAXID = TAXES.ID
            LEFT JOIN CUSTOMERS ON TICKETS.CUSTOMER = CUSTOMERS.ID
            WHERE DATE_FORMAT(DATENEW, '%Y %m %d') BETWEEN ? AND ?
            AND (CUSTOMERS.NAME IS NULL OR CUSTOMERS.NAME NOT IN ('Kitchen', 'Coffee'))
            GROUP BY PAYMENTS.PAYMENT, TAXES.RATE
        ";

        $mainSales = DB::connection('pos')->select($mainSalesQuery, [$formattedStartDate, $formattedEndDate]);

        // Process the results to calculate totals and VAT breakdown
        $paymentTotals = [
            'cash' => ['net' => 0, 'vat' => 0],
            'magcard' => ['net' => 0, 'vat' => 0],
            'debt' => ['net' => 0, 'vat' => 0],
            'free' => ['net' => 0, 'vat' => 0],
            'paperin' => ['net' => 0, 'vat' => 0],
        ];

        $vatBreakdown = [
            '0.23' => ['net' => 0, 'vat' => 0],
            '0.135' => ['net' => 0, 'vat' => 0],
            '0.09' => ['net' => 0, 'vat' => 0],
            '0' => ['net' => 0, 'vat' => 0],
        ];

        $totalNetSales = 0;
        $totalVatOnSales = 0;
        $paperinGrossTotal = 0;

        foreach ($mainSales as $sale) {
            $net = $sale->Net ?? 0;
            $vat = $net * $sale->RATE;
            $payment = strtolower($sale->PAYMENT);
            $rateKey = (string) $sale->RATE;

            // Track by payment type
            if (isset($paymentTotals[$payment])) {
                $paymentTotals[$payment]['net'] += $net;
                $paymentTotals[$payment]['vat'] += $vat;
            }

            // Track by VAT rate
            if (isset($vatBreakdown[$rateKey])) {
                $vatBreakdown[$rateKey]['net'] += $net;
                $vatBreakdown[$rateKey]['vat'] += $vat;
            }

            $totalNetSales += $net;
            $totalVatOnSales += $vat;

            // Track paperin for adjustment
            if ($payment === 'paperin') {
                $paperinGrossTotal += ($net + $vat);
            }
        }

        // Apply paperin adjustment (gift vouchers should not be counted as revenue)
        $adjustedNetRevenue = $totalNetSales - $paperinGrossTotal;

        // Get transaction count separately
        $transactionCount = DB::connection('pos')
            ->table('RECEIPTS as r')
            ->leftJoin('TICKETS as t', 'r.ID', '=', 't.ID')
            ->leftJoin('CUSTOMERS as c', 't.CUSTOMER', '=', 'c.ID')
            ->whereBetween('r.DATENEW', [$startDate, $endDate])
            ->where(function ($query) {
                $query->whereNull('c.NAME')
                    ->orWhereNotIn('c.NAME', ['Kitchen', 'Coffee']);
            })
            ->count('r.ID');

        // Return the calculated values
        return [
            'gross_sales' => $totalNetSales + $totalVatOnSales,
            'refunds' => 0, // Would need separate query to calculate
            'gross_revenue' => $totalNetSales + $totalVatOnSales,
            'net_revenue' => $adjustedNetRevenue,
            'vat_on_sales' => $totalVatOnSales,
            'total_revenue' => $adjustedNetRevenue, // Use adjusted net revenue for P&L
            'cash_sales_net' => $paymentTotals['cash']['net'] ?? 0,
            'card_sales_net' => $paymentTotals['magcard']['net'] ?? 0,
            'debt_sales_net' => $paymentTotals['debt']['net'] ?? 0,
            'free_sales' => $paymentTotals['free']['net'] ?? 0,
            'transaction_count' => $transactionCount,
            'avg_transaction' => $transactionCount > 0 ? $adjustedNetRevenue / $transactionCount : 0,
            'vat_breakdown' => [
                'standard_net' => $vatBreakdown['0.23']['net'] ?? 0,
                'standard_vat' => $vatBreakdown['0.23']['vat'] ?? 0,
                'reduced_net' => $vatBreakdown['0.135']['net'] ?? 0,
                'reduced_vat' => $vatBreakdown['0.135']['vat'] ?? 0,
                'second_reduced_net' => $vatBreakdown['0.09']['net'] ?? 0,
                'second_reduced_vat' => $vatBreakdown['0.09']['vat'] ?? 0,
                'zero_net' => $vatBreakdown['0']['net'] ?? 0,
            ],
            'paperin_adjustment' => $paperinGrossTotal, // Track the adjustment amount
            'data_source' => 'real-time',
        ];
    }

    private function getAggregatedRevenueData($startDate, $endDate)
    {
        // Get aggregated sales data (excluding Kitchen/Coffee transfers)
        $salesData = DB::table('sales_accounting_daily')
            ->whereBetween('sale_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->selectRaw('
                SUM(net_amount) as total_net_sales,
                SUM(vat_amount) as total_vat_on_sales,
                SUM(gross_amount) as gross_revenue,
                SUM(transaction_count) as transaction_count,
                SUM(CASE WHEN payment_type = "cash" THEN net_amount ELSE 0 END) as cash_sales_net,
                SUM(CASE WHEN payment_type = "magcard" THEN net_amount ELSE 0 END) as card_sales_net,
                SUM(CASE WHEN payment_type = "debt" THEN net_amount ELSE 0 END) as debt_sales_net,
                SUM(CASE WHEN payment_type = "free" THEN net_amount ELSE 0 END) as free_sales,
                SUM(CASE WHEN vat_rate = 0.23 THEN net_amount ELSE 0 END) as standard_net,
                SUM(CASE WHEN vat_rate = 0.23 THEN vat_amount ELSE 0 END) as standard_vat,
                SUM(CASE WHEN vat_rate = 0.135 THEN net_amount ELSE 0 END) as reduced_net,
                SUM(CASE WHEN vat_rate = 0.135 THEN vat_amount ELSE 0 END) as reduced_vat,
                SUM(CASE WHEN vat_rate = 0.09 THEN net_amount ELSE 0 END) as second_reduced_net,
                SUM(CASE WHEN vat_rate = 0.09 THEN vat_amount ELSE 0 END) as second_reduced_vat,
                SUM(CASE WHEN vat_rate = 0 THEN net_amount ELSE 0 END) as zero_net
            ')
            ->first();

        $netRevenue = $salesData->total_net_sales ?? 0;
        $vatOnSales = $salesData->total_vat_on_sales ?? 0;
        $grossRevenue = $salesData->gross_revenue ?? 0;

        return [
            'gross_sales' => $grossRevenue,
            'refunds' => 0, // Not tracked separately in aggregated data
            'gross_revenue' => $grossRevenue,
            'net_revenue' => $netRevenue,
            'vat_on_sales' => $vatOnSales,
            'total_revenue' => $netRevenue, // Use net revenue for P&L
            'cash_sales_net' => $salesData->cash_sales_net ?? 0,
            'card_sales_net' => $salesData->card_sales_net ?? 0,
            'debt_sales_net' => $salesData->debt_sales_net ?? 0,
            'free_sales' => $salesData->free_sales ?? 0,
            'transaction_count' => $salesData->transaction_count ?? 0,
            'avg_transaction' => $salesData->transaction_count > 0 ? $netRevenue / $salesData->transaction_count : 0,
            'vat_breakdown' => [
                'standard_net' => $salesData->standard_net ?? 0,
                'standard_vat' => $salesData->standard_vat ?? 0,
                'reduced_net' => $salesData->reduced_net ?? 0,
                'reduced_vat' => $salesData->reduced_vat ?? 0,
                'second_reduced_net' => $salesData->second_reduced_net ?? 0,
                'second_reduced_vat' => $salesData->second_reduced_vat ?? 0,
                'zero_net' => $salesData->zero_net ?? 0,
            ],
            'data_source' => 'aggregated',
        ];
    }

    private function getCostData($startDate, $endDate)
    {
        // Get invoice costs by payment status - using VAT-exclusive amounts (subtotal)
        $invoiceData = Invoice::dateRange($startDate, $endDate)
            ->selectRaw('
                SUM(subtotal) as total_invoiced_net,
                SUM(vat_amount) as total_vat_on_invoices,
                SUM(total_amount) as total_invoiced_gross,
                SUM(CASE WHEN payment_status = "paid" THEN subtotal ELSE 0 END) as paid_invoices_net,
                SUM(CASE WHEN payment_status = "paid" THEN vat_amount ELSE 0 END) as paid_invoices_vat,
                SUM(CASE WHEN payment_status = "pending" THEN subtotal ELSE 0 END) as pending_invoices_net,
                SUM(CASE WHEN payment_status = "overdue" THEN subtotal ELSE 0 END) as overdue_invoices_net,
                COUNT(*) as invoice_count
            ')
            ->first();

        // Get VAT breakdown for paid invoices
        $vatBreakdown = Invoice::dateRange($startDate, $endDate)
            ->paid()
            ->selectRaw('
                SUM(subtotal) as total_net,
                SUM(vat_amount) as total_vat,
                SUM(standard_net) as standard_net,
                SUM(standard_vat) as standard_vat,
                SUM(reduced_net) as reduced_net,
                SUM(reduced_vat) as reduced_vat,
                SUM(zero_net) as zero_net
            ')
            ->first();

        return [
            'total_costs' => $invoiceData->paid_invoices_net ?? 0, // Use net amount for P&L
            'total_costs_vat' => $invoiceData->paid_invoices_vat ?? 0,
            'total_invoiced_net' => $invoiceData->total_invoiced_net ?? 0,
            'total_invoiced_gross' => $invoiceData->total_invoiced_gross ?? 0,
            'paid_invoices_net' => $invoiceData->paid_invoices_net ?? 0,
            'pending_invoices_net' => $invoiceData->pending_invoices_net ?? 0,
            'overdue_invoices_net' => $invoiceData->overdue_invoices_net ?? 0,
            'invoice_count' => $invoiceData->invoice_count ?? 0,
            'vat_breakdown' => [
                'total_net' => $vatBreakdown->total_net ?? 0,
                'total_vat' => $vatBreakdown->total_vat ?? 0,
                'standard_net' => $vatBreakdown->standard_net ?? 0,
                'standard_vat' => $vatBreakdown->standard_vat ?? 0,
                'reduced_net' => $vatBreakdown->reduced_net ?? 0,
                'reduced_vat' => $vatBreakdown->reduced_vat ?? 0,
                'zero_net' => $vatBreakdown->zero_net ?? 0,
            ],
        ];
    }

    private function getSupplierPayments($startDate, $endDate)
    {
        return DB::table('cash_reconciliation_payments')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');
    }

    private function getPreviousPeriodComparison($startDate, $endDate)
    {
        $periodLength = $startDate->diffInDays($endDate) + 1;
        $previousStart = $startDate->copy()->subDays($periodLength);
        $previousEnd = $endDate->copy()->subDays($periodLength);

        // Previous period revenue - using same methodology as current period
        $formattedPrevStart = $previousStart->format('Y m d');
        $formattedPrevEnd = $previousEnd->format('Y m d');

        $prevSalesQuery = "
            SELECT TAXES.RATE, SUM(PRICE * UNITS) AS Net, PAYMENTS.PAYMENT
            FROM TICKETLINES
            JOIN TICKETS ON TICKETLINES.TICKET = TICKETS.ID
            JOIN RECEIPTS ON TICKETS.ID = RECEIPTS.ID
            JOIN PAYMENTS ON RECEIPTS.ID = PAYMENTS.RECEIPT
            JOIN TAXES ON TICKETLINES.TAXID = TAXES.ID
            LEFT JOIN CUSTOMERS ON TICKETS.CUSTOMER = CUSTOMERS.ID
            WHERE DATE_FORMAT(DATENEW, '%Y %m %d') BETWEEN ? AND ?
            AND (CUSTOMERS.NAME IS NULL OR CUSTOMERS.NAME NOT IN ('Kitchen', 'Coffee'))
            GROUP BY PAYMENTS.PAYMENT, TAXES.RATE
        ";

        $prevSales = DB::connection('pos')->select($prevSalesQuery, [$formattedPrevStart, $formattedPrevEnd]);

        $prevTotalNet = 0;
        $prevPaperinGross = 0;

        foreach ($prevSales as $sale) {
            $net = $sale->Net ?? 0;
            $vat = $net * $sale->RATE;
            $prevTotalNet += $net;

            if (strtolower($sale->PAYMENT) === 'paperin') {
                $prevPaperinGross += ($net + $vat);
            }
        }

        $previousRevenue = $prevTotalNet - $prevPaperinGross;

        // Previous period costs - VAT exclusive
        $previousCosts = Invoice::dateRange($previousStart, $previousEnd)
            ->paid()
            ->sum('subtotal'); // Use subtotal instead of total_amount

        $previousSupplierPayments = DB::table('cash_reconciliation_payments')
            ->whereBetween('created_at', [$previousStart, $previousEnd])
            ->sum('amount');

        $previousTotalCosts = $previousCosts + $previousSupplierPayments;
        $previousProfit = $previousRevenue - $previousTotalCosts;

        return [
            'revenue' => $previousRevenue,
            'costs' => $previousTotalCosts,
            'profit' => $previousProfit,
            'start_date' => $previousStart->format('Y-m-d'),
            'end_date' => $previousEnd->format('Y-m-d'),
        ];
    }
}
