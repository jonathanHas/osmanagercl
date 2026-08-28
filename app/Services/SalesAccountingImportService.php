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
        // Never import today — the day isn't complete yet, and partial data
        // would be treated as "existing" and skipped on future runs.
        $yesterday = Carbon::yesterday();
        if ($endDate->gt($yesterday)) {
            $endDate = $yesterday;
        }
        if ($startDate->gt($endDate)) {
            return;
        }

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
     * Net sales by payment type and VAT rate, excluding Kitchen/Coffee customers.
     *
     * Ticket line values are apportioned across a receipt's payment types by each
     * payment's share of the receipt total. A plain JOIN on PAYMENTS repeats every
     * ticket line once per payment row, inflating net/VAT on split-payment receipts
     * (part cash / part card). Single-payment receipts get a share of 1 and are
     * unaffected, so only split receipts change. Receipts whose payments net to zero
     * (debt settlements) are excluded — they carry no ticket lines.
     *
     * Shared by the daily import, the sales accounting report and the P&L so the four
     * call sites cannot drift apart. See docs/features/vat-returns.md.
     *
     * @param  Carbon  $endExclusive  Upper bound, exclusive.
     * @return array<int, object> Rows of {RATE, PAYMENT, Net, TransactionCount}
     */
    public function getApportionedSales(Carbon $start, Carbon $endExclusive): array
    {
        $from = $start->format('Y-m-d H:i:s');
        $to = $endExclusive->format('Y-m-d H:i:s');

        return DB::connection('pos')->select("
            SELECT
                TAXES.RATE,
                pay.PAYMENT,
                SUM(TICKETLINES.PRICE * TICKETLINES.UNITS * (pay.paid / tot.total)) AS Net,
                COUNT(DISTINCT RECEIPTS.ID) AS TransactionCount
            FROM TICKETLINES
            JOIN TICKETS ON TICKETLINES.TICKET = TICKETS.ID
            JOIN RECEIPTS ON TICKETS.ID = RECEIPTS.ID
            JOIN TAXES ON TICKETLINES.TAXID = TAXES.ID
            JOIN (
                SELECT p.RECEIPT, p.PAYMENT, SUM(p.TOTAL) AS paid
                FROM PAYMENTS p
                JOIN RECEIPTS r2 ON p.RECEIPT = r2.ID
                WHERE r2.DATENEW >= ? AND r2.DATENEW < ?
                GROUP BY p.RECEIPT, p.PAYMENT
            ) pay ON pay.RECEIPT = RECEIPTS.ID
            JOIN (
                SELECT p.RECEIPT, SUM(p.TOTAL) AS total
                FROM PAYMENTS p
                JOIN RECEIPTS r3 ON p.RECEIPT = r3.ID
                WHERE r3.DATENEW >= ? AND r3.DATENEW < ?
                GROUP BY p.RECEIPT
                HAVING SUM(p.TOTAL) <> 0
            ) tot ON tot.RECEIPT = RECEIPTS.ID
            LEFT JOIN CUSTOMERS ON TICKETS.CUSTOMER = CUSTOMERS.ID
            WHERE RECEIPTS.DATENEW >= ? AND RECEIPTS.DATENEW < ?
            AND (CUSTOMERS.NAME IS NULL OR CUSTOMERS.NAME NOT IN ('Kitchen', 'Coffee'))
            GROUP BY pay.PAYMENT, TAXES.RATE
            ORDER BY pay.PAYMENT ASC, TAXES.RATE ASC
        ", [$from, $to, $from, $to, $from, $to]);
    }

    /**
     * Import main sales data for a day (excluding Kitchen/Coffee customers).
     */
    public function importMainSalesData(Carbon $date): array
    {
        $salesData = $this->getApportionedSales(
            $date->copy()->startOfDay(),
            $date->copy()->addDay()->startOfDay()
        );

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
        $dayStart = $date->format('Y-m-d 00:00:00');
        $dayEnd = $date->copy()->addDay()->format('Y-m-d 00:00:00');

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
            WHERE DATENEW >= ? AND DATENEW < ?
            AND CUSTOMERS.NAME IN ('Kitchen', 'Coffee')
            GROUP BY CUSTOMERS.NAME, TAXES.RATE
        ", [$dayStart, $dayEnd]);

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
