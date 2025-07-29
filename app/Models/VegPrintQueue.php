<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VegPrintQueue extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'veg_print_queue';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_code',
        'added_at',
        'reason',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'added_at' => 'datetime',
    ];

    /**
     * Get the product that needs printing.
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_code', 'CODE');
    }

    /**
     * Add a product to the print queue.
     */
    public static function addToQueue(string $productCode, string $reason = 'manual'): self
    {
        return self::updateOrCreate(
            ['product_code' => $productCode],
            [
                'reason' => $reason,
                'added_at' => now(),
            ]
        );
    }

    /**
     * Remove a product from the print queue.
     */
    public static function removeFromQueue(string $productCode): bool
    {
        return self::where('product_code', $productCode)->delete() > 0;
    }

    /**
     * Remove multiple products from the print queue.
     */
    public static function removeMultipleFromQueue(array $productCodes): int
    {
        return self::whereIn('product_code', $productCodes)->delete();
    }

    /**
     * Clear the entire print queue.
     */
    public static function clearQueue(): int
    {
        $count = self::count();
        self::truncate();

        return $count;
    }

    /**
     * Get all product codes in the queue.
     */
    public static function getQueuedProductCodes(): array
    {
        return self::pluck('product_code')->toArray();
    }
}
