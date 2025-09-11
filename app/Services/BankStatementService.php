<?php

namespace App\Services;

use App\Models\BankTransaction;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BankStatementService
{
    /**
     * Process the uploaded bank statement CSV file.
     */
    public function processCsv(string $filePath, string $sourceFilename): array
    {
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new Exception("Failed to open file: {$filePath}");
        }

        $importedCount = 0;
        $skippedCount = 0;
        $rowCount = 0;

        // Start a database transaction
        DB::beginTransaction();

        try {
            while (($row = fgetcsv($handle)) !== false) {
                $rowCount++;
                // Skip header or malformed rows (assuming at least 10 columns)
                if ($rowCount === 1 || count($row) < 10) {
                    continue;
                }

                try {
                    $transactionData = $this->parseRow($row, $sourceFilename);

                    if ($this->isDuplicate($transactionData)) {
                        $skippedCount++;

                        continue;
                    }

                    BankTransaction::create($transactionData);
                    $importedCount++;

                } catch (Exception $e) {
                    Log::warning("Skipping row {$rowCount} due to parsing error: ".$e->getMessage(), ['row' => $row]);
                    $skippedCount++;
                }
            }

            // Commit the transaction if all rows are processed successfully
            DB::commit();

        } catch (Exception $e) {
            // Rollback the transaction on any error during processing
            DB::rollBack();
            Log::error("Failed to process CSV file {$sourceFilename}: ".$e->getMessage());
            throw $e; // Re-throw the exception to be handled by the Job
        } finally {
            fclose($handle);
        }

        return [
            'imported' => $importedCount,
            'skipped' => $skippedCount,
        ];
    }

    /**
     * Parse a single row from the CSV file.
     */
    private function parseRow(array $row, string $sourceFilename): array
    {
        // Columns: 5:transaction_date, 7:description, 8:debit, 9:credit, 10:balance
        $dateStr = $row[4]; // Column 5 is index 4
        $description = $row[6]; // Column 7 is index 6
        $debit = $this->cleanAmount($row[7]); // Column 8 is index 7
        $credit = $this->cleanAmount($row[8]); // Column 9 is index 8
        $balance = $this->cleanAmount($row[9]); // Column 10 is index 9

        return [
            'transaction_date' => Carbon::createFromFormat('d/m/Y', $dateStr)->startOfDay(),
            'description' => $description,
            'debit_amount' => $debit,
            'credit_amount' => $credit,
            'balance' => $balance,
            'source_filename' => $sourceFilename,
        ];
    }

    /**
     * Clean the amount value from the CSV.
     */
    private function cleanAmount(string $amount): float
    {
        // Remove any currency symbols, commas, etc.
        return (float) preg_replace('/[^0-9.]/', '', $amount);
    }

    /**
     * Check if the transaction is a duplicate.
     */
    private function isDuplicate(array $transactionData): bool
    {
        return BankTransaction::where('transaction_date', $transactionData['transaction_date'])
            ->where('description', $transactionData['description'])
            ->where('debit_amount', $transactionData['debit_amount'])
            ->where('credit_amount', $transactionData['credit_amount'])
            ->where('balance', $transactionData['balance'])
            ->exists();
    }
}
