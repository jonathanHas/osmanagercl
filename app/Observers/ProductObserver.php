<?php

namespace App\Observers;

use App\Models\KitchenIngredientProfile;
use App\Models\Product;
use Illuminate\Support\Facades\Log;

class ProductObserver
{
    /**
     * Handle the Product "updated" event.
     */
    public function updated(Product $product): void
    {
        if ($product->wasChanged('PRICEBUY')) {
            $this->recalculateKitchenProfiles($product);
        }
    }

    /**
     * Refresh the stored cost_per_base_unit on any kitchen ingredient profile
     * linked to this product.
     *
     * Recipe line costs read the stored column (via KitchenIngredientProfile::calculateCost),
     * so without this a cost price change never reaches recipe costings.
     *
     * Failures are logged rather than thrown - this runs inside delivery imports and
     * stock valuation fixes, where a kitchen issue must not abort the product save.
     */
    protected function recalculateKitchenProfiles(Product $product): void
    {
        try {
            KitchenIngredientProfile::where('pos_product_id', $product->ID)
                ->get()
                ->each
                ->recalculateCost();
        } catch (\Throwable $e) {
            Log::error('Failed to recalculate kitchen ingredient profiles after cost change', [
                'product_id' => $product->ID,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
