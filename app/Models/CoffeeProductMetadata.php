<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class CoffeeProductMetadata extends Model
{
    protected $table = 'coffee_product_metadata';

    /**
     * New metadata implies the product should feed the KDS, so put it on the
     * kds_products allow-list too. Without this the two lists drift apart and
     * the product silently never appears on /kds. Failures (POS unreachable)
     * are logged, never surfaced: the metadata row is still worth saving.
     */
    protected static function booted(): void
    {
        static::created(function (self $metadata) {
            try {
                $result = KdsProduct::ensureListed($metadata->product_id);

                if (in_array($result['action'], ['created', 'reactivated'], true)) {
                    Log::info('KDS allow-list updated from coffee metadata', [
                        'product_id' => $metadata->product_id,
                        'product_name' => $metadata->product_name,
                        'action' => $result['action'],
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('Could not auto-add product to KDS allow-list', [
                    'product_id' => $metadata->product_id,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    protected $fillable = [
        'product_id',
        'product_name',
        'type',
        'short_name',
        'group_name',
        'badge_kind',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * The twelve modifier badge kinds of ModifierBadge.dc.html in the Claude
     * Design project "KDS Modifier Icons"
     * (47420d2a-5362-4f13-bc11-44b10205f5bc). `family` selects the shape and
     * the CSS placement class; `group` is the optgroup label on
     * /coffee/metadata. Insertion order is the picker order — keep it.
     *
     * @var array<string, array{family: string, group: string}>
     */
    public const BADGE_KINDS = [
        'ice' => ['family' => 'ice', 'group' => 'Temperature'],
        'oat' => ['family' => 'milk', 'group' => 'Milk'],
        'almond' => ['family' => 'milk', 'group' => 'Milk'],
        'soy' => ['family' => 'milk', 'group' => 'Milk'],
        'coconut' => ['family' => 'milk', 'group' => 'Milk'],
        'whole' => ['family' => 'milk', 'group' => 'Milk'],
        'caramel' => ['family' => 'syrup', 'group' => 'Syrups'],
        'vanilla' => ['family' => 'syrup', 'group' => 'Syrups'],
        'hazelnut' => ['family' => 'syrup', 'group' => 'Syrups'],
        'mocha' => ['family' => 'syrup', 'group' => 'Syrups'],
        'shot' => ['family' => 'shot', 'group' => 'Espresso'],
        'decaf' => ['family' => 'shot', 'group' => 'Espresso'],
    ];

    /**
     * Decorations per badge family: which sprite symbol to <use> and the inline
     * style that positions it, copied from ModifierBadge.dc.html. Shared by
     * resources/views/components/kds/modifier-badge.blade.php and by
     * window.kdsModifierBadgeHtml() (see kds/_modifier-badge-assets.blade.php)
     * so the server-rendered and JS-rendered badges cannot drift.
     *
     * @var array<string, array<int, array{symbol: string, style: string}>>
     */
    public const BADGE_DECOS = [
        'ice' => [
            ['symbol' => 'mb-deco-cube', 'style' => 'width:0.95em;height:0.95em;top:-0.5em;right:-0.4em;transform:rotate(18deg)'],
            ['symbol' => 'mb-deco-cube-plain', 'style' => 'width:0.65em;height:0.65em;bottom:-0.35em;left:-0.35em;transform:rotate(-16deg)'],
        ],
        'milk' => [
            ['symbol' => 'mb-deco-dot-a', 'style' => 'width:0.42em;height:0.42em;right:-0.62em;bottom:0.05em'],
            ['symbol' => 'mb-deco-dot-b', 'style' => 'width:0.24em;height:0.24em;right:-0.78em;bottom:0.52em'],
            ['symbol' => 'mb-deco-dot-b', 'style' => 'width:0.28em;height:0.28em;left:-0.5em;top:-0.2em'],
        ],
        'syrup' => [
            ['symbol' => 'mb-deco-drop', 'style' => 'width:0.34em;height:0.48em;right:calc(29% - 0.17em);bottom:-1.35em'],
        ],
        'shot' => [],
    ];

    /**
     * The badge family (shape) for a kind, or null when the kind is null or
     * not one of BADGE_KINDS — callers render the plain chip in that case.
     */
    public static function badgeFamily(?string $kind): ?string
    {
        return self::BADGE_KINDS[$kind]['family'] ?? null;
    }

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
