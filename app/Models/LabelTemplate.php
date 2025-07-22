<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LabelTemplate extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'width_mm',
        'height_mm',
        'margin_mm',
        'font_size_name',
        'font_size_barcode',
        'font_size_price',
        'barcode_height',
        'layout_config',
        'is_default',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'layout_config' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Scope to get only active templates.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the default template.
     */
    public static function getDefault()
    {
        return self::where('is_default', true)->where('is_active', true)->first()
            ?? self::where('is_active', true)->first();
    }

    /**
     * Calculate how many labels fit per A4 sheet.
     */
    public function getLabelsPerA4Attribute()
    {
        // A4 dimensions: 210mm x 297mm with 10mm margins = 190mm x 277mm usable
        $usableWidth = 190; // mm
        $usableHeight = 277; // mm

        $labelsPerRow = floor($usableWidth / $this->width_mm);
        $labelsPerColumn = floor($usableHeight / $this->height_mm);

        return $labelsPerRow * $labelsPerColumn;
    }

    /**
     * Get template dimensions as CSS.
     */
    public function getCssDimensionsAttribute()
    {
        return [
            'width' => $this->width_mm.'mm',
            'height' => $this->height_mm.'mm',
            'margin' => $this->margin_mm.'mm',
            'font_size_name' => $this->font_size_name.'pt',
            'font_size_barcode' => $this->font_size_barcode.'pt',
            'font_size_price' => $this->font_size_price.'pt',
            'barcode_height' => $this->barcode_height.'px',
        ];
    }
}
