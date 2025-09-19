<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Models\VegLabelPrintBatch;
use App\Models\VegPrintQueue;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FruitVegLabelPrintingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension is required for Fruit & Veg label printing tests.');
        }

        parent::setUp();

        Config::set('database.connections.pos', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        DB::purge('pos');

        $schema = DB::connection('pos')->getSchemaBuilder();

        $schema->create('PRODUCTS', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('NAME')->nullable();
            $table->string('CODE')->unique();
            $table->string('CATEGORY')->nullable();
            $table->decimal('PRICESELL', 8, 2)->default(0);
            $table->string('TAXCAT')->nullable();
        });
    }

    private function createProduct(string $id, string $code, string $name): Product
    {
        return Product::query()->create([
            'ID' => $id,
            'CODE' => $code,
            'NAME' => $name,
            'CATEGORY' => 'SUB1',
            'PRICESELL' => 2.50,
            'TAXCAT' => 'VAT0',
        ]);
    }

    public function test_print_labels_clears_queue_and_records_batch(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $productOne = $this->createProduct('P-100', '100', 'Conference Pears');
        $productTwo = $this->createProduct('P-200', '200', 'Pink Lady Apples');

        VegPrintQueue::addToQueue($productOne->CODE);
        VegPrintQueue::addToQueue($productTwo->CODE);

        $response = $this->post(route('fruit-veg.labels.print'));

        $response->assertOk()
            ->assertViewIs('fruit-veg.label-preview')
            ->assertViewHas('printedBatch');

        $this->assertSame(0, VegPrintQueue::count(), 'Print queue should be empty after printing.');

        $batch = VegLabelPrintBatch::first();
        $this->assertNotNull($batch, 'Printed batch should be recorded.');
        $this->assertSame(2, $batch->product_count);
        $this->assertEqualsCanonicalizing([$productOne->CODE, $productTwo->CODE], $batch->product_codes);
    }

    public function test_restore_last_printed_batch_requeues_products(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $productOne = $this->createProduct('P-100', '100', 'Conference Pears');
        $productTwo = $this->createProduct('P-200', '200', 'Pink Lady Apples');

        VegPrintQueue::addToQueue($productOne->CODE);
        VegPrintQueue::addToQueue($productTwo->CODE);

        $this->post(route('fruit-veg.labels.print'));

        $this->assertSame(0, VegPrintQueue::count(), 'Print queue should be empty before restoration.');

        $response = $this->post(route('fruit-veg.labels.restore-last'));

        $response->assertRedirect(route('fruit-veg.labels'));
        $response->assertSessionHas('success');

        $queuedCodes = VegPrintQueue::getQueuedProductCodes();
        $this->assertEqualsCanonicalizing([$productOne->CODE, $productTwo->CODE], $queuedCodes);

        $batch = VegLabelPrintBatch::first();
        $this->assertNotNull($batch->restored_at);
    }
}
