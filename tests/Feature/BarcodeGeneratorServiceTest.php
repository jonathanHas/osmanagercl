<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\BarcodeGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Regression net for pulling the barcode helpers out of ProductController.
 * Barcode uniqueness is a global invariant across PRODUCTS.CODE, so these
 * assertions pin the behaviour the products page depends on.
 */
class BarcodeGeneratorServiceTest extends TestCase
{
    use RefreshDatabase;

    private BarcodeGeneratorService $service;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is not installed on this machine.');
        }

        parent::setUp();

        Schema::connection('pos')->create('PRODUCTS', function ($table) {
            $table->string('ID')->primary();
            $table->string('NAME')->nullable();
            $table->string('CODE')->nullable();
            $table->string('CATEGORY')->nullable();
            $table->decimal('PRICEBUY', 10, 4)->default(0);
            $table->decimal('PRICESELL', 10, 4)->default(0);
        });

        config([
            'barcode_patterns.categories' => [
                'GAPS' => ['name' => 'Gaps', 'ranges' => [[4000, 4999]], 'priority' => 'fill_gaps'],
                'INCR' => ['name' => 'Increment', 'ranges' => [[1000, 2999]], 'priority' => 'increment'],
            ],
            'barcode_patterns.settings' => [
                'max_search_range' => 200,
                'max_internal_code' => 99999,
                'default_start' => 1000,
                'generic_start' => 9000,
            ],
        ]);

        $this->service = new BarcodeGeneratorService;
    }

    protected function tearDown(): void
    {
        if (extension_loaded('pdo_sqlite')) {
            Schema::connection('pos')->dropIfExists('PRODUCTS');
        }

        parent::tearDown();
    }

    private function makeProduct(string $code, string $category): void
    {
        $product = new Product;
        $product->ID = 'p-'.$code;
        $product->NAME = 'Product '.$code;
        $product->CODE = $code;
        $product->CATEGORY = $category;
        $product->save();
    }

    public function test_an_empty_category_starts_at_the_beginning_of_its_range(): void
    {
        $this->assertSame('4000', $this->service->nextForConfiguredCategory('GAPS'));
        $this->assertSame('1000', $this->service->nextForConfiguredCategory('INCR'));
    }

    public function test_fill_gaps_finds_the_hole_in_the_sequence(): void
    {
        $this->makeProduct('4000', 'GAPS');
        $this->makeProduct('4001', 'GAPS');
        // 4002 is free
        $this->makeProduct('4003', 'GAPS');

        $this->assertSame('4002', $this->service->nextForConfiguredCategory('GAPS'));
    }

    public function test_increment_ignores_gaps_and_goes_past_the_highest_code(): void
    {
        $this->makeProduct('1000', 'INCR');
        // 1001 is free but increment must not reuse it
        $this->makeProduct('1002', 'INCR');

        $this->assertSame('1003', $this->service->nextForConfiguredCategory('INCR'));
    }

    public function test_a_code_used_by_another_category_is_skipped(): void
    {
        $this->makeProduct('4000', 'GAPS');
        $this->makeProduct('4001', 'GAPS');
        $this->makeProduct('4003', 'GAPS');

        // The gap at 4002 is taken by a product in a different category -
        // barcodes are globally unique, so it is not available.
        $this->makeProduct('4002', 'SOMETHING_ELSE');

        $this->assertSame('4004', $this->service->nextForConfiguredCategory('GAPS'));
    }

    public function test_an_unconfigured_category_has_no_configured_code(): void
    {
        $this->assertNull($this->service->nextForConfiguredCategory('NOT_CONFIGURED'));
    }

    public function test_an_unconfigured_category_falls_back_to_the_generic_band(): void
    {
        $this->assertSame('9000', $this->service->nextForCategory('NOT_CONFIGURED'));
        $this->assertSame('9000', $this->service->nextForCategory(null));
    }

    public function test_the_generic_band_skips_codes_already_in_use(): void
    {
        $this->makeProduct('9000', 'ANY');
        $this->makeProduct('9001', 'ANY');

        $this->assertSame('9002', $this->service->nextGeneric());
    }

    public function test_the_generic_band_ignores_ean_barcodes_below_it(): void
    {
        // A real EAN sits far above the internal band but is not numericly
        // adjacent to it; short internal codes below the band are irrelevant.
        $this->makeProduct('4000', 'ANY');
        $this->makeProduct('5012345678900', 'ANY');

        $this->assertSame('9000', $this->service->nextGeneric());
    }

    public function test_range_membership(): void
    {
        $ranges = [[1000, 1999], [4000, 4999]];

        $this->assertTrue($this->service->isCodeInRange(1000, $ranges));
        $this->assertTrue($this->service->isCodeInRange(4999, $ranges));
        $this->assertFalse($this->service->isCodeInRange(2500, $ranges));
        $this->assertFalse($this->service->isCodeInRange(5000, $ranges));
    }
}
