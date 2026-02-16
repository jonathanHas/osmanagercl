<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SalesAccountingImportService
{
    /**
     * Ensure sales_accounting_daily and stock_transfer_daily have data for the given date range.
     * Only imports days that are missing — already-imported days are skipped.
     */
    public function ensureDataExists(Carbon $startDate, Carbon $endDate): void
    {
        $start = $startDate->format('Y-m-d');
        $end = $endDate->format('Y-m-d');

        $existingDates = DB::table('sales_accounting_daily')
            ->whereBetween('sale_date', [$start, $end])
            ->distinct()
            ->pluck('sale_date')
            ->map(fn ($d) => Carbon::parse($d)->format('Y-m-d'))
            ->toArray();

        $current = $startDate->copy();
        while ($current->lte($endDate)) {
            if (! in_array($current->format('Y-m-d'), $existingDates)) {
                $this->importDay($current);
            }
            $current->addDay();
        }
    }

    /**
     * Import sales and stock transfer data for a single day from POS.
     */
    public function importDay(Carbon $date): array
    {
        $inserted = 0;
        $updated = 0;

        DB::beginTransaction();

        try {
            $salesResult = $this->importMainSalesData($date);
            $inserted += $salesResult['inserted'];
            $updated += $salesResult['updated'];

            $transferResult = $this->importStockTransferData($date);
            $inserted += $transferResult['inserted'];
            $updated += $transferResult['updated'];

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return ['inserted' => $inserted, 'updated' => $updated];
    }

    /**
     * Import main sales data for a day (excluding Kitchen/Coffee customers).
     */
    public function importMainSalesData(Carbon $date): array
    {
        $formattedDate = $date->format('Y m d');

        $salesData = DB::connection('pos')->select("
            SELECT
                TAXES.RATE,
                SUM(PRICE * UNITS) AS Net,
                PAYMENTS.PAYMENT,
                COUNT(DISTINCT RECEIPTS.ID) as TransactionCount
            FROM TICKETLINES
            JOIN TICKETS ON TICKETLINES.TICKET = TICKETS.ID
            JOIN RECEIPTS ON TICKETS.ID = RECEIPTS.ID
            JOIN PAYMENTS ON RECEIPTS.ID = PAYMENTS.RECEIPT
            JOIN TAXES ON TICKETLINES.TAXID = TAXES.ID
            LEFT JOIN CUSTOMERS ON TICKETS.CUSTOMER = CUSTOMERS.ID
            WHERE DATE_FORMAT(DATENEW, '%Y %m %d') = ?
            AND (CUSTOMERS.NAME IS NULL OR CUSTOMERS.NAME NOT IN ('Kitchen', 'Coffee'))
            GROUP BY PAYMENTS.PAYMENT, TAXES.RATE
        ", [$formattedDate]);

        $inserted = 0;
        $updated = 0;

        foreach ($salesData as $sale) {
            $netAmount = $sale->Net;
            $vatAmount = $netAmount * $sale->RATE;
            $grossAmount = $netAmount + $vatAmount;

            $data = [
                'sale_date' => $date->format('Y-m-d'),
                'payment_type' => $sale->PAYMENT,
                'vat_rate' => $sale->RATE,
                'net_amount' => $netAmount,
                'vat_amount' => $vatAmount,
                'gross_amount' => $grossAmount,
                'transaction_count' => $sale->TransactionCount,
                'updated_at' => now(),
                'created_at' => now(),
            ];

            try {
                DB::table('sales_accounting_daily')->insert($data);
                $inserted++;
            } catch (\Exception $e) {
                // Record already exists, skip
            }
        }

        return ['inserted' => $inserted, 'updated' => $updated];
    }

    /**
     * Import stock transfer data for a day (Kitchen/Coffee customers).
     */
    public function importStockTransferData(Carbon $date): array
    {
        $formattedDate = $date->format('Y m d');

        $transferData = DB::connection('pos')->select("
            SELECT
                TAXES.RATE,
                SUM(PRICE * UNITS) AS Net,
                SUM(PRICE * RATE * UNITS) AS VATtotals,
                CUSTOMERS.NAME as Department,
                COUNT(DISTINCT RECEIPTS.ID) as TransactionCount
            FROM TICKETLINES
            JOIN TICKETS ON TICKETLINES.TICKET = TICKETS.ID
            JOIN RECEIPTS ON TICKETS.ID = RECEIPTS.ID
            JOIN TAXES ON TICKETLINES.TAXID = TAXES.ID
            JOIN CUSTOMERS ON TICKETS.CUSTOMER = CUSTOMERS.ID
            WHERE DATE_FORMAT(DATENEW, '%Y %m %d') = ?
            AND CUSTOMERS.NAME IN ('Kitchen', 'Coffee')
            GROUP BY CUSTOMERS.NAME, TAXES.RATE
        ", [$formattedDate]);

        $inserted = 0;
        $updated = 0;

        foreach ($transferData as $transfer) {
            $netAmount = $transfer->Net;
            $vatAmount = $transfer->VATtotals;
            $grossAmount = $netAmount + $vatAmount;

            $data = [
                'transfer_date' => $date->format('Y-m-d'),
                'department' => $transfer->Department,
                'vat_rate' => $transfer->RATE,
                'net_amount' => $netAmount,
                'vat_amount' => $vatAmount,
                'gross_amount' => $grossAmount,
                'transaction_count' => $transfer->TransactionCount,
                'updated_at' => now(),
                'created_at' => now(),
            ];

            try {
                DB::table('stock_transfer_daily')->insert($data);
                $inserted++;
            } catch (\Exception $e) {
                // Record already exists, skip
            }
        }

        return ['inserted' => $inserted, 'updated' => $updated];
    }

    /**
     * Force re-import data for a date range (overwrites existing records).
     */
    public function forceImportDay(Carbon $date): array
    {
        $inserted = 0;
        $updated = 0;

        DB::beginTransaction();

        try {
            // Delete existing data for this day
            DB::table('sales_accounting_daily')->where('sale_date', $date->format('Y-m-d'))->delete();
            DB::table('stock_transfer_daily')->where('transfer_date', $date->format('Y-m-d'))->delete();

            // Re-import
            $salesResult = $this->importMainSalesData($date);
            $inserted += $salesResult['inserted'];

            $transferResult = $this->importStockTransferData($date);
            $inserted += $transferResult['inserted'];

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return ['inserted' => $inserted, 'updated' => $updated];
    }
}
