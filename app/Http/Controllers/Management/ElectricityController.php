<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\ElectricityBill;
use App\Models\ElectricityMeter;
use App\Models\ElectricitySupplyPoint;
use App\Services\Electricity\ElectricityUsageService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Electricity overview: monthly kWh and cost from confirmed bills.
 * See docs/features/electricity.md.
 */
class ElectricityController extends Controller
{
    public function __construct(private ElectricityUsageService $usage) {}

    public function index(Request $request)
    {
        $points = ElectricitySupplyPoint::orderBy('id')->get();
        $point = $points->firstWhere('id', (int) $request->query('point')) ?? $points->first();
        $needsReview = ElectricityBill::where('status', ElectricityBill::STATUS_NEEDS_REVIEW)->count();

        if (! $point) {
            return view('management.electricity.index', [
                'point' => null, 'points' => $points, 'needsReview' => $needsReview,
            ]);
        }

        $data = $this->usage->monthly($point);
        $months = $data['months'];

        $range = in_array($request->query('months'), ['12', '24', 'all'], true) ? $request->query('months') : '24';
        $shown = $range === 'all' ? $months : array_slice($months, -((int) $range), null, true);

        $summary = $months ? $this->usage->yearSummary($months, array_key_last($months)) : null;

        return view('management.electricity.index', [
            'point' => $point,
            'points' => $points,
            'meters' => $data['meters'],
            'months' => $shown,
            'uncovered' => $data['uncovered'],
            'excludedCredits' => $data['excluded_credits'],
            'summary' => $summary,
            'range' => $range,
            'needsReview' => $needsReview,
            'billCount' => ElectricityBill::confirmed()->where('electricity_supply_point_id', $point->id)->count(),
            'chart' => $this->chartData($shown, $data['meters']),
        ]);
    }

    /** Each meter's kWh and share of the cost per month, for recharging. */
    public function meterSplitCsv(Request $request): StreamedResponse
    {
        $point = ElectricitySupplyPoint::findOrFail((int) $request->query('point'));
        $data = $this->usage->monthly($point);

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Month', 'Meter', 'kWh', 'Share %', 'Net cost', 'VAT', 'Gross cost', 'Net per kWh', 'Based on']);
            foreach ($data['months'] as $ym => $month) {
                foreach ($month['meters'] as $meterId => $m) {
                    fputcsv($out, [
                        $ym,
                        $data['meters'][$meterId] ?? $meterId,
                        number_format($m['kwh'], 1, '.', ''),
                        number_format($m['share'] * 100, 2, '.', ''),
                        number_format($m['net'], 2, '.', ''),
                        number_format($m['vat'], 2, '.', ''),
                        number_format($m['net'] + $m['vat'], 2, '.', ''),
                        $m['kwh'] > 0 ? number_format($m['net'] / $m['kwh'], 4, '.', '') : '',
                        ! $month['covered'] ? 'no bill for this month' : ($month['estimated'] ? 'estimated reading' : 'actual readings'),
                    ]);
                }
            }
            fclose($out);
        }, 'electricity-meter-split-'.$point->mprn.'.csv', ['Content-Type' => 'text/csv']);
    }

    public function updateMeter(Request $request, ElectricityMeter $meter)
    {
        $meter->update($request->validate(['name' => 'nullable|string|max:60']));

        return back()->with('success', 'Meter name saved.');
    }

    private function chartData(array $months, array $meters): array
    {
        $categories = [
            'energy' => 'Energy',
            'market' => 'Market charges',
            'standing' => 'Standing charge',
            'pso_levy' => 'PSO levy',
            'electricity_tax' => 'Electricity tax',
            'network' => 'Network',
            'other' => 'Other',
        ];

        $costs = [];
        foreach ($categories as $key => $label) {
            $values = array_map(fn ($m) => round(
                ($m['categories'][$key] ?? 0) + ($key === 'other' ? ($m['categories']['adjustment'] ?? 0) : 0), 2
            ), array_values($months));
            if (array_filter($values)) {
                $costs[] = ['key' => $key, 'label' => $label, 'values' => $values];
            }
        }

        return [
            'labels' => array_column($months, 'label'),
            'estimated' => array_column($months, 'estimated'),
            'meters' => array_map(fn ($id, $label) => [
                'label' => $label,
                'values' => array_map(fn ($m) => $m['meters'][$id]['kwh'] ?? 0, array_values($months)),
            ], array_keys($meters), $meters),
            'costs' => $costs,
            'centsPerKwh' => array_column($months, 'cents_per_kwh'),
            'exportKwh' => array_column($months, 'export_kwh'),
        ];
    }
}
