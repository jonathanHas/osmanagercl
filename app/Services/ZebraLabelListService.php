<?php

namespace App\Services;

use App\Models\Country;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\ZebraLabel;

/**
 * The saved Zebra labels and translated labels, as lists to print from.
 *
 * Moved out of LabelAreaController::zebra() so the office Zebra page and the Shop
 * mode Zebra tab group labels and work out price / country mismatches through
 * one implementation. The office page renders grouped(); the Shop tab reads both
 * methods as JSON (LabelAreaController::zebraList()).
 */
class ZebraLabelListService
{
    public function __construct(protected TillVisibilityService $tillVisibilityService) {}

    /**
     * Saved Zebra labels in the office page's three groups: product on the till,
     * product not on the till, and standalone (no product).
     *
     * Mismatches are computed for every product-linked label, on the till or not.
     *
     * @return array{onTill: array<int, array>, other: array<int, array>, standalone: array<int, array>}
     */
    public function grouped(?string $search = null): array
    {
        $zebraLabels = [];
        $otherLabels = [];

        $zebraLabelsQuery = ZebraLabel::active()->whereNotNull('product_code');

        if ($search) {
            $zebraLabelsQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('product_code', 'like', "%{$search}%");
            });
        }

        $allZebraLabels = $zebraLabelsQuery->get();

        $standAloneQuery = ZebraLabel::active()->whereNull('product_code');
        if ($search) {
            $standAloneQuery->where('name', 'like', "%{$search}%");
        }
        $standAloneLabels = $standAloneQuery->get()->map(fn ($label) => [
            'id' => $label->id,
            'name' => $label->name,
            'product_name' => null,
            'product_code' => null,
            'width_mm' => $label->label_width_mm,
            'height_mm' => $label->label_height_mm,
            'default_copies' => $label->default_copies ?? 1,
            'mismatches' => null,
            'fields' => ZebraLabel::extractTextFields($label->zpl_content),
        ])->values()->toArray();

        $countryNames = Country::pluck('name')->toArray();

        foreach ($allZebraLabels as $label) {
            $product = $label->product;
            if (! $product) {
                continue;
            }

            $isOnTill = $this->tillVisibilityService->isVisibleOnTill($product->ID);

            $fields = ZebraLabel::extractTextFields($label->zpl_content);
            $mismatches = [];

            $priceField = ZebraLabel::findPriceField($fields);
            if ($priceField) {
                $dbPrice = (float) $product->getGrossPrice();
                if (abs($priceField[1] - $dbPrice) > 0.005) {
                    $mismatches['price'] = [
                        'field_index' => $priceField[0],
                        'label_value' => number_format($priceField[1], 2),
                        'db_value' => number_format($dbPrice, 2),
                        'new_field' => '\\15'.number_format($dbPrice, 2),
                    ];
                }
            }

            $product->load('vegDetails.country');
            $countryField = ZebraLabel::findCountryField($fields, $countryNames);
            $dbCountry = $product->vegDetails?->country?->name;
            if ($countryField && $dbCountry && $countryField[1] !== $dbCountry) {
                $mismatches['country'] = [
                    'field_index' => $countryField[0],
                    'label_value' => $countryField[1],
                    'db_value' => $dbCountry,
                    'new_field' => $dbCountry,
                ];
            }

            $labelData = [
                'id' => $label->id,
                'name' => $label->name,
                'product_name' => $product->NAME,
                'product_code' => $label->product_code,
                'width_mm' => $label->label_width_mm,
                'height_mm' => $label->label_height_mm,
                'default_copies' => $label->default_copies ?? 1,
                'mismatches' => $mismatches ?: null,
                'fields' => $fields,
            ];

            if ($isOnTill) {
                $zebraLabels[] = $labelData;
            } else {
                $otherLabels[] = $labelData;
            }
        }

        return [
            'onTill' => $zebraLabels,
            'other' => $otherLabels,
            'standalone' => $standAloneLabels,
        ];
    }

    /**
     * Translated labels: the newest translation per product code, sorted by name.
     *
     * The newest row is picked first and only then skipped when it has no ZPL,
     * so an older translation never stands in for a newer one (see
     * ProductTranslation::latestPerProductCode()). The ZPL itself is not returned.
     *
     * @return array<int, array{id: int, name: string, product_code: ?string, label_size: ?string, width_mm: mixed, height_mm: mixed, auto_print: bool}>
     */
    public function translated(): array
    {
        $translations = ProductTranslation::orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('product_code')
            ->map->first()
            ->filter(fn ($t) => filled($t->zpl_content))
            ->values();

        $codes = $translations->pluck('product_code')->filter()->unique()->values()->all();
        $products = $codes === []
            ? collect()
            : Product::whereIn('CODE', $codes)->get()->keyBy('CODE');

        return $translations->map(function ($t) use ($products) {
            $product = $products->get($t->product_code);
            $size = $t->label_size;

            return [
                'id' => $t->id,
                'name' => $t->label_data['product_name'] ?? $product?->NAME ?? $t->product_code,
                'product_code' => $t->product_code,
                'label_size' => $size,
                'width_mm' => config("label-sizes.$size.widthMm"),
                'height_mm' => config("label-sizes.$size.heightMm"),
                'auto_print' => (bool) $t->auto_print,
            ];
        })
            ->sortBy(fn ($item) => mb_strtolower((string) $item['name']))
            ->values()
            ->all();
    }
}
