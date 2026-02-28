<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\WageEntry;
use App\Services\WageImportService;
use Illuminate\Http\Request;

class WageController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'year' => 'nullable|integer',
        ]);

        $years = WageEntry::selectRaw('DISTINCT year')->orderByDesc('year')->pluck('year');

        // Determine filtering mode: date range takes priority over year
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $selectedYear = $request->get('year');
        $filterMode = 'year';

        if ($startDate && $endDate) {
            $filterMode = 'date_range';
            $entries = WageEntry::forDateRange($startDate, $endDate)
                ->orderBy('year')
                ->orderBy('week_number')
                ->get();
        } elseif ($selectedYear) {
            $entries = WageEntry::forYear((int) $selectedYear)
                ->orderBy('week_number')
                ->get();
        } else {
            $selectedYear = $years->first();
            $entries = $selectedYear
                ? WageEntry::forYear((int) $selectedYear)->orderBy('week_number')->get()
                : collect();
        }

        $totals = null;
        if ($entries->count() > 0) {
            $totals = [
                'gross_pay' => $entries->sum('gross_pay'),
                'taxable_benefits' => $entries->sum('taxable_benefits'),
                'taxable_adds' => $entries->sum('taxable_adds'),
                'allow_deds' => $entries->sum('allow_deds'),
                'tax' => $entries->sum('tax'),
                'usc_levy' => $entries->sum('usc_levy'),
                'prsi_ee' => $entries->sum('prsi_ee'),
                'lpt' => $entries->sum('lpt'),
                'non_tax_adds' => $entries->sum('non_tax_adds'),
                'non_allow_deds' => $entries->sum('non_allow_deds'),
                'net_pay' => $entries->sum('net_pay'),
                'prsi_er' => $entries->sum('prsi_er'),
                'employer_cost' => $entries->sum('gross_pay') + $entries->sum('taxable_adds') + $entries->sum('non_tax_adds') + $entries->sum('prsi_er'),
            ];
        }

        // Chart data
        $chartData = $entries->map(fn ($e) => [
            'label' => 'W'.$e->week_number.($filterMode === 'date_range' ? ' ('.$e->year.')' : ''),
            'gross_pay' => $e->gross_pay,
            'net_pay' => $e->net_pay,
            'employer_cost' => $e->total_employer_cost,
            'prsi_er' => $e->prsi_er,
            'tax' => $e->tax,
        ])->values();

        return view('management.wages.index', compact(
            'entries', 'years', 'selectedYear', 'totals', 'chartData',
            'startDate', 'endDate', 'filterMode'
        ));
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimetypes:application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/octet-stream,application/x-ole-storage',
        ]);

        try {
            $service = new WageImportService;
            $result = $service->import($request->file('file')->getRealPath());

            return redirect()->route('management.wages.index', ['year' => $result['year']])
                ->with('success', "Wages imported: {$result['imported']} new, {$result['updated']} updated ({$result['total']} total rows).");
        } catch (\Exception $e) {
            return redirect()->route('management.wages.index')
                ->with('error', 'Failed to import wages: '.$e->getMessage());
        }
    }

    public function destroy(WageEntry $wageEntry)
    {
        $wageEntry->delete();

        return redirect()->route('management.wages.index', ['year' => $wageEntry->year])
            ->with('success', "Deleted wage entry for Week {$wageEntry->week_number}.");
    }

    public function destroyYear(Request $request, int $year)
    {
        $deleted = WageEntry::forYear($year)->delete();

        return redirect()->route('management.wages.index')
            ->with('success', "Deleted {$deleted} wage entries for {$year}.");
    }
}
