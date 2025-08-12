<?php

namespace App\Models\OSAccounts;

use Illuminate\Database\Eloquent\Model;

/**
 * OSAccounts Supplier_Types table model
 * Represents supplier categorization from the legacy OSAccounts system
 */
class OSSupplierType extends Model
{
    protected $connection = 'osaccounts';

    protected $table = 'Supplier_Types';

    protected $primaryKey = 'Supplier_Type_ID';

    public $timestamps = false;

    protected $fillable = [
        'Supplier_Type_ID',
        'Supplier_Type_Name',
        'Description',
    ];

    protected $casts = [
        'Supplier_Type_ID' => 'integer',
    ];

    /**
     * Get all expenses/suppliers with this type
     */
    public function expenses()
    {
        return $this->hasMany(OSExpense::class, 'Supplier_Type_ID', 'Supplier_Type_ID');
    }

    /**
     * Map OSAccounts supplier type to Laravel supplier type
     */
    public function toLaravelSupplierType()
    {
        // Map based on common patterns
        $name = strtolower($this->Supplier_Type_Name ?? '');
        $description = strtolower($this->Description ?? '');

        if (str_contains($name, 'product') || str_contains($description, 'product')) {
            return 'product';
        }

        if (str_contains($name, 'service') || str_contains($description, 'service')) {
            return 'service';
        }

        if (str_contains($name, 'utility') || str_contains($description, 'utility') ||
            str_contains($name, 'electric') || str_contains($name, 'gas') || str_contains($name, 'water')) {
            return 'utility';
        }

        if (str_contains($name, 'professional') || str_contains($description, 'professional') ||
            str_contains($name, 'legal') || str_contains($name, 'accounting') || str_contains($name, 'consulting')) {
            return 'professional';
        }

        // Default to 'other' for unmappable types
        return 'other';
    }

    /**
     * Get supplier count for this type
     */
    public function getSupplierCountAttribute()
    {
        return $this->expenses()->count();
    }
}
