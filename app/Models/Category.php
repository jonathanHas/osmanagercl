<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
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
    protected $table = 'CATEGORIES';

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
        'NAME',
        'PARENTID',
        'TEXTTIP',
        'CATSHOWNAME',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'CATSHOWNAME' => 'boolean',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'IMAGE',
    ];

    /**
     * Get the parent category.
     */
    public function parent()
    {
        return $this->belongsTo(Category::class, 'PARENTID', 'ID');
    }

    /**
     * Get the child categories.
     */
    public function children()
    {
        return $this->hasMany(Category::class, 'PARENTID', 'ID');
    }

    /**
     * Get all products in this category.
     */
    public function products()
    {
        return $this->hasMany(Product::class, 'CATEGORY', 'ID');
    }

    /**
     * Scope a query to only include categories that should be shown.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeVisible($query)
    {
        return $query->where('CATSHOWNAME', 1);
    }

    /**
     * Scope a query to only include root categories (no parent).
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRoot($query)
    {
        return $query->whereNull('PARENTID');
    }

    /**
     * Search categories by name.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where('NAME', 'like', '%'.$search.'%');
    }

    /**
     * Check if this category has child categories.
     */
    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    /**
     * Check if this category is a root category (no parent).
     */
    public function isRoot(): bool
    {
        return is_null($this->PARENTID);
    }

    /**
     * Get the full category path (parent names joined).
     */
    public function getFullPathAttribute(): string
    {
        $path = [];
        $category = $this;

        while ($category) {
            array_unshift($path, $category->NAME);
            $category = $category->parent;
        }

        return implode(' > ', $path);
    }

    /**
     * Get all ancestor categories.
     */
    public function ancestors()
    {
        $ancestors = collect();
        $category = $this->parent;

        while ($category) {
            $ancestors->push($category);
            $category = $category->parent;
        }

        return $ancestors;
    }

    /**
     * Get all descendant categories recursively.
     */
    public function descendants()
    {
        $descendants = collect();
        
        foreach ($this->children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->descendants());
        }

        return $descendants;
    }
}