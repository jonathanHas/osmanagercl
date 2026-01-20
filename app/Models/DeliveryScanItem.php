<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DeliveryScanItem extends Model
{
    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'pos';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'deliveriesScanItems';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'ID';

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'ID',
        'delID',
        'barcode',
        'quantity',
        'dateScan',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'quantity' => 'decimal:2',
        'dateScan' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->ID)) {
                $model->ID = Str::uuid()->toString();
            }
            if (empty($model->dateScan)) {
                $model->dateScan = now();
            }
        });
    }

    /**
     * Get the scan session this item belongs to.
     */
    public function scanSession()
    {
        return $this->belongsTo(DeliveryScan::class, 'delID', 'ID');
    }
}
