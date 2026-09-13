<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A customer asking us for one or more items — a pre-order of something we
 * stock, or something new to source. Each line (CustomerRequestItem) carries
 * its own status; the request is "open" while any line is still open.
 */
class CustomerRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_name',
        'customer_phone',
        'wanted_on',
        'notes',
        'closed_at',
        'created_by',
        'updated_by',
        'closed_by',
    ];

    protected function casts(): array
    {
        return [
            'wanted_on' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(CustomerRequestItem::class)->orderBy('position')->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * Requests with at least one line still pending / ordered / put aside.
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('closed_at');
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->whereNotNull('closed_at');
    }

    /**
     * Open requests whose wanted-by date is today or has passed.
     */
    public function scopeDueTodayOrOverdue(Builder $query): Builder
    {
        return $query->open()->whereDate('wanted_on', '<=', today());
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    public function isDueToday(): bool
    {
        return $this->isOpen() && $this->wanted_on !== null && $this->wanted_on->isSameDay(today());
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->wanted_on !== null && $this->wanted_on->lt(today());
    }

    public function isDue(): bool
    {
        return $this->isDueToday() || $this->isOverdue();
    }

    /**
     * Lines that still need something to happen (pending / ordered / put aside).
     */
    public function openItems()
    {
        return $this->items->filter(fn (CustomerRequestItem $item) => $item->isOpen());
    }

    /**
     * Recompute closed_at / closed_by from the lines' statuses.
     *
     * Called after every line status change or edit so the header never drifts
     * from its lines: a request closes when no line is open and reopens when a
     * line is added or moved back to an open status.
     */
    public function refreshClosedState(?User $user = null): void
    {
        $hasOpenLine = $this->items()->whereIn('status', CustomerRequestItem::OPEN_STATUSES)->exists();

        if ($hasOpenLine && $this->closed_at !== null) {
            $this->forceFill(['closed_at' => null, 'closed_by' => null])->save();
        } elseif (! $hasOpenLine && $this->closed_at === null) {
            $this->forceFill(['closed_at' => now(), 'closed_by' => $user?->id])->save();
        }
    }
}
