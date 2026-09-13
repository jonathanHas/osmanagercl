<?php

namespace Tests\Feature;

use App\Models\CustomerRequest;
use App\Models\CustomerRequestItem;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The legacy delivery match / scan screen flags products that are on an open
 * customer request so staff put them aside on arrival.
 */
class CustomerRequestDeliveryFlagTest extends TestCase
{
    use RefreshDatabase;

    private const DELIVERY_ID = 'd-1';

    private const SUPPLIER_ID = '999';

    private const BARCODE = '5000000000017';

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension is required.');
        }

        parent::setUp();

        Config::set('database.connections.pos', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        DB::purge('pos');

        $pos = DB::connection('pos')->getSchemaBuilder();

        // Every table the legacy match page's raw POS queries touch.
        $pos->create('PRODUCTS', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('NAME')->nullable();
            $table->string('CODE')->unique();
            $table->string('REFERENCE')->nullable();
            $table->string('CATEGORY')->nullable();
            $table->string('TAXCAT')->nullable();
            $table->decimal('PRICEBUY', 10, 4)->default(0);
            $table->decimal('PRICESELL', 10, 4)->default(0);
        });
        $pos->create('CATEGORIES', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('NAME')->nullable();
        });
        $pos->create('TAXES', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('CATEGORY')->nullable();
            $table->decimal('RATE', 8, 6)->default(0);
        });
        $pos->create('STOCKCURRENT', function (Blueprint $table) {
            $table->string('PRODUCT');
            $table->decimal('UNITS', 10, 2)->default(0);
        });
        $pos->create('suppliers', function (Blueprint $table) {
            $table->string('SupplierID')->primary();
            $table->string('Supplier')->nullable();
        });
        $pos->create('supplier_link', function (Blueprint $table) {
            $table->string('Barcode');
            $table->string('SupplierCode')->nullable();
            $table->string('SupplierID')->nullable();
            $table->integer('CaseUnits')->default(1);
            $table->string('OuterCode')->nullable();
        });
        $pos->create('delivery', function (Blueprint $table) {
            $table->increments('id');
            $table->string('prodName')->nullable();
            $table->string('supCode')->nullable();
            $table->decimal('rrPrice', 10, 2)->nullable();
            $table->decimal('cost', 10, 4)->nullable();
            $table->decimal('myOrder', 10, 3)->default(0);
            $table->integer('caseUnits')->default(1);
            $table->string('orderNumber')->nullable();
        });
        $pos->create('deliveriesScan', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->integer('status')->default(0);
        });
        $pos->create('deliveriesScanItems', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('delID');
            $table->string('barcode');
            $table->decimal('quantity', 10, 3)->default(0);
            $table->dateTime('dateScan')->nullable();
        });

        DB::connection('pos')->table('PRODUCTS')->insert([
            'ID' => 'p1', 'NAME' => 'Organic Oat Milk 1L', 'CODE' => self::BARCODE, 'CATEGORY' => 'c1', 'TAXCAT' => '001', 'PRICEBUY' => 1.2, 'PRICESELL' => 2.1,
        ]);
        DB::connection('pos')->table('CATEGORIES')->insert(['ID' => 'c1', 'NAME' => 'Dairy Alternatives']);
        DB::connection('pos')->table('TAXES')->insert(['ID' => 't1', 'CATEGORY' => '001', 'RATE' => 0]);
        DB::connection('pos')->table('STOCKCURRENT')->insert(['PRODUCT' => 'p1', 'UNITS' => 3]);
        DB::connection('pos')->table('suppliers')->insert(['SupplierID' => self::SUPPLIER_ID, 'Supplier' => 'Test Supplier']);
        DB::connection('pos')->table('supplier_link')->insert([
            'Barcode' => self::BARCODE, 'SupplierCode' => 'SUP-OAT', 'SupplierID' => self::SUPPLIER_ID, 'CaseUnits' => 6,
        ]);
        DB::connection('pos')->table('delivery')->insert([
            'prodName' => 'OAT MILK ORG 1L', 'supCode' => 'SUP-OAT', 'rrPrice' => 2.1, 'cost' => 1.2, 'myOrder' => 1, 'caseUnits' => 6,
        ]);
        DB::connection('pos')->table('deliveriesScan')->insert(['ID' => self::DELIVERY_ID, 'status' => 0]);

        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $this->actingAs(User::factory()->create(['role_id' => $role->id]));
    }

    private function requestFor(string $customer, string $status): CustomerRequestItem
    {
        $request = CustomerRequest::factory()->create(['customer_name' => $customer, 'wanted_on' => today()->addDays(2)]);

        return CustomerRequestItem::factory()
            ->for($request, 'request')
            ->forProduct(self::BARCODE, 'Organic Oat Milk 1L')
            ->status($status)
            ->create(['quantity' => 2]);
    }

    private function matchPage()
    {
        return $this->get(route('delivery-legacy.match', ['delID' => self::DELIVERY_ID, 'supplierID' => self::SUPPLIER_ID]));
    }

    public function test_match_page_flags_products_on_an_open_request(): void
    {
        $this->requestFor('Jane Doe', CustomerRequestItem::STATUS_ORDERED);
        $this->requestFor('Collected Colin', CustomerRequestItem::STATUS_COLLECTED);

        $response = $this->matchPage();

        $response->assertOk();
        $response->assertSee('Put aside for customer requests (1)');
        $response->assertSee('Jane Doe');
        $response->assertDontSee('Collected Colin');
    }

    public function test_match_page_has_no_flag_without_requests(): void
    {
        $response = $this->matchPage();

        $response->assertOk();
        $response->assertDontSee('Put aside for customer requests');
        $response->assertDontSee('Put aside for');
    }

    public function test_scan_increment_returns_open_request_lines(): void
    {
        $item = $this->requestFor('Jane Doe', CustomerRequestItem::STATUS_PENDING);
        $this->requestFor('Put-aside Pat', CustomerRequestItem::STATUS_PUT_ASIDE);

        $response = $this->postJson(route('delivery-legacy.scan-increment'), [
            'delID' => self::DELIVERY_ID,
            'barcode' => self::BARCODE,
            'quantity' => 1,
            'supplierID' => self::SUPPLIER_ID,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonCount(1, 'customerRequests');
        $response->assertJsonPath('customerRequests.0.id', $item->id);
        $response->assertJsonPath('customerRequests.0.customer_name', 'Jane Doe');
        $response->assertJsonPath('customerRequests.0.quantity', 2);
        $response->assertJsonPath('customerRequests.0.status', 'pending');
    }

    public function test_scan_increment_returns_empty_list_for_unrequested_product(): void
    {
        $this->postJson(route('delivery-legacy.scan-increment'), [
            'delID' => self::DELIVERY_ID,
            'barcode' => self::BARCODE,
            'quantity' => 1,
            'supplierID' => self::SUPPLIER_ID,
        ])->assertOk()->assertJsonCount(0, 'customerRequests');
    }
}
