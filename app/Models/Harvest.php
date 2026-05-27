<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Harvest extends Model
{
    use HasFactory;

    /**
     * The connection name for the model (Laravel primary DB).
     *
     * @var string
     */
    protected $connection = 'mysql';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'harvest_date',
        'product_code',
        'product_name',
        'quantity',
        'unit',
        'notes',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'harvest_date' => 'date',
        'quantity' => 'decimal:2',
    ];

    /**
     * The POS product this harvest line refers to.
     *
     * Cross-connection (mysql -> pos): Eloquent resolves the related model on
     * Product's own connection. Convenience only — lists rely on the snapshot
     * product_name/unit columns so history stays readable if the POS product
     * is later renamed or removed.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_code', 'CODE');
    }

    /**
     * The user who recorded this harvest line.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
