<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoffeeProductMetadata extends Model
{
    protected $table = 'coffee_product_metadata';
    
    protected $fillable = [
        'product_id',
        'product_name',
        'type',
        'short_name',
        'group_name',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Get coffee types (main drinks)
    public static function getCoffeeTypes()
    {
        return self::where('type', 'coffee')
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('short_name')
            ->get();
    }

    // Get options grouped by category
    public static function getOptionsGrouped()
    {
        return self::where('type', 'option')
            ->where('is_active', true)
            ->orderBy('group_name')
            ->orderBy('display_order')
            ->orderBy('short_name')
            ->get()
            ->groupBy('group_name');
    }

    // Get short name for a product ID
    public static function getShortName($productId)
    {
        $metadata = self::where('product_id', $productId)->first();
        return $metadata ? $metadata->short_name : null;
    }

    // Check if product is a coffee type
    public static function isCoffeeType($productId)
    {
        return self::where('product_id', $productId)
            ->where('type', 'coffee')
            ->exists();
    }

    // Check if product is an option
    public static function isOption($productId)
    {
        return self::where('product_id', $productId)
            ->where('type', 'option')
            ->exists();
    }
}