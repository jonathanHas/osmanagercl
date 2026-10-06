<?php

namespace App\Support;

use Illuminate\Support\Str;

class SpecialOrderCategories
{
    /**
     * Definition of suppliers with grouped category handling.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function definitions(): array
    {
        return [
            'udea' => [
                'supplier_ids' => ['5', '44', '85'],
                'name_contains' => ['udea'],
                'groups' => [
                    'cheese' => [
                        'label' => 'Cheese',
                        'category_codes' => ['032'],
                        'default_coverage_days' => 7,
                    ],
                    'refrigerated' => [
                        'label' => 'Refrigerated',
                        'category_codes' => ['002'],
                        'default_coverage_days' => 5,
                    ],
                ],
            ],
            'independent' => [
                'supplier_ids' => ['37'],
                'name_contains' => ['independent'],
                'groups' => [
                    'refrigerated' => [
                        'label' => 'Refrigerated',
                        'category_codes' => ['002'],
                        'default_coverage_days' => 5,
                    ],
                ],
            ],
        ];
    }

    /**
     * Retrieve the special groups for a supplier.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function forSupplier(?string $supplierId, ?string $supplierName = null): array
    {
        if ($supplierId === null && $supplierName === null) {
            return [];
        }

        foreach (self::definitions() as $definition) {
            if (
                ($supplierId !== null && in_array((string) $supplierId, $definition['supplier_ids'], true))
                || ($supplierName !== null && self::nameMatches($supplierName, $definition['name_contains'] ?? []))
            ) {
                return $definition['groups'] ?? [];
            }
        }

        return [];
    }

    /**
     * Every special group across all suppliers, keyed by group key in first-seen
     * order, each with its label and the union of its category codes.
     *
     * Used by the Shop order review to group chilled lines together whatever the
     * supplier; the per-supplier definitions above stay the office's rule for
     * coverage overrides.
     *
     * @return array<string, array{label: string, category_codes: array<int, string>}>
     */
    public static function displayGroups(): array
    {
        $groups = [];

        foreach (self::definitions() as $definition) {
            foreach ($definition['groups'] ?? [] as $key => $group) {
                $groups[$key] ??= ['label' => $group['label'], 'category_codes' => []];
                $groups[$key]['category_codes'] = array_values(array_unique(array_merge(
                    $groups[$key]['category_codes'],
                    $group['category_codes'] ?? []
                )));
            }
        }

        return $groups;
    }

    /**
     * Map suppliers to their groups for easy view consumption.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function mapSuppliers(iterable $suppliers): array
    {
        $mapped = [];

        foreach ($suppliers as $supplier) {
            $id = (string) ($supplier->SupplierID ?? '');
            $name = (string) ($supplier->Supplier ?? '');
            $groups = self::forSupplier($id, $name);

            if (! empty($groups)) {
                $mapped[$id] = $groups;
            }
        }

        return $mapped;
    }

    /**
     * Resolve a human readable label for a group key.
     */
    public static function labelForGroup(string $groupKey, array $groups): string
    {
        if (isset($groups[$groupKey]['label'])) {
            return $groups[$groupKey]['label'];
        }

        return Str::headline($groupKey);
    }

    /**
     * Determine whether the supplier name matches any of the provided fragments.
     *
     * @param  array<int, string>  $fragments
     */
    protected static function nameMatches(string $supplierName, array $fragments): bool
    {
        if (empty($fragments)) {
            return false;
        }

        $normalisedName = Str::lower($supplierName);

        foreach ($fragments as $fragment) {
            if (Str::contains($normalisedName, Str::lower($fragment))) {
                return true;
            }
        }

        return false;
    }
}
