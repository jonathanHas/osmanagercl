<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TillReviewAudit extends Model
{
    protected $table = 'till_review_audit';

    protected $fillable = [
        'user_id',
        'viewed_date',
        'filters_used',
        'action',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'viewed_date' => 'date',
        'filters_used' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
