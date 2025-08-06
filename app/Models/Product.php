<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
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
    protected $table = 'PRODUCTS';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'ID';

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
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'ID',
        'NAME',
        'CODE',
        'REFERENCE',
        'CATEGORY',
        'TAXCAT',
        'PRICESELL',
        'PRICEBUY',
        'DISPLAY',
        'IMAGE',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'PRICEBUY' => 'decimal:2',
        'PRICESELL' => 'decimal:4',
        'STOCKCOST' => 'decimal:2',
        'STOCKVOLUME' => 'decimal:2',
        'STOCKUNITS' => 'decimal:2',
        'ISCOM' => 'boolean',
        'ISSCALE' => 'boolean',
        'ISKITCHEN' => 'boolean',
        'PRINTKB' => 'boolean',
        'SENDSTATUS' => 'boolean',
        'ISSERVICE' => 'boolean',
        'ISVPRICE' => 'integer',
        'ISVERPATRIB' => 'integer',
        'WARRANTY' => 'integer',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'IMAGE',
        'ATTRIBUTES',
    ];

    /**
     * Get the formatted price for display.
     */
    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->PRICESELL, 2);
    }

    /**
     * Check if the product is a service.
     */
    public function isService(): bool
    {
        return (bool) $this->ISSERVICE;
    }

    /**
     * Check if the product is sold by weight/scale.
     */
    public function isSoldByWeight(): bool
    {
        return (bool) $this->ISSCALE;
    }

    /**
     * Check if the product is a kitchen item.
     */
    public function isKitchenItem(): bool
    {
        return (bool) $this->ISKITCHEN;
    }

    /**
     * Scope a query to only include active (non-service) products.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('ISSERVICE', 0);
    }

    /**
     * Scope a query to only include products in stock.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeInStock($query)
    {
        return $query->where('STOCKUNITS', '>', 0);
    }

    /**
     * Search products by name, code, or reference.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('NAME', 'like', '%'.$search.'%')
                ->orWhere('CODE', 'like', '%'.$search.'%')
                ->orWhere('REFERENCE', 'like', '%'.$search.'%');
        });
    }

    /**
     * Get the supplier link for this product.
     */
    public function supplierLink()
    {
        return $this->hasOne(SupplierLink::class, 'Barcode', 'CODE');
    }

    /**
     * Get all supplier links for this product (plural for consistency).
     */
    public function supplierLinks()
    {
        return $this->hasMany(SupplierLink::class, 'Barcode', 'CODE');
    }

    /**
     * Get the supplier for this product through the supplier link.
     */
    public function supplier()
    {
        return $this->hasOneThrough(
            Supplier::class,
            SupplierLink::class,
            'Barcode',         // Foreign key on SupplierLink
            'SupplierID',      // Foreign key on Supplier
            'CODE',            // Local key on Product
            'SupplierID'       // Local key on SupplierLink
        );
    }

    /**
     * Get the stocking record for this product.
     */
    public function stocking()
    {
        return $this->hasOne(Stocking::class, 'Barcode', 'CODE');
    }

    /**
     * Get the veg details for this product.
     */
    public function vegDetails()
    {
        // VegDetails now uses POS connection and 'product' field
        return $this->hasOne(VegDetails::class, 'product', 'CODE');
    }

    /**
     * Get the product image as a base64 data URL for thumbnails.
     */
    public function getImageThumbnailAttribute(): ?string
    {
        if (! $this->IMAGE) {
            return null;
        }

        // Assume JPEG format for simplicity - you might want to detect the actual format
        return 'data:image/jpeg;base64,'.base64_encode($this->IMAGE);
    }

    /**
     * Check if the product has an image.
     */
    public function hasImage(): bool
    {
        return ! empty($this->IMAGE);
    }

    /**
     * Scope a query to only include products that are stocked.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeStocked($query)
    {
        // Using EXISTS avoids Cartesian products when combined with other filters
        return $query->whereExists(function ($q) {
            $q->select(\DB::raw(1))
                ->from('stocking')
                ->whereRaw('stocking.Barcode = PRODUCTS.CODE');
        });
    }

    /**
     * Get the current stock record for this product.
     */
    public function stockCurrent()
    {
        return $this->hasOne(StockCurrent::class, 'PRODUCT', 'ID');
    }

    /**
     * Get the current stock quantity for this product.
     */
    public function getCurrentStock(): float
    {
        return $this->stockCurrent?->UNITS ?? 0.0;
    }

    /**
     * Scope a query to only include products with current stock.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeInCurrentStock($query)
    {
        // Using EXISTS avoids Cartesian products when combined with other filters
        return $query->whereExists(function ($q) {
            $q->select(\DB::raw(1))
                ->from('STOCKCURRENT')
                ->whereRaw('STOCKCURRENT.PRODUCT = PRODUCTS.ID')
                ->where('STOCKCURRENT.UNITS', '>', 0);
        });
    }

    /**
     * Get the tax category for this product.
     */
    public function taxCategory()
    {
        return $this->belongsTo(TaxCategory::class, 'TAXCAT', 'ID');
    }

    /**
     * Get the primary tax for this product.
     */
    public function tax()
    {
        return $this->hasOneThrough(
            Tax::class,
            TaxCategory::class,
            'ID',        // Foreign key on TaxCategory
            'CATEGORY',  // Foreign key on Tax
            'TAXCAT',    // Local key on Product
            'ID'         // Local key on TaxCategory
        )->orderBy('RATEORDER')->orderBy('ID');
    }

    /**
     * Get the VAT rate for this product.
     */
    public function getVatRate(): float
    {
        return $this->tax?->RATE ?? 0.0;
    }

    /**
     * Get the VAT rate as a percentage.
     */
    public function getVatRatePercentage(): float
    {
        return $this->getVatRate() * 100;
    }

    /**
     * Get the formatted VAT rate as a percentage string.
     */
    public function getFormattedVatRateAttribute(): string
    {
        $rate = $this->getVatRatePercentage();

        return $rate > 0 ? number_format($rate, 1).'%' : '0%';
    }

    /**
     * Calculate the VAT amount for this product's sell price.
     */
    public function getVatAmount(): float
    {
        return $this->PRICESELL * $this->getVatRate();
    }

    /**
     * Get the gross price (net price + VAT).
     */
    public function getGrossPrice(): float
    {
        return $this->PRICESELL * (1 + $this->getVatRate());
    }

    /**
     * Get the formatted gross price for display.
     */
    public function getFormattedPriceWithVatAttribute(): string
    {
        $grossPrice = $this->getGrossPrice();

        return '€'.number_format($grossPrice, 2);
    }

    /**
     * Get the tax category name with fallback.
     */
    public function getTaxCategoryNameAttribute(): string
    {
        return $this->taxCategory?->NAME ?? 'Unknown';
    }

    /**
     * Get a colored badge class for the tax category.
     */
    public function getTaxCategoryBadgeClassAttribute(): string
    {
        $rate = $this->getVatRatePercentage();

        if ($rate == 0) {
            return 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-100';
        } elseif ($rate < 15) {
            return 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100';
        } elseif ($rate < 20) {
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100';
        } else {
            return 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100';
        }
    }

    /**
     * Get the category for this product.
     */
    public function category()
    {
        return $this->belongsTo(Category::class, 'CATEGORY', 'ID');
    }

    /**
     * Get the category name with fallback.
     */
    public function getCategoryNameAttribute(): string
    {
        return $this->category?->NAME ?? 'Uncategorized';
    }

    /**
     * Get the full category path for this product.
     */
    public function getCategoryPathAttribute(): string
    {
        return $this->category?->full_path ?? 'Uncategorized';
    }

    /**
     * Scope a query to filter by category.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeInCategory($query, string $categoryId)
    {
        return $query->where('CATEGORY', $categoryId);
    }

    /**
     * Get the stock diary entries for this product.
     */
    public function stockDiary()
    {
        return $this->hasMany(StockDiary::class, 'PRODUCT', 'ID');
    }

    /**
     * Get the sales entries for this product.
     */
    public function salesEntries()
    {
        return $this->hasMany(StockDiary::class, 'PRODUCT', 'ID')->where('REASON', StockDiary::REASON_SALE);
    }

    /**
     * Get the metadata for this product from the Laravel database.
     */
    public function metadata(): HasOne
    {
        return $this->hasOne(ProductMetadata::class, 'product_id', 'ID');
    }

    /**
     * Get the creation date for this product.
     * 
     * @return \Illuminate\Support\Carbon|null
     */
    public function getCreatedAtAttribute()
    {
        return $this->metadata?->created_at;
    }

    /**
     * Get the user who created this product.
     * 
     * @return \App\Models\User|null
     */
    public function getCreatedByAttribute()
    {
        return $this->metadata?->user;
    }

    /**
     * Check if this product has creation metadata.
     * 
     * @return bool
     */
    public function hasCreationMetadata(): bool
    {
        return $this->metadata !== null;
    }
}
