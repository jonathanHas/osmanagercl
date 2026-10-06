<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductDepositRequest;
use App\Http\Requests\UpdateProductDepositRequest;
use App\Models\BarrelCode;
use App\Models\Product;
use App\Models\ProductDeposit;
use App\Services\Deposits\DepositEvidenceService;
use App\Services\Deposits\DepositPosService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Customer bottle deposits (/deposits): which deposit tiers are charged to
 * customers, and which till products carry one. Confirmed products get the
 * deposit.* properties on the till, so the till adds the deposit line itself.
 */
class DepositController extends Controller
{
    private const DEFAULT_STATUSES = [ProductDeposit::STATUS_SUGGESTED, ProductDeposit::STATUS_CONFIRMED];

    public function __construct(
        private DepositEvidenceService $evidence,
        private DepositPosService $pos,
    ) {}

    public function index(Request $request): View
    {
        $statuses = array_values(array_intersect(
            explode(',', (string) $request->query('status', implode(',', self::DEFAULT_STATUSES))),
            ProductDeposit::STATUSES
        )) ?: self::DEFAULT_STATUSES;

        $tiers = BarrelCode::whereIn('supplier_id', $this->evidence->udeaSupplierIds())
            ->where('unit_price', '>', 0)
            ->withCount(['productDeposits as confirmed_count' => fn ($q) => $q->confirmed()])
            ->get()
            ->sortBy(fn (BarrelCode $t) => [(int) $t->supplier_code, $t->supplier_id])
            ->values();

        $rows = ProductDeposit::with('barrelCode')
            ->whereIn('status', $statuses)
            ->orderByRaw("case status when 'suggested' then 0 when 'confirmed' then 1 else 2 end")
            ->orderBy('product_code')
            ->get();

        $posIds = $rows->pluck('product_id')
            ->merge($tiers->pluck('pos_product_id'))
            ->merge($tiers->pluck('pos_refund_product_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return view('deposits.index', [
            'tiers' => $tiers,
            'chargeTiers' => $tiers->where('charge_customer', true)->values(),
            'rows' => $rows,
            'products' => Product::whereIn('ID', $posIds)->get(['ID', 'NAME', 'CODE'])->keyBy('ID'),
            'details' => $this->evidence->evidenceDetails($rows),
            'statuses' => $statuses,
            'suggestedCount' => ProductDeposit::suggested()->count(),
        ]);
    }

    public function updateTier(Request $request, BarrelCode $barrelCode): RedirectResponse
    {
        $validated = $request->validate(['charge_customer' => ['required', 'boolean']]);
        $on = (bool) $validated['charge_customer'];

        $barrelCode->update(['charge_customer' => $on]);

        if ($on) {
            $this->pos->ensureTierProducts($barrelCode);
            $this->evidence->refreshSuggestions();
        }
        $this->pos->syncAll();

        return back()->with('success', $on
            ? "Deposit {$barrelCode->supplier_code} is now charged to customers; till products ready."
            : "Deposit {$barrelCode->supplier_code} is no longer charged; its products were cleared on the till.");
    }

    public function storeProduct(StoreProductDepositRequest $request): RedirectResponse
    {
        $product = Product::findOrFail($request->validated('product_id'));

        $row = ProductDeposit::firstOrNew(['product_id' => $product->ID], ['source' => ProductDeposit::SOURCE_MANUAL]);
        $row->fill([
            'product_code' => $product->CODE,
            'barrel_code_id' => (int) $request->validated('barrel_code_id'),
        ])->decide(ProductDeposit::STATUS_CONFIRMED, $request->user())->save();

        $outcome = $this->pos->syncProduct($row->fresh('barrelCode'));

        return back()->with('success', "{$product->NAME} added ({$outcome} on the till).");
    }

    public function updateProduct(UpdateProductDepositRequest $request, ProductDeposit $productDeposit): RedirectResponse
    {
        if ($request->filled('barrel_code_id')) {
            $productDeposit->barrel_code_id = (int) $request->validated('barrel_code_id');
        }
        if ($request->filled('status')) {
            $productDeposit->decide($request->validated('status'), $request->user());
        }
        $productDeposit->save();

        $outcome = $this->pos->syncProduct($productDeposit->fresh('barrelCode'));

        return back()->with('success', "{$productDeposit->product_code}: {$productDeposit->status} ({$outcome} on the till).");
    }

    public function destroyProduct(ProductDeposit $productDeposit): RedirectResponse
    {
        // Clear the till first, then forget the row.
        $productDeposit->decide(ProductDeposit::STATUS_REJECTED, null)->save();
        $this->pos->syncProduct($productDeposit->fresh('barrelCode'));
        $productDeposit->delete();

        return back()->with('success', "{$productDeposit->product_code} removed and cleared on the till.");
    }

    public function confirmAll(Request $request): RedirectResponse
    {
        $rows = ProductDeposit::suggested()->get();
        foreach ($rows as $row) {
            $row->decide(ProductDeposit::STATUS_CONFIRMED, $request->user())->save();
        }

        $totals = $this->pos->syncAll()['totals'];

        return back()->with('success', sprintf(
            '%d suggestion(s) confirmed; till: %d written, %d refused.',
            $rows->count(), $totals['written'], $totals['refused']
        ));
    }

    public function refresh(): RedirectResponse
    {
        $result = $this->evidence->refreshSuggestions();

        return back()->with('success', sprintf(
            'Suggestions recomputed: %d new, %d updated, %d unchanged%s.',
            $result['created'], $result['updated'], $result['unchanged'],
            $result['unmatched'] ? '; Udea codes with no till product: '.implode(', ', $result['unmatched']) : ''
        ));
    }

    public function sync(): RedirectResponse
    {
        $t = $this->pos->syncAll()['totals'];

        return back()->with('success', sprintf(
            'Till synced: %d written, %d cleared, %d stray cleared, %d unchanged, %d refused, %d missing.',
            $t['written'], $t['cleared'], $t['stray_cleared'], $t['unchanged'], $t['refused'], $t['missing']
        ));
    }
}
