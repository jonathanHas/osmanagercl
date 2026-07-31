<?php

namespace App\Services;

use App\Models\KitchenRecipe;
use App\Models\LabelLog;
use App\Models\Product;
use App\Models\ProductMetadata;
use App\Models\StockCurrent;
use App\Models\TaxCategory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Prices kitchen recipes for wholesale and keeps the matching POS product in step.
 *
 * A wholesale unit is one FULL BATCH of the recipe - the cost basis is
 * KitchenCostingService::calculateRecipeCost()['total_cost'], never
 * cost_per_portion. The prices the user enters are inc-VAT; PRODUCTS.PRICESELL
 * is stored ex-VAT, so every price crosses through exVat() on the way in.
 */
class KitchenWholesaleService
{
    /**
     * Suffix appended to the recipe name to form the wholesale product name.
     */
    public const NAME_SUFFIX = ' Wholesale';

    public function __construct(
        protected KitchenCostingService $costingService,
        protected BarcodeGeneratorService $barcodeGenerator,
        protected TillVisibilityService $tillVisibilityService,
    ) {}

    /**
     * Convert an inc-VAT price to the ex-VAT price stored in PRICESELL.
     */
    public static function exVat(float $incVat, float $vatRate): float
    {
        return $vatRate > 0 ? $incVat / (1 + $vatRate) : $incVat;
    }

    /**
     * Convert an ex-VAT price to its inc-VAT equivalent.
     */
    public static function incVat(float $exVat, float $vatRate): float
    {
        return $exVat * (1 + $vatRate);
    }

    /**
     * Margin on the ex-VAT price. Null when there is no price to measure against.
     */
    public static function marginPercentage(float $exVat, float $cost): ?float
    {
        if ($exVat <= 0) {
            return null;
        }

        return (($exVat - $cost) / $exVat) * 100;
    }

    /**
     * The inc-VAT price that hits a target margin on the given batch cost.
     *
     * A target of 100% or more has no finite answer (the divisor hits zero), so
     * it is clamped rather than allowed to divide by zero.
     */
    public static function priceForTargetMargin(float $cost, float $targetMargin, float $vatRate): float
    {
        if ($targetMargin >= 100) {
            return 0.0;
        }

        $exVat = $cost / (1 - ($targetMargin / 100));

        return self::incVat($exVat, $vatRate);
    }

    /**
     * The wholesale product name for a recipe, before collision handling.
     */
    public function wholesaleName(KitchenRecipe $recipe): string
    {
        return $recipe->name.self::NAME_SUFFIX;
    }

