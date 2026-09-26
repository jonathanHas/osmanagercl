<?php

namespace Tests\Feature\Shop;

use App\Models\CustomerRequest;
use App\Models\CustomerRequestItem;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The customer-requests board as a Shop mode screen.
 *
 * The URL, route name, permissions and every write endpoint are unchanged from
 * the office board — CustomerRequestTest still covers those. What is new here is
 * the rendering: one card per line, a guest view with no phone numbers and a
 * meta refresh, and staff actions as plain PATCH forms.
 */
class ShopRequestsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Facades\Storage::fake('local');

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
            // The rest of ProductSearchService::SELECT_COLUMNS, which the picture
            // resolver selects (cycle 21) — it never selects IMAGE itself, only
            // LENGTH(IMAGE) through the has_image expression.
            $table->decimal('PRICEBUY', 10, 4)->default(0);
            $table->boolean('ISSERVICE')->default(false);
            $table->string('DISPLAY')->nullable();
            $table->string('TAXCAT')->nullable();
            $table->binary('IMAGE')->nullable();
        });
        $pos->create('supplier_link', function (Blueprint $table) {
            $table->string('Barcode');
            $table->string('SupplierCode')->nullable();
            $table->string('SupplierID')->nullable();
        });

        DB::connection('pos')->table('PRODUCTS')->insert([
            ['ID' => 'p1', 'NAME' => 'Oat drink 1 L', 'CODE' => '5000000000017', 'REFERENCE' => 'OAT1', 'CATEGORY' => 'c1', 'PRICESELL' => 2.10, 'TAXCAT' => '001', 'IMAGE' => self::photoBlob()],
            // A second product with no photo, for the 404 case.
            ['ID' => 'p2', 'NAME' => 'Rye bread', 'CODE' => '5000000000024', 'REFERENCE' => 'RYE1', 'CATEGORY' => 'c1', 'PRICESELL' => 3.00, 'TAXCAT' => '001', 'IMAGE' => null],
        ]);
    }

    private function employee(): User
    {
        $role = Role::firstOrCreate(['name' => 'employee'], ['display_name' => 'Employee']);
        $permission = Permission::firstOrCreate(
            ['name' => 'customer-requests.manage'],
            ['display_name' => 'Manage Customer Requests', 'module' => 'Customer Requests']
        );
        $role->givePermissionTo($permission);

        return User::factory()->create(['role_id' => $role->id]);
    }

    /**
     * A due request for "Walk-in Wendy" with one stocked line.
     */
    /**
     * A real (tiny) PNG, so ProductThumbnailService can actually decode it — the
     * public photo route 404s on a blob it cannot encode.
     */
    private static function photoBlob(): string
    {
        $im = imagecreatetruecolor(60, 40);
        imagefill($im, 0, 0, imagecolorallocate($im, 20, 120, 200));
        ob_start();
        imagepng($im);
        imagedestroy($im);

        return ob_get_clean();
    }

    /**
     * The picture the board resolver produces for the fixture's product: the
     * public thumbnail route, versioned by the photo's md5 (cycle 22), not
     * `products.image`, which a guest cannot load.
     */
    private function photoUrl(): string
    {
        return route('customer-requests.photo', [
            'code' => '5000000000017',
            'v' => substr(md5(self::photoBlob()), 0, 8),
        ]);
    }

    /** A free-text sourcing line: no product, so no picture and no placeholder. */
    private function seedSourcingLine(): CustomerRequestItem
    {
        $request = CustomerRequest::factory()->create([
            'customer_name' => 'Sourcing Sam',
            'wanted_on' => today(),
        ]);

        return CustomerRequestItem::factory()->for($request, 'request')->create([
            'product_code' => null,
            'product_name' => null,
            'description' => 'Something we do not stock',
            'quantity' => 1,
            'status' => CustomerRequestItem::STATUS_PENDING,
            'position' => 1,
        ]);
    }

    private function seedDueRequest(): CustomerRequestItem
    {
        $request = CustomerRequest::factory()->create([
            'customer_name' => 'Walk-in Wendy',
            'customer_phone' => '087 111',
            'wanted_on' => today(),
        ]);

        return CustomerRequestItem::factory()->for($request, 'request')->create([
            'product_code' => '5000000000017',
            'product_name' => 'Oat milk',
            'quantity' => 2,
            'status' => CustomerRequestItem::STATUS_PENDING,
            'position' => 1,
        ]);
    }

    public function test_guest_board_is_shop_styled_and_read_only(): void
    {
        $this->seedDueRequest();

        $response = $this->get('/customer-requests')->assertOk();

        $response->assertSee('data-shell="shop"', false);
        $response->assertSee('shop-page--wide', false);
        $response->assertSee('http-equiv="refresh" content="300"', false);
        $response->assertSee('Walk-in Wendy');
        $response->assertSee('Oat milk');
        $response->assertSee('Due today');

        // Cycle 21: the guest card shows the product's picture. A guest cannot
        // load `products.image` (auth + products.view), so the img's error handler
        // is what puts the placeholder there — assert it is wired.
        $response->assertSee('class="shop-thumb"', false);
        $response->assertSee('src="'.$this->photoUrl().'"', false);
        $response->assertSee('x-on:error="ok = false"', false);
        $response->assertSee('Staff sign in');

        // Nothing a customer should not see, and nothing they could act on.
        $response->assertDontSee('087 111');
        $response->assertDontSee('New request');
        $response->assertDontSee(route('customer-requests.items.status', 1), false);

        // Guests keep the cycle 12 cards: none of the staff v2 furniture.
        $response->assertSee('shop-request', false);
        // The guest card no longer takes staff-only arguments, so none of what
        // they gated can appear.
        $response->assertDontSee(route('customer-requests.edit', 1), false);
        $response->assertDontSee('shop-req__date', false);
        $response->assertDontSee('shop-steps', false);
        $response->assertDontSee('Search customer or item');
    }

    // --- cycle 22: the public thumbnail route ------------------------------

    private function photoRoute(string $code = '5000000000017', array $query = []): string
    {
        return route('customer-requests.photo', array_merge(['code' => $code], $query));
    }

    public function test_a_guest_can_load_a_request_line_photo(): void
    {
        $this->seedDueRequest();

        // No session at all: this is the case that used to show a placeholder.
        $response = $this->get($this->photoRoute())->assertOk();

        $response->assertHeader('Content-Type', 'image/jpeg');

        $info = getimagesizefromstring($response->getContent());
        $this->assertSame([112, 112], [$info[0], $info[1]]);
        $this->assertSame('image/jpeg', $info['mime']);
    }

    public function test_the_photo_route_caches_hard_only_with_a_matching_version(): void
    {
        $this->seedDueRequest();
        $version = substr(md5(self::photoBlob()), 0, 8);

        $good = $this->get($this->photoRoute('5000000000017', ['v' => $version]))->assertOk();
        $this->assertStringContainsString('max-age=604800', $good->headers->get('Cache-Control'));
        $this->assertStringContainsString('immutable', $good->headers->get('Cache-Control'));

        foreach (['deadbeef', strtoupper($version), ''] as $bad) {
            $response = $this->get($this->photoRoute('5000000000017', ['v' => $bad]))->assertOk();
            $this->assertStringContainsString('must-revalidate', $response->headers->get('Cache-Control'), "v={$bad}");
            $this->assertStringNotContainsString('604800', $response->headers->get('Cache-Control'), "v={$bad}");
        }

        // A revalidation keeps the long lifetime, as the F&V route does.
        $etag = $good->headers->get('ETag');
        $revalidated = $this->get($this->photoRoute('5000000000017', ['v' => $version]), ['If-None-Match' => $etag]);
        $revalidated->assertStatus(304);
        $this->assertStringContainsString('max-age=604800', $revalidated->headers->get('Cache-Control'));
    }

    public function test_the_photo_route_refuses_a_product_that_is_not_on_a_current_request(): void
    {
        // The product exists and has a photo, but nobody has asked for it.
        $this->get($this->photoRoute())->assertNotFound();

        // A line on a request closed long ago is not current either.
        $item = $this->seedDueRequest();
        $item->request->update(['closed_at' => now()->subDays(40)]);
        $this->get($this->photoRoute())->assertNotFound();

        // Recently closed is still on the staff board's Done view, so it is served.
        $item->request->update(['closed_at' => now()->subDays(5)]);
        $this->get($this->photoRoute())->assertOk();

        // And reopening it works too.
        $item->request->update(['closed_at' => null]);
        $this->get($this->photoRoute())->assertOk();
    }

    public function test_the_photo_route_refuses_a_product_with_no_photo(): void
    {
        $request = CustomerRequest::factory()->create(['customer_name' => 'No Photo Nora']);
        CustomerRequestItem::factory()->for($request, 'request')->create([
            'product_code' => '5000000000024',
            'product_name' => 'Rye bread',
            'position' => 1,
        ]);

        $this->get($this->photoRoute('5000000000024'))->assertNotFound();
    }

    public function test_the_photo_route_refuses_an_unknown_code(): void
    {
        $this->seedDueRequest();

        $this->get($this->photoRoute('9999999999999'))->assertNotFound();
    }

    public function test_the_photo_route_is_public_and_throttled_but_never_serves_the_full_photo(): void
    {
        $this->seedDueRequest();

        $route = app('router')->getRoutes()->getByName('customer-requests.photo');

        // Public by design; the 404 rule above is the whole authorisation.
        $this->assertNotContains('auth', $route->gatherMiddleware());
        $this->assertContains('throttle:120,1', $route->gatherMiddleware());

        // What comes back is a 112 px JPEG, never the stored blob.
        $body = $this->get($this->photoRoute())->assertOk()->getContent();
        $this->assertNotSame(self::photoBlob(), $body);
        $this->assertSame([112, 112], array_slice(getimagesizefromstring($body), 0, 2));
    }

    public function test_a_sourcing_line_has_no_picture_and_no_placeholder(): void
    {
        $employee = $this->employee();
        $this->seedSourcingLine();

        // Sourcing lines are free text with no product behind them, so the row
        // keeps the layout it had before cycle 21 — not even a placeholder circle.
        // `shop-photo` is the server component's wrapper; `shop-thumb` alone would
        // be the wrong assertion, because the New request sheet on the same page
        // renders the Alpine thumbnail regardless.
        $this->actingAs($employee)->get('/customer-requests')
            ->assertOk()
            ->assertSee('Something we do not stock')
            ->assertDontSee('shop-photo', false);

        // And the same board with a pre-order line does show one, so the assertion
        // above is not passing merely because nothing ever emits it.
        $this->seedDueRequest();

        $this->actingAs($employee)->get('/customer-requests')
            ->assertOk()
            ->assertSee('shop-photo', false);
    }

    public function test_staff_board_shows_actions_and_form(): void
    {
        $item = $this->seedDueRequest();

        $response = $this->actingAs($this->employee())
            ->get('/customer-requests')
            ->assertOk();

        // v2 furniture: request rows with the lifecycle strip.
        $response->assertSee('shop-req__date', false);
        $response->assertSee('shop-steps', false);
        $response->assertSee('#more', false);

        // Cycle 21: a pre-order row shows the product's picture.
        $response->assertSee('class="shop-thumb"', false);
        $response->assertSee('src="'.$this->photoUrl().'"', false);

        // A pending line offers "Mark ordered" and can be put aside or marked
        // unavailable from the menu; there is nothing yet to undo.
        $response->assertSee(route('customer-requests.items.status', $item), false);
        $response->assertSee('Mark ordered');
        $response->assertSee('Put aside now');
        $response->assertSee('Not available');
        $response->assertDontSee('Undo last step');

        $response->assertSee(route('customer-requests.cancel', $item->request), false);
        $response->assertSee(route('customer-requests.edit', $item->request), false);
        $response->assertSee('087 111');

        // Filter, search and the sheet.
        $response->assertSee('Open 1');
        $response->assertSee('Search customer or item');
        $response->assertSee('New request');
        $response->assertSee('data-search-url="'.e(route('api.products.search')).'"', false);
        $response->assertSee('id="new-request-form"', false);
        $response->assertSee('form="new-request-form"', false);

        $response->assertSee('class="shop-thumb"', false);
        $response->assertSee('x-on:error="imageFailed(p)"', false);

        // The handler names must match what the module actually exposes. Cycle 14
        // renamed pick() to pickResult() in the shared typeahead and left this
        // call site behind, so clicking a search result did nothing.
        $response->assertSee('@click="pickResult(p)"', false);
        $response->assertSee('@keydown.enter.prevent="pickFirst()"', false);
        $response->assertSee('@click="unpick()"', false);
        $response->assertDontSee('http-equiv="refresh"', false);

        // Search hides whole groups, not just rows, and says when nothing is left.
        $response->assertSee('x-show="groupMatches($el)"', false);
        $response->assertSee('Nothing matches your search.');

        // The extra actions expand inside the card rather than floating over the
        // rows around it, so no control is ever underneath another.
        $response->assertSee('x-data="{ more: false }"', false);
        $response->assertSee(':aria-expanded="more"', false);
        $response->assertSee('shop-menu--static shop-req__more', false);
        $response->assertSee('x-show="more"', false);
    }

    public function test_board_panel_links_to_details(): void
    {
        $item = $this->seedDueRequest();

        $this->actingAs($this->employee())
            ->get('/customer-requests')
            ->assertOk()
            ->assertSee(route('customer-requests.show', $item->request), false)
            ->assertSee('Details');
    }

    public function test_detail_page_is_shop_styled_and_lists_lines_and_history(): void
    {
        $employee = $this->employee();
        $request = CustomerRequest::factory()->create(['customer_name' => 'History Hetty', 'wanted_on' => today()]);
        $first = CustomerRequestItem::factory()->for($request, 'request')->create(['description' => 'First line', 'position' => 1]);
        CustomerRequestItem::factory()->for($request, 'request')->create(['description' => 'Second line', 'position' => 2]);

        // Move one line so there is a history entry with a note and a user.
        $this->actingAs($employee)
            ->patch(route('customer-requests.items.status', $first), [
                'status' => CustomerRequestItem::STATUS_ORDERED,
                'note' => 'Udea order #123',
            ])
            ->assertRedirect();

        $response = $this->actingAs($employee)
            ->get(route('customer-requests.show', $request))
            ->assertOk();

        $response->assertSee('data-shell="shop"', false);
        $response->assertSee('shop-page--narrow', false);
        $response->assertSee('History Hetty');
        $response->assertSee('First line');
        $response->assertSee('Second line');
        $response->assertSee('Ordered');
        $response->assertSee('Status history');
        $response->assertSee('Pending');
        $response->assertSee('Udea order #123');
        $response->assertSee($employee->name);
        $response->assertSee(route('customer-requests.edit', $request), false);
    }

    public function test_detail_page_shows_the_picture_for_a_pre_order_line(): void
    {
        $item = $this->seedDueRequest();

        $this->actingAs($this->employee())
            ->get(route('customer-requests.show', $item->request))
            ->assertOk()
            ->assertSee('class="shop-thumb"', false)
            ->assertSee('src="'.$this->photoUrl().'"', false);
    }

    public function test_edit_page_seeds_lines_and_posts_to_update(): void
    {
        $employee = $this->employee();
        $request = CustomerRequest::factory()->create(['customer_name' => 'Edit Edna']);
        $keep = CustomerRequestItem::factory()->for($request, 'request')->create(['description' => 'Keep me', 'position' => 1]);

        $response = $this->actingAs($employee)
            ->get(route('customer-requests.edit', $request))
            ->assertOk();

        $response->assertSee('x-data="shopRequestEdit(', false);
        $response->assertSee('data-search-url="'.e(route('api.products.search')).'"', false);

        // Cycle 21 closes the cycle 14 gap: a seeded line carries the product
        // object the Alpine thumbnail needs, so an edited request shows its
        // pictures without the user re-picking every product.
        $seeded = CustomerRequestItem::factory()->for($request, 'request')
            ->create(['product_code' => '5000000000017', 'product_name' => 'Oat milk', 'position' => 2]);

        // The seed goes through @js(), i.e. Illuminate\Support\Js::from(), which
        // hex-escapes quotes — so the assertion has to be written the same way
        // rather than in plain JSON.
        $expected = trim((string) \Illuminate\Support\Js::from([
            'product' => ['code' => '5000000000017', 'image_url' => $this->photoUrl()],
        ]));
        $expected = str_replace(["JSON.parse('", "')"], '', $expected);
        $expected = trim($expected, '{}');

        $this->actingAs($employee)->get(route('customer-requests.edit', $request))
            ->assertOk()
            ->assertSee($expected, false);

        $this->assertNotNull($seeded->id);
        $response->assertSee('name="_method" value="PUT"', false);
        $response->assertSee('Save changes');
        $response->assertSee('Line statuses are changed from the board');
        $response->assertSee('Keep me');
        // A stocked line seeded without an image still reads as a product; only
        // a free-text line gets the "to source" sprout.
        $response->assertSee('#package', false);
        $response->assertSee('#sprout', false);

        $this->actingAs($employee)
            ->put(route('customer-requests.update', $request), [
                'customer_name' => 'Edit Edna',
                'items' => [
                    ['id' => $keep->id, 'description' => 'Renamed line', 'quantity' => 3],
                    ['description' => 'Brand new line', 'quantity' => 1],
                ],
            ])
            ->assertRedirect(route('customer-requests.index'))
            ->assertSessionHas('status');

        $this->assertSame('Renamed line', $keep->fresh()->description);
        $this->assertSame(2, $request->fresh()->items()->count());
        // Editing details never moves a line's status.
        $this->assertSame(CustomerRequestItem::STATUS_PENDING, $keep->fresh()->status);
    }

    public function test_board_layout_is_gone(): void
    {
        $this->assertFileDoesNotExist(resource_path('views/layouts/board.blade.php'));
        // Second argument false: do not invoke the autoloader, so this asserts the
        // class is gone rather than that the classmap happens to be fresh.
        $this->assertFalse(class_exists(\App\View\Components\BoardLayout::class, false));
        $this->assertDirectoryDoesNotExist(resource_path('views/customer-requests'));
    }

    public function test_rows_are_grouped_and_counted(): void
    {
        $due = CustomerRequest::factory()->create(['customer_name' => 'Two Liner', 'wanted_on' => today()]);
        CustomerRequestItem::factory()->for($due, 'request')->create(['description' => 'Line one', 'position' => 1]);
        CustomerRequestItem::factory()->for($due, 'request')->create([
            'description' => 'Line two', 'position' => 2, 'status' => CustomerRequestItem::STATUS_ORDERED,
        ]);

        $later = CustomerRequest::factory()->create(['customer_name' => 'Later Larry', 'wanted_on' => null]);
        CustomerRequestItem::factory()->for($later, 'request')->create(['description' => 'Someday', 'position' => 1]);

        $aside = CustomerRequest::factory()->create(['customer_name' => 'Aside Amy', 'wanted_on' => today()]);
        CustomerRequestItem::factory()->for($aside, 'request')->create([
            'description' => 'Waiting', 'position' => 1, 'status' => CustomerRequestItem::STATUS_PUT_ASIDE,
        ]);

        $response = $this->actingAs($this->employee())->get('/customer-requests')->assertOk();

        $response->assertSee('Due today <small>2</small>', false);
        $response->assertSee('Coming up <small>1</small>', false);
        $response->assertSee('Open 3');
        $response->assertSee('Put aside 1');

        // The put-aside line is not on the Open view; it has its own.
        $response->assertDontSee('Waiting');
        $this->actingAs($this->employee())
            ->get(route('customer-requests.index', ['show' => 'aside']))
            ->assertOk()
            ->assertSee('Waiting');
    }

    public function test_done_view_lists_finished_lines_from_the_last_30_days(): void
    {
        $request = CustomerRequest::factory()->create(['customer_name' => 'Done Dora', 'wanted_on' => today()]);

        CustomerRequestItem::factory()->for($request, 'request')->create([
            'description' => 'Collected just now', 'position' => 1,
            'status' => CustomerRequestItem::STATUS_COLLECTED, 'status_changed_at' => now()->subHours(2),
        ]);
        CustomerRequestItem::factory()->for($request, 'request')->create([
            'description' => 'Cancelled recently', 'position' => 2,
            'status' => CustomerRequestItem::STATUS_CANCELLED, 'status_changed_at' => now()->subDays(2),
        ]);
        CustomerRequestItem::factory()->for($request, 'request')->create([
            'description' => 'Unavailable ages ago', 'position' => 3,
            'status' => CustomerRequestItem::STATUS_NOT_AVAILABLE, 'status_changed_at' => now()->subDays(40),
        ]);

        $response = $this->actingAs($this->employee())
            ->get(route('customer-requests.index', ['show' => 'done']))
            ->assertOk();

        $response->assertSee('Done recently');
        $response->assertSee('Collected just now');
        $response->assertSee('Undo last step');
        $response->assertSee('Cancelled recently');
        $response->assertSee('Reopen');
        $response->assertDontSee('Unavailable ages ago');

        // ?closed=1 is kept as an alias so old links still work.
        $this->actingAs($this->employee())
            ->get(route('customer-requests.index', ['closed' => 1]))
            ->assertOk()
            ->assertSee('Done recently')
            ->assertSee('Collected just now');
    }

    public function test_put_aside_view_shows_collected_as_primary(): void
    {
        $request = CustomerRequest::factory()->create(['customer_name' => 'Aside Amy', 'wanted_on' => today()->addWeek()]);
        $item = CustomerRequestItem::factory()->for($request, 'request')->create([
            'description' => 'Waiting', 'position' => 1, 'status' => CustomerRequestItem::STATUS_PUT_ASIDE,
        ]);

        $response = $this->actingAs($this->employee())
            ->get(route('customer-requests.index', ['show' => 'aside']))
            ->assertOk();

        // Collected is always primary, even when the request is not due yet.
        $response->assertSee('shop-btn shop-btn--primary" type="submit">Collected', false);
        $response->assertSee('Undo last step');
        $response->assertSee(route('customer-requests.cancel', $request), false);
        $this->assertSame(
            [CustomerRequestItem::STATUS_COLLECTED, CustomerRequestItem::STATUS_ORDERED, CustomerRequestItem::STATUS_CANCELLED],
            $item->fresh()->nextStatuses()
        );
    }

    public function test_overdue_row_uses_the_late_block_and_keeps_the_sr_text(): void
    {
        $request = CustomerRequest::factory()->create([
            'customer_name' => 'Overdue Olly', 'wanted_on' => today()->subDay(),
        ]);
        CustomerRequestItem::factory()->for($request, 'request')->create(['description' => 'Late thing', 'position' => 1]);

        $response = $this->actingAs($this->employee())->get('/customer-requests')->assertOk();

        $response->assertSee('shop-req__date is-late', false);
        $response->assertSee('Late');
        // The old board's phrasing survives for screen readers.
        $response->assertSee('Overdue 1d');
    }

    public function test_store_from_the_shop_form_creates_a_single_line_request(): void
    {
        $employee = $this->employee();

        $this->actingAs($employee)
            ->post(route('customer-requests.store'), [
                'customer_name' => 'Form Fiona',
                'wanted_on' => today()->toDateString(),
                'items' => [
                    ['product_code' => '5000000000017', 'quantity' => 2],
                ],
            ])
            ->assertRedirect(route('customer-requests.index'))
            ->assertSessionHas('status');

        $this->actingAs($employee)
            ->get('/customer-requests')
            ->assertOk()
            ->assertSee('Form Fiona')
            // The service snapshots the POS name for a stocked line.
            ->assertSee('Oat drink 1 L');
    }

    public function test_failed_submission_reopens_the_form_with_old_input(): void
    {
        $employee = $this->employee();

        $this->actingAs($employee)
            ->from('/customer-requests')
            ->post(route('customer-requests.store'), [
                'customer_name' => '',
                'items' => [
                    ['description' => 'Kept line', 'quantity' => 1],
                ],
            ])
            ->assertRedirect('/customer-requests');

        // Old input is re-seeded the way the office board's test does it: a
        // separate test request does not carry the previous one's flash.
        $response = $this->actingAs($employee)
            ->withSession(['_old_input' => [
                'customer_name' => 'Kept Name',
                'items' => [['description' => 'Kept line', 'quantity' => 1]],
            ]])
            ->get('/customer-requests')
            ->assertOk();

        $response->assertSee('x-data="{ open: true }"', false);
        $response->assertSee('Kept Name', false);
        // The line comes back through the Alpine seed rather than a value
        // attribute, because the description input is x-model bound.
        $response->assertSee('Kept line', false);
    }
}
