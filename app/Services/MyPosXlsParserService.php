<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class MyPosXlsParserService
{
    // myPOS XLS column mapping (0-indexed)
    private const COL_DATETIME = 0;          // A: Date and Time

    private const COL_TID = 1;               // B: TID (Terminal ID)

    private const COL_TERMINAL_NAME = 2;     // C: Terminal name

    private const COL_TRANSACTION_TYPE = 3;  // D: Transaction type

    private const COL_TRANSACTION_REF = 4;   // E: Transaction reference

    private const COL_REFERENCE_NUMBER = 5;  // F: Reference number

    private const COL_TRANSACTION_STATUS = 6; // G: Transaction status

    private const COL_PAYMENT_STATUS = 7;    // H: Payment status

    private const COL_CARD_MASKED = 8;       // I: Payment from card

    private const COL_AMOUNT = 9;            // J: Transaction Amount

    private const COL_CURRENCY = 10;         // K: Transaction Curr

    private const COL_SETTLEMENT_DATETIME = 11; // L: Settlement Date and Time

    private const COL_SETTLEMENT_AMOUNT = 12;   // M: Settlement Amount

    private const COL_FEE = 13;              // N: Fee

    private const COL_FEE_CURRENCY = 14;     // O: Fee Curr

    private const COL_TIP_AMOUNT = 15;       // P: Tip Amount

    private const COL_TIP_CURRENCY = 16;     // Q: Tip Curr

    private const COL_OPERATOR_CODE = 17;    // R: Operator code

    private const COL_PROCESSOR = 18;        // S: Processor (Mastercard, Visa)

    private const COL_CARD_TYPE = 19;        // T: Card type

    private const COL_AUTH_CODE = 20;        // U: Auth. Code

    private const COL_OPERATIONAL_MODE = 21; // V: Operational mode

    private const HEADER_ROW = 3;  // Headers are on row 3

    private const DATA_START_ROW = 5;  // Data starts from row 5

    public function parse(string $filePath): Collection
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestRow();

        $transactions = collect();

        for ($row = self::DATA_START_ROW; $row <= $highestRow; $row++) {
            $rowData = $this->getRowData($sheet, $row);

            // Skip empty rows
            if (empty($rowData[self::COL_DATETIME]) || empty($rowData[self::COL_TRANSACTION_REF])) {
                continue;
            }

            $transaction = $this->parseRow($rowData);
            if ($transaction) {
                $transactions->push($transaction);
            }
        }

        return $transactions;
    }

    private function getRowData($sheet, int $row): array
    {
        $data = [];
        for ($col = 0; $col <= self::COL_OPERATIONAL_MODE; $col++) {
            $columnLetter = Coordinate::stringFromColumnIndex($col + 1);
            $cell = $sheet->getCell($columnLetter.$row);
            $data[$col] = $cell->getValue();
        }

        return $data;
    }

    private function parseRow(array $row): ?array
    {
        try {
            $transactionDatetime = $this->parseDateTime($row[self::COL_DATETIME]);
            $settlementDatetime = $this->parseDateTime($row[self::COL_SETTLEMENT_DATETIME]);

            return [
                'transaction_datetime' => $transactionDatetime,
                'terminal_id' => $this->cleanString($row[self::COL_TID]),
                'terminal_name' => $this->cleanString($row[self::COL_TERMINAL_NAME]),
                'transaction_type' => $this->cleanString($row[self::COL_TRANSACTION_TYPE]),
                'transaction_reference' => $this->cleanString($row[self::COL_TRANSACTION_REF]),
                'transaction_status' => $this->cleanString($row[self::COL_TRANSACTION_STATUS]),
                'payment_status' => $this->cleanString($row[self::COL_PAYMENT_STATUS]),
                'card_masked' => $this->cleanString($row[self::COL_CARD_MASKED]),
                'amount' => $this->parseAmount($row[self::COL_AMOUNT]),
                'currency' => $this->cleanString($row[self::COL_CURRENCY]) ?: 'EUR',
                'settlement_datetime' => $settlementDatetime,
                'settlement_amount' => $this->parseAmount($row[self::COL_SETTLEMENT_AMOUNT]),
                'fee' => $this->parseAmount($row[self::COL_FEE]),
                'processor' => $this->cleanString($row[self::COL_PROCESSOR]),
                'card_type' => $this->cleanString($row[self::COL_CARD_TYPE]),
                'auth_code' => $this->cleanString($row[self::COL_AUTH_CODE]),
            ];
        } catch (\Exception $e) {
            \Log::warning('Failed to parse card transaction row', [
                'error' => $e->getMessage(),
                'row' => $row,
            ]);

            return null;
        }
    }

    private function parseDateTime($value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        // Handle Excel date/time serial number
        if (is_numeric($value)) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject($value));
            } catch (\Exception $e) {
                return null;
            }
        }

        // Handle string date format: DD.MM.YYYY HH:MM
        if (is_string($value)) {
            try {
                return Carbon::createFromFormat('d.m.Y H:i', trim($value));
            } catch (\Exception $e) {
                // Try alternative formats
                try {
                    return Carbon::parse($value);
                } catch (\Exception $e) {
                    return null;
                }
            }
        }

        return null;
    }

    private function parseAmount($value): ?float
    {
        if (empty($value) && $value !== 0 && $value !== '0') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        // Handle comma as decimal separator (European format)
        $cleaned = str_replace(',', '.', (string) $value);
        $cleaned = preg_replace('/[^0-9.\-]/', '', $cleaned);

        return is_numeric($cleaned) ? (float) $cleaned : null;
    }

    private function cleanString($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return trim((string) $value);
    }

    public function validateFile(string $filePath): array
    {
        $errors = [];

        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();

            // Check for header row
            $headerCell = $sheet->getCell('A'.self::HEADER_ROW)->getValue();
            if (stripos($headerCell, 'Date') === false && stripos($headerCell, 'Time') === false) {
                $errors[] = 'Expected header row not found at row '.self::HEADER_ROW;
            }

            // Check for data rows
            $firstDataCell = $sheet->getCell('A'.self::DATA_START_ROW)->getValue();
            if (empty($firstDataCell)) {
                $errors[] = 'No data found starting at row '.self::DATA_START_ROW;
            }
        } catch (\Exception $e) {
            $errors[] = 'Failed to read file: '.$e->getMessage();
        }

        return $errors;
    }
}