    /**
     * Build the view-model row for one recipe.
     *
     * @param  array<string, float>  $taxRates  TAXCAT id => VAT rate as a fraction
     * @return array<string, mixed>
     */
    public function buildRow(KitchenRecipe $recipe, array $taxRates): array
    {
        $costs = $this->costingService->calculateRecipeCost($recipe);
        $batchCost = (float) $costs['total_cost'];

        $wholesaleProduct = $recipe->wholesaleProduct;
        $retailProduct = $recipe->product;

        // Inherit classification from the wholesale product if it exists, else
        // from the retail product. Null on both means the row has to ask.
        $taxcat = $wholesaleProduct?->TAXCAT ?? $retailProduct?->TAXCAT;
        $category = $wholesaleProduct?->CATEGORY ?? $retailProduct?->CATEGORY;

        $vatRate = (float) ($taxRates[$taxcat] ?? 0);

        // The price is never stored - it is always derived from the product's
        // own PRICESELL so a price edited directly in uniCenta still shows true.
        $priceExVat = $wholesaleProduct ? (float) $wholesaleProduct->PRICESELL : null;
        $priceIncVat = $priceExVat !== null ? round(self::incVat($priceExVat, $vatRate), 2) : null;

        // Cost drift: PRICEBUY was the batch cost when the price was last set.
        $pricedCost = $wholesaleProduct ? (float) $wholesaleProduct->PRICEBUY : null;
        $costChanged = $pricedCost !== null
            && $pricedCost > 0
            && abs($batchCost - $pricedCost) / $pricedCost > 0.01;

        return [
            'id' => $recipe->id,
            'name' => $recipe->name,
            'showUrl' => route('kitchen.show', $recipe),
            'saveUrl' => route('kitchen.wholesale.store', $recipe),

            'batchCost' => round($batchCost, 2),
            'portions' => (int) $costs['portions_produced'],
            'costPerPortion' => (float) $costs['cost_per_portion'],
            'breakdown' => [
                'ingredient' => (float) $costs['ingredient_cost'],
                'labour' => (float) $costs['labour_cost'],
                'labourMinutes' => (float) $costs['labour_minutes'],
                'electricity' => (float) $costs['electricity_cost'],
                'packaging' => (float) $costs['packaging_cost'],
            ],
            'costAccuracy' => (float) $costs['cost_accuracy'],
            'isFullyProfiled' => (bool) $costs['is_fully_profiled'],

            'retailProductName' => $retailProduct?->NAME,
            'isLinked' => $recipe->hasLinkedProduct(),

            'wholesaleProductId' => $wholesaleProduct?->ID,
            'wholesaleProductName' => $wholesaleProduct?->NAME,
            'wholesaleProductCode' => $wholesaleProduct?->CODE,
            'isPriced' => $wholesaleProduct !== null,
            'costChanged' => $costChanged,
            'pricedCost' => $pricedCost !== null ? round($pricedCost, 2) : null,

            'category' => $category,
            'taxcat' => $taxcat,
            // Classification can only be inherited when something upstream has
            // it; otherwise the row must render the two dropdowns.
            'needsClassification' => $taxcat === null || $category === null,

            'incVat' => $priceIncVat,
            'savedIncVat' => $priceIncVat,
            'target' => $recipe->wholesale_target_margin !== null
                ? (float) $recipe->wholesale_target_margin
                : null,
            'pricedAt' => $recipe->wholesale_priced_at?->format('d/m/Y'),
            'expanded' => false,
        ];
    }

