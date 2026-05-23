<?php

namespace App\Models;

use App\Models\POS\Ticket;
use App\Services\CoffeeOrderGroupingService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KdsOrder extends Model
{
    protected $fillable = [
        'ticket_id',
        'ticket_number',
        'person',
        'person_name',
        'status',
        'order_time',
        'viewed_at',
        'started_at',
        'ready_at',
        'completed_at',
        'prep_time',
        'customer_info',
    ];

    protected $casts = [
        'order_time' => 'datetime',
        'viewed_at' => 'datetime',
        'started_at' => 'datetime',
        'ready_at' => 'datetime',
        'completed_at' => 'datetime',
        'customer_info' => 'array',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(KdsOrderItem::class);
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'ID');
    }

    public function getWaitingTimeAttribute(): ?int
    {
        if (! $this->order_time) {
            return null;
        }

        $endTime = $this->completed_at ?? $this->ready_at ?? now();

        return $this->order_time->diffInSeconds($endTime);
    }

    public function getWaitingTimeFormattedAttribute(): string
    {
        $seconds = $this->waiting_time;
        if (! $seconds) {
            return '0:00';
        }

        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;

        return sprintf('%d:%02d', $minutes, $remainingSeconds);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['new', 'viewed', 'preparing', 'ready']);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('order_time', Carbon::today());
    }

    public function markAsViewed(): void
    {
        if ($this->status === 'new') {
            $this->update([
                'status' => 'viewed',
                'viewed_at' => now(),
            ]);
        }
    }

    public function startPreparing(): void
    {
        $this->update([
            'status' => 'preparing',
            'started_at' => now(),
            'viewed_at' => $this->viewed_at ?? now(),
        ]);
    }

    public function markAsReady(): void
    {
        $this->update([
            'status' => 'ready',
            'ready_at' => now(),
            'prep_time' => $this->started_at ? now()->diffInSeconds($this->started_at) : null,
        ]);
    }

    public function complete(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function cancel(): void
    {
        $this->update([
            'status' => 'cancelled',
            'completed_at' => now(),
        ]);
    }

    /**
     * Get grouped items for mobile display
     */
    public function getGroupedItemsAttribute()
    {
        $groupingService = new CoffeeOrderGroupingService;
        $items = $this->items->map(function ($item) {
            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->display_name,
                'display_name' => $item->display_name,
                'quantity' => $item->quantity,
                'formatted_quantity' => $item->formatted_quantity,
                'notes' => $item->notes,
                'modifiers' => $item->modifiers,
            ];
        })->toArray();

        return $groupingService->groupOrderItems($items);
    }

    /**
     * Get compact display lines for mobile
     */
    public function getCompactDisplayAttribute()
    {
        $groupingService = new CoffeeOrderGroupingService;

        return $groupingService->getCompactDisplay($this->grouped_items);
    }

    /**
     * Build the option-folded item list used by the /kds card layout.
     * Option-typed lines (per coffee_product_metadata) collapse into the
     * previous drink's modifiers as short-name chips; everything else
     * stays as its own card item.
     */
    public function getCardItemsAttribute(): array
    {
        $productIds = $this->items->pluck('product_id')->unique()->all();
        $optionTypeFor = empty($productIds)
            ? collect()
            : \App\Models\CoffeeProductMetadata::whereIn('product_id', $productIds)->pluck('type', 'product_id');
        $shortNameFor = empty($productIds)
            ? collect()
            : \App\Models\CoffeeProductMetadata::whereIn('product_id', $productIds)->pluck('short_name', 'product_id');

        $out = [];
        foreach ($this->items as $item) {
            $isOption = ($optionTypeFor[$item->product_id] ?? null) === 'option';

            // Flatten POS ATTRIBUTES modifiers (key/value array) into bare values.
            $native = is_array($item->modifiers)
                ? array_values(array_filter(array_values($item->modifiers), fn ($v) => $v !== null && $v !== ''))
                : [];

            $lastIdx = count($out) - 1;
            if ($isOption && $lastIdx >= 0 && $out[$lastIdx]['kind'] === 'drink') {
                $label = $shortNameFor[$item->product_id] ?? $item->display_name;
                $out[$lastIdx]['modifiers'][] = (string) $label;

                continue;
            }

            $out[] = [
                'id' => $item->id,
                'product_name' => $item->display_name,
                'quantity' => $item->formatted_quantity,
                'kind' => $item->kind,
                'modifiers' => $native,
                'notes' => $item->notes,
            ];
        }

        return $out;
    }

    /**
     * Check if order should use compact display (mobile view)
     */
    public function shouldUseCompactDisplay()
    {
        // Use compact display if:
        // 1. Order has 3+ items total, OR
        // 2. Order has at least 1 coffee type AND 1 option (allowing grouping even with 2 items)

        $itemCount = $this->items->count();

        if ($itemCount >= 3) {
            return true;
        }

        if ($itemCount >= 2) {
            // Check if we have both coffee types and options for grouping
            $hasCoffee = false;
            $hasOption = false;

            foreach ($this->items as $item) {
                $metadata = \App\Models\CoffeeProductMetadata::where('product_id', $item->product_id)->first();
                if ($metadata) {
                    if ($metadata->type === 'coffee') {
                        $hasCoffee = true;
                    } elseif ($metadata->type === 'option') {
                        $hasOption = true;
                    }
                }

                // Early exit if we found both
                if ($hasCoffee && $hasOption) {
                    return true;
                }
            }
        }

        return false;
    }
}
