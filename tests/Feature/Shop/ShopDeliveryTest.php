<?php

namespace Tests\Feature\Shop;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesLegacyDeliveryPosTables;
use Tests\TestCase;

/**
 * Shop mode receive delivery: the session list, the scan screen, and the JSON
 * the scan screen renders from. The legacy endpoints do the writing; these tests
 * pin the classification and the routing into and out of Shop mode.
 */
class ShopDeliveryTest extends TestCase
{
    use CreatesLegacyDeliveryPosTables;
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension is required.');
        }

        parent::setUp();

        $this->createLegacyDeliveryPosTables();
        $this->seedLegacyDelivery();
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
                ['display_name' => $name, 'module' => 'Shop']
            );
            $role->givePermissionTo($permission);
        }

        return User::factory()->create(['role_id' => $role->id, 'name' => 'Maya Jensen']);
    }

    private function employee(): User
    {
        return $this->userWith('employee', ['deliveries.process']);
    }

    private function scanUrl(): string
    {
        return route('shop.deliveries.scan', ['delID' => 'd-1', 'supplierID' => '999']);
    }

    private function summaryUrl(): string
    {
        return route('shop.deliveries.summary', ['delID' => 'd-1', 'supplierID' => '999']);
    }

    private function stockOf(string $productId): float
    {
        return (float) DB::connection('pos')
            ->table('STOCKCURRENT')
            ->where('PRODUCT', $productId)
            ->value('UNITS');
    }

    public function test_employee_can_open_the_delivery_list(): void
    {
        $response = $this->actingAs($this->employee())
            ->get(route('shop.deliveries'))
            ->assertOk();

        $response->assertSee('data-shell="shop"', false);
        $response->assertSee('Start a delivery');
        $response->assertSee('Hof Linde');
        $response->assertSee('href="'.e($this->scanUrl()).'"', false);
        $response->assertSee('In progress');
    }

    /**
     * Regression, from the live walkthrough on 2026-09-24: index() used to take
     * the 50 most recent sessions and only then filter to open ones, so on the
     * real database (1,201 sessions) an open session older than that window was
     * invisible while the Home badge still counted it — badge 6, list 5.
     */
    public function test_old_open_sessions_are_listed(): void
    {
        $rows = [];

        for ($i = 0; $i < 60; $i++) {
            $rows[] = [
                'ID' => "done-{$i}",
                'supID' => '999',
                'dateUpload' => now()->subDays($i % 7)->subMinutes($i),
                'status' => 1,
            ];
        }

        $rows[] = ['ID' => 'd-old', 'supID' => '999', 'dateUpload' => now()->subDays(120), 'status' => 0];

        DB::connection('pos')->table('deliveriesScan')->insert($rows);

        $response = $this->actingAs($this->employee())
            ->get(route('shop.deliveries'))
            ->assertOk();

        // The old open session is well outside any recent-N window.
        $response->assertSee('href="'.e(route('shop.deliveries.scan', ['delID' => 'd-old', 'supplierID' => '999'])).'"', false);
        $response->assertSee('2 open');

        // The completed list is still capped.
        $this->assertLessThanOrEqual(
            10,
            substr_count($response->getContent(), 'shop-pill--ok">Completed'),
            'Recently completed should stay capped at 10 rows.'
        );
    }

    public function test_barista_is_forbidden(): void
    {
        $barista = $this->userWith('barista', ['kds.access']);

        $this->actingAs($barista)->get(route('shop.deliveries'))->assertForbidden();
        $this->actingAs($barista)->get($this->scanUrl())->assertForbidden();
        $this->actingAs($barista)
            ->getJson(route('delivery-legacy.items', ['delID' => 'd-1', 'supplierID' => '999']))
            ->assertForbidden();
    }

    public function test_items_endpoint_classifies_the_session(): void
    {
        $json = $this->actingAs($this->employee())
            ->getJson(route('delivery-legacy.items', ['delID' => 'd-1', 'supplierID' => '999']))
            ->assertOk()
            ->json();

        $rows = collect($json['rows'])->keyBy('barcode');

        $this->assertSame('ok', $rows['5000000000017']['status']);
        $this->assertEquals(12, $rows['5000000000017']['expected']);
        $this->assertEquals(12, $rows['5000000000017']['scanned']);

        $this->assertSame('short', $rows['5000000000024']['status']);
        $this->assertEquals(8, $rows['5000000000024']['expected']);
        $this->assertEquals(6, $rows['5000000000024']['scanned']);

        $this->assertSame('unexpected', $rows['4260009912200']['status']);
        $this->assertNull($rows['4260009912200']['expected']);
        $this->assertEquals(3, $rows['4260009912200']['scanned']);

        // Only rows that resolved to a product can be added to stock; the summary
        // relies on this flag so its unit total matches what completion writes.
        $this->assertTrue($rows['5000000000017']['stockable']);
        $this->assertTrue($rows['5000000000024']['stockable']);
        $this->assertFalse($rows['4260009912200']['stockable']);

        // Cycle 23: the row carries the shop's current stock so the scan screen
        // can show it. Zero is a figure; a row whose product never resolved has
        // no stock to report and gets null, not 0.
        $this->assertEquals(3, $rows['5000000000017']['stock']);
        $this->assertEquals(0, $rows['5000000000024']['stock']);
        $this->assertNull($rows['4260009912200']['stock']);

        $this->assertSame(['total' => 2, 'checked' => 2, 'issues' => 2], $json['progress']);
        $this->assertSame('Hof Linde', $json['session']['supplier']);
        $this->assertFalse($json['session']['completed']);
    }

    public function test_scan_screen_renders_with_the_endpoint_urls(): void
    {
        $response = $this->actingAs($this->employee())
            ->get($this->scanUrl())
            ->assertOk();

        $response->assertSee('data-items-url="'.e(route('delivery-legacy.items')).'"', false);
        $response->assertSee('data-scan-url="'.e(route('delivery-legacy.scan-increment')).'"', false);
        $response->assertSee('data-update-url="'.e(route('delivery-legacy.update-quantity')).'"', false);
        $response->assertSee('data-del-id="d-1"', false);
        $response->assertSee('data-supplier-id="999"', false);
        $response->assertSee('shop-scan__input', false);
        $response->assertSee('New first');
        $response->assertSee('href="'.e(route('shop.deliveries')).'"', false);

        // Screen 05 v2 (cycle 23): a row is a button that opens a correction card,
        // the top bar carries a second line, and the scan field is the compact one.
        $response->assertSee('class="shop-row shop-item"', false);
        $response->assertSee('shop-row__stock', false);
        $response->assertSee('shop-scan--inline', false);
        $response->assertSee('shop-topbar__sub', false);
        $response->assertSee('shop-notice', false);
        $response->assertSee('shop-status', false);
        $response->assertSee('toggleSort()', false);
        $response->assertSee('Correct quantity', false);
        $response->assertSee('adjust(editingRow, 1)', false);

        // The per-row stepper and its app CSS are gone with it.
        $response->assertDontSee('shop-row--wrap', false);
        $response->assertDontSee('shop-row__controls', false);

        $additions = substr(
            $css = file_get_contents(resource_path('css/shop.css')),
            strpos($css, 'APP ADDITIONS START')
        );

        $this->assertStringNotContainsString('.shop-row--wrap', $additions);
        $this->assertStringNotContainsString('.shop-row__controls', $additions);
    }

    public function test_the_scan_page_top_bar_carries_the_session_and_date(): void
    {
        $response = $this->actingAs($this->employee())
            ->get(route('shop.deliveries.scan', ['delID' => 'd-1', 'supplierID' => 999]))
            ->assertOk();

        // Supplier on line one, session and when on line two. The fixture's
        // session is dated today, which reads better than the date itself.
        $response->assertSee('<span class="shop-topbar__sub shop-code">d-1 · today</span>', false);
        $response->assertSee('<h1 class="shop-topbar__title">', false);
    }

    public function test_completed_session_hides_the_scan_input(): void
    {
        DB::connection('pos')->table('deliveriesScan')->where('ID', 'd-1')->update(['status' => 1]);

        $response = $this->actingAs($this->employee())
            ->get($this->scanUrl())
            ->assertOk();

        $response->assertSee('This delivery is completed');
        $response->assertDontSee('shop-scan__input', false);
    }

    public function test_starting_a_delivery_from_shop_lands_on_the_shop_scan_screen(): void
    {
        $response = $this->actingAs($this->employee())
            ->post(route('delivery-legacy.create-session'), ['supplierID' => '999', 'return' => 'shop']);

        $response->assertRedirect();
        $target = $response->headers->get('Location');
        $this->assertStringStartsWith(route('shop.deliveries.scan'), $target);
        $this->assertStringContainsString('supplierID=999', $target);

        // Without the flag the office form behaves exactly as before.
        $office = $this->actingAs($this->employee())
            ->post(route('delivery-legacy.create-session'), ['supplierID' => '999']);

        $office->assertRedirect();
        $this->assertStringStartsWith(route('delivery-legacy.match'), $office->headers->get('Location'));
    }

    public function test_scan_screen_renders_the_quantity_prompt(): void
    {
        $response = $this->actingAs($this->employee())
            ->get($this->scanUrl())
            ->assertOk();

        $response->assertSee('Quantity to add');
        $response->assertSee('commit()', false);
        $response->assertSee('cancelPending()', false);
        $response->assertSee('bump(1)', false);
        $response->assertSee('bump(-1)', false);
    }

    public function test_lookup_with_zero_quantity_records_nothing(): void
    {
        $employee = $this->employee();
        $scanned = fn () => DB::connection('pos')
            ->table('deliveriesScanItems')
            ->where('delID', 'd-1')
            ->where('barcode', '5000000000017')
            ->sum('quantity');

        $lookup = $this->actingAs($employee)
            ->postJson(route('delivery-legacy.scan-increment'), [
                'delID' => 'd-1', 'barcode' => '5000000000017', 'quantity' => 0, 'supplierID' => '999',
            ])
            ->assertOk();

        $this->assertSame('Oat drink 1 L', $lookup->json('product.name'));
        $this->assertEquals(12, $lookup->json('newQuantity'));
        $this->assertEquals(12, $scanned(), 'A zero-quantity lookup must not write.');

        $add = $this->actingAs($employee)
            ->postJson(route('delivery-legacy.scan-increment'), [
                'delID' => 'd-1', 'barcode' => '5000000000017', 'quantity' => 2, 'supplierID' => '999',
            ])
            ->assertOk();

        $this->assertEquals(14, $add->json('newQuantity'));
        $this->assertEquals(14, $scanned());
    }

    public function test_summary_screen_renders(): void
    {
        $response = $this->actingAs($this->employee())
            ->get($this->summaryUrl())
            ->assertOk();

        $response->assertSee('data-shell="shop"', false);
        $response->assertSee('data-items-url="'.e(route('delivery-legacy.items')).'"', false);
        $response->assertSee('Discrepancies');
        $response->assertSee('Complete delivery');
        $response->assertSee('Keep scanning');
        $response->assertSee('href="'.e($this->scanUrl()).'"', false);
        $response->assertSee('<input type="hidden" name="return" value="shop">', false);
    }

    public function test_summary_is_forbidden_for_a_barista(): void
    {
        $this->actingAs($this->userWith('barista', ['kds.access']))
            ->get($this->summaryUrl())
            ->assertForbidden();
    }

    public function test_scan_screen_links_to_the_summary_and_has_no_window_enter_handler(): void
    {
        $response = $this->actingAs($this->employee())
            ->get($this->scanUrl())
            ->assertOk();

        $response->assertSee('href="'.e($this->summaryUrl()).'"', false);
        $response->assertSee('Summary');

        // Enter is handled by the component's own scan-empty event, so one
        // keystroke can never reach commit() twice.
        $response->assertSee('@scan-empty="pending && commit()"', false);
        $response->assertDontSee('keydown.enter.window', false);
    }

    public function test_completing_from_shop_updates_stock_and_returns_to_the_list(): void
    {
        $before = ['p1' => $this->stockOf('p1'), 'p2' => $this->stockOf('p2')];

        $response = $this->actingAs($this->employee())
            ->post(route('delivery-legacy.complete'), [
                'delID' => 'd-1', 'supplierID' => '999', 'return' => 'shop',
            ]);

        $response->assertRedirect(route('shop.deliveries'));
        $response->assertSessionHas('success', fn (string $message) => str_contains($message, '18 units')
            && str_contains($message, '2 products'));

        $this->assertSame(1, (int) DB::connection('pos')->table('deliveriesScan')->where('ID', 'd-1')->value('status'));

        // 12 for the oat drink, 6 for the leeks. The unknown barcode has no
        // product row, so completion adds nothing for it.
        $this->assertEquals($before['p1'] + 12, $this->stockOf('p1'));
        $this->assertEquals($before['p2'] + 6, $this->stockOf('p2'));
    }

    /**
     * Completion is not naturally idempotent: the item queries read the scan rows,
     * which completing does not clear, so a second run would increment the same
     * amounts again. The guard refuses instead of double-writing.
     */
    public function test_completing_twice_does_not_add_stock_twice(): void
    {
        $employee = $this->employee();
        $before = ['p1' => $this->stockOf('p1'), 'p2' => $this->stockOf('p2')];

        $this->actingAs($employee)
            ->post(route('delivery-legacy.complete'), [
                'delID' => 'd-1', 'supplierID' => '999', 'return' => 'shop',
            ])
            ->assertRedirect(route('shop.deliveries'));

        $afterFirst = ['p1' => $this->stockOf('p1'), 'p2' => $this->stockOf('p2')];
        $this->assertEquals($before['p1'] + 12, $afterFirst['p1']);
        $this->assertEquals($before['p2'] + 6, $afterFirst['p2']);

        $second = $this->actingAs($employee)
            ->post(route('delivery-legacy.complete'), [
                'delID' => 'd-1', 'supplierID' => '999', 'return' => 'shop',
            ]);

        $second->assertRedirect();
        $second->assertSessionHas('error', 'This delivery is already completed.');

        $this->assertEquals($afterFirst['p1'], $this->stockOf('p1'), 'Stock must not move on a second completion.');
        $this->assertEquals($afterFirst['p2'], $this->stockOf('p2'), 'Stock must not move on a second completion.');
        $this->assertSame(1, (int) DB::connection('pos')->table('deliveriesScan')->where('ID', 'd-1')->value('status'));
    }

    /**
     * With no referer, back() would otherwise land on "/". The refusal should put
     * the person back where they were: the Shop summary, or the office match page.
     */
    public function test_refusing_a_second_completion_falls_back_to_the_right_page(): void
    {
        $employee = $this->employee();

        DB::connection('pos')->table('deliveriesScan')->where('ID', 'd-1')->update(['status' => 1]);

        $this->actingAs($employee)
            ->post(route('delivery-legacy.complete'), [
                'delID' => 'd-1', 'supplierID' => '999', 'return' => 'shop',
            ])
            ->assertRedirect(route('shop.deliveries.summary', ['delID' => 'd-1', 'supplierID' => '999']))
            ->assertSessionHas('error', 'This delivery is already completed.');

        $this->actingAs($employee)
            ->post(route('delivery-legacy.complete'), ['delID' => 'd-1', 'supplierID' => '999'])
            ->assertRedirect(route('delivery-legacy.match', ['delID' => 'd-1', 'supplierID' => '999']))
            ->assertSessionHas('error', 'This delivery is already completed.');
    }

    public function test_completing_without_the_shop_flag_returns_to_the_office_page(): void
    {
        $response = $this->actingAs($this->employee())
            ->post(route('delivery-legacy.complete'), ['delID' => 'd-1', 'supplierID' => '999']);

        $response->assertRedirect(route('delivery-legacy.match', ['delID' => 'd-1', 'supplierID' => '999']));
    }

    /**
     * The Complete button is gated client-side by x-show="canComplete", which a
     * server-side assertion cannot see: the markup is always present. What is
     * server-rendered and worth pinning is the completed notice, plus the fact
     * that the summary still loads for a closed session.
     */
    public function test_completed_summary_shows_the_completed_notice(): void
    {
        DB::connection('pos')->table('deliveriesScan')->where('ID', 'd-1')->update(['status' => 1]);

        $this->actingAs($this->employee())
            ->get($this->summaryUrl())
            ->assertOk()
            ->assertSee('This delivery is completed');
    }

    public function test_home_tile_links_to_the_shop_delivery_list(): void
    {
        $response = $this->actingAs($this->employee())
            ->get(route('shop.home'))
            ->assertOk();

        $response->assertSee('href="'.e(route('shop.deliveries')).'"', false);
        $response->assertSee('Receive delivery');

        // One open session is seeded, so the tile carries a badge of 1.
        $response->assertSee('<span class="shop-tile__badge" aria-label="1 waiting">1</span>', false);
    }
}
