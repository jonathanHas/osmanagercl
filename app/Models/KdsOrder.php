<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;
use App\Models\POS\Ticket;

class KdsOrder extends Model
{
    protected $fillable = [
        'ticket_id',
        'ticket_number',
        'person',
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
        if (!$this->order_time) {
            return null;
        }

        $endTime = $this->completed_at ?? $this->ready_at ?? now();
        return $this->order_time->diffInSeconds($endTime);
    }

    public function getWaitingTimeFormattedAttribute(): string
    {
        $seconds = $this->waiting_time;
        if (!$seconds) {
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
}