<?php

namespace App\Services;

use App\Models\WageEntry;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

class WageImportService
{
    private const COL_WEEK_NO = 0;

    private const DATA_START_ROW = 12; // 1-indexed row where weekly data begins (after header rows + blank)

    private const YEAR_SCAN_ROWS = 10; // Scan first N rows for the date range string

    // Offsets from the detected Gross Pay column (0 = gross pay itself)
    private const OFFSET_GROSS_PAY = 0;

    private const OFFSET_TAXABLE_BENEFITS = 1;

    private const OFFSET_TAXABLE_ADDS = 2;

    private const OFFSET_ALLOW_DEDS = 3;

    private const OFFSET_TAX = 4;

    private const OFFSET_USC_LEVY = 5;

    private const OFFSET_PRSI_EE = 6;

    private const OFFSET_LPT = 7;

    private const OFFSET_NON_TAX_ADDS = 8;

    private const OFFSET_NON_ALLOW_DEDS = 9;

    private const OFFSET_NET_PAY = 10;

    private const OFFSET_PRSI_ER = 11;

    /**
     * Parse the XLS file and return a collection of validated row arrays.
     */
    public function parse(string $filePath): Collection
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();

        $year = $this->extractYear($sheet);
        $grossPayCol = $this->detectGrossPayColumn($sheet);

        $rows = collect();
        $highestRow = $sheet->getHighestRow();

        for ($row = self::DATA_START_ROW; $row <= $highestRow; $row++) {
            $weekNo = $this->getCellValue($sheet, $row, self::COL_WEEK_NO);

            // Stop when week number is empty or non-numeric
            if ($weekNo === null || $weekNo === '' || ! is_numeric($weekNo)) {
                break;
            }

            $weekNumber = (int) $weekNo;
            [$weekStart, $weekEnd] = $this->computeWeekDates($year, $weekNumber);

            $rows->push([
                'year' => $year,
                'week_number' => $weekNumber,
                'week_start_date' => $weekStart->format('Y-m-d'),
                'week_end_date' => $weekEnd->format('Y-m-d'),
                'gross_pay' => $this->getNumericValue($sheet, $row, $grossPayCol + self::OFFSET_GROSS_PAY),
                'taxable_benefits' => $this->getNumericValue($sheet, $row, $grossPayCol + self::OFFSET_TAXABLE_BENEFITS),
                'taxable_adds' => $this->getNumericValue($sheet, $row, $grossPayCol + self::OFFSET_TAXABLE_ADDS),
                'allow_deds' => $this->getNumericValue($sheet, $row, $grossPayCol + self::OFFSET_ALLOW_DEDS),
                'tax' => $this->getNumericValue($sheet, $row, $grossPayCol + self::OFFSET_TAX),
                'usc_levy' => $this->getNumericValue($sheet, $row, $grossPayCol + self::OFFSET_USC_LEVY),
                'prsi_ee' => $this->getNumericValue($sheet, $row, $grossPayCol + self::OFFSET_PRSI_EE),
                'lpt' => $this->getNumericValue($sheet, $row, $grossPayCol + self::OFFSET_LPT),
                'non_tax_adds' => $this->getNumericValue($sheet, $row, $grossPayCol + self::OFFSET_NON_TAX_ADDS),
                'non_allow_deds' => $this->getNumericValue($sheet, $row, $grossPayCol + self::OFFSET_NON_ALLOW_DEDS),
                'net_pay' => $this->getNumericValue($sheet, $row, $grossPayCol + self::OFFSET_NET_PAY),
                'prsi_er' => $this->getNumericValue($sheet, $row, $grossPayCol + self::OFFSET_PRSI_ER),
            ]);
        }

        return $rows;
    }

    /**
     * Import the XLS file: parse and upsert into wage_entries.
     *
     * @return array{imported: int, updated: int, total: int, year: int}
     */
    public function import(string $filePath): array
    {
        $rows = $this->parse($filePath);

        $imported = 0;
        $updated = 0;
        $year = $rows->first()['year'] ?? null;

        foreach ($rows as $row) {
            $entry = WageEntry::updateOrCreate(
                ['year' => $row['year'], 'week_number' => $row['week_number']],
                $row
            );

            if ($entry->wasRecentlyCreated) {
                $imported++;
            } else {
                $updated++;
            }
        }

        return [
            'imported' => $imported,
            'updated' => $updated,
            'total' => $rows->count(),
            'year' => $year,
        ];
    }

    /**
     * Auto-detect the Gross Pay column by scanning header rows for "Gross" or "Total" + "Pay".
     * 2025 format: Gross Pay at column 2
     * 2026 format: Gross Pay at column 3 (labelled "Total Pay")
     */
    private function detectGrossPayColumn($sheet): int
    {
        $highestCol = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        // Scan rows 9-10 (header rows) for "Gross" or "Total" followed by "Pay"
        for ($col = 1; $col < $highestCol; $col++) {
            $row9Val = trim((string) $this->getCellValue($sheet, 9, $col));
            $row10Val = trim((string) $this->getCellValue($sheet, 10, $col));

            // Match "Gross"/"Total" in row 9 with "Pay" in row 10
            if (($row9Val === 'Gross' || $row9Val === 'Total') && $row10Val === 'Pay') {
                return $col;
            }
        }

        // Fallback: find first numeric value in first data row after column 0
        for ($col = 1; $col < $highestCol; $col++) {
            $val = $this->getCellValue($sheet, self::DATA_START_ROW, $col);
            if ($val !== null && is_numeric($val)) {
                return $col;
            }
        }

        // Default to column 3 (2026 format)
        return 3;
    }

    /**
     * Extract the payroll year from the date range string.
     */
    private function extractYear($sheet): int
    {
        $highestCol = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        for ($row = 1; $row <= self::YEAR_SCAN_ROWS; $row++) {
            for ($col = 0; $col < $highestCol; $col++) {
                $value = $this->getCellValue($sheet, $row, $col);

                if ($value && preg_match('/(\d{2}\/\d{2}\/(\d{4}))\s*-\s*(\d{2}\/\d{2}\/(\d{4}))/', (string) $value, $matches)) {
                    return (int) $matches[4];
                }
            }
        }

        return (int) now()->format('Y');
    }

    /**
     * Compute week start and end dates from year + week number.
     * Irish payroll weeks: Week 1 = Jan 1-7, Week 2 = Jan 8-14, etc.
     */
    private function computeWeekDates(int $year, int $weekNumber): array
    {
        $jan1 = Carbon::createFromDate($year, 1, 1);
        $weekStart = $jan1->copy()->addDays(($weekNumber - 1) * 7);
        $weekEnd = $weekStart->copy()->addDays(6);

        return [$weekStart, $weekEnd];
    }

    private function getCellValue($sheet, int $row, int $col): mixed
    {
        $columnLetter = Coordinate::stringFromColumnIndex($col + 1);

        return $sheet->getCell($columnLetter.$row)->getValue();
    }

    private function getNumericValue($sheet, int $row, int $col): float
    {
        $value = $this->getCellValue($sheet, $row, $col);

        if ($value === null || $value === '') {
            return 0.0;
        }

        return round((float) $value, 2);
    }
}
