<?php

namespace App\Services;

use App\Models\AccountingSupplier;
use App\Models\KitchenIngredientProfile;
use App\Models\KitchenRecipe;
use App\Models\KitchenRecipeIngredient;
use App\Models\SupplierLink;
use Illuminate\Support\Collection;

/**
 * Assembles the data for an Organic Trust "Multi-Ingredient Product Registration Form".
 *
 * The form requires every input listed in descending order by weight of input, each with its share
 * of the overall product, its organic status and - for organic inputs - the certification body of
 * the supplier it came from.
 *
 * Recipe quantities are recorded in mixed units, so each is converted to grams:
 *   - weight units (kg, g) convert directly;
 *   - volume units (L, ml, tbsp, tsp) convert via the profile's `density` (g/ml);
 *   - count units (unit, dozen, pack) convert via the profile's `unit_weight_grams`.
 * Anything that cannot be converted is reported as a warning and printed with a blank percentage
 * rather than a guessed one - an understated form can be corrected, a wrong one misleads.
 */
class KitchenOrganicRegistrationService
{
    /**
     * Build the form rows and any data gaps for a recipe.
     *
     * @return array{product_name:string, rows:array<int,array<string,mixed>>, warnings:array<int,string>, total_grams:float}
     */
    public function build(KitchenRecipe $recipe): array
    {
        $ingredients = $recipe->ingredients()->with(['profile.product', 'product'])->get();

        return $this->buildFrom($recipe->name, $ingredients, $this->certificationBodiesByBarcode($ingredients));
    }

    /**
     * Summarise how ready several recipes are for registration, in a fixed number of queries
     * regardless of how many recipes are passed - the listing page needs this for a whole page of
     * recipes and would otherwise re-query per row.
     *
     * @param  Collection<int,KitchenRecipe>  $recipes
     * @return array<int,array{incomplete:int, total:int, warnings:array<int,string>}> keyed by recipe id
     */
    public function summarise(Collection $recipes): array
    {
        if ($recipes->isEmpty()) {
            return [];
        }

        $ingredients = KitchenRecipeIngredient::whereIn('recipe_id', $recipes->pluck('id'))
            ->with(['profile.product', 'product'])
            ->get();

        $certificationBodies = $this->certificationBodiesByBarcode($ingredients);

        $summary = [];

        foreach ($recipes as $recipe) {
            $form = $this->buildFrom(
                $recipe->name,
                $ingredients->where('recipe_id', $recipe->id),
                $certificationBodies
            );

            $summary[$recipe->id] = [
                'incomplete' => count(array_filter($form['rows'], fn ($row) => $row['percent'] === null)),
                'total' => count($form['rows']),
                'warnings' => $form['warnings'],
            ];
        }

        return $summary;
    }

    /**
     * Turn a set of ingredients into form rows, ordered and weighted as the form requires.
     *
     * @param  Collection<int,KitchenRecipeIngredient>  $ingredients
     * @param  array<string,string|null>  $certificationBodies
     * @return array{product_name:string, rows:array<int,array<string,mixed>>, warnings:array<int,string>, total_grams:float}
     */
    private function buildFrom(string $productName, Collection $ingredients, array $certificationBodies): array
    {
        $warnings = [];
        $rows = [];

        foreach ($ingredients as $ingredient) {
            $profile = $ingredient->profile;
            $grams = $this->grams($ingredient, $profile, $warnings);
            $isOrganic = $profile?->isOrganic() ?? true;

            $barcode = $ingredient->getEffectiveProduct()?->CODE;
            $body = $barcode !== null ? ($certificationBodies[$barcode] ?? null) : null;

            if ($isOrganic && empty($body)) {
                $warnings[] = sprintf(
                    '"%s" has no organic certification body - set one for its supplier on the Organic Trust report.',
                    $ingredient->product_name
                );
            }

            $rows[] = [
                'ingredient' => $ingredient->product_name,
                'quantity' => $ingredient->formatted_quantity,
                'grams' => $grams,
                'percent' => null,
                'organic' => $isOrganic,
                'certification_body' => $isOrganic ? ($body ?? '') : '',
            ];
        }

        $totalGrams = array_sum(array_map(fn ($row) => $row['grams'] ?? 0, $rows));

        if ($totalGrams > 0) {
            foreach ($rows as $index => $row) {
                if ($row['grams'] !== null) {
                    $rows[$index]['percent'] = $row['grams'] / $totalGrams * 100;
                }
            }
        }

        // Descending by weight of input, as the form requires. Rows whose weight is unknown cannot
        // be placed in that order, so they trail the list.
        usort($rows, function ($a, $b) {
            if (($a['grams'] === null) !== ($b['grams'] === null)) {
                return $a['grams'] === null ? 1 : -1;
            }

            return ($b['grams'] ?? 0) <=> ($a['grams'] ?? 0);
        });

        return [
            'product_name' => $productName,
            'rows' => $rows,
            'warnings' => array_values(array_unique($warnings)),
            'total_grams' => $totalGrams,
        ];
    }

