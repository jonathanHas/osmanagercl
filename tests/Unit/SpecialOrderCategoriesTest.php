<?php

namespace Tests\Unit;

use App\Support\SpecialOrderCategories;
use Illuminate\Support\Collection;
use Tests\TestCase;

class SpecialOrderCategoriesTest extends TestCase
{
    public function test_for_supplier_returns_groups_for_independent(): void
    {
        $groups = SpecialOrderCategories::forSupplier('37');

        $this->assertArrayHasKey('refrigerated', $groups);

        $refrigerated = $groups['refrigerated'];
        $this->assertSame('Refrigerated', $refrigerated['label']);
        $this->assertSame(['002'], $refrigerated['category_codes']);
        $this->assertSame(5, $refrigerated['default_coverage_days']);
    }

    public function test_map_suppliers_includes_independent_supplier(): void
    {
        $suppliers = new Collection([
            (object) ['SupplierID' => 37, 'Supplier' => 'Independent Health Foods'],
            (object) ['SupplierID' => 99, 'Supplier' => 'Another Supplier'],
        ]);

        $mapped = SpecialOrderCategories::mapSuppliers($suppliers);

        $this->assertArrayHasKey('37', $mapped);
        $this->assertArrayHasKey('refrigerated', $mapped['37']);
        $this->assertArrayNotHasKey('99', $mapped); // Supplier without config should be excluded
    }
}
