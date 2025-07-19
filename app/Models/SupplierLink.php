<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierLink extends Model
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
    protected $table = 'supplier_link';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'ID';

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
        'Barcode',
        'SupplierCode',
        'SupplierID',
        'CaseUnits',
        'stocked',
        'OuterCode',
        'Cost',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'Cost' => 'decimal:2',
        'CaseUnits' => 'integer',
        'stocked' => 'boolean',
    ];

    /**
     * Get the product for this supplier link.
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'Barcode', 'CODE');
    }

    /**
     * Get the supplier for this supplier link.
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'SupplierID', 'SupplierID');
    }
}
