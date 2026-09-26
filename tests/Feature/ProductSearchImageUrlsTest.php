<?php

namespace Tests\Feature;

use App\Services\ProductSearch\ProductSearchService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `ProductSearchService::imageUrlsByCode()` — the shared picture resolver.
 *
 * Pages that already hold product codes (a customer request's lines, an order)
 * use this so the picture they show is the same one the staff member saw in the
 * product search. Its rules are `imageUrl()`'s, unchanged; what is tested here is
 * that the batched entry point gives the same answers.
 */
class ProductSearchImageUrlsTest extends TestCase
{
    use RefreshDatabase;

    private const UDEA = 5;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.connections.pos', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        DB::purge('pos');

        $pos = DB::connection('pos')->getSchemaBuilder();

        $pos->create('PRODUCTS', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('NAME')->nullable();
            $table->string('CODE')->unique();
            $table->string('REFERENCE')->nullable();
            $table->string('CATEGORY')->nullable();
            $table->decimal('PRICESELL', 10, 4)->default(0);
            $table->decimal('PRICEBUY', 10, 4)->default(0);
            $table->string('TAXCAT')->nullable();
            $table->boolean('ISSERVICE')->default(false);
            $table->string('DISPLAY')->nullable();
            $table->binary('IMAGE')->nullable();
        });

        $pos->create('supplier_link', function (Blueprint $table) {
            $table->string('Barcode');
            $table->string('SupplierCode')->nullable();
            $table->string('SupplierID')->nullable();
        });
    }

    private function product(string $id, string $code, ?string $image = null): void
    {
        DB::connection('pos')->table('PRODUCTS')->insert([
            'ID' => $id,
            'NAME' => 'Product '.$code,
            'CODE' => $code,
            'REFERENCE' => 'REF-'.$code,
            'CATEGORY' => 'c1',
            'PRICESELL' => 1.0,
            'TAXCAT' => '001',
            'IMAGE' => $image,
        ]);
    }

    private function linkTo(string $code, int $supplierId, ?string $supplierCode): void
    {
        DB::connection('pos')->table('supplier_link')->insert([
            'Barcode' => $code,
            'SupplierCode' => $supplierCode,
            'SupplierID' => (string) $supplierId,
        ]);
    }

    private function service(): ProductSearchService
    {
        return app(ProductSearchService::class);
    }

    public function test_a_product_with_a_pos_photo_gets_the_image_route(): void
    {
        $this->product('p1', '5000000000017', 'fake-jpeg-bytes');

        $urls = $this->service()->imageUrlsByCode(['5000000000017']);

        $this->assertSame(route('products.image', 'p1'), $urls['5000000000017']);
    }

    public function test_a_product_with_no_photo_and_no_supplier_gets_null(): void
    {
        $this->product('p2', '5000000000024');

        $this->assertNull($this->service()->imageUrlsByCode(['5000000000024'])['5000000000024']);
    }

    public function test_an_empty_blob_is_not_a_photo(): void
    {
        // The has_image expression checks LENGTH > 0, not just NOT NULL.
        $this->product('p3', '5000000000031', '');

        $this->assertNull($this->service()->imageUrlsByCode(['5000000000031'])['5000000000031']);
    }

    public function test_a_supplier_with_a_barcode_template_gets_the_cdn_url(): void
    {
        Config::set('suppliers.external_links', [
            'udea' => [
                'supplier_ids' => [self::UDEA],
                'enabled' => true,
                'image_url' => 'https://cdn.example/{CODE}.jpg',
            ],
        ]);

        $this->product('p4', '8712345678901');
        $this->linkTo('8712345678901', self::UDEA, 'SUP-1');

        $url = $this->service()->imageUrlsByCode(['8712345678901'])['8712345678901'];

        // No {SUPPLIER_CODE} in the template, so the barcode route is used.
        $this->assertSame('https://cdn.example/8712345678901.jpg', $url);
    }

    public function test_a_supplier_that_keys_images_by_supplier_code_uses_the_template(): void
    {
        Config::set('suppliers.external_links', [
            'udea' => [
                'supplier_ids' => [self::UDEA],
                'enabled' => true,
                'image_url' => 'https://cdn.example/sup/{SUPPLIER_CODE}.jpg',
            ],
        ]);

        $this->product('p5', '8712345678918');
        $this->linkTo('8712345678918', self::UDEA, 'AB-1234');

        $this->assertSame(
            'https://cdn.example/sup/AB-1234.jpg',
            $this->service()->imageUrlsByCode(['8712345678918'])['8712345678918']
        );
    }

    public function test_a_disabled_integration_gets_null(): void
    {
        Config::set('suppliers.external_links', [
            'udea' => [
                'supplier_ids' => [self::UDEA],
                'enabled' => false,
                'image_url' => 'https://cdn.example/sup/{SUPPLIER_CODE}.jpg',
            ],
        ]);

        $this->product('p6', '8712345678925');
        $this->linkTo('8712345678925', self::UDEA, 'AB-9');

        $this->assertNull($this->service()->imageUrlsByCode(['8712345678925'])['8712345678925']);
    }

    public function test_a_pos_photo_wins_over_a_supplier_picture(): void
    {
        Config::set('suppliers.external_links', [
            'udea' => [
                'supplier_ids' => [self::UDEA],
                'enabled' => true,
                'image_url' => 'https://cdn.example/{CODE}.jpg',
            ],
        ]);

        $this->product('p7', '8712345678932', 'fake-jpeg-bytes');
        $this->linkTo('8712345678932', self::UDEA, 'AB-7');

        $this->assertSame(
            route('products.image', 'p7'),
            $this->service()->imageUrlsByCode(['8712345678932'])['8712345678932']
        );
    }

    public function test_an_unknown_code_is_absent_rather_than_null(): void
    {
        $this->product('p1', '5000000000017', 'bytes');

        $urls = $this->service()->imageUrlsByCode(['5000000000017', 'no-such-code']);

        $this->assertArrayHasKey('5000000000017', $urls);
        $this->assertArrayNotHasKey('no-such-code', $urls);
    }

    public function test_an_empty_list_runs_no_query(): void
    {
        DB::connection('pos')->enableQueryLog();

        $this->assertSame([], $this->service()->imageUrlsByCode([]));
        $this->assertSame([], $this->service()->imageUrlsByCode([null, '']));

        $this->assertSame([], DB::connection('pos')->getQueryLog());
    }

    public function test_duplicate_codes_are_queried_once(): void
    {
        $this->product('p1', '5000000000017', 'bytes');

        DB::connection('pos')->enableQueryLog();
        $urls = $this->service()->imageUrlsByCode(['5000000000017', '5000000000017', '5000000000017']);

        $this->assertCount(1, $urls);
        // One products query plus the eager-loaded supplierLink — not one per code.
        $this->assertLessThanOrEqual(2, count(DB::connection('pos')->getQueryLog()));
    }

    public function test_the_blob_never_leaves_the_database(): void
    {
        $this->product('p1', '5000000000017', 'fake-jpeg-bytes');

        DB::connection('pos')->enableQueryLog();
        $this->service()->imageUrlsByCode(['5000000000017']);

        // IMAGE is a mediumblob; selecting it would pull every photo into PHP.
        foreach (DB::connection('pos')->getQueryLog() as $entry) {
            $this->assertStringNotContainsString('PRODUCTS.*', $entry['query']);
            $this->assertStringNotContainsString('"IMAGE"', $entry['query']);
        }
    }
}