    /**
     * Convert one ingredient's quantity to grams, or null when there is no way to weigh it.
     *
     * @param  array<int,string>  $warnings
     */
    private function grams(KitchenRecipeIngredient $ingredient, ?KitchenIngredientProfile $profile, array &$warnings): ?float
    {
        $quantity = (float) $ingredient->quantity;
        $unit = $ingredient->unit_type;
        $category = KitchenIngredientProfile::getUnitCategory($unit);

        // "slice" and "portion" are recipe units with no entry in UNIT_CONVERSIONS, so they fall
        // through here rather than being silently treated as a multiplier of 1.
        if ($category === null) {
            $warnings[] = sprintf(
                '"%s" is measured in %s, which has no weight equivalent - its %% weight is left blank.',
                $ingredient->product_name,
                $unit
            );

            return null;
        }

        $baseUnits = $quantity * KitchenIngredientProfile::getMultiplier($unit);

        if ($category === 'weight') {
            return $baseUnits;
        }

        if ($category === 'volume') {
            if (! $profile || ! $profile->hasDensity()) {
                $warnings[] = sprintf(
                    'Set a density (g/ml) on the "%s" ingredient profile so its %% weight can be calculated.',
                    $ingredient->product_name
                );

                return null;
            }

            return $baseUnits * (float) $profile->density;
        }

        if (! $profile || ! $profile->hasUnitWeight()) {
            $warnings[] = sprintf(
                'Set a weight per unit (g) on the "%s" ingredient profile so its %% weight can be calculated.',
                $ingredient->product_name
            );

            return null;
        }

        return $baseUnits * (float) $profile->unit_weight_grams;
    }

    /**
     * Resolve each ingredient's certification body, keyed by POS product barcode.
     *
     * Chain: PRODUCTS.CODE -> supplier_link.Barcode -> supplier_link.SupplierID
     *        -> accounting_suppliers.external_pos_id -> organic_certification_body
     *
     * Loaded in two queries for the whole recipe rather than per ingredient.
     *
     * @param  Collection<int,KitchenRecipeIngredient>  $ingredients
     * @return array<string,string|null>
     */
    private function certificationBodiesByBarcode(Collection $ingredients): array
    {
        $barcodes = $ingredients
            ->map(fn (KitchenRecipeIngredient $i) => $i->getEffectiveProduct()?->CODE)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($barcodes === []) {
            return [];
        }

        // A product can carry several supplier links; prefer the stocked one.
        $links = SupplierLink::whereIn('Barcode', $barcodes)
            ->orderByDesc('stocked')
            ->get()
            ->unique('Barcode')   // keyBy would keep the last match; unique keeps the stocked one
            ->keyBy('Barcode');

        $bodies = AccountingSupplier::whereIn('external_pos_id', $links->pluck('SupplierID')->filter()->unique())
            ->pluck('organic_certification_body', 'external_pos_id');

        $result = [];

        foreach ($links as $barcode => $link) {
            $result[$barcode] = $bodies[$link->SupplierID] ?? null;
        }

        return $result;
    }
}
