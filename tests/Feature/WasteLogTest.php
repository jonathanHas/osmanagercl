<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WasteLog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WasteLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // In-memory POS connection with the tables the waste endpoints touch.
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
            $table->string('CATEGORY')->nullable();
            $table->decimal('PRICESELL', 10, 4)->default(0);
            $table->string('TAXCAT')->nullable();
        });

        $pos->create('vegDetails', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('product')->nullable();
            $table->integer('countryCode')->nullable();
            $table->string('classId')->nullable();
            $table->string('unitId')->nullable();
        });

        // Needed by Product::getGrossPrice() (tax hasOneThrough).
        $pos->create('TAXCATEGORIES', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('NAME')->nullable();
        });

        $pos->create('TAXES', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('CATEGORY')->nullable();
            $table->decimal('RATE', 8, 6)->default(0);
        });

        // WasteLog is pinned to the 'mysql' connection; point it at an
        // in-memory sqlite DB and create its table there.
        Config::set('database.connections.mysql', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        DB::purge('mysql');

        DB::connection('mysql')->getSchemaBuilder()->create('fv_waste_logs', function (Blueprint $table) {
            $table->id();
            $table->date('waste_date');
            $table->string('product_code');
            $table->string('product_name');
            $table->decimal('quantity', 10, 2);
            $table->string('unit', 20)->default('kg');
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->decimal('value', 10, 2)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique(['waste_date', 'product_code']);
        });
    }

    private function createProduct(string $code = '2243', string $category = 'SUB1', float $priceSell = 4.20): void
    {
        DB::connection('pos')->table('PRODUCTS')->insert([
            'ID' => 'PRODUCT-'.$code,
            'NAME' => 'Test Apples',
            'CODE' => $code,
            'CATEGORY' => $category,
            'PRICESELL' => $priceSell,
        ]);
    }

    public function test_entry_requires_authentication(): void
    {
        $response = $this->postJson(route('fruit-veg.waste.entry'), [
            'date' => '2026-06-06',
            'product_code' => '2243',
            'quantity' => 1.5,
            'unit' => 'kg',
        ]);

        $response->assertStatus(401);
    }

    public function test_entry_creates_a_waste_row_with_value_snapshot(): void
    {
        $this->createProduct();

        $response = $this->actingAs(User::factory()->create())
            ->postJson(route('fruit-veg.waste.entry'), [
                'date' => '2026-06-06',
                'product_code' => '2243',
                'quantity' => 1.5,
                'unit' => 'kg',
            ]);

        $response->assertOk()->assertJson(['saved' => true, 'quantity' => 1.5, 'unit' => 'kg']);

        $entry = WasteLog::where('product_code', '2243')->first();
        $this->assertNotNull($entry);
        $this->assertSame('Test Apples', $entry->product_name);
        $this->assertEquals(1.5, (float) $entry->quantity);
        // No vegDetails row -> priced unit defaults to kg, so value is computed.
        $this->assertNotNull($entry->value);
        $this->assertEquals(round(1.5 * $entry->unit_price, 2), (float) $entry->value);
    }

    public function test_entry_updates_the_same_day_row_instead_of_duplicating(): void
    {
        $this->createProduct();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('fruit-veg.waste.entry'), [
            'date' => '2026-06-06', 'product_code' => '2243', 'quantity' => 1.5, 'unit' => 'kg',
        ])->assertOk();

        $this->actingAs($user)->postJson(route('fruit-veg.waste.entry'), [
            'date' => '2026-06-06', 'product_code' => '2243', 'quantity' => 3, 'unit' => 'kg',
        ])->assertOk();

        $this->assertSame(1, WasteLog::count());
        $this->assertEquals(3.0, (float) WasteLog::first()->quantity);
    }

    public function test_zero_quantity_deletes_the_entry(): void
    {
        $this->createProduct();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('fruit-veg.waste.entry'), [
            'date' => '2026-06-06', 'product_code' => '2243', 'quantity' => 2, 'unit' => 'kg',
        ])->assertOk();

        $this->actingAs($user)->postJson(route('fruit-veg.waste.entry'), [
            'date' => '2026-06-06', 'product_code' => '2243', 'quantity' => 0, 'unit' => 'kg',
        ])->assertOk()->assertJson(['deleted' => true]);

        $this->assertSame(0, WasteLog::count());
    }

    public function test_value_is_null_when_logged_in_a_non_priced_unit(): void
    {
        $this->createProduct(); // priced unit defaults to kg

        $this->actingAs(User::factory()->create())->postJson(route('fruit-veg.waste.entry'), [
            'date' => '2026-06-06', 'product_code' => '2243', 'quantity' => 4, 'unit' => 'unit',
        ])->assertOk()->assertJson(['saved' => true, 'value' => null]);

        $this->assertNull(WasteLog::first()->value);
    }

    public function test_unknown_product_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())->postJson(route('fruit-veg.waste.entry'), [
            'date' => '2026-06-06', 'product_code' => 'NOPE', 'quantity' => 1, 'unit' => 'kg',
        ])->assertStatus(422);
    }

    public function test_non_fruit_veg_product_is_rejected(): void
    {
        $this->createProduct('9999', 'COFFEE');

        $this->actingAs(User::factory()->create())->postJson(route('fruit-veg.waste.entry'), [
            'date' => '2026-06-06', 'product_code' => '9999', 'quantity' => 1, 'unit' => 'kg',
        ])->assertStatus(422);

        $this->assertSame(0, WasteLog::count());
    }
}
