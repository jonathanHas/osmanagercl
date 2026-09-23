<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class KdsProduct extends Model
{
    protected $fillable = [
        'product_id',
        'product_name',
        'category_id',
        'category_name',
        'is_active',
        'trigger_mode',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopePrimary(Builder $query): Builder
    {
        return $query->where('trigger_mode', 'primary');
    }

    public function scopeCompanion(Builder $query): Builder
    {
        return $query->where('trigger_mode', 'companion');
    }

    public function scopeExcluder(Builder $query): Builder
    {
        return $query->where('trigger_mode', 'excluder');
    }

    /**
     * Make sure a POS product is on the active KDS allow-list.
     *
     * The KDS importers only copy ticket lines whose product is listed here,
     * so a product with coffee metadata but no allow-list row never reaches
     * the screen. Creates the row from POS data when missing, reactivates it
     * when it was switched off, and leaves an already-active row untouched.
     *
     * Returns the row, or null when the product does not exist in the POS
     * (synthetic ids such as SYRUP_VANILLA can never match a ticket line, so
     * listing them would be meaningless).
     *
     * @return array{product: ?KdsProduct, action: 'created'|'reactivated'|'unchanged'|'not_in_pos'}
     */
    public static function ensureListed(string $productId): array
    {
        $existing = static::where('product_id', $productId)->first();

        if ($existing && $existing->is_active) {
            return ['product' => $existing, 'action' => 'unchanged'];
        }

        if ($existing) {
            $existing->update(['is_active' => true]);

            return ['product' => $existing, 'action' => 'reactivated'];
        }

        $pos = DB::connection('pos')
            ->table('PRODUCTS')
            ->where('ID', $productId)
            ->select('ID', 'NAME', 'CATEGORY')
            ->first();

        if (! $pos) {
            return ['product' => null, 'action' => 'not_in_pos'];
        }

        $categoryName = $pos->CATEGORY
            ? DB::connection('pos')->table('CATEGORIES')->where('ID', $pos->CATEGORY)->value('NAME')
            : null;

        $product = static::create([
            'product_id' => $pos->ID,
            'product_name' => $pos->NAME ?? 'Unknown',
            'category_id' => $pos->CATEGORY,
            'category_name' => $categoryName,
            'is_active' => true,
            'trigger_mode' => 'primary',
            'notes' => 'Auto-added from coffee metadata',
        ]);

        return ['product' => $product, 'action' => 'created'];
    }
}
