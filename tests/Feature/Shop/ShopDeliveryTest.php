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

        // Three restarts: a committed add, a cancelled prompt, a code that is
        // not in the delivery.
        $this->assertSame(3, substr_count($js, 'this.announceSaved();'));

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
