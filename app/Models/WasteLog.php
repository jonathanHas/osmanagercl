<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WasteLog extends Model
{
    use HasFactory;

    /**
     * Units a waste quantity can be recorded in (matches harvest convention).
     */
    public const UNITS = ['kg', 'unit'];

    /**
     * The connection name for the model (Laravel primary DB).
     *
     * @var string
     */
    protected $connection = 'mysql';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'fv_waste_logs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'waste_date',
        'product_code',
        'product_name',
        'quantity',
        'unit',
        'unit_price',
        'value',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'waste_date' => 'date',
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'value' => 'decimal:2',
    ];

    /**
     * The POS product this waste line refers to.
     *
     * Cross-connection (mysql -> pos): Eloquent resolves the related model on
     * Product's own connection. Convenience only — lists rely on the snapshot
     * product_name/unit_price columns so history stays readable if the POS
     * product is later renamed, repriced or removed.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_code', 'CODE');
    }

    /**
     * The user who recorded this waste line.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
