<?php

namespace App\Http\Controllers;

use App\Models\VatRate;
use Illuminate\Http\Request;
use Carbon\Carbon;

class VatRateController extends Controller
{
    /**
     * Display VAT rates management page.
     */
    public function index()
    {
        $vatRates = VatRate::orderBy('code')
            ->orderBy('effective_from', 'desc')
            ->get();

        $currentRates = VatRate::current()->get();

        return view('vat-rates.index', compact('vatRates', 'currentRates'));
    }

    /**
     * Store a new VAT rate.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20',
            'name' => 'required|string|max:100',
            'rate' => 'required|numeric|min:0|max:1',
            'effective_from' => 'required|date|after:today',
        ]);

        // Check if there's an existing rate for this code
        $existingRate = VatRate::where('code', $validated['code'])
            ->whereNull('effective_to')
            ->first();

        // If there's an existing rate, set its effective_to to the day before the new rate
        if ($existingRate) {
            $existingRate->effective_to = Carbon::parse($validated['effective_from'])->subDay();
            $existingRate->save();
        }

        // Create the new rate
        VatRate::create([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'rate' => $validated['rate'],
            'effective_from' => $validated['effective_from'],
            'effective_to' => null,
        ]);

        return redirect()->route('vat-rates.index')
            ->with('success', 'VAT rate scheduled successfully. It will take effect on ' . Carbon::parse($validated['effective_from'])->format('d/m/Y'));
    }

    /**
     * Update an existing VAT rate (only if it hasn't taken effect yet).
     */
    public function update(Request $request, VatRate $vatRate)
    {
        // Only allow editing if the rate hasn't taken effect yet
        if ($vatRate->effective_from <= now()) {
            return back()->with('error', 'Cannot edit VAT rates that have already taken effect. Create a new rate instead.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'rate' => 'required|numeric|min:0|max:1',
            'effective_from' => 'required|date|after:today',
        ]);

        $vatRate->update($validated);

        return redirect()->route('vat-rates.index')
            ->with('success', 'VAT rate updated successfully.');
    }

    /**
     * Delete a VAT rate (only if it hasn't taken effect yet).
     */
    public function destroy(VatRate $vatRate)
    {
        // Only allow deletion if the rate hasn't taken effect yet
        if ($vatRate->effective_from <= now()) {
            return back()->with('error', 'Cannot delete VAT rates that have already taken effect.');
        }

        // If this was replacing another rate, restore the previous rate's effective_to
        $previousRate = VatRate::where('code', $vatRate->code)
            ->where('effective_to', Carbon::parse($vatRate->effective_from)->subDay())
            ->first();

        if ($previousRate) {
            $previousRate->effective_to = null;
            $previousRate->save();
        }

        $vatRate->delete();

        return redirect()->route('vat-rates.index')
            ->with('success', 'VAT rate deleted successfully.');
    }
}