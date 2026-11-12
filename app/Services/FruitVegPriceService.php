<?php

namespace App\Services;

use App\Models\Product;
use App\Models\VegPrintQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The one place a fruit & veg till price is changed: the POS sell price, the
 * veg_price_history audit row and the label print queue move together.
 *
 * Used by Fruit & Veg > Manage / Prices and by Dynamis invoice pricing.
 */
class FruitVegPriceService
{
    /**
     * Set the shelf (VAT-inclusive) price of a product.
     *
     * @return bool true when the price changed, false when it was already that price
     *
     * @throws \Throwable when either database write fails (both are rolled back)
     */
    public function setPrice(Product $product, float $newPrice, ?int $userId): bool
    {
        $newPrice = round($newPrice, 2);
        $tillPrice = round($product->getGrossPrice(), 2);

        $lastHistoryPrice = DB::table('veg_price_history')
            ->where('product_code', $product->CODE)
            ->orderBy('changed_at', 'desc')
            ->value('new_price');

        // Nothing to do only when the till and the history both already agree.
        $historyAgrees = $lastHistoryPrice === null || round((float) $lastHistoryPrice, 2) === $newPrice;
        if ($tillPrice === $newPrice && $historyAgrees) {
            return false;
        }

        DB::beginTransaction();
        DB::connection('pos')->beginTransaction();

        try {
            DB::table('veg_price_history')->insert([
                'product_code' => $product->CODE,
                'old_price' => $lastHistoryPrice ?? $tillPrice,
                'new_price' => $newPrice,
                'changed_by' => $userId,
                'changed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // The till stores the price net of VAT
            DB::connection('pos')->table('PRODUCTS')
                ->where('ID', $product->ID)
                ->update(['PRICESELL' => $newPrice / (1 + $product->getVatRate())]);

            DB::commit();
            DB::connection('pos')->commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            DB::connection('pos')->rollBack();

            Log::error('Failed to update product price', [
                'product_code' => $product->CODE,
                'old_price' => $tillPrice,
                'new_price' => $newPrice,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        VegPrintQueue::addToQueue($product->CODE, 'price_change');

        return true;
    }
}
