<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductDepositRequest;
use App\Http\Requests\UpdateProductDepositRequest;
use App\Models\AppSetting;
use App\Models\BarrelCode;
use App\Models\DepositSighting;
use App\Models\Product;
use App\Models\ProductDeposit;
use App\Services\Deposits\DepositEvidenceService;
use App\Services\Deposits\DepositPosService;
use App\Services\Deposits\DepositPriceReviewService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Customer bottle deposits (/deposits): which deposit tiers are charged to
 * customers, and which till products carry one. Confirmed products get the
 * deposit.* properties on the till, so the till adds the deposit line itself.
 */
class DepositController extends Controller
{
    private const DEFAULT_STATUSES = [ProductDeposit::STATUS_SUGGESTED, ProductDeposit::STATUS_CONFIRMED];

    private const SORTS = ['name', 'markup', 'sales'];

    private const GROUPS = ['tier', 'brand', 'none'];

    /** Shop-wide delivery charge on Udea cost, in percent (landed cost = cost × (1 + pct)). */
    public const DELIVERY_PCT_KEY = 'deposits.delivery_pct';

    public const DELIVERY_PCT_DEFAULT = 15;

    public function __construct(
        private DepositEvidenceService $evidence,
        private DepositPosService $pos,
        private DepositPriceReviewService $prices,
    ) {}

