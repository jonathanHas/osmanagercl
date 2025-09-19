<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VegLabelPrintBatch extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_codes',
        'product_count',
        'printed_at',
        'restored_at',
        'user_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'product_codes' => 'array',
        'printed_at' => 'datetime',
        'restored_at' => 'datetime',
    ];

    /**
     * Get the user who triggered this print batch.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
