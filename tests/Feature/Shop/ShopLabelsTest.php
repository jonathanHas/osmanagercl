<?php

namespace Tests\Feature\Shop;

use App\Models\LabelLog;
use App\Models\LabelTemplate;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\LabelQueueService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Shop mode print labels: the shelf-label queue.
 *
 * The queue is a derivation over LabelLog rather than a table — a product needs a
 * label when its latest queue event is newer than its latest print — so these
 * tests drive it through the loggers rather than inserting rows.
 */
class ShopLabelsTest extends TestCase
{
    use RefreshDatabase;

    private const OAT = '5000000000017';

    private const LEEKS = '5000000000024';

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

        $pos->create('PRODUCTS', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('NAME')->nullable();
            $table->string('CODE')->unique();
            $table->string('CATEGORY')->nullable();
            $table->string('TAXCAT')->nullable();
            $table->decimal('PRICEBUY', 10, 4)->default(0);
            $table->decimal('PRICESELL', 10, 4)->default(0);
        });
        $pos->create('TAXES', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('CATEGORY')->nullable();
            $table->decimal('RATE', 8, 6)->default(0);
        });
        // The price-with-VAT accessor goes PRODUCTS.TAXCAT -> TAXCATEGORIES.ID ->
        // TAXES.CATEGORY, so the middle table is required even though nothing
        // reads its name.
        $pos->create('TAXCATEGORIES', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('NAME')->nullable();
        });

        DB::connection('pos')->table('PRODUCTS')->insert([
            ['ID' => 'p1', 'NAME' => 'Oat drink 1 L', 'CODE' => self::OAT, 'TAXCAT' => 'tc1', 'PRICESELL' => 2.43],
            ['ID' => 'p2', 'NAME' => 'Leeks', 'CODE' => self::LEEKS, 'TAXCAT' => 'tc1', 'PRICESELL' => 1.22],
        ]);
        DB::connection('pos')->table('TAXCATEGORIES')->insert(['ID' => 'tc1', 'NAME' => 'Standard']);
        DB::connection('pos')->table('TAXES')->insert(['ID' => 't1', 'CATEGORY' => 'tc1', 'RATE' => 0.23]);

        // 190mm x 277mm usable / 63 x 34 = 3 across, 8 down = 24 per sheet.
        LabelTemplate::create([
            'name' => 'Test 24-up',
            'width_mm' => 63,
            'height_mm' => 34,
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function userWith(string $roleName, array $permissions): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName)]);

        foreach ($permissions as $name) {
            $permission = Permission::firstOrCreate(
                ['name' => $name],
                ['display_name' => $name, 'module' => 'Labels']
            );
            $role->givePermissionTo($permission);
        }

        return User::factory()->create(['role_id' => $role->id, 'name' => 'Maya Jensen']);
    }

    private function employee(): User
    {
        return $this->userWith('employee', ['labels.print']);
    }

    private function manager(): User
    {
        return $this->userWith('manager', ['labels.print', 'labels.manage']);
    }

    public function test_employee_can_open_the_labels_screen(): void
    {
        $response = $this->actingAs($this->employee())
            ->get(route('shop.labels'))
            ->assertOk();

        $response->assertSee('data-shell="shop"', false);
        $response->assertSee('data-queue-url="'.e(route('labels.queue')).'"', false);
        $response->assertSee('shop-scan__input', false);
        $response->assertSee('Print queue');
        $response->assertSee('href="'.e(route('labels.zebra')).'"', false);
        $response->assertDontSee('Clear queue');

        // A failed load must show only the error, not "All caught up" beside it.
        $response->assertSee('! loading && ! error && total === 0', false);
    }

    public function test_manager_sees_clear_queue(): void
    {
        $this->actingAs($this->manager())
            ->get(route('shop.labels'))
            ->assertOk()
            ->assertSee('Clear queue')
            ->assertSee('data-clear-url="'.e(route('labels.dismiss-all')).'"', false);
    }

    public function test_barista_is_forbidden(): void
    {
        $barista = $this->userWith('barista', ['kds.access']);

        $this->actingAs($barista)->get(route('shop.labels'))->assertForbidden();
        $this->actingAs($barista)->getJson(route('labels.queue'))->assertForbidden();
    }

    public function test_queue_lists_products_whose_latest_event_is_newer_than_their_last_print(): void
    {
        LabelLog::logPriceUpdate(self::OAT);

        // Queued, then printed: no longer needs a label.
        LabelLog::logNewProduct(self::LEEKS);
        LabelLog::logLabelPrint(self::LEEKS);

        $json = $this->actingAs($this->employee())
            ->getJson(route('labels.queue'))
            ->assertOk()
            ->json();

        $this->assertCount(1, $json['rows']);
        $this->assertSame(self::OAT, $json['rows'][0]['code']);
        $this->assertSame('Price changed', $json['rows'][0]['reason']);
        $this->assertSame(1, $json['counts']['total']);
        $this->assertSame(24, $json['labels_per_sheet']);
    }

    public function test_dismiss_takes_a_product_off_the_queue(): void
    {
        LabelLog::logPriceUpdate(self::OAT);
        LabelLog::logNewProduct(self::LEEKS);

        $employee = $this->employee();

        $this->actingAs($employee)
            ->postJson(route('labels.dismiss'), ['barcode' => self::OAT])
            ->assertOk()
            ->assertJson(['success' => true, 'code' => self::OAT]);

        $rows = $this->actingAs($employee)->getJson(route('labels.queue'))->json('rows');

        $this->assertCount(1, $rows);
        $this->assertSame(self::LEEKS, $rows[0]['code']);

        $this->assertDatabaseHas('label_logs', [
            'barcode' => self::OAT,
            'event_type' => LabelLog::EVENT_LABEL_DISMISS,
            'user_id' => $employee->id,
        ]);

        // Taking a label off the queue is not printing it; the office history
        // must not claim otherwise.
        $this->assertDatabaseMissing('label_logs', [
            'barcode' => self::OAT,
            'event_type' => LabelLog::EVENT_LABEL_PRINT,
        ]);
    }

    public function test_dismiss_rejects_an_unknown_barcode(): void
    {
        $this->actingAs($this->employee())
            ->postJson(route('labels.dismiss'), ['barcode' => '9999999999999'])
            ->assertNotFound()
            ->assertJson(['success' => false]);
    }

    public function test_scan_adds_a_product_to_the_queue(): void
    {
        $employee = $this->employee();

        $this->actingAs($employee)
            ->postJson(route('labels.scan'), ['barcode' => self::LEEKS])
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('product.name', 'Leeks');

        $rows = $this->actingAs($employee)->getJson(route('labels.queue'))->json('rows');

        $this->assertCount(1, $rows);
        $this->assertSame(self::LEEKS, $rows[0]['code']);
        $this->assertSame('Re-queued', $rows[0]['reason']);
    }

    public function test_home_tile_links_to_the_labels_screen_with_a_badge(): void
    {
        LabelLog::logPriceUpdate(self::OAT);

        $this->actingAs($this->employee())
            ->get(route('shop.home'))
            ->assertOk()
            ->assertSee('href="'.e(route('shop.labels')).'"', false)
            ->assertSee('Print labels')
            ->assertSee('<span class="shop-tile__badge" aria-label="1 waiting">1</span>', false);
    }

    /**
     * The derivation used to issue one print lookup per candidate barcode, which
     * on production-sized windows (180-1,430 barcodes) made the Home badge cost
     * seconds. It must now be a fixed number of queries whatever the size.
     */
    public function test_queue_derivation_uses_a_fixed_number_of_queries(): void
    {
        foreach (['B1', 'B2', 'B3', 'B4', 'B5'] as $barcode) {
            LabelLog::logPriceUpdate($barcode);
        }

        $service = app(LabelQueueService::class);

        DB::connection()->enableQueryLog();
        DB::connection()->flushQueryLog();
        $service->needingLabels();
        $this->assertLessThanOrEqual(3, count(DB::connection()->getQueryLog()));

        DB::connection()->flushQueryLog();
        $service->countsByEventType();
        $this->assertLessThanOrEqual(2, count(DB::connection()->getQueryLog()));
        DB::connection()->disableQueryLog();
    }

    public function test_queue_ignores_events_and_prints_outside_the_window(): void
    {
        $service = app(LabelQueueService::class);

        // Event older than the 30-day window: not listed.
        LabelLog::logPriceUpdate('OLD');
        LabelLog::where('barcode', 'OLD')->update(['created_at' => now()->subDays(31)]);

        // Event inside the window, last print outside it: still needs a label,
        // because the print window matches the event window.
        LabelLog::logLabelPrint('STALE-PRINT');
        LabelLog::where('barcode', 'STALE-PRINT')->update(['created_at' => now()->subDays(40)]);
        LabelLog::logPriceUpdate('STALE-PRINT');
        LabelLog::where('barcode', 'STALE-PRINT')
            ->where('event_type', LabelLog::EVENT_PRICE_UPDATE)
            ->update(['created_at' => now()->subDays(5)]);

        // Event inside the window, printed since: not listed.
        LabelLog::logPriceUpdate('PRINTED');
        LabelLog::where('barcode', 'PRINTED')->update(['created_at' => now()->subDays(5)]);
        LabelLog::logLabelPrint('PRINTED');
        LabelLog::where('barcode', 'PRINTED')
            ->where('event_type', LabelLog::EVENT_LABEL_PRINT)
            ->update(['created_at' => now()->subDays(2)]);

        // Event inside the window, dismissed since: not listed, exactly as if it
        // had been printed.
        LabelLog::logPriceUpdate('DISMISSED');
        LabelLog::where('barcode', 'DISMISSED')->update(['created_at' => now()->subDays(5)]);
        LabelLog::logLabelDismiss('DISMISSED');
        LabelLog::where('barcode', 'DISMISSED')
            ->where('event_type', LabelLog::EVENT_LABEL_DISMISS)
            ->update(['created_at' => now()->subDays(2)]);

        $barcodes = $this->candidateBarcodes($service);

        $this->assertNotContains('OLD', $barcodes, 'An event older than the window must not queue a label.');
        $this->assertContains('STALE-PRINT', $barcodes, 'A print outside the window does not satisfy an event inside it.');
        $this->assertNotContains('PRINTED', $barcodes, 'An event printed since must not stay queued.');
        $this->assertNotContains('DISMISSED', $barcodes, 'An event dismissed since must not stay queued.');
    }

    /**
     * candidates() is private by design — it is the shared derivation, not API —
     * so reach it through reflection rather than widening it for a test.
     *
     * @return array<int, string>
     */
    private function candidateBarcodes(LabelQueueService $service): array
    {
        $method = new \ReflectionMethod($service, 'candidates');
        $method->setAccessible(true);

        return $method->invoke($service)->pluck('barcode')->map(fn ($b) => (string) $b)->all();
    }

    /**
     * The label carries the barcode, so changing it makes the shelf label wrong.
     * The event has been logged since August 2025 and was never counted.
     */
    public function test_barcode_change_queues_a_label(): void
    {
        LabelLog::create([
            'barcode' => self::LEEKS,
            'event_type' => LabelLog::EVENT_BARCODE_CHANGE,
            'metadata' => json_encode(['old_barcode' => '1', 'new_barcode' => self::LEEKS]),
        ]);

        $json = $this->actingAs($this->employee())
            ->getJson(route('labels.queue'))
            ->assertOk()
            ->json();

        $this->assertCount(1, $json['rows']);
        $this->assertSame(self::LEEKS, $json['rows'][0]['code']);
        $this->assertSame('Barcode changed', $json['rows'][0]['reason']);
        $this->assertSame(1, $json['counts'][LabelLog::EVENT_BARCODE_CHANGE]);
        $this->assertSame(1, $json['counts']['total']);

        LabelLog::logLabelPrint(self::LEEKS);

        $this->assertCount(0, $this->actingAs($this->employee())->getJson(route('labels.queue'))->json('rows'));
    }

    public function test_manager_can_dismiss_the_whole_queue(): void
    {
        LabelLog::logPriceUpdate(self::OAT);
        LabelLog::logNewProduct(self::LEEKS);

        $manager = $this->manager();

        $this->actingAs($manager)
            ->postJson(route('labels.dismiss-all'))
            ->assertOk()
            ->assertJson(['success' => true, 'cleared_count' => 2]);

        $this->assertCount(0, $this->actingAs($manager)->getJson(route('labels.queue'))->json('rows'));

        foreach ([self::OAT, self::LEEKS] as $barcode) {
            $this->assertDatabaseHas('label_logs', [
                'barcode' => $barcode,
                'event_type' => LabelLog::EVENT_LABEL_DISMISS,
                'user_id' => $manager->id,
            ]);
        }

        $this->assertDatabaseMissing('label_logs', ['event_type' => LabelLog::EVENT_LABEL_PRINT]);
    }

    public function test_employee_cannot_dismiss_the_whole_queue(): void
    {
        $this->actingAs($this->employee())
            ->postJson(route('labels.dismiss-all'))
            ->assertForbidden();
    }

    /**
     * hub() summed an array that already contained its own 'total', so the count
     * on the label hub was double the real queue.
     */
    public function test_office_hub_counts_the_queue_once(): void
    {
        LabelLog::logPriceUpdate(self::OAT);

        // hub() counts ProductTranslation, which hardcodes the mysql connection;
        // point it at its own in-memory database so the page can render here.
        Config::set('database.connections.mysql', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        DB::purge('mysql');
        DB::connection('mysql')->getSchemaBuilder()->create('product_translations', function (Blueprint $table) {
            $table->id();
        });

        $response = $this->actingAs($this->manager())
            ->get(route('labels.index'))
            ->assertOk();

        $response->assertSee('1 product needing labels');
        $response->assertDontSee('2 products');
    }

    public function test_dismissals_do_not_appear_as_recent_prints(): void
    {
        LabelLog::logPriceUpdate(self::OAT);

        $employee = $this->employee();

        $this->actingAs($employee)
            ->postJson(route('labels.dismiss'), ['barcode' => self::OAT])
            ->assertOk();

        // Neither queued nor printed, so the product should not appear anywhere
        // on the office shelf-labels page.
        $this->actingAs($this->manager())
            ->get(route('labels.shelf-labels'))
            ->assertOk()
            ->assertDontSee('Oat drink 1 L');
    }

    /**
     * The office page and the Shop screen must read one queue, which is the point
     * of extracting LabelQueueService.
     */
    public function test_service_and_controller_agree(): void
    {
        LabelLog::logPriceUpdate(self::OAT);
        LabelLog::logNewProduct(self::LEEKS);

        $rows = $this->actingAs($this->employee())->getJson(route('labels.queue'))->json('rows');

        $this->assertSame(
            app(LabelQueueService::class)->countsByEventType()['total'],
            count($rows)
        );
    }
}
