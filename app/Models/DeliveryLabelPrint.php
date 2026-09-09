<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * One row per (barcode, print batch): the translated labels actually sent to the Zebra
 * for a delivery. Used to work out what is still outstanding so a second press of
 * "Print Translated Labels" only prints the delta rather than the whole delivery again.
 */
class DeliveryLabelPrint extends Model
{
    protected $connection = 'mysql';

    protected $fillable = [
        'delivery_id',
        'supplier_id',
        'barcode',
        'product_translation_id',
        'quantity',
        'batch_uuid',
        'idempotency_key',
        'cups_job_id',
        'lp_output',
        'printed_at',
        'failed_at',
        'forced',
        'user_id',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'supplier_id' => 'integer',
        'forced' => 'boolean',
        'printed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function translation(): BelongsTo
    {
        return $this->belongsTo(ProductTranslation::class, 'product_translation_id');
    }

    /**
     * Only prints the printer accepted. A stamped failed_at releases the quantity back
     * to "outstanding" so the next press reprints it.
     */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->whereNull('failed_at');
    }

    /**
     * Labels successfully printed so far in a delivery, keyed by barcode.
     *
     * @return Collection<string, int>
     */
    public static function printedQuantitiesFor(string $deliveryId): Collection
    {
        return static::query()
            ->where('delivery_id', $deliveryId)
            ->successful()
            ->groupBy('barcode')
            ->selectRaw('barcode, SUM(quantity) as printed')
            ->pluck('printed', 'barcode')
            ->map(fn ($printed) => (int) $printed);
    }

    /**
     * Has this submit attempt already been recorded? Guards double-clicks and retries
     * of a request whose response was lost.
     */
    public static function hasIdempotencyKey(string $key): bool
    {
        return static::query()->where('idempotency_key', $key)->exists();
    }

    /**
     * Stamp the outcome of the lp call onto every row in the batch. A definitive
     * refusal sets failed_at, which restores the outstanding quantity.
     */
    public static function markBatchResult(string $batchUuid, ?string $jobId, ?string $output, bool $failed): void
    {
        static::query()
            ->where('batch_uuid', $batchUuid)
            ->update([
                'cups_job_id' => $jobId,
                'lp_output' => $output,
                'failed_at' => $failed ? now() : null,
                'updated_at' => now(),
            ]);
    }

    /**
     * The most recent batch for a delivery, for the undo action.
     */
    public static function latestBatchFor(string $deliveryId): ?string
    {
        return static::query()
            ->where('delivery_id', $deliveryId)
            ->successful()
            ->orderByDesc('printed_at')
            ->orderByDesc('id')
            ->value('batch_uuid');
    }
}
