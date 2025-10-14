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
        ];
    }

    /**
     * Retrieve the special groups for a supplier.
     *
     * @param  string|null  $supplierId
     * @param  string|null  $supplierName
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
     * Map suppliers to their groups for easy view consumption.
     *
     * @param  iterable  $suppliers
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
     * @param  string  $supplierName
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
