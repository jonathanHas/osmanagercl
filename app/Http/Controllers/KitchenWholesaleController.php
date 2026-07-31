<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWholesalePriceRequest;
use App\Models\Category;
use App\Models\KitchenRecipe;
use App\Models\TaxCategory;
use App\Repositories\KitchenRepository;
use App\Services\KitchenCostingService;
use App\Services\KitchenWholesaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class KitchenWholesaleController extends Controller
{
    public function __construct(
        protected KitchenRepository $repository,
        protected KitchenCostingService $costingService,
        protected KitchenWholesaleService $wholesaleService,
    ) {}

    /**
     * Show the wholesale pricing page for every recipe.
     */
    public function index(): View
    {
        // Loaded once and reused as the Alpine payload - it is what lets the
        // grid recompute inc/ex/margin client-side with no round trips.
        $taxRates = $this->taxRates();

        $recipes = $this->repository->getRecipesForWholesale();

        $rows = $recipes->map(fn (KitchenRecipe $recipe) => $this->wholesaleService->buildRow($recipe, $taxRates))
            ->values()
            ->all();

        $defaultTarget = (float) config('kitchen.wholesale_target_margin', 35);

        return view('kitchen.wholesale', [
            'rows' => $rows,
            'taxRates' => $taxRates,
            'defaultTarget' => $defaultTarget,
            'marginBands' => [
                'excellent' => KitchenCostingService::MARGIN_EXCELLENT,
                'good' => KitchenCostingService::MARGIN_GOOD,
                'low' => KitchenCostingService::MARGIN_LOW,
            ],
            'stats' => $this->buildStats($rows, $taxRates, $defaultTarget),
            'categories' => Category::orderBy('NAME')->get(),
            'taxCategories' => TaxCategory::orderBy('NAME')->get(),
        ]);
    }

    /**
     * Create or update the wholesale product for a recipe.
     */
    public function store(StoreWholesalePriceRequest $request, KitchenRecipe $recipe): JsonResponse
    {
        try {
            $result = $this->wholesaleService->setPrice(
                $recipe,
                (float) $request->input('price_inc_vat'),
                $request->input('category'),
                $request->input('tax_category'),
                $request->filled('target_margin') ? (float) $request->input('target_margin') : null,
            );
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $row = $this->wholesaleService->buildRow($recipe, $this->taxRates());

        return response()->json([
            'success' => true,
            'created' => $result['created'],
            'row' => $row,
            'batch_cost' => $result['batch_cost'],
            'margin_percentage' => $result['margin_percentage'] !== null
                ? round($result['margin_percentage'], 1)
                : null,
            'margin_status' => $this->costingService->getMarginStatus($result['margin_percentage']),
            'warnings' => $result['warnings'],
            'message' => $result['created']
                ? "Wholesale product created (code {$row['wholesaleProductCode']})."
                : 'Wholesale price updated.',
        ]);
    }

    /**
     * TAXCAT id => VAT rate as a fraction, for the whole (tiny) tax table.
     *
     * @return array<string, float>
     */
    protected function taxRates(): array
    {
        return TaxCategory::with('primaryTax')
            ->get()
            ->mapWithKeys(fn (TaxCategory $tc) => [$tc->ID => (float) ($tc->primaryTax?->RATE ?? 0)])
            ->all();
    }

    /**
     * Summary cards, computed from the rows already built.
     *
     * Deliberately not KitchenCostingService::getOverallStatistics() - that
     * re-fetches and re-costs every recipe, doubling the page's work.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, float>  $taxRates
     * @return array<string, mixed>
     */
    protected function buildStats(array $rows, array $taxRates, float $defaultTarget): array
    {
        $priced = 0;
        $belowTarget = 0;
        $margins = [];

        foreach ($rows as $row) {
            if (! $row['isPriced'] || $row['incVat'] === null) {
                continue;
            }

            $priced++;

            $vatRate = (float) ($taxRates[$row['taxcat']] ?? 0);
            $exVat = KitchenWholesaleService::exVat((float) $row['incVat'], $vatRate);
            $margin = KitchenWholesaleService::marginPercentage($exVat, (float) $row['batchCost']);

            if ($margin === null) {
                continue;
            }

            $margins[] = $margin;

            if ($margin < ($row['target'] ?? $defaultTarget)) {
                $belowTarget++;
            }
        }

        return [
            'total' => count($rows),
            'priced' => $priced,
            'unpriced' => count($rows) - $priced,
            'below_target' => $belowTarget,
            'average_margin' => $margins ? round(array_sum($margins) / count($margins), 1) : null,
        ];
    }
}
