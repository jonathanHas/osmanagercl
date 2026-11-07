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
            // This test drives delivery-legacy.scan-increment, which since cycle 24
            // resolves a product picture; the resolver reads these columns.
            $table->string('DISPLAY')->nullable();
            $table->boolean('ISSERVICE')->default(false);
            $table->binary('IMAGE')->nullable();
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
            $table->string('delID', 64)->nullable();
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
            'delID' => self::DELIVERY_ID, 'prodName' => 'OAT MILK ORG 1L', 'supCode' => 'SUP-OAT', 'rrPrice' => 2.1, 'cost' => 1.2, 'myOrder' => 1, 'caseUnits' => 6,
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

    public function test_a_line_by_the_case_reads_as_cases_on_the_delivery_screens(): void
    {
        $request = CustomerRequest::factory()->create(['customer_name' => 'Jane Doe', 'wanted_on' => today()->addDays(2)]);
        CustomerRequestItem::factory()
            ->for($request, 'request')
            ->forProduct(self::BARCODE, 'Organic Oat Milk 1L')
            ->byTheCase(6)
            ->status(CustomerRequestItem::STATUS_ORDERED)
            ->create(['quantity' => 2]);

        $this->matchPage()->assertOk()->assertSee('2 cases of 6');
        $this->get(route('customer-requests.index'))->assertOk()->assertSee('Jane Doe &middot; 2 cases of 6', false);
        $this->get(route('customer-requests.show', $request))->assertOk()->assertSee('2 cases of 6');

        $this->postJson(route('delivery-legacy.scan-increment'), [
            'delID' => self::DELIVERY_ID,
            'barcode' => self::BARCODE,
            'quantity' => 1,
            'supplierID' => self::SUPPLIER_ID,
        ])->assertOk()
            ->assertJsonPath('customerRequests.0.quantity', 2)
            ->assertJsonPath('customerRequests.0.unit', 'case')
            ->assertJsonPath('customerRequests.0.case_units', 6)
            ->assertJsonPath('customerRequests.0.quantity_label', '2 cases of 6');
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

    public function test_a_line_that_is_just_to_be_got_in_is_not_flagged_for_putting_aside(): void
    {
        $request = CustomerRequest::factory()->create(['customer_name' => 'Shelf Sam', 'wanted_on' => today()->addDays(2)]);
        $request->forceFill(['created_at' => now()->subDays(3)])->save();
        CustomerRequestItem::factory()
            ->for($request, 'request')
            ->forProduct(self::BARCODE, 'Organic Oat Milk 1L')
            ->justGetItIn()
            ->status(CustomerRequestItem::STATUS_ORDERED)
            ->create();

        $this->matchPage()->assertOk()
            ->assertSee('Requested by Shelf Sam')
            ->assertDontSee('Put aside for Shelf Sam')
            ->assertSee('taken '.now()->subDays(3)->format('D j M'));

        $this->postJson(route('delivery-legacy.scan-increment'), [
            'delID' => self::DELIVERY_ID,
            'barcode' => self::BARCODE,
            'quantity' => 1,
            'supplierID' => self::SUPPLIER_ID,
        ])->assertOk()
            ->assertJsonPath('customerRequests.0.hold_for_collection', false)
            ->assertJsonPath('customerRequests.0.taken_on', now()->subDays(3)->toDateString());
    }

    // ------------------------------------------------------------- arrivals

    public function test_a_scan_stamps_the_waiting_request_lines_as_arrived(): void
    {
        $pending = $this->requestFor('Jane Doe', CustomerRequestItem::STATUS_PENDING);
        $ordered = $this->requestFor('Ordered Olly', CustomerRequestItem::STATUS_ORDERED);
        $aside = $this->requestFor('Put-aside Pat', CustomerRequestItem::STATUS_PUT_ASIDE);

        $this->postJson(route('delivery-legacy.scan-increment'), [
            'delID' => self::DELIVERY_ID,
            'barcode' => self::BARCODE,
            'quantity' => 4,
            'supplierID' => self::SUPPLIER_ID,
        ])->assertOk()
            ->assertJsonPath('customerRequests.0.arrived_quantity', 4)
            ->assertJsonPath('customerRequests.1.arrived_quantity', 4);

        foreach ([$pending, $ordered] as $line) {
            $line->refresh();
            $this->assertNotNull($line->arrived_at);
            $this->assertSame(4.0, (float) $line->arrived_quantity);
            $this->assertSame(self::DELIVERY_ID, $line->arrived_session_id);
            $this->assertTrue($line->hasArrived());
            $this->assertSame('Arrived '.now()->format('D j M').' · 4 scanned', $line->arrivalLabel());
        }

        // A line already put aside is not waiting for anything.
        $this->assertNull($aside->fresh()->arrived_at);

        // A second scan updates the running total.
        $this->postJson(route('delivery-legacy.scan-increment'), [
            'delID' => self::DELIVERY_ID,
            'barcode' => self::BARCODE,
            'quantity' => 2,
            'supplierID' => self::SUPPLIER_ID,
        ])->assertOk();
        $this->assertSame(6.0, (float) $pending->fresh()->arrived_quantity);

        // Correcting the scan back to nothing takes the stamp off again.
        $this->patchJson(route('delivery-legacy.update-quantity'), [
            'delID' => self::DELIVERY_ID,
            'barcode' => self::BARCODE,
            'quantity' => 0,
            'supplierID' => self::SUPPLIER_ID,
            'financials' => false,
        ])->assertOk();
        $this->assertNull($pending->fresh()->arrived_at);
        $this->assertNull($ordered->fresh()->arrived_session_id);

        // And a typed correction to a quantity stamps it like a scan.
        $this->patchJson(route('delivery-legacy.update-quantity'), [
            'delID' => self::DELIVERY_ID,
            'barcode' => self::BARCODE,
            'quantity' => 3,
            'supplierID' => self::SUPPLIER_ID,
            'financials' => false,
        ])->assertOk();
        $this->assertSame(3.0, (float) $pending->fresh()->arrived_quantity);
    }

    public function test_the_arrival_stamp_stays_as_history_once_the_line_moves_on(): void
    {
        $line = $this->requestFor('Jane Doe', CustomerRequestItem::STATUS_ORDERED);
        $line->forceFill(['arrived_at' => now(), 'arrived_quantity' => 2, 'arrived_session_id' => self::DELIVERY_ID])->save();

        app(\App\Services\CustomerRequestService::class)
            ->changeItemStatus($line, CustomerRequestItem::STATUS_PUT_ASIDE, auth()->user());

        $line->refresh();
        $this->assertNotNull($line->arrived_at);
        $this->assertFalse($line->hasArrived(), 'put aside: no longer waiting, so not "arrived" on the board');

        // The detail page still tells the story.
        $this->get(route('customer-requests.show', $line->request))
            ->assertOk()
            ->assertSee('2 scanned in Receiving');
    }

    public function test_backfill_stamps_open_lines_from_past_scans(): void
    {
        $ordered = $this->requestFor('Jane Doe', CustomerRequestItem::STATUS_ORDERED);
        $ordered->forceFill(['status_changed_at' => now()->subDays(5)])->save();

        // Scanned before it was ordered: not this order's arrival.
        $stale = $this->requestFor('Stale Stan', CustomerRequestItem::STATUS_ORDERED);
        $stale->forceFill(['status_changed_at' => now()->subHour()])->save();

        DB::connection('pos')->table('deliveriesScanItems')->insert([
            ['ID' => 's-old', 'delID' => 'd-old', 'barcode' => self::BARCODE, 'quantity' => 1, 'dateScan' => now()->subDays(10)],
            ['ID' => 's-new', 'delID' => self::DELIVERY_ID, 'barcode' => self::BARCODE, 'quantity' => 6, 'dateScan' => now()->subDays(2)],
        ]);

        $this->artisan('customer-requests:backfill-arrivals')
            ->expectsOutputToContain('1 request line(s) stamped')
            ->assertSuccessful();

        $ordered->refresh();
        $this->assertSame(now()->subDays(2)->toDateTimeString(), $ordered->arrived_at->toDateTimeString());
        $this->assertSame(6.0, (float) $ordered->arrived_quantity);
        $this->assertSame(self::DELIVERY_ID, $ordered->arrived_session_id);

        $this->assertNull($stale->fresh()->arrived_at);

        // Re-running finds nothing new.
        $this->artisan('customer-requests:backfill-arrivals')
            ->expectsOutputToContain('0 request line(s) stamped');
    }
}
