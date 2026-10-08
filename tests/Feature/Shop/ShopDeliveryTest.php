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

    // --- cycle 24: pictures on delivery rows --------------------------------

    private function photoUrl(string $code = '5000000000017'): string
    {
        return route('shop.product-photo', [
            'code' => $code,
            'v' => substr(md5(self::photoBlob()), 0, 8),
        ]);
    }

    public function test_items_carry_picture_urls(): void
    {
        $rows = collect(
            $this->actingAs($this->employee())
                ->getJson(route('delivery-legacy.items', ['delID' => 'd-1', 'supplierID' => 999]))
                ->assertOk()
                ->json('rows')
        )->keyBy('barcode');

        // Every row carries the key, so the front end never has to guess.
        foreach ($rows as $row) {
            $this->assertArrayHasKey('image_url', $row);
        }

        // The product with a till photo resolves to the Shop thumbnail route,
        // versioned by the photo itself — never to the full-size products.image.
        $this->assertSame($this->photoUrl(), $rows['5000000000017']['image_url']);
        $this->assertStringNotContainsString('/products/p1/image', (string) $rows['5000000000017']['image_url']);

        // No photo and no supplier picture: nothing, so the placeholder shows.
        $this->assertNull($rows['5000000000024']['image_url']);
    }

    public function test_scan_increment_returns_the_picture(): void
    {
        $product = $this->actingAs($this->employee())
            ->postJson(route('delivery-legacy.scan-increment'), [
                'delID' => 'd-1',
                // A string: the endpoint validates supplierID as one.
                'supplierID' => '999',
                'barcode' => '5000000000017',
                'quantity' => 0,
            ])
            ->assertOk()
            ->json('product');

        $this->assertSame($this->photoUrl(), $product['image_url']);
    }

    /**
     * Cycle 32. A USB hand scanner types its digits into whatever has focus. If
     * that is the quantity field or a stepper button, a 13-digit barcode could
     * be posted as a quantity and written to the delivery. The client-side fix
     * is capture() in scan-input.js; this is the backstop, and it also covers
     * the office page, which posts the same endpoints.
     */
    /**
     * Cycle 33. On a 390 px phone the camera block is ~268 px tall, so with the
     * prompt below the scan field its "Add" button landed off the bottom of the
     * screen on every item. The prompt is now the first thing in the left
     * column, and the page scrolls it into view for someone who had scrolled
     * down the list.
     */
    public function test_the_quantity_prompt_comes_before_the_scan_field(): void
    {
        $html = $this->actingAs($this->employee())->get($this->scanUrl())->assertOk()->getContent();

        $promptAt = strpos($html, 'x-ref="prompt"');
        $fieldAt = strpos($html, 'shop-scan__input');
        $this->assertNotFalse($promptAt);
        $this->assertNotFalse($fieldAt);
        $this->assertLessThan(
            $fieldAt,
            $promptAt,
            'The quantity prompt must render above the scan field, or "Add" falls below the fold on a phone.'
        );

        $this->assertStringContainsString(
            "this.\$refs.prompt?.scrollIntoView({ block: 'nearest' })",
            file_get_contents(resource_path('js/shop/delivery-scan.js'))
        );
    }

    /**
     * Cycle 33. The sticky bar took a strip of every screen for a link wanted
     * once, at the end. Summary now sits in the Items header, and the bar after
     * the list is the static variant.
     */
    public function test_summary_is_in_the_header_and_the_bottom_bar_is_not_sticky(): void
    {
        $response = $this->actingAs($this->employee())->get($this->scanUrl())->assertOk();

        // Two links to the summary now: one in the Items header, one in the
        // static bar after the list. Both carry the issue count.
        $html = $response->getContent();
        $href = 'href="'.e($this->summaryUrl()).'"';
        $this->assertSame(2, substr_count($html, $href), 'Expected the header link and the end-of-list link.');
        $this->assertSame(2, substr_count($html, 'shop-btn__count'));

        // The header one is inside the Items header's shop-inline, before the list.
        $headerAt = strpos($html, 'shop-group-title');
        $listAt = strpos($html, 'class="shop-list"');
        $firstLinkAt = strpos($html, $href);
        $this->assertGreaterThan($headerAt, $firstLinkAt);
        $this->assertLessThan($listAt, $firstLinkAt);

        // The sticky variant is gone; only the static one remains.
        $response->assertDontSee('<div class="shop-actions">', false);
        $response->assertSee('<div class="shop-actions shop-actions--static">', false);
    }

    public function test_a_barcode_sized_quantity_is_refused_by_both_endpoints(): void
    {
        $user = $this->employee();

        $increment = $this->actingAs($user)
            ->postJson(route('delivery-legacy.scan-increment'), [
                'delID' => 'd-1',
                'supplierID' => '999',
                'barcode' => '5000000000017',
                'quantity' => '5412533401912',
            ]);
        $increment->assertStatus(422)->assertJsonValidationErrors('quantity');

        $update = $this->actingAs($user)
            ->patchJson(route('delivery-legacy.update-quantity'), [
                'delID' => 'd-1',
                'supplierID' => '999',
                'barcode' => '5000000000017',
                'quantity' => '5412533401912',
            ]);
        $update->assertStatus(422)->assertJsonValidationErrors('quantity');

        $this->assertDatabaseMissing('deliveriesScanItems', ['quantity' => '5412533401912'], 'pos');
    }

    public function test_a_plausible_quantity_is_still_accepted(): void
    {
        $this->actingAs($this->employee())
            ->postJson(route('delivery-legacy.scan-increment'), [
                'delID' => 'd-1',
                'supplierID' => '999',
                'barcode' => '5000000000017',
                'quantity' => 9999,
            ])
            ->assertOk();
    }

    /**
     * Weights are typed with decimals and added on PHP floats, which leaves a
     * tail (0.1 + 0.2 = 0.30000000000000004). Stored totals are rounded to 3 dp.
     * The row starts from nothing: added to the seeded 6, many sequences happen
     * to land on a representable double and would pass without the rounding.
     */
    public function test_weights_accumulate_without_a_floating_point_tail(): void
    {
        $user = $this->employee();

        DB::connection('pos')->table('deliveriesScanItems')
            ->where('delID', 'd-1')
            ->where('barcode', '5000000000024')
            ->delete();

        foreach (['0.1', '0.2'] as $quantity) {
            $response = $this->actingAs($user)
                ->postJson(route('delivery-legacy.scan-increment'), [
                    'delID' => 'd-1',
                    'supplierID' => '999',
                    'barcode' => '5000000000024',
                    'quantity' => $quantity,
                ])
                ->assertOk();
        }

        $this->assertSame(0.3, (float) $response->json('newQuantity'));

        $rows = DB::connection('pos')->table('deliveriesScanItems')
            ->where('delID', 'd-1')
            ->where('barcode', '5000000000024')
            ->pluck('quantity');

        $this->assertCount(1, $rows);
        $this->assertSame(0.3, (float) $rows->first());
    }

    public function test_a_corrected_quantity_is_rounded_to_three_decimals(): void
    {
        $this->actingAs($this->employee())
            ->patchJson(route('delivery-legacy.update-quantity'), [
                'delID' => 'd-1',
                'supplierID' => '999',
                'barcode' => '5000000000024',
                'quantity' => '2.34567',
            ])
            ->assertOk();

        $stored = DB::connection('pos')->table('deliveriesScanItems')
            ->where('delID', 'd-1')
            ->where('barcode', '5000000000024')
            ->value('quantity');

        $this->assertSame(2.346, (float) $stored);
    }

    public function test_product_photo_route_serves_a_thumbnail(): void
    {
        $user = $this->employee();
        $version = substr(md5(self::photoBlob()), 0, 8);

        $good = $this->actingAs($user)
            ->get(route('shop.product-photo', ['code' => '5000000000017', 'v' => $version]))
            ->assertOk();

        $good->assertHeader('Content-Type', 'image/jpeg');
        $this->assertSame([112, 112], array_slice(getimagesizefromstring($good->getContent()), 0, 2));
        $this->assertStringContainsString('max-age=604800', $good->headers->get('Cache-Control'));
        $this->assertStringContainsString('immutable', $good->headers->get('Cache-Control'));

        // The stored blob is never what comes back.
        $this->assertNotSame(self::photoBlob(), $good->getContent());

        // A wrong version is served short-lived, so a stale link pins nothing.
        $wrong = $this->actingAs($user)
            ->get(route('shop.product-photo', ['code' => '5000000000017', 'v' => 'deadbeef']))
            ->assertOk();
        $this->assertStringContainsString('must-revalidate', $wrong->headers->get('Cache-Control'));

        // A revalidation keeps the long lifetime.
        $revalidated = $this->actingAs($user)->get(
            route('shop.product-photo', ['code' => '5000000000017', 'v' => $version]),
            ['If-None-Match' => $good->headers->get('ETag')]
        );
        $revalidated->assertStatus(304);
        $this->assertStringContainsString('max-age=604800', $revalidated->headers->get('Cache-Control'));
    }

    public function test_product_photo_route_needs_a_login(): void
    {
        $this->get(route('shop.product-photo', ['code' => '5000000000017']))->assertRedirect('/login');
    }

    public function test_product_photo_route_404s_without_a_photo(): void
    {
        $user = $this->employee();

        $this->actingAs($user)->get(route('shop.product-photo', ['code' => '5000000000024']))->assertNotFound();
        $this->actingAs($user)->get(route('shop.product-photo', ['code' => '9999999999999']))->assertNotFound();
    }

    public function test_the_summary_page_shows_pictures(): void
    {
        $this->actingAs($this->employee())
            ->get(route('shop.deliveries.summary', ['delID' => 'd-1', 'supplierID' => 999]))
            ->assertOk()
            ->assertSee('hasImage(row)', false);
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
        // Cycle 24 adds the picture column to the row.
        $response->assertSee('class="shop-row shop-item shop-item--pic"', false);
        $response->assertSee('hasImage(row)', false);
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

    public function test_quantities_on_the_delivery_screens_are_formatted(): void
    {
        $employee = $this->employee();

        $this->actingAs($employee)
            ->get($this->scanUrl())
            ->assertOk()
            ->assertSee('stockText(row.scanned)', false)
            ->assertSee('stockText(pending.scannedSoFar)', false);

        $this->actingAs($employee)
            ->get($this->summaryUrl())
            ->assertOk()
            ->assertSee('quantityText(unitsToAdd)', false);
    }

    public function test_the_scan_prompt_offers_a_typed_quantity(): void
    {
        $response = $this->actingAs($this->employee())
            ->get($this->scanUrl())
            ->assertOk();

        $response->assertSee('typeQuantity()', false);
        $response->assertSee('inputmode="decimal"', false);
        $response->assertSee('qtyValue === null', false);
    }

    public function test_the_correction_card_offers_a_typed_quantity(): void
    {
        $response = $this->actingAs($this->employee())
            ->get($this->scanUrl())
            ->assertOk();

        $response->assertSee('typeCorrection()', false);
        $response->assertSee('setCorrection()', false);
    }

    public function test_scan_screen_offers_find_by_name(): void
    {
        $response = $this->actingAs($this->userWith('employee', ['deliveries.process', 'products.view']))
            ->get($this->scanUrl())
            ->assertOk();

        $response->assertSee('No barcode? Find by name');
        $response->assertSee('openManual()', false);
        $response->assertSee('data-search-url="'.e(route('api.products.search')).'"', false);
    }

    public function test_find_by_name_is_hidden_without_products_view(): void
    {
        $this->actingAs($this->employee())
            ->get($this->scanUrl())
            ->assertOk()
            ->assertDontSee('openManual()', false);
    }

    public function test_find_by_name_is_hidden_on_a_completed_session(): void
    {
        DB::connection('pos')->table('deliveriesScan')->where('ID', 'd-1')->update(['status' => 1]);

        $this->actingAs($this->userWith('employee', ['deliveries.process', 'products.view']))
            ->get($this->scanUrl())
            ->assertOk()
            ->assertDontSee('openManual()', false);
    }

    /** The request a product picked by name makes: its code, with a typed weight. */
    public function test_a_product_picked_by_name_is_recorded_by_its_code_with_a_weight(): void
    {
        $user = $this->employee();

        $this->actingAs($user)
            ->postJson(route('delivery-legacy.scan-increment'), [
                'delID' => 'd-1',
                'supplierID' => '999',
                'barcode' => '5000000000024',
                'quantity' => 4.35,
            ])
            ->assertOk()
            ->assertJsonPath('newQuantity', 10.35);

        $row = collect($this->actingAs($user)
            ->getJson(route('delivery-legacy.items', ['delID' => 'd-1', 'supplierID' => '999']))
            ->assertOk()
            ->json('rows'))
            ->firstWhere('barcode', '5000000000024');

        $this->assertEquals(10.35, $row['scanned']);
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
     * A product with no STOCKCURRENT row at all, delivered 2.5 on d-1. Seeded
     * per test rather than in the trait so no other test's figures move.
     */
    private function seedProductWithoutStockRecord(): void
    {
        $pos = DB::connection('pos');

        $pos->table('PRODUCTS')->insert([
            'ID' => 'p3', 'NAME' => 'Mature cheddar', 'CODE' => '5000000000031', 'CATEGORY' => 'c1',
            'TAXCAT' => '001', 'PRICEBUY' => 15, 'PRICESELL' => 28.5,
        ]);
        $pos->table('deliveriesScanItems')->insert([
            'ID' => 'i4', 'delID' => 'd-1', 'barcode' => '5000000000031', 'quantity' => 2.5, 'dateScan' => now(),
        ]);
    }

    private function stockRowsOf(string $productId)
    {
        return DB::connection('pos')->table('STOCKCURRENT')->where('PRODUCT', $productId)->get();
    }

    public function test_completion_creates_a_stock_record_for_a_product_without_one(): void
    {
        $this->seedProductWithoutStockRecord();
        $this->assertCount(0, $this->stockRowsOf('p3'));

        $this->actingAs($this->employee())
            ->post(route('delivery-legacy.complete'), [
                'delID' => 'd-1', 'supplierID' => '999', 'return' => 'shop',
            ])
            ->assertRedirect(route('shop.deliveries'));

        $rows = $this->stockRowsOf('p3');
        $this->assertCount(1, $rows);
        $this->assertEquals(2.5, $rows->first()->UNITS);
        $this->assertSame('0', $rows->first()->LOCATION);

        $this->assertEquals(15, $this->stockOf('p1'));
    }

    public function test_undo_takes_a_created_stock_record_back_to_zero(): void
    {
        $this->seedProductWithoutStockRecord();
        $user = $this->userWith('employee', ['deliveries.process', 'deliveries.manage']);

        $this->actingAs($user)
            ->post(route('delivery-legacy.complete'), [
                'delID' => 'd-1', 'supplierID' => '999', 'return' => 'shop',
            ])
            ->assertRedirect(route('shop.deliveries'));

        $this->actingAs($user)
            ->post(route('delivery-legacy.undo-complete'), ['delID' => 'd-1', 'supplierID' => '999'])
            ->assertRedirect();

        $rows = $this->stockRowsOf('p3');
        $this->assertCount(1, $rows, 'Undo returns a created record to 0; it does not delete it.');
        $this->assertEquals(0, $rows->first()->UNITS);
        $this->assertSame(0, (int) DB::connection('pos')->table('deliveriesScan')->where('ID', 'd-1')->value('status'));
    }

    public function test_completing_twice_does_not_create_or_add_twice(): void
    {
        $this->seedProductWithoutStockRecord();
        $employee = $this->employee();

        foreach ([1, 2] as $attempt) {
            $this->actingAs($employee)
                ->post(route('delivery-legacy.complete'), [
                    'delID' => 'd-1', 'supplierID' => '999', 'return' => 'shop',
                ]);
        }

        $rows = $this->stockRowsOf('p3');
        $this->assertCount(1, $rows);
        $this->assertEquals(2.5, $rows->first()->UNITS);
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

    /**
     * Cycle 29. A camera scan stops the camera, so the page must dispatch
     * `shop-scan-saved` to bring it back — otherwise staff press the camera
     * button before every item.
     *
     * What this pins is the *ordering* rule, which is the part that is easy to
     * undo by accident: the restart must not be dispatched while the quantity
     * prompt is open. If it were, the barcode still under the lens would
     * confirm the prompt and silently record a unit nobody scanned. A source
     * assertion in the cycle-14 style, because the behaviour is camera-only and
     * cannot be driven from a feature test.
     */
    public function test_the_delivery_scan_page_reopens_the_camera_but_not_while_the_prompt_is_open(): void
    {
        $js = file_get_contents(resource_path('js/shop/delivery-scan.js'));

        $this->assertStringContainsString("'shop-scan-saved'", $js);

        // Five restarts: a committed add, a cancelled prompt, a code that is
        // not in the delivery, a return to waiting for the unit barcode
        // (backToLinking: a refused or failed link, a lookup that found nothing,
        // or Not this one), and Dismiss while a candidate was showing.
        $this->assertSame(5, substr_count($js, 'this.announceSaved();'));

        // ...and none of them between the prompt being built and its own
        // announceDone(), which is the lookup that opens the prompt.
        $promptAt = strpos($js, 'this.pending = {');
        $this->assertNotFalse($promptAt);
        $doneAt = strpos($js, 'this.announceDone();', $promptAt);
        $this->assertNotFalse($doneAt);
        $this->assertStringNotContainsString(
            'announceSaved',
            substr($js, $promptAt, $doneAt - $promptAt),
            'The camera must not reopen while the quantity prompt is open: the code still in frame would confirm it and add an extra unit.'
        );
    }

    private function scanJs(): string
    {
        return file_get_contents(resource_path('js/shop/delivery-scan.js'));
    }

    /** The correction card's logic, shared by the scan and summary pages (cycle 3). */
    private function correctionJs(): string
    {
        return file_get_contents(resource_path('js/shop/delivery-correction.js'));
    }

    /**
     * Without invoice lines every row is "unexpected", so the old warning showed
     * on every item. The no-invoice guard must come before either warning.
     */
    public function test_adding_without_an_invoice_confirms_rather_than_warns(): void
    {
        $js = $this->scanJs();

        $this->assertStringContainsString('so far', $js);

        $reportAt = strpos($js, 'reportRow(barcode) {');
        $this->assertNotFalse($reportAt);
        $guardAt = strpos($js, 'if (! this.hasInvoice)', $reportAt);
        $warnAt = strpos($js, "'Not on this invoice'", $reportAt);
        $this->assertNotFalse($guardAt);
        $this->assertNotFalse($warnAt);
        $this->assertLessThan($warnAt, $guardAt);
    }

    public function test_a_scan_over_an_unfilled_prompt_says_the_item_was_not_added(): void
    {
        $this->assertStringContainsString('not added, no amount entered', $this->scanJs());
    }

    public function test_the_correction_card_has_one_primary_button_while_typing(): void
    {
        $this->actingAs($this->employee())
            ->get($this->scanUrl())
            ->assertOk()
            ->assertSee(":class=\"editTyped !== null ? 'shop-btn--ghost' : 'shop-btn--primary'\"", false);

        // A failed save keeps the typed field open: editTyped is cleared only
        // when saveQuantity() reports success.
        $js = $this->correctionJs();
        $setAt = strpos($js, 'async setCorrection() {');
        $this->assertNotFalse($setAt);
        $body = substr($js, $setAt, strpos($js, 'quantityBody(barcode, quantity) {', $setAt) - $setAt);
        // Cycle 2: Set goes through flushNow() (the same save the stepper makes).
        $this->assertStringContainsString("if (await this.flushNow()) {\n            this.editTyped = null;", $body);
        $this->assertSame(1, substr_count($body, 'this.editTyped = null'));
    }

    public function test_not_stocked_is_part_of_the_result_meta_line(): void
    {
        $response = $this->actingAs($this->userWith('employee', ['deliveries.process', 'products.view']))
            ->get($this->scanUrl())
            ->assertOk();

        $response->assertSee("(! p.is_stocked ? ' · Not stocked' : '')\"", false);
        $response->assertDontSee('shop-pill--muted" x-show="! p.is_stocked"', false);
    }

    public function test_find_by_name_says_when_the_search_failed(): void
    {
        $response = $this->actingAs($this->userWith('employee', ['deliveries.process', 'products.view']))
            ->get($this->scanUrl())
            ->assertOk();

        $response->assertSee('x-text="searchMessage"', false);
        $response->assertSee('Try again');
        $response->assertSee('x-show="searchError === \'failed\'"', false);
        $response->assertSee('x-show="noMatches" x-cloak>No products match', false);

        // Hidden until Alpine has started, so an early tap cannot be lost.
        $response->assertSee('<button class="shop-btn shop-btn--ghost" type="button" x-show="! manual" x-cloak x-on:click="openManual()">', false);
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

    // --- deliveries cycle 1: link an outer barcode from the scan screen ---

    /**
     * A scan no product has opens a "Not found" card above the scan field (the
     * same position as the prompt, for the same phone reason) with a button that
     * makes the next scan the unit barcode to link it to.
     */
    public function test_scan_screen_offers_to_link_an_unknown_outer_barcode(): void
    {
        $response = $this->actingAs($this->employee())->get($this->scanUrl())->assertOk();
        $html = $response->getContent();

        $response->assertSee('data-outer-url="'.e(route('delivery-legacy.save-outer-barcode')).'"', false);
        $response->assertSee('Link as outer barcode');
        $response->assertSee('startLink()', false);
        $response->assertSee('dismissUnknown()', false);

        $cardAt = strpos($html, 'x-ref="unknown"');
        $fieldAt = strpos($html, 'shop-scan__input');
        $this->assertNotFalse($cardAt);
        $this->assertNotFalse($fieldAt);
        $this->assertLessThan(
            $fieldAt,
            $cardAt,
            'The Not found card must render above the scan field, or its button falls below the fold on a phone.'
        );

        // Revision 2: the three states and the confirmation.
        $response->assertSee('confirmLink()', false);
        $response->assertSee('rejectCandidate()', false);
        $response->assertSee('Yes, link it');
        $response->assertSee('Not this one');
        $response->assertSee('Cancel linking');
        $response->assertSee('shop-card--linking', false);
        $response->assertSee('is-linking', false);

        $js = $this->scanJs();
        $this->assertStringContainsString('outer barcode again', $js);
        $this->assertSame(1, substr_count($js, 'this.post(this.outerUrl'));
        $this->assertLessThan(
            strpos($js, 'this.post(this.outerUrl'),
            strpos($js, 'confirmLink()'),
            'Only the confirmation may save a link: a scan alone must never post to save-outer-barcode.'
        );
    }

    /**
     * Revision 2, from the owner's browser check: after tapping Link, a scan of
     * the next delivery item used to link it silently. The scan now only looks
     * the code up and shows the product; the save waits for "Yes, link it".
     */
    public function test_linking_waits_for_a_tap(): void
    {
        $js = $this->scanJs();

        $this->assertStringContainsString('backToLinking(null)', $js);
        $this->assertStringContainsString('candidate = { code: data.product.barcode', $js);

        $findAt = strpos($js, 'async findUnit(');
        $confirmAt = strpos($js, 'async confirmLink(');
        $this->assertNotFalse($findAt);
        $this->assertNotFalse($confirmAt);
        $this->assertLessThan($confirmAt, $findAt);
        $this->assertStringNotContainsString(
            'this.post(this.outerUrl',
            substr($js, $findAt, $confirmAt - $findAt),
            'findUnit() looks the unit barcode up and must not save the link itself.'
        );
    }

    /**
     * The client refuses an outer code scanned as the unit ("already the case
     * barcode of"); this pins the server backstop: no supplier_link.Barcode
     * equals an outer code, so the save 404s.
     */
    public function test_an_outer_code_cannot_be_linked_as_a_unit_barcode(): void
    {
        $this->actingAs($this->employee())
            ->postJson(route('delivery-legacy.save-outer-barcode'), [
                'unitBarcode' => '15000000000014',
                'supplierID' => '999',
                'outerCode' => '15000000000021',
            ])
            ->assertStatus(404)
            ->assertJsonPath('success', false);

        $this->assertSame(
            '15000000000014',
            DB::connection('pos')->table('supplier_link')->where('Barcode', '5000000000017')->value('OuterCode')
        );
    }

    /**
     * The link is saved through the office page's own endpoint, and the outer
     * code then resolves as a case of the linked product. The lookup with a zero
     * quantity records nothing.
     */
    public function test_linking_an_outer_barcode_makes_the_next_scan_a_case(): void
    {
        DB::connection('pos')->table('supplier_link')
            ->where('Barcode', '5000000000024')
            ->update(['CaseUnits' => 4]);

        $this->actingAs($this->employee())
            ->postJson(route('delivery-legacy.save-outer-barcode'), [
                'unitBarcode' => '5000000000024',
                'supplierID' => '999',
                'outerCode' => '15000000000021',
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'productName' => 'Leeks',
                'unitBarcode' => '5000000000024',
            ]);

        $this->assertSame(
            '15000000000021',
            DB::connection('pos')->table('supplier_link')->where('Barcode', '5000000000024')->value('OuterCode')
        );

        $this->actingAs($this->employee())
            ->postJson(route('delivery-legacy.scan-increment'), [
                'delID' => 'd-1',
                'barcode' => '15000000000021',
                'quantity' => 0,
                'supplierID' => '999',
            ])
            ->assertOk()
            ->assertJsonPath('product.name', 'Leeks')
            ->assertJsonPath('product.barcode', '5000000000024')
            ->assertJsonPath('scanType', 'case')
            ->assertJsonPath('caseUnits', 4);

        $this->assertSame(3, DB::connection('pos')->table('deliveriesScanItems')->where('delID', 'd-1')->count());
    }

    public function test_an_outer_barcode_already_on_another_product_is_refused(): void
    {
        $response = $this->actingAs($this->employee())
            ->postJson(route('delivery-legacy.save-outer-barcode'), [
                'unitBarcode' => '5000000000024',
                'supplierID' => '999',
                'outerCode' => '15000000000014',
            ]);

        $response->assertStatus(409)->assertJsonPath('success', false);
        $this->assertStringContainsString('Oat drink 1 L', $response->json('message'));

        $links = DB::connection('pos')->table('supplier_link')->where('SupplierID', '999')->pluck('OuterCode', 'Barcode');
        $this->assertNull($links['5000000000024']);
        $this->assertSame('15000000000014', $links['5000000000017']);
    }

    public function test_a_unit_barcode_the_supplier_does_not_have_is_refused(): void
    {
        $this->actingAs($this->employee())
            ->postJson(route('delivery-legacy.save-outer-barcode'), [
                'unitBarcode' => '4260009912200',
                'supplierID' => '999',
                'outerCode' => '15000000000021',
            ])
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_a_gs1_outer_code_is_stored_as_its_gtin(): void
    {
        $this->actingAs($this->employee())
            ->postJson(route('delivery-legacy.save-outer-barcode'), [
                'unitBarcode' => '5000000000024',
                'supplierID' => '999',
                'outerCode' => "]C10115000000000021\x1D10LOT42",
            ])
            ->assertOk();

        $this->assertSame(
            '15000000000021',
            DB::connection('pos')->table('supplier_link')->where('Barcode', '5000000000024')->value('OuterCode')
        );
    }

    public function test_a_barista_cannot_link_an_outer_barcode(): void
    {
        $this->actingAs($this->userWith('barista', ['kds.access']))
            ->postJson(route('delivery-legacy.save-outer-barcode'), [
                'unitBarcode' => '5000000000024',
                'supplierID' => '999',
                'outerCode' => '15000000000021',
            ])
            ->assertForbidden();
    }

    // --- deliveries cycle 2: instant correction card + the extras query ---

    /**
     * getScannedNotOnInvoice() was rewritten without its cross-collation join.
     * An extra row that has a product and a supplier link keeps every detail the
     * old SQL gave it (code, name, stock, stockable), beside the no-product row.
     */
    public function test_an_extra_product_with_a_supplier_link_keeps_its_details(): void
    {
        $pos = DB::connection('pos');
        $pos->table('PRODUCTS')->insert([
            'ID' => 'p3', 'NAME' => 'Hazelnuts 500 g', 'CODE' => '5000000000031', 'CATEGORY' => 'c2', 'TAXCAT' => '001',
            'PRICEBUY' => 3.0, 'PRICESELL' => 5.5, 'IMAGE' => null,
        ]);
        $pos->table('STOCKCURRENT')->insert(['PRODUCT' => 'p3', 'UNITS' => 4]);
        $pos->table('supplier_link')->insert([
            'Barcode' => '5000000000031', 'SupplierCode' => 'S3', 'SupplierID' => '999', 'CaseUnits' => 10, 'OuterCode' => null,
        ]);
        $pos->table('deliveriesScanItems')->insert([
            'ID' => 'i4', 'delID' => 'd-1', 'barcode' => '5000000000031', 'quantity' => 2, 'dateScan' => now(),
        ]);

        $json = $this->actingAs($this->employee())
            ->getJson(route('delivery-legacy.items', ['delID' => 'd-1', 'supplierID' => '999']))
            ->assertOk()
            ->json();

        $rows = collect($json['rows'])->keyBy('barcode');

        $this->assertSame('unexpected', $rows['5000000000031']['status']);
        $this->assertSame('S3', $rows['5000000000031']['code']);
        $this->assertSame('Hazelnuts 500 g', $rows['5000000000031']['name']);
        $this->assertEquals(4, $rows['5000000000031']['stock']);
        $this->assertEquals(2, $rows['5000000000031']['scanned']);
        $this->assertTrue($rows['5000000000031']['stockable']);

        $this->assertNull($rows['4260009912200']['code']);
        $this->assertSame('4260009912200', $rows['4260009912200']['name']);
        $this->assertFalse($rows['4260009912200']['stockable']);

        // Extras are not invoice lines; both are issues beside the short leeks.
        $this->assertSame(['total' => 2, 'checked' => 2, 'issues' => 3], $json['progress']);

        // Name order among the extras, nulls first, as MySQL ordered them.
        $order = array_column($json['rows'], 'barcode');
        $this->assertLessThan(
            array_search('5000000000031', $order, true),
            array_search('4260009912200', $order, true)
        );
    }

    /**
     * The Shop page reloads `items` after every correction and never reads the
     * office financials (three queries); it asks the PATCH to skip them. The
     * office page sends no flag and keeps getting them.
     */
    public function test_the_shop_page_can_skip_financials_on_a_correction(): void
    {
        $this->actingAs($this->employee())
            ->patchJson(route('delivery-legacy.update-quantity'), [
                'delID' => 'd-1',
                'barcode' => '5000000000024',
                'quantity' => 7,
                'supplierID' => '999',
                'financials' => false,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('quantity', 7)
            ->assertJsonMissingPath('financials');

        $this->assertEquals(
            7,
            DB::connection('pos')->table('deliveriesScanItems')->where('delID', 'd-1')->where('barcode', '5000000000024')->sum('quantity')
        );

        $this->actingAs($this->employee())
            ->patchJson(route('delivery-legacy.update-quantity'), [
                'delID' => 'd-1',
                'barcode' => '5000000000024',
                'quantity' => 8,
                'supplierID' => '999',
            ])
            ->assertOk()
            ->assertJsonPath('financials.totalItems', 2);

        $this->assertSame(1, substr_count($this->correctionJs(), 'financials: false'));
    }

    /**
     * From the owner's 2026-10-08 delivery: each + / − waited about a second on
     * a PATCH and a reload. The number now steps locally and one save follows
     * 400 ms after the last tap; a tap never posts and is never disabled.
     */
    public function test_the_correction_card_steps_locally_and_saves_once(): void
    {
        $response = $this->actingAs($this->employee())->get($this->scanUrl())->assertOk();

        $response->assertSee('x-text="stockText(editValue)"', false);
        $response->assertSee('aria-label="One fewer" @click="adjust(editingRow, -1)"', false);
        $response->assertSee('aria-label="One more" @click="adjust(editingRow, 1)"', false);
        $response->assertDontSee(':disabled="busy" @click="adjust(', false);

        $js = $this->correctionJs();
        $this->assertStringContainsString('flushNow()', $js);
        $this->assertStringContainsString('setTimeout(() => this.flushNow(), 400)', $js);
        $this->assertStringContainsString('keepalive: true', $js);

        // One implementation: a copy of the stepper must not creep back into the page.
        $this->assertStringNotContainsString('setTimeout(() => this.flushNow(), 400)', $this->scanJs());

        $adjustAt = strpos($js, 'adjust(row, delta) {');
        $this->assertNotFalse($adjustAt);
        $body = substr($js, $adjustAt, strpos($js, '},', $adjustAt) - $adjustAt);
        $this->assertStringNotContainsString('saveQuantity(', $body, 'A tap steps the number; it must not post.');
    }

    /** A scan closes the card; its unsaved taps must land before the reload. */
    public function test_a_scan_flushes_a_pending_correction_first(): void
    {
        $js = $this->scanJs();

        $lookupAt = strpos($js, 'async lookup(');
        $this->assertNotFalse($lookupAt);
        $flushAt = strpos($js, 'await this.flushNow();', $lookupAt);
        $closeAt = strpos($js, 'this.editing = null;', $lookupAt);
        $this->assertNotFalse($flushAt);
        $this->assertNotFalse($closeAt);
        $this->assertLessThan($closeAt, $flushAt, 'lookup() must flush the correction card before closing it.');
    }

    // --- deliveries cycle 3: corrections from the summary ---

    /** A discrepancy row on the summary opens the same correction card, above the list. */
    public function test_summary_rows_open_the_correction_card(): void
    {
        $response = $this->actingAs($this->employee())->get($this->summaryUrl())->assertOk();
        $html = $response->getContent();

        $response->assertSee('data-update-url="'.e(route('delivery-legacy.update-quantity')).'"', false);
        $response->assertSee('@click="edit(row)"', false);
        $response->assertSee(':aria-pressed="editing === row.barcode"', false);
        $response->assertSee('x-ref="correct"', false);
        $response->assertSee('Correct quantity');
        $response->assertSee('x-text="stockText(editValue)"', false);
        $response->assertSee('shop-toasts', false);

        $cardAt = strpos($html, 'x-ref="correct"');
        $listAt = strpos($html, 'x-for="row in discrepancies"');
        $this->assertNotFalse($cardAt);
        $this->assertNotFalse($listAt);
        $this->assertLessThan($listAt, $cardAt, 'The card sits above the discrepancy list (Shop rule 6: nothing pops over neighbouring rows).');
    }

    /** Completion has already added stock, so a completed summary is read-only. */
    public function test_completed_summary_offers_no_correction(): void
    {
        DB::connection('pos')->table('deliveriesScan')->where('ID', 'd-1')->update(['status' => 1]);

        $response = $this->actingAs($this->employee())->get($this->summaryUrl())->assertOk();

        $response->assertSee('This delivery is completed');
        $response->assertDontSee('edit(row)', false);
        $response->assertDontSee('x-ref="correct"', false);
    }

    /** One card: a shared JS part and a shared partial, used by both screens. */
    public function test_both_delivery_screens_share_the_correction_card(): void
    {
        $summaryJs = file_get_contents(resource_path('js/shop/delivery-summary.js'));
        $this->assertStringContainsString("from './delivery-correction.js'", $this->scanJs());
        $this->assertStringContainsString("from './delivery-correction.js'", $summaryJs);
        $this->assertStringNotContainsString('async flushNow(', $this->scanJs());
        $this->assertStringContainsString('async flushNow(', $this->correctionJs());

        $scanView = file_get_contents(resource_path('views/shop/delivery-scan.blade.php'));
        $summaryView = file_get_contents(resource_path('views/shop/delivery-summary.blade.php'));
        $partial = file_get_contents(resource_path('views/shop/partials/delivery-correction.blade.php'));
        $this->assertStringContainsString("@include('shop.partials.delivery-correction'", $scanView);
        $this->assertStringContainsString("@include('shop.partials.delivery-correction'", $summaryView);
        $this->assertSame(1, substr_count($partial, 'x-ref="correct"'));
    }

    /**
     * The summary derives its totals and its list from the items JSON, so a
     * correction's reload is what updates them: short leeks corrected to 8 are
     * "ok" and only the unexpected row is left as an issue.
     */
    public function test_a_summary_correction_changes_the_totals(): void
    {
        $this->actingAs($this->employee())
            ->patchJson(route('delivery-legacy.update-quantity'), [
                'delID' => 'd-1',
                'barcode' => '5000000000024',
                'quantity' => 8,
                'supplierID' => '999',
                'financials' => false,
            ])
            ->assertOk();

        $json = $this->actingAs($this->employee())
            ->getJson(route('delivery-legacy.items', ['delID' => 'd-1', 'supplierID' => '999']))
            ->assertOk()
            ->json();

        $rows = collect($json['rows'])->keyBy('barcode');
        $this->assertSame('ok', $rows['5000000000024']['status']);
        $this->assertSame(1, $json['progress']['issues']);
    }
}
