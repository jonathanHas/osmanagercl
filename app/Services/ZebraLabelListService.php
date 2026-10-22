<?php

namespace App\Services;

use App\Models\Country;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\VegDetails;
use App\Models\ZebraLabel;
use Illuminate\Support\Collection;

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

        $countries = $this->countries();

        $standAloneLabels = $standAloneQuery->get()
            ->map(fn ($label) => $this->labelData($label, null, $countries))
            ->values()
            ->toArray();

        foreach ($allZebraLabels as $label) {
            $product = $label->product;
            if (! $product) {
                continue;
            }

            $labelData = $this->labelData($label, $product, $countries);

            if ($this->tillVisibilityService->isVisibleOnTill($product->ID)) {
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
     * Set a label's country of origin: the label's country text (when it has one)
     * and the product's till record (when it has a product). Returns the label's
     * refreshed list array (same shape as grouped() items).
     *
     * The till record is written through VegDetails::upsertForProduct(), as the
     * F&V page does, but without its shelf label reprint: a bagged line gets a
     * Zebra label, not a shelf label.
     */
    public function setCountry(ZebraLabel $label, Country $country): array
    {
        $countries = $this->countries();
        $fields = ZebraLabel::extractTextFields($label->zpl_content);
        $countryField = ZebraLabel::findCountryField($fields, $countries->pluck('name')->all());

        if ($countryField) {
            $label->zpl_content = ZebraLabel::replaceTextFields($label->zpl_content, [$countryField[0] => $country->name]);
            $label->save();
        }

        $product = $label->product_code ? $label->product : null;

        if ($product) {
            VegDetails::upsertForProduct($label->product_code, ['countryCode' => $country->id]);
            $product->unsetRelation('vegDetails');
        }

        return $this->labelData($label->fresh(), $product, $countries);
    }

    /**
     * The group grouped() puts a label in: 'till', 'other' or 'standalone'.
     */
    public function groupOf(ZebraLabel $label): string
    {
        $product = $label->product_code ? $label->product : null;

        if (! $product) {
            return 'standalone';
        }

        return $this->tillVisibilityService->isVisibleOnTill($product->ID) ? 'till' : 'other';
    }

    /**
     * A grouped() item as the Shop mode Zebra tab reads it. The ZPL and its text
     * fields stay on the server; each item carries its own print and country URLs.
     */
    public function shopItem(array $label, string $group): array
    {
        return [
            'kind' => 'zebra',
            'group' => $group,
            'id' => $label['id'],
            'name' => $label['product_name'] ?? $label['name'],
            // Standalone labels have no product, so their name is the title.
            'label' => $group === 'standalone' ? null : $label['name'],
            'product_code' => $label['product_code'],
            'width_mm' => $label['width_mm'],
            'height_mm' => $label['height_mm'],
            'default_copies' => (int) ($label['default_copies'] ?? 1),
            'mismatches' => $label['mismatches'],
            'country' => $label['country'],
            'print_url' => route('zebra-labels.print', $label['id']),
            'country_url' => route('zebra-labels.set-country', $label['id']),
        ];
    }

    /**
     * One label's list array. Standalone labels ($product null) carry no
     * mismatches, but their country text is still detected.
     */
    private function labelData(ZebraLabel $label, ?Product $product, Collection $countries): array
    {
        $fields = ZebraLabel::extractTextFields($label->zpl_content);
        $countryField = ZebraLabel::findCountryField($fields, $countries->pluck('name')->all());

        $country = [
            'label' => $countryField[1] ?? null,
            'label_id' => $countryField ? $countries->firstWhere('name', $countryField[1])?->id : null,
            'field_index' => $countryField[0] ?? null,
            'till' => null,
            'till_id' => null,
        ];

        if (! $product) {
            return [
                'id' => $label->id,
                'name' => $label->name,
                'product_name' => null,
                'product_code' => null,
                'width_mm' => $label->label_width_mm,
                'height_mm' => $label->label_height_mm,
                'default_copies' => $label->default_copies ?? 1,
                'mismatches' => null,
                'fields' => $fields,
                'country' => $country,
            ];
        }

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
        $dbCountry = $product->vegDetails?->country?->name;
        $country['till'] = $dbCountry;
        $country['till_id'] = $product->vegDetails?->country?->id;

        if ($countryField && $dbCountry && $countryField[1] !== $dbCountry) {
            $mismatches['country'] = [
                'field_index' => $countryField[0],
                'label_value' => $countryField[1],
                'db_value' => $dbCountry,
                'new_field' => $dbCountry,
            ];
        }

        return [
            'id' => $label->id,
            'name' => $label->name,
            'product_name' => $product->NAME,
            'product_code' => $label->product_code,
            'width_mm' => $label->label_width_mm,
            'height_mm' => $label->label_height_mm,
            'default_copies' => $label->default_copies ?? 1,
            'mismatches' => $mismatches ?: null,
            'fields' => $fields,
            'country' => $country,
        ];
    }

    /**
     * Country names and ids, read once per call.
     */
    private function countries(): Collection
    {
        return Country::orderBy('name')->get(['id', 'name']);
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
