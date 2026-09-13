<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only record of a customer request line moving between statuses.
 */
class CustomerRequestItemStatusLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * Shorter than the conventional customer_request_item_status_logs so the
     * auto-generated foreign key name fits MySQL's 64-character identifier limit.
     */
    protected $table = 'customer_request_status_logs';

    protected $fillable = [
        'customer_request_item_id',
        'from_status',
        'to_status',
        'user_id',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(CustomerRequestItem::class, 'customer_request_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
