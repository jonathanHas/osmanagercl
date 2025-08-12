<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesAccountingReportController extends Controller
{
    /**
     * Display the sales accounting report interface
     */
    public function index(Request $request)
    {
        // Set default date range (current month)
        $defaultStartDate = Carbon::now()->startOfMonth()->format('Y-m-d');
        $defaultEndDate = Carbon::now()->format('Y-m-d');

        $startDate = $request->get('start_date', $defaultStartDate);
        $endDate = $request->get('end_date', $defaultEndDate);

        // Validate dates
        $startDate = Carbon::parse($startDate);
        $endDate = Carbon::parse($endDate);

        $data = null;
        $errors = [];

        // Only generate report if dates are provided
        if ($startDate && $endDate) {
            try {
                $data = $this->generateSalesAccountingReport($startDate, $endDate);
            } catch (\Exception $e) {
                $errors[] = 'Error generating report: '.$e->getMessage();
            }
        }

        return view('management.sales-accounting.index', compact(
            'startDate',
            'endDate',
            'data',
            'errors'
        ));
    }

    /**
     * Generate the sales accounting report data
     */
    private function generateSalesAccountingReport($startDate, $endDate)
    {
        // Check if we have pre-aggregated data for this date range
        $hasAggregatedData = $this->hasAggregatedData($startDate, $endDate);

        if ($hasAggregatedData) {
            return $this->getAggregatedReport($startDate, $endDate);
        } else {
            // Fall back to real-time query from POS database
            return $this->getRealTimeReport($startDate, $endDate);
        }
    }

    /**
     * Check if we have pre-aggregated data for the date range
     */
    private function hasAggregatedData($startDate, $endDate): bool
    {
        $salesDataExists = DB::table('sales_accounting_daily')
            ->whereBetween('sale_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->exists();

        $transferDataExists = DB::table('stock_transfer_daily')
            ->whereBetween('transfer_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->exists();

        return $salesDataExists || $transferDataExists;
    }

    /**
     * Get report data from pre-aggregated tables (100x faster)
     */
    private function getAggregatedReport($startDate, $endDate)
    {
        // Get VAT rates for column headers
        $vatRates = DB::table('sales_accounting_daily')
            ->whereBetween('sale_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->distinct()
            ->pluck('vat_rate')
            ->sort()
            ->values()
            ->toArray();

        // Get main sales data (excluding stock transfers)
        $mainSales = DB::table('sales_accounting_daily')
            ->select(
                'payment_type',
                'vat_rate',
                DB::raw('SUM(net_amount) as net_amount'),
                DB::raw('SUM(vat_amount) as vat_amount'),
                DB::raw('SUM(gross_amount) as gross_amount'),
                DB::raw('SUM(transaction_count) as transaction_count')
            )
            ->whereBetween('sale_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->groupBy('payment_type', 'vat_rate')
            ->orderBy('payment_type')
            ->orderBy('vat_rate')
            ->get();

        // Get stock transfer data (Kitchen & Coffee)
        $stockTransfers = DB::table('stock_transfer_daily')
            ->select(
                'department',
                'vat_rate',
                DB::raw('SUM(net_amount) as net_amount'),
                DB::raw('SUM(vat_amount) as vat_amount'),
                DB::raw('SUM(gross_amount) as gross_amount'),
                DB::raw('SUM(transaction_count) as transaction_count')
            )
            ->whereBetween('transfer_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->groupBy('department', 'vat_rate')
            ->orderBy('department')
            ->orderBy('vat_rate')
            ->get();

        return $this->formatReportData($vatRates, $mainSales, $stockTransfers);
    }

    /**
     * Get report data directly from POS database (fallback for missing aggregated data)
     */
    private function getRealTimeReport($startDate, $endDate)
    {
        // Format dates for POS database query
        $formattedStartDate = $startDate->format('Y m d');
        $formattedEndDate = $endDate->format('Y m d');

        // Get VAT rates from TAXES table
        $vatRates = DB::connection('pos')
            ->table('TAXES')
            ->distinct()
            ->pluck('RATE')
            ->sort()
            ->values()
            ->toArray();

        // Main sales query (excluding Kitchen and Coffee customers)
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
            ORDER BY PAYMENTS.PAYMENT ASC, TAXES.RATE ASC
        ";

        $mainSales = DB::connection('pos')
            ->select($mainSalesQuery, [$formattedStartDate, $formattedEndDate]);

        // Stock transfers query (Kitchen and Coffee customers only)
        $transfersQuery = "
            SELECT TAXES.RATE, 
                   SUM(PRICE * UNITS) AS Net,
                   SUM(PRICE * RATE * UNITS) AS VATtotals,
                   CUSTOMERS.NAME as Department
            FROM TICKETLINES
            JOIN TICKETS ON TICKETLINES.TICKET = TICKETS.ID
            JOIN RECEIPTS ON TICKETS.ID = RECEIPTS.ID
            JOIN TAXES ON TICKETLINES.TAXID = TAXES.ID
            JOIN CUSTOMERS ON TICKETS.CUSTOMER = CUSTOMERS.ID
            WHERE DATE_FORMAT(DATENEW, '%Y %m %d') BETWEEN ? AND ?
            AND CUSTOMERS.NAME IN ('Kitchen', 'Coffee')
            GROUP BY CUSTOMERS.NAME, TAXES.RATE
            ORDER BY CUSTOMERS.NAME ASC, TAXES.RATE ASC
        ";

        $stockTransfers = DB::connection('pos')
            ->select($transfersQuery, [$formattedStartDate, $formattedEndDate]);

        return $this->formatRealTimeReportData($vatRates, $mainSales, $stockTransfers);
    }

    /**
     * Format aggregated report data for display
     */
    private function formatReportData($vatRates, $mainSales, $stockTransfers)
    {
        // Group main sales by payment type (using string keys for consistency)
        $salesByPayment = [];
        foreach ($mainSales as $sale) {
            $rateKey = (string) $sale->vat_rate;
            $salesByPayment[$sale->payment_type][$rateKey] = $sale;
        }

        // Group stock transfers by department (using string keys for consistency)
        $transfersByDept = [];
        foreach ($stockTransfers as $transfer) {
            $rateKey = (string) $transfer->vat_rate;
            $transfersByDept[$transfer->department][$rateKey] = $transfer;
        }

        $data = [
            'vat_rates' => $vatRates,
            'payment_types' => array_keys($salesByPayment),
            'departments' => array_keys($transfersByDept),
            'main_sales' => $salesByPayment,
            'stock_transfers' => $transfersByDept,
            'data_source' => 'aggregated',
        ];

        // Add enhanced data for improved UI
        return $this->enhanceReportData($data);
    }

    /**
     * Format real-time report data for display
     */
    private function formatRealTimeReportData($vatRates, $mainSales, $stockTransfers)
    {
        // Convert real-time data to same format as aggregated data
        $salesByPayment = [];
        foreach ($mainSales as $sale) {
            // Use string keys to avoid PHP 8.3 float-to-int conversion issues
            $rateKey = (string) $sale->RATE;
            $salesByPayment[$sale->PAYMENT][$rateKey] = (object) [
                'payment_type' => $sale->PAYMENT,
                'vat_rate' => $sale->RATE,
                'net_amount' => $sale->Net,
                'vat_amount' => $sale->Net * $sale->RATE,
                'gross_amount' => $sale->Net + ($sale->Net * $sale->RATE),
                'transaction_count' => 1, // Not available in real-time query
            ];
        }

        $transfersByDept = [];
        foreach ($stockTransfers as $transfer) {
            // Use string keys to avoid PHP 8.3 float-to-int conversion issues
            $rateKey = (string) $transfer->RATE;
            $transfersByDept[$transfer->Department][$rateKey] = (object) [
                'department' => $transfer->Department,
                'vat_rate' => $transfer->RATE,
                'net_amount' => $transfer->Net,
                'vat_amount' => $transfer->VATtotals,
                'gross_amount' => $transfer->Net + $transfer->VATtotals,
                'transaction_count' => 1, // Not available in real-time query
            ];
        }

        $data = [
            'vat_rates' => $vatRates,
            'payment_types' => array_keys($salesByPayment),
            'departments' => array_keys($transfersByDept),
            'main_sales' => $salesByPayment,
            'stock_transfers' => $transfersByDept,
            'data_source' => 'real_time',
        ];

        // Add enhanced data for improved UI
        return $this->enhanceReportData($data);
    }

    /**
     * Enhance report data with active VAT rates and summary metrics
     */
    private function enhanceReportData($data)
    {
        // Find active VAT rates (rates that actually have data)
        $activeVatRates = [];
        $zeroVatRates = [];

        foreach ($data['vat_rates'] as $rate) {
            $hasData = false;
            $rateKey = (string) $rate;

            // Check main sales
            foreach ($data['payment_types'] as $paymentType) {
                if (isset($data['main_sales'][$paymentType][$rateKey])) {
                    $hasData = true;
                    break;
                }
            }

            // Check stock transfers if no main sales data
            if (! $hasData) {
                foreach ($data['departments'] as $department) {
                    if (isset($data['stock_transfers'][$department][$rateKey])) {
                        $hasData = true;
                        break;
                    }
                }
            }

            if ($hasData) {
                if ($rate == 0) {
                    $zeroVatRates[] = $rate;
                } else {
                    $activeVatRates[] = $rate;
                }
            }
        }

        // Calculate summary metrics
        $summaryMetrics = $this->calculateSummaryMetrics($data);

        // Calculate paperin total for adjustments
        $paperinTotal = 0;
        if (isset($data['main_sales']['paperin'])) {
            foreach ($data['main_sales']['paperin'] as $rateKey => $sale) {
                $paperinTotal += $sale->gross_amount;
            }
        }

        $data['active_vat_rates'] = $activeVatRates;
        $data['zero_vat_rates'] = $zeroVatRates;
        $data['all_active_rates'] = array_merge($zeroVatRates, $activeVatRates);
        $data['summary_metrics'] = $summaryMetrics;
        $data['paperin_total'] = $paperinTotal;

        return $data;
    }

    /**
     * Calculate summary metrics for dashboard cards
     */
    private function calculateSummaryMetrics($data)
    {
        $totalRevenue = 0;
        $totalVat = 0;
        $totalTransfers = 0;
        $transferVat = 0;
        $voucherSales = 0;

        // Calculate main sales totals (excluding paperin/voucher sales)
        foreach ($data['payment_types'] as $paymentType) {
            foreach ($data['vat_rates'] as $rate) {
                $rateKey = (string) $rate;
                if (isset($data['main_sales'][$paymentType][$rateKey])) {
                    $sale = $data['main_sales'][$paymentType][$rateKey];

                    if ($paymentType === 'paperin') {
                        // Track voucher sales separately
                        $voucherSales += $sale->gross_amount;
                    } else {
                        // Actual revenue from real sales
                        $totalRevenue += $sale->net_amount;
                        $totalVat += $sale->vat_amount;
                    }
                }
            }
        }

        // Calculate stock transfer totals
        foreach ($data['departments'] as $department) {
            foreach ($data['vat_rates'] as $rate) {
                $rateKey = (string) $rate;
                if (isset($data['stock_transfers'][$department][$rateKey])) {
                    $transfer = $data['stock_transfers'][$department][$rateKey];
                    $totalTransfers += $transfer->net_amount;
                    $transferVat += $transfer->vat_amount;
                }
            }
        }

        // Calculate paperin adjustment
        $paperinAdjustment = 0;
        if (isset($data['main_sales']['paperin'])) {
            foreach ($data['main_sales']['paperin'] as $rateKey => $sale) {
                $paperinAdjustment += $sale->gross_amount;
            }
        }

        return [
            'total_revenue' => $totalRevenue,
            'total_vat' => $totalVat,
            'total_transfers' => $totalTransfers,
            'transfer_vat' => $transferVat,
            'voucher_sales' => $voucherSales,
            'paperin_adjustment' => $paperinAdjustment,
            'gross_revenue' => $totalRevenue + $totalVat,
        ];
    }

    /**
     * Export sales accounting report to CSV
     */
    public function exportCsv(Request $request)
    {
        $startDate = Carbon::parse($request->get('start_date'));
        $endDate = Carbon::parse($request->get('end_date'));

        $data = $this->generateSalesAccountingReport($startDate, $endDate);

        $filename = 'sales_accounting_'.$startDate->format('Y-m-d').'_to_'.$endDate->format('Y-m-d').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($data, $startDate, $endDate) {
            $file = fopen('php://output', 'w');

            // Write report header information
            fputcsv($file, ['Sales Accounting Report']);
            fputcsv($file, ['Date Range:', $startDate->format('M j, Y').' to '.$endDate->format('M j, Y')]);
            fputcsv($file, ['Generated:', now()->format('M j, Y H:i:s')]);
            fputcsv($file, ['Data Source:', $data['data_source'] === 'aggregated' ? 'Optimized (Fast)' : 'Real-time (May be slower)']);
            fputcsv($file, []); // Empty row

            // Write summary metrics
            fputcsv($file, ['SUMMARY METRICS']);
            fputcsv($file, ['Customer Revenue (excludes vouchers):', '€'.number_format($data['summary_metrics']['gross_revenue'], 2)]);
            fputcsv($file, ['Total VAT Owed:', '€'.number_format($data['summary_metrics']['total_vat'], 2)]);
            if ($data['summary_metrics']['total_transfers'] > 0) {
                fputcsv($file, ['Stock Transfers:', '€'.number_format($data['summary_metrics']['total_transfers'] + $data['summary_metrics']['transfer_vat'], 2)]);
            }
            if ($data['summary_metrics']['voucher_sales'] > 0) {
                fputcsv($file, ['Voucher Sales:', '€'.number_format($data['summary_metrics']['voucher_sales'], 2)]);
            }
            fputcsv($file, []); // Empty row

            // Write VAT breakdown
            fputcsv($file, ['VAT BREAKDOWN']);
            foreach ($data['all_active_rates'] as $rate) {
                $ratePercent = number_format($rate * 100, 1).'%';
                $vatAmount = 0;

                // Calculate VAT for this rate across all payment types
                foreach ($data['payment_types'] as $paymentType) {
                    if ($paymentType === 'paperin') {
                        continue;
                    } // Skip vouchers for VAT calculation
                    $rateKey = (string) $rate;
                    if (isset($data['main_sales'][$paymentType][$rateKey])) {
                        $vatAmount += $data['main_sales'][$paymentType][$rateKey]->vat_amount;
                    }
                }

                if ($vatAmount > 0) {
                    fputcsv($file, [$ratePercent.' VAT:', '€'.number_format($vatAmount, 2)]);
                }
            }
            fputcsv($file, []); // Empty row

            // Write main sales table
            fputcsv($file, ['CUSTOMER SALES (REVENUE)']);
            fputcsv($file, ['Actual sales to customers - included in revenue calculations']);

            // Sales table header
            $headerRow = ['Payment Type'];
            foreach ($data['all_active_rates'] as $rate) {
                $headerRow[] = number_format($rate * 100, 1).'% Net';
                if ($rate != 0) {
                    $headerRow[] = number_format($rate * 100, 1).'% VAT';
                }
            }
            $headerRow[] = 'Net Total';
            $headerRow[] = 'VAT Total';
            $headerRow[] = 'Gross Total';
            fputcsv($file, $headerRow);

            // Write main sales data
            foreach ($data['payment_types'] as $paymentType) {
                $row = [$paymentType];
                $netTotal = $vatTotal = 0;

                foreach ($data['all_active_rates'] as $rate) {
                    $rateKey = (string) $rate;
                    $sale = $data['main_sales'][$paymentType][$rateKey] ?? null;
                    $net = $sale ? $sale->net_amount : 0;
                    $vat = $sale ? $sale->vat_amount : 0;

                    $row[] = number_format($net, 2);
                    if ($rate != 0) {
                        $row[] = number_format($vat, 2);
                    }

                    $netTotal += $net;
                    $vatTotal += $vat;
                }

                $row[] = number_format($netTotal, 2);
                $row[] = number_format($vatTotal, 2);
                $row[] = number_format($netTotal + $vatTotal, 2);

                fputcsv($file, $row);
            }

            // Write paperin adjust row (gift voucher double-counting prevention)
            $paperinTotal = 0;
            if (isset($data['main_sales']['paperin'])) {
                foreach ($data['main_sales']['paperin'] as $rateKey => $sale) {
                    $paperinTotal += $sale->gross_amount;
                }
            }

            if ($paperinTotal > 0) {
                $row = ['paperin adjust*'];
                foreach ($data['all_active_rates'] as $rate) {
                    if ($rate == 0) {
                        $row[] = number_format(-$paperinTotal, 2);
                    } else {
                        $row[] = '0.00';
                        if ($rate != 0) {
                            $row[] = '0.00';
                        }
                    }
                }
                $row[] = number_format(-$paperinTotal, 2);
                $row[] = '0.00';
                $row[] = number_format(-$paperinTotal, 2);

                fputcsv($file, $row);
            }

            // Write sales totals
            $salesTotalsRow = ['TOTAL SALES'];
            $grandNetTotal = $grandVatTotal = 0;

            foreach ($data['all_active_rates'] as $rate) {
                $rateNetTotal = $rateVatTotal = 0;
                foreach ($data['payment_types'] as $paymentType) {
                    $rateKey = (string) $rate;
                    if (isset($data['main_sales'][$paymentType][$rateKey])) {
                        $sale = $data['main_sales'][$paymentType][$rateKey];
                        $rateNetTotal += $sale->net_amount;
                        $rateVatTotal += $sale->vat_amount;
                    }
                }
                // Apply paperin adjustment to 0% rate
                if ($rate == 0 && $paperinTotal > 0) {
                    $rateNetTotal -= $paperinTotal;
                }

                $grandNetTotal += $rateNetTotal;
                $grandVatTotal += $rateVatTotal;

                $salesTotalsRow[] = '€'.number_format($rateNetTotal, 2);
                if ($rate != 0) {
                    $salesTotalsRow[] = '€'.number_format($rateVatTotal, 2);
                }
            }

            $salesTotalsRow[] = '€'.number_format($grandNetTotal, 2);
            $salesTotalsRow[] = '€'.number_format($grandVatTotal, 2);
            $salesTotalsRow[] = '€'.number_format($grandNetTotal + $grandVatTotal, 2);
            fputcsv($file, $salesTotalsRow);
            fputcsv($file, []); // Empty row

            // Write stock transfers section if there are any
            if (! empty($data['departments'])) {
                fputcsv($file, ['INTERNAL STOCK TRANSFERS']);
                fputcsv($file, ['Stock movements between departments - not included in revenue']);

                // Transfers table header (same as sales)
                $transferHeaderRow = ['Department'];
                foreach ($data['all_active_rates'] as $rate) {
                    $transferHeaderRow[] = number_format($rate * 100, 1).'% Net';
                    if ($rate != 0) {
                        $transferHeaderRow[] = number_format($rate * 100, 1).'% VAT';
                    }
                }
                $transferHeaderRow[] = 'Net Total';
                $transferHeaderRow[] = 'VAT Total';
                $transferHeaderRow[] = 'Gross Total';
                fputcsv($file, $transferHeaderRow);

                // Write transfer data
                foreach ($data['departments'] as $department) {
                    $row = [$department];
                    $netTotal = $vatTotal = 0;

                    foreach ($data['all_active_rates'] as $rate) {
                        $rateKey = (string) $rate;
                        $transfer = $data['stock_transfers'][$department][$rateKey] ?? null;
                        $net = $transfer ? $transfer->net_amount : 0;
                        $vat = $transfer ? $transfer->vat_amount : 0;

                        $row[] = number_format($net, 2);
                        if ($rate != 0) {
                            $row[] = number_format($vat, 2);
                        }

                        $netTotal += $net;
                        $vatTotal += $vat;
                    }

                    $row[] = number_format($netTotal, 2);
                    $row[] = number_format($vatTotal, 2);
                    $row[] = number_format($netTotal + $vatTotal, 2);

                    fputcsv($file, $row);
                }

                // Write transfers totals
                $transferTotalsRow = ['TOTAL TRANSFERS'];
                $transferNetTotal = $transferVatTotal = 0;

                foreach ($data['all_active_rates'] as $rate) {
                    $rateNetTotal = $rateVatTotal = 0;
                    foreach ($data['departments'] as $department) {
                        $rateKey = (string) $rate;
                        if (isset($data['stock_transfers'][$department][$rateKey])) {
                            $transfer = $data['stock_transfers'][$department][$rateKey];
                            $rateNetTotal += $transfer->net_amount;
                            $rateVatTotal += $transfer->vat_amount;
                        }
                    }

                    $transferNetTotal += $rateNetTotal;
                    $transferVatTotal += $rateVatTotal;

                    $transferTotalsRow[] = '€'.number_format($rateNetTotal, 2);
                    if ($rate != 0) {
                        $transferTotalsRow[] = '€'.number_format($rateVatTotal, 2);
                    }
                }

                $transferTotalsRow[] = '€'.number_format($transferNetTotal, 2);
                $transferTotalsRow[] = '€'.number_format($transferVatTotal, 2);
                $transferTotalsRow[] = '€'.number_format($transferNetTotal + $transferVatTotal, 2);
                fputcsv($file, $transferTotalsRow);
                fputcsv($file, []); // Empty row
            }

            // Write final summary
            fputcsv($file, ['FINAL SUMMARY']);
            fputcsv($file, ['Total Customer Revenue:', '€'.number_format($data['summary_metrics']['gross_revenue'], 2)]);
            if (! empty($data['departments'])) {
                fputcsv($file, ['Total Stock Transfers:', '€'.number_format($data['summary_metrics']['total_transfers'] + $data['summary_metrics']['transfer_vat'], 2)]);
            }
            fputcsv($file, ['Total VAT for Returns:', '€'.number_format($data['summary_metrics']['total_vat'], 2)]);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
