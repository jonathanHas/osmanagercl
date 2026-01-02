<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\StockValuationCategory;
use App\Models\StockValuationSnapshot;
use App\Services\StockValuationService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class StockValuationController extends Controller
{
    public function __construct(
        protected StockValuationService $valuationService
    ) {}

    /**
     * Display a listing of snapshots.
     */
    public function index()
    {
        $snapshots = StockValuationSnapshot::with('creator')
            ->orderBy('valuation_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('management.stock-valuation.index', compact('snapshots'));
    }

    /**
     * Display live/current stock valuation.
     */
    public function live()
    {
        $valuation = $this->valuationService->calculateLiveValuation();

        return view('management.stock-valuation.live', compact('valuation'));
    }

    /**
     * Display live products for a category.
     */
    public function liveCategory(string $categoryId)
    {
        $category = $this->valuationService->getLiveCategoryProducts($categoryId);

        return view('management.stock-valuation.live-category', compact('category'));
    }

    /**
     * Show the form for creating a new snapshot.
     */
    public function create()
    {
        return view('management.stock-valuation.create');
    }

    /**
     * Store a newly created snapshot.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'valuation_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $snapshot = $this->valuationService->createSnapshot(
            $validated['name'],
            Carbon::parse($validated['valuation_date']),
            auth()->id(),
            $validated['notes'] ?? null
        );

        return redirect()
            ->route('management.stock-valuation.show', $snapshot)
            ->with('success', 'Stock valuation snapshot created successfully.');
    }

    /**
     * Display the specified snapshot.
     */
    public function show(StockValuationSnapshot $snapshot)
    {
        $snapshot->load(['creator', 'finalizer', 'categories' => function ($query) {
            $query->orderBy('category_name');
        }]);

        return view('management.stock-valuation.show', compact('snapshot'));
    }

    /**
     * Display products in a category for a snapshot.
     */
    public function category(StockValuationSnapshot $snapshot, StockValuationCategory $category)
    {
        // Ensure category belongs to snapshot
        if ($category->snapshot_id !== $snapshot->id) {
            abort(404);
        }

        $category->load(['items' => function ($query) {
            $query->orderBy('product_name');
        }]);

        return view('management.stock-valuation.category', compact('snapshot', 'category'));
    }

    /**
     * Set override for a category.
     */
    public function override(Request $request, StockValuationSnapshot $snapshot)
    {
        if (! $snapshot->canBeModified()) {
            return back()->with('error', 'Cannot modify a finalized snapshot.');
        }

        $validated = $request->validate([
            'category_id' => 'required|exists:stock_valuation_categories,id',
            'override_value' => 'nullable|numeric|min:0',
            'override_reason' => 'nullable|string|max:255',
        ]);

        $category = StockValuationCategory::findOrFail($validated['category_id']);

        // Ensure category belongs to snapshot
        if ($category->snapshot_id !== $snapshot->id) {
            abort(404);
        }

        if ($validated['override_value'] !== null && $validated['override_value'] !== '') {
            $this->valuationService->setCategoryOverride(
                $category,
                (float) $validated['override_value'],
                $validated['override_reason'] ?? null
            );
            $message = 'Override value set for '.$category->category_name.'.';
        } else {
            $this->valuationService->clearCategoryOverride($category);
            $message = 'Override cleared for '.$category->category_name.'.';
        }

        return back()->with('success', $message);
    }

    /**
     * Refresh snapshot from current data.
     */
    public function refresh(StockValuationSnapshot $snapshot)
    {
        if (! $snapshot->canBeModified()) {
            return back()->with('error', 'Cannot refresh a finalized snapshot.');
        }

        $this->valuationService->refreshSnapshot($snapshot);

        return back()->with('success', 'Snapshot refreshed with current stock data.');
    }

    /**
     * Finalize the snapshot.
     */
    public function finalize(StockValuationSnapshot $snapshot)
    {
        if (! $snapshot->canBeModified()) {
            return back()->with('error', 'Snapshot is already finalized.');
        }

        $this->valuationService->finalizeSnapshot($snapshot, auth()->id());

        return back()->with('success', 'Snapshot finalized successfully.');
    }

    /**
     * Export snapshot to CSV.
     */
    public function export(StockValuationSnapshot $snapshot)
    {
        $csv = $this->valuationService->exportToCsv($snapshot);
        $filename = 'stock-valuation-'.str_replace(' ', '-', strtolower($snapshot->name)).'-'.$snapshot->valuation_date->format('Y-m-d').'.csv';

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    /**
     * Delete a draft snapshot.
     */
    public function destroy(StockValuationSnapshot $snapshot)
    {
        if (! $snapshot->canBeModified()) {
            return back()->with('error', 'Cannot delete a finalized snapshot.');
        }

        $snapshot->delete();

        return redirect()
            ->route('management.stock-valuation.index')
            ->with('success', 'Snapshot deleted successfully.');
    }
}
