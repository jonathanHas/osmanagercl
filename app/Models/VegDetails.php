<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class VegDetails extends Model
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
    protected $table = 'vegDetails';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'ID';

    /**
     * The "type" of the auto-incrementing ID.
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
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'ID',
        'product',
        'countryCode',
        'classId',
        'unitId',
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'class_name',
        'unit_name',
    ];

    /**
     * Update the veg details for a product, creating the row if it does not exist yet.
     *
     * ID is a varchar(36) with no auto-increment, so it has to be supplied. The
     * table is uniCenta's, and every row it created holds a UUID - the same
     * convention PRODUCTS.ID uses - so new rows get one too.
     *
     * Missing columns fall back to the POS defaults (Ireland / Class I / kg) so a
     * partially filled form never writes a null into an FK-constrained column.
     *
     * Updates are keyed on `product` (the table's only unique index) rather than
     * on ID: an earlier ID generator produced the literal "1" for every row it
     * created, so several rows can share an ID and an ID-keyed update would write
     * to all of them at once.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function upsertForProduct(string $productCode, array $attributes): self
    {
        $detail = static::where('product', $productCode)->first();

        if ($detail) {
            static::where('product', $productCode)->update($attributes);
            $detail->forceFill($attributes);

            return $detail;
        }

        return static::create(array_merge([
            'ID' => (string) Str::uuid(),
            'product' => $productCode,
            'countryCode' => 1,
            'classId' => 1,
            'unitId' => 1,
        ], $attributes));
    }

    /**
     * Get the product that owns the veg details.
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product', 'CODE');
    }

    /**
     * Get the country for this veg detail.
     */
    public function country()
    {
        // Cross-database relationship: POS vegDetails.countryCode -> main DB countries.id
        return $this->setConnection('mysql')->belongsTo(Country::class, 'countryCode', 'id');
    }

    /**
     * Get the class for this veg detail.
     */
    public function vegClass()
    {
        return $this->belongsTo(VegClass::class, 'classId', 'ID');
    }

    /**
     * Get the unit for this veg detail.
     */
    public function vegUnit()
    {
        // Now correctly references POS database units table
        return $this->belongsTo(PosUnit::class, 'unitId', 'ID');
    }

    /**
     * Get the class name attribute for backward compatibility.
     */
    public function getClassNameAttribute()
    {
        return $this->vegClass ? $this->vegClass->name : '';
    }

    /**
     * Get the unit name attribute for backward compatibility.
     */
    public function getUnitNameAttribute()
    {
        return $this->vegUnit ? $this->vegUnit->abbreviation : 'kg';
    }
}
