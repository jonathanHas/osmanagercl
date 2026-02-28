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
        $years = WageEntry::selectRaw('DISTINCT year')->orderByDesc('year')->pluck('year');
        $selectedYear = $request->get('year', $years->first());

        $entries = collect();
        $totals = null;

        if ($selectedYear) {
            $entries = WageEntry::forYear((int) $selectedYear)
                ->orderBy('week_number')
                ->get();

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
                'employer_cost' => $entries->sum('gross_pay') + $entries->sum('prsi_er'),
            ];
        }

        return view('management.wages.index', compact('entries', 'years', 'selectedYear', 'totals'));
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xls,xlsx',
        ]);

        try {
            $service = new WageImportService;
            $result = $service->import($request->file('file')->getRealPath());

            return redirect()->route('management.wages.index')
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