    public function index(Request $request): View
    {
        $statuses = array_values(array_intersect(
            explode(',', (string) $request->query('status', implode(',', self::DEFAULT_STATUSES))),
            ProductDeposit::STATUSES
        )) ?: self::DEFAULT_STATUSES;

        $sort = in_array($request->query('sort'), self::SORTS, true) ? $request->query('sort') : 'name';
        $group = in_array($request->query('group'), self::GROUPS, true) ? $request->query('group') : 'tier';

        $tiers = BarrelCode::whereIn('supplier_id', $this->evidence->udeaSupplierIds())
            ->where('unit_price', '>', 0)
            ->withCount(['productDeposits as confirmed_count' => fn ($q) => $q->confirmed()])
            ->get()
            ->sortBy(fn (BarrelCode $t) => [(int) $t->supplier_code, $t->supplier_id])
            ->values();

        // The strip shows codes charged to customers or seen on a product line;
        // crates and pallets only with ?codes=all. $tiers stays complete for
        // the till-product lookups and the tier selects.
        $codesAll = $request->query('codes') === 'all';
        $seenCodes = DepositSighting::query()->distinct()->pluck('barrel_code')->map(fn ($code) => (string) $code)->all();
        $stripTiers = $codesAll ? $tiers : $tiers
            ->filter(fn (BarrelCode $t) => $t->charge_customer || in_array((string) $t->supplier_code, $seenCodes, true))
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

        $products = Product::whereIn('ID', $posIds)
            ->with('tax')
            ->get(['ID', 'NAME', 'CODE', 'PRICESELL', 'PRICEBUY', 'TAXCAT'])
            ->keyBy('ID');
        $prices = $this->prices->forRows($rows, $products, $this->deliveryPct());
        $lastSyncedAt = ProductDeposit::max('pos_synced_at');

        return view('deposits.index', [
            'tiers' => $tiers,
            'stripTiers' => $stripTiers,
            'codesAll' => $codesAll,
            'tierCount' => $tiers->count(),
            'chargeTiers' => $tiers->where('charge_customer', true)->values(),
            'rows' => $rows,
            'groups' => $this->groupRows($rows, $products, $prices, $tiers, $group, $sort),
            'products' => $products,
            'details' => $this->evidence->evidenceDetails($rows),
            'prices' => $prices,
            'statuses' => $statuses,
            'sort' => $sort,
            'group' => $group,
            'counts' => ProductDeposit::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')
                ->map(fn ($n) => (int) $n)->union(array_fill_keys(ProductDeposit::STATUSES, 0))->all(),
            'suggestedCount' => ProductDeposit::suggested()->count(),
            'decidedCount' => $rows->where('status', '!=', ProductDeposit::STATUS_SUGGESTED)->count(),
            'reviewedCount' => $rows->whereNotNull('price_review')->count(),
            'deliveryPct' => $this->deliveryPct(),
            'lastSyncedAt' => $lastSyncedAt ? Carbon::parse($lastSyncedAt) : null,
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $pct = $request->validate(['delivery_pct' => ['required', 'numeric', 'min:0', 'max:100']])['delivery_pct'];

        AppSetting::set(self::DELIVERY_PCT_KEY, $pct + 0);

        return back()->with('success', 'Delivery charge set to '.($pct + 0).'%.');
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
        $redirect = back()->with('success', "{$productDeposit->product_code}: {$productDeposit->status} ({$outcome} on the till).");

        // The price choice rides on the row's own Confirm only; the deposit
        // confirmation stands even when the price write is refused.
        $priceAction = $request->validated('price_action');
        if ($productDeposit->status !== ProductDeposit::STATUS_CONFIRMED || ! $priceAction) {
            return $redirect;
        }

        if ($priceAction === 'keep') {
            $productDeposit->reviewPrice(ProductDeposit::PRICE_KEPT, $request->user())->save();

            return $redirect;
        }

        $productDeposit->load('barrelCode');
        $result = $priceAction === 'lower'
            ? $this->prices->lowerByDeposit($productDeposit, $request->user())
            : $this->prices->setGrossPrice($productDeposit, $request->user(), (float) $request->validated('new_price'));

        return $result['ok']
            ? $redirect->with('success', "{$productDeposit->product_code}: {$productDeposit->status} ({$outcome} on the till). {$result['message']}")
            : $redirect->withErrors(['price_review' => $result['message']]);
    }

    public function destroyProduct(ProductDeposit $productDeposit): RedirectResponse
    {
        // Clear the till first, then forget the row.
        $productDeposit->decide(ProductDeposit::STATUS_REJECTED, null)->save();
        $this->pos->syncProduct($productDeposit->fresh('barrelCode'));
        $productDeposit->delete();

        return back()->with('success', "{$productDeposit->product_code} removed and cleared on the till.");
    }

    /**
     * Confirm every suggestion, or those of one tier or one brand (the first
     * word of the till name). Prices are never touched here.
     */
    public function confirmAll(Request $request): RedirectResponse
    {
        $filter = $request->validate([
            'barrel_code_id' => ['nullable', 'integer', 'exists:App\Models\BarrelCode,id'],
            'brand' => ['nullable', 'string', 'max:40'],
        ]);

        $rows = ProductDeposit::suggested()
            ->when($filter['barrel_code_id'] ?? null, fn ($q, $id) => $q->where('barrel_code_id', $id))
            ->get();

        if (filled($filter['brand'] ?? null)) {
            $names = Product::whereIn('ID', $rows->pluck('product_id')->all())->pluck('NAME', 'ID');
            $rows = $rows->filter(fn (ProductDeposit $row) => strcasecmp(self::brandOf($names[$row->product_id] ?? null), $filter['brand']) === 0);
        }

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

    /**
     * The owner's price review of one row: keep the shelf price, lower it by
     * the deposit (writes the till), clear the review (price unchanged), or
     * undo the decision (back to suggested, price restored when it changed).
     */
    public function priceReview(Request $request, ProductDeposit $productDeposit): RedirectResponse
    {
        $action = $request->validate(['action' => ['required', 'in:keep,lower,reset,undo']])['action'];

        if ($action === 'undo') {
            return back()->with('success', $this->prices->undo($productDeposit->load('barrelCode'))['message']);
        }

        if ($action === 'lower') {
            $result = $this->prices->lowerByDeposit($productDeposit, $request->user());

            return $result['ok']
                ? back()->with('success', $result['message'])
                : back()->withErrors(['price_review' => $result['message']]);
        }

        if ($action === 'keep') {
            $productDeposit->reviewPrice(ProductDeposit::PRICE_KEPT, $request->user())->save();
            $product = Product::with('tax')->find($productDeposit->product_id);
            $gross = $product ? number_format(round($product->getGrossPrice(), 2), 2) : '?';

            return back()->with('success', "{$productDeposit->product_code}: price kept at €{$gross}.");
        }

        $productDeposit->reviewPrice(null, null)->save();

        return back()->with('success', "{$productDeposit->product_code}: review cleared (price unchanged).");
    }

    public function sync(): RedirectResponse
    {
        $t = $this->pos->syncAll()['totals'];

        return back()->with('success', sprintf(
            'Till synced: %d written, %d cleared, %d stray cleared, %d unchanged, %d refused, %d missing.',
            $t['written'], $t['cleared'], $t['stray_cleared'], $t['unchanged'], $t['refused'], $t['missing']
        ));
    }

    /** The brand used for grouping: the first word of the till name (crude, by design). */
    public static function brandOf(?string $name): string
    {
        return (string) strtok(trim((string) $name), ' ');
    }

    private function deliveryPct(): float
    {
        return (float) AppSetting::get(self::DELIVERY_PCT_KEY, self::DELIVERY_PCT_DEFAULT);
    }

    /**
     * The rows under their group headers (tier, brand, or one hidden group),
     * each group sorted by $sort. 'confirm' is the filter the group's
     * "Confirm n" button sends to confirmAll(), or null for no button.
     *
     * @return array<int, array<string, mixed>>
     */
    private function groupRows(Collection $rows, Collection $products, array $prices, Collection $tiers, string $group, string $sort): array
    {
        $name = fn (ProductDeposit $row) => $products->get($row->product_id)?->NAME;
        $make = fn (string $key, string $title, string $sub, Collection $members, ?array $confirm, bool $show = true) => [
            'key' => $key,
            'title' => $title,
            'sub' => $sub,
            'rows' => $this->sortRows($members, $name, $prices, $sort),
            'open' => $members->where('status', ProductDeposit::STATUS_SUGGESTED)->count(),
            'confirm' => $confirm,
            'show' => $show,
        ];

        if ($group === 'none') {
            return $rows->isEmpty() ? [] : [$make('all', '', '', $rows, null, false)];
        }

        if ($group === 'brand') {
            return $rows->groupBy(fn (ProductDeposit $row) => mb_strtolower(self::brandOf($name($row))))
                ->sortKeys()
                ->map(function (Collection $members, string $key) use ($make, $name) {
                    $brand = self::brandOf($name($members->first()));

                    return $make('brand-'.$key, $brand !== '' ? $brand : 'Not on till',
                        $members->count().' '.Str::plural('product', $members->count()),
                        $members, $brand !== '' ? ['brand' => $brand] : null);
                })
                ->values()
                ->all();
        }

        $order = $tiers->pluck('id')->flip();

        return $rows->groupBy('barrel_code_id')
            ->sortBy(fn (Collection $members, $id) => $order[$id] ?? PHP_INT_MAX)
            ->map(fn (Collection $members, $id) => $make('tier-'.$id,
                'Tier '.($members->first()->barrelCode?->supplier_code ?? '?'),
                sprintf('€%s deposit · %d %s', number_format((float) $members->first()->barrelCode?->unit_price, 2),
                    $members->count(), Str::plural('product', $members->count())),
                $members, ['barrel_code_id' => (int) $id]))
            ->values()
            ->all();
    }

    /**
     * Name A–Z (till name; rows not on the till last, by code), markup lowest
     * first, or sales over twelve months highest first. Rows without the
     * figure go last, by name.
     */
    private function sortRows(Collection $rows, callable $name, array $prices, string $sort): Collection
    {
        $byName = fn (Collection $c) => $c->sortBy(fn ($row) => [$name($row) === null ? 1 : 0, mb_strtolower($name($row) ?? (string) $row->product_code)])->values();

        if ($sort === 'name') {
            return $byName($rows);
        }

        $key = $sort === 'markup'
            ? fn ($row) => $prices[$row->product_id]['markup_pct'] ?? null
            : fn ($row) => $prices[$row->product_id]['sold_12m'] ?? null;
        [$with, $without] = $byName($rows)->partition(fn ($row) => $key($row) !== null);

        $with = $sort === 'markup' ? $with->sortBy($key, SORT_NUMERIC) : $with->sortByDesc($key, SORT_NUMERIC);

        return $with->values()->concat($without->values());
    }
}