    /**
     * Create or update the wholesale POS product for a recipe.
     *
     * @return array<string, mixed> the refreshed row plus warnings
     */
    public function setPrice(
        KitchenRecipe $recipe,
        float $priceIncVat,
        ?string $categoryId = null,
        ?string $taxCategoryId = null,
        ?float $targetMargin = null,
    ): array {
        $costs = $this->costingService->calculateRecipeCost($recipe);
        $batchCost = (float) $costs['total_cost'];

        $existing = $this->resolveExistingProduct($recipe);

        $taxcat = $this->resolveTaxCategoryId($recipe, $existing, $taxCategoryId);
        $category = $this->resolveCategoryId($recipe, $existing, $categoryId);

        $vatRate = (float) (TaxCategory::with('primaryTax')->find($taxcat)?->primaryTax?->RATE ?? 0);
        $exVat = self::exVat($priceIncVat, $vatRate);

        $productId = $existing?->ID ?? (string) Str::uuid();
        $created = $existing === null;

        DB::connection('pos')->transaction(function () use (
            $recipe, $existing, $productId, $category, $taxcat, $exVat, $batchCost
        ) {
            if ($existing) {
                // Deliberately does NOT rename the product. PRODUCTS.NAME is
                // unique and till button layouts reference it, so a recipe
                // rename must not cascade into the POS.
                $existing->update([
                    'PRICESELL' => $exVat,
                    'PRICEBUY' => $batchCost,
                    'TAXCAT' => $taxcat,
                    'CATEGORY' => $category,
                ]);

                LabelLog::logPriceUpdate($existing->CODE);

                return;
            }

            $code = $this->barcodeGenerator->nextForCategory($category);
            $name = $this->uniqueName($this->wholesaleName($recipe), $code);

            $product = Product::create([
                'ID' => $productId,
                'NAME' => $name,
                'CODE' => $code,
                'REFERENCE' => $code,
                'CATEGORY' => $category,
                'TAXCAT' => $taxcat,
                'PRICEBUY' => $batchCost,
                'PRICESELL' => $exVat,
            ]);

            // Non-fatal: a missing stock row does not invalidate the product.
            try {
                StockCurrent::create([
                    'LOCATION' => '0',
                    'PRODUCT' => $product->ID,
                    'ATTRIBUTESETINSTANCE_ID' => null,
                    'UNITS' => 0.0,
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to create STOCKCURRENT for wholesale product', [
                    'product_id' => $product->ID,
                    'error' => $e->getMessage(),
                ]);
            }

            // A wholesale batch is a sellable line, so it belongs on the till.
            $this->tillVisibilityService->setVisibility($product->ID, true, 'category');

            ProductMetadata::createForProduct($product->ID, $product->CODE, Auth::id(), [
                'source' => 'kitchen_wholesale',
                'recipe_id' => $recipe->id,
                'recipe_name' => $recipe->name,
                'batch_cost' => $batchCost,
            ]);

            LabelLog::logNewProduct($product->CODE);
        });

        // Written after the POS commit - the two connections cannot share a
        // transaction. If this throws, resolveExistingProduct()'s name lookup
        // recovers the link on the next save.
        try {
            $recipe->update([
                'wholesale_pos_product_id' => $productId,
                'wholesale_target_margin' => $targetMargin ?? $recipe->wholesale_target_margin,
                'wholesale_priced_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Wholesale product saved but recipe link failed', [
                'recipe_id' => $recipe->id,
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);
        }

        $recipe->refresh()->load(['wholesaleProduct', 'product']);

        return [
            'created' => $created,
            'warnings' => $this->warningsFor($exVat, $batchCost, $targetMargin ?? $recipe->wholesale_target_margin),
            'margin_percentage' => self::marginPercentage($exVat, $batchCost),
            'batch_cost' => round($batchCost, 2),
        ];
    }

    /**
     * Warnings that inform without blocking the save.
     *
     * A wholesale price below cost is a legitimate business decision, so it is
     * flagged rather than rejected - blocking it would just push the user into
     * uniCenta to do it behind the app's back.
     *
     * @return array<int, string>
     */
    protected function warningsFor(float $exVat, float $batchCost, ?float $targetMargin): array
    {
        $warnings = [];

        if ($exVat < $batchCost) {
            $warnings[] = 'price_below_cost';
        }

        $margin = self::marginPercentage($exVat, $batchCost);

        if ($targetMargin !== null && $margin !== null && $margin < $targetMargin) {
            $warnings[] = 'margin_below_target';
        }

        return $warnings;
    }

    /**
     * Find the existing wholesale product for a recipe.
     *
     * Falls back to a name lookup so a product orphaned by a partial failure is
     * adopted rather than duplicated. A dangling ID clears itself.
     */
    protected function resolveExistingProduct(KitchenRecipe $recipe): ?Product
    {
        if ($recipe->wholesale_pos_product_id) {
            $product = Product::find($recipe->wholesale_pos_product_id);

            if ($product) {
                return $product;
            }

            Log::warning('Wholesale product id no longer exists in POS, relinking', [
                'recipe_id' => $recipe->id,
                'product_id' => $recipe->wholesale_pos_product_id,
            ]);

            $recipe->update(['wholesale_pos_product_id' => null]);
        }

        return Product::where('NAME', $this->wholesaleName($recipe))->first();
    }

    /**
     * Resolve the tax category, most explicit source first.
     *
     * Never falls back to a 0% rate - a silent zero would store a 23% item's
     * gross price as its net price and overprice it by the full VAT.
     */
    protected function resolveTaxCategoryId(KitchenRecipe $recipe, ?Product $existing, ?string $requested): string
    {
        $taxcat = $requested ?: ($existing?->TAXCAT ?: $recipe->product?->TAXCAT);

        if (! $taxcat) {
            throw new \RuntimeException('No tax category available for this recipe - choose one.');
        }

        return $taxcat;
    }

    /**
     * Resolve the POS category, most explicit source first.
     */
    protected function resolveCategoryId(KitchenRecipe $recipe, ?Product $existing, ?string $requested): string
    {
        $category = $requested ?: ($existing?->CATEGORY ?: $recipe->product?->CATEGORY);

        if (! $category) {
            throw new \RuntimeException('No category available for this recipe - choose one.');
        }

        return $category;
    }

    /**
     * Produce a name that clears the unique index on PRODUCTS.NAME.
     *
     * The code suffix is unique by construction, so one fallback is enough.
     */
    protected function uniqueName(string $base, string $code): string
    {
        if (! Product::where('NAME', $base)->exists()) {
            return $base;
        }

        return $base.' ['.$code.']';
    }
}
