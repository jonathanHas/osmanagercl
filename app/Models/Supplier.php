<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
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
    protected $table = 'suppliers';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'SupplierID';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The data type of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Get the supplier link for this supplier.
     */
    public function supplierLink()
    {
        return $this->hasOne(SupplierLink::class, 'SupplierID', 'SupplierID');
    }

    /**
     * Get the product for this supplier through the supplier link.
     */
    public function product()
    {
        return $this->hasOneThrough(
            Product::class,
            SupplierLink::class,
            'SupplierID',      // Foreign key on SupplierLink
            'CODE',            // Foreign key on Product
            'SupplierID',      // Local key on Supplier
            'Barcode'          // Local key on SupplierLink
        );
    }
}
