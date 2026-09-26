<?php

namespace Tests\Feature;

use App\Models\CustomerRequest;
use App\Models\CustomerRequestItem;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\CustomerRequestService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Customer requests: the public board, staff-only writes, the line status
 * lifecycle and the product typeahead.
 */
class CustomerRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // In-memory POS connection with the tables the product search touches.
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
            $table->string('TAXCAT')->nullable();
        });

        // Product::scopeSearch() also looks up supplier codes.
        $pos->create('supplier_link', function (Blueprint $table) {
            $table->string('Barcode');
            $table->string('SupplierCode')->nullable();
            $table->string('SupplierID')->nullable();
        });

        DB::connection('pos')->table('PRODUCTS')->insert([
            ['ID' => 'p1', 'NAME' => 'Organic Oat Milk 1L', 'CODE' => '5000000000017', 'REFERENCE' => 'OAT1', 'CATEGORY' => 'c1', 'PRICESELL' => 2.10, 'TAXCAT' => '001'],
            ['ID' => 'p2', 'NAME' => 'Almond Butter 250g', 'CODE' => '5000000000024', 'REFERENCE' => 'ALM250', 'CATEGORY' => 'c1', 'PRICESELL' => 5.50, 'TAXCAT' => '001'],
        ]);
    }

    private function admin(): User
    {
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);

        return User::factory()->create(['role_id' => $role->id]);
    }

    /**
     * An employee whose role carries only the customer-requests permission.
     */
    private function employee(bool $withPermission = true): User
    {
        $role = Role::firstOrCreate(['name' => 'employee'], ['display_name' => 'Employee']);

        if ($withPermission) {
            $permission = Permission::firstOrCreate(
                ['name' => 'customer-requests.manage'],
                ['display_name' => 'Manage Customer Requests', 'module' => 'Customer Requests']
            );
            $role->givePermissionTo($permission);
        }

        return User::factory()->create(['role_id' => $role->id]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function payload(array $items, array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'Jane Doe',
            'customer_phone' => '087 123 4567',
            'wanted_on' => today()->addDays(3)->toDateString(),
            'notes' => 'Ring when in.',
            'items' => $items,
        ], $overrides);
    }

    // ---------------------------------------------------------------- board

    public function test_guest_can_view_the_board_without_controls(): void
    {
        $request = CustomerRequest::factory()->create(['customer_name' => 'Walk-in Wendy']);
        CustomerRequestItem::factory()->for($request, 'request')->create(['description' => 'Oat milk']);

        $response = $this->get(route('customer-requests.index'));

        $response->assertOk();
        $response->assertSee('Walk-in Wendy');
        $response->assertSee('Oat milk');
        $response->assertSee('Staff sign in');
        $response->assertSee(route('login', ['redirect' => '/customer-requests']), false);
        $response->assertDontSee('New request');
        $response->assertDontSee(route('customer-requests.items.status', $request->items->first()));
    }

    public function test_staff_see_controls_on_the_board(): void
    {
        $request = CustomerRequest::factory()->create();
        $item = CustomerRequestItem::factory()->for($request, 'request')->create();

        $response = $this->actingAs($this->employee())->get(route('customer-requests.index'));

        $response->assertOk();
        $response->assertSee('New request');
        $response->assertSee(route('customer-requests.items.status', $item));
        // Cycle 13 replaced the "Show closed" link with a segmented view filter;
        // the Done view is the same set of requests.
        $response->assertSee('Done');
        $response->assertSee(route('customer-requests.index', ['show' => 'aside']), false);
    }

    public function test_board_groups_due_open_and_hides_closed(): void
    {
        $overdue = CustomerRequest::factory()->create(['customer_name' => 'Overdue Olly', 'wanted_on' => today()->subDay()]);
        CustomerRequestItem::factory()->for($overdue, 'request')->create();

        $dueToday = CustomerRequest::factory()->create(['customer_name' => 'Today Tara', 'wanted_on' => today()]);
        CustomerRequestItem::factory()->for($dueToday, 'request')->create();

        $future = CustomerRequest::factory()->create(['customer_name' => 'Future Fred', 'wanted_on' => today()->addDay()]);
        CustomerRequestItem::factory()->for($future, 'request')->create();

        $closed = CustomerRequest::factory()->create(['customer_name' => 'Closed Cleo', 'wanted_on' => today()->subDay(), 'closed_at' => now()]);
        CustomerRequestItem::factory()->for($closed, 'request')->status(CustomerRequestItem::STATUS_COLLECTED)->create();

        $board = app(CustomerRequestService::class)->board();

        $this->assertSame(['Overdue Olly', 'Today Tara'], $board['due']->pluck('customer_name')->all());
        $this->assertSame(['Future Fred'], $board['open']->pluck('customer_name')->all());

        $response = $this->get(route('customer-requests.index'));
        $response->assertOk();
        $response->assertSee('Overdue Olly');
        $response->assertSee('Overdue 1d');
        $response->assertSee('Due today');
        $response->assertDontSee('Closed Cleo');
    }

    public function test_closed_requests_are_only_shown_to_staff_who_ask(): void
    {
        $closed = CustomerRequest::factory()->create(['customer_name' => 'Closed Cleo', 'closed_at' => now()]);
        CustomerRequestItem::factory()->for($closed, 'request')->status(CustomerRequestItem::STATUS_COLLECTED)->create();

        $this->get(route('customer-requests.index', ['closed' => 1]))->assertDontSee('Closed Cleo');

        $this->actingAs($this->employee())
            ->get(route('customer-requests.index', ['closed' => 1]))
            ->assertSee('Closed Cleo');
    }

    // ----------------------------------------------------------------- auth

    public function test_guest_is_redirected_to_login_for_writes(): void
    {
        $request = CustomerRequest::factory()->create();
        $item = CustomerRequestItem::factory()->for($request, 'request')->create();

        $this->get(route('customer-requests.create'))->assertRedirect(route('login'));
        $this->post(route('customer-requests.store'), $this->payload([]))->assertRedirect(route('login'));
        $this->patch(route('customer-requests.items.status', $item), ['status' => 'ordered'])->assertRedirect(route('login'));

        $this->assertDatabaseCount('customer_requests', 1);
        $this->assertSame('pending', $item->fresh()->status);
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $user = $this->employee(withPermission: false);

        $this->actingAs($user)->get(route('customer-requests.create'))->assertForbidden();
        $this->actingAs($user)->post(route('customer-requests.store'), $this->payload([
            ['description' => 'Anything', 'quantity' => 1],
        ]))->assertForbidden();

        $this->assertDatabaseCount('customer_requests', 0);
    }

    // ---------------------------------------------------------------- create

    public function test_employee_can_create_a_request_with_a_stocked_and_a_sourced_line(): void
    {
        $user = $this->employee();

        $response = $this->actingAs($user)->post(route('customer-requests.store'), $this->payload([
            ['product_code' => '5000000000017', 'description' => '', 'quantity' => 2],
            ['description' => 'Gluten-free sourdough (any brand)', 'quantity' => 1, 'notes' => 'Seeded if possible'],
        ]));

        $response->assertRedirect(route('customer-requests.index'));
        $response->assertSessionHas('status');

        $request = CustomerRequest::sole();
        $this->assertSame('Jane Doe', $request->customer_name);
        $this->assertSame($user->id, $request->created_by);
        $this->assertNull($request->closed_at);

        $items = $request->items;
        $this->assertCount(2, $items);

        $stocked = $items[0];
        $this->assertSame('5000000000017', $stocked->product_code);
        $this->assertSame('Organic Oat Milk 1L', $stocked->product_name, 'POS name is snapshotted');
        $this->assertSame('Organic Oat Milk 1L', $stocked->description);
        $this->assertSame('pending', $stocked->status);
        $this->assertSame($user->id, $stocked->status_changed_by);
        $this->assertCount(1, $stocked->statusLogs);
        $this->assertNull($stocked->statusLogs->first()->from_status);
        $this->assertSame('pending', $stocked->statusLogs->first()->to_status);

        $sourced = $items[1];
        $this->assertNull($sourced->product_code);
        $this->assertNull($sourced->product_name);
        $this->assertSame('Gluten-free sourdough (any brand)', $sourced->description);
        $this->assertSame('Seeded if possible', $sourced->notes);
    }

    public function test_validation_rejects_bad_input_and_keeps_old_input(): void
    {
        $user = $this->employee();

        $this->actingAs($user)
            ->from(route('customer-requests.index'))
            ->post(route('customer-requests.store'), $this->payload([], ['customer_name' => '']))
            ->assertRedirect(route('customer-requests.index'))
            ->assertSessionHasErrors(['customer_name', 'items']);

        $this->actingAs($user)
            ->from(route('customer-requests.index'))
            ->post(route('customer-requests.store'), $this->payload([
                ['description' => '', 'quantity' => 0],
            ]))
            ->assertSessionHasErrors(['items.0.description', 'items.0.quantity']);

        $this->assertDatabaseCount('customer_requests', 0);
        $this->assertDatabaseCount('customer_request_items', 0);

        // The bounced form re-seeds from old() so nothing typed is lost, and the modal re-opens.
        $response = $this->actingAs($user)
            ->withSession(['_old_input' => $this->payload([['description' => 'Kept line', 'quantity' => 3]], ['customer_name' => 'Kept Name'])])
            ->get(route('customer-requests.index'));
        $response->assertOk();
        $response->assertSee('Kept Name');
        $response->assertSee('Kept line');
        $response->assertSee('x-data="{ open: true }"', false);
    }

    public function test_create_route_opens_the_new_request_modal_on_the_board(): void
    {
        $user = $this->employee();

        $this->actingAs($user)->get(route('customer-requests.create'))
            ->assertRedirect(route('customer-requests.index', ['new' => 1]));

        $this->actingAs($user)->get(route('customer-requests.index', ['new' => 1]))
            ->assertOk()
            ->assertSee('x-data="{ open: true }"', false);

        $this->actingAs($user)->get(route('customer-requests.index'))
            ->assertOk()
            ->assertSee('x-data="{ open: false }"', false);
    }

    // ---------------------------------------------------------------- update

    public function test_update_syncs_lines_without_touching_status(): void
    {
        $user = $this->employee();
        $service = app(CustomerRequestService::class);

        $request = $service->create(['customer_name' => 'Jane Doe'], [
            ['description' => 'Keep me', 'quantity' => 1],
            ['description' => 'Delete me', 'quantity' => 1],
        ], $user);
        [$keep, $delete] = $request->items;
        $service->changeItemStatus($keep, 'ordered', $user);

        $this->actingAs($user)->get(route('customer-requests.edit', $request))
            ->assertOk()
            ->assertSee('Keep me')
            ->assertSee('Delete me');

        $response = $this->actingAs($user)->put(route('customer-requests.update', $request), $this->payload([
            ['id' => $keep->id, 'description' => 'Keep me (renamed)', 'quantity' => 5],
            ['description' => 'New line', 'quantity' => 2],
        ], ['customer_name' => 'Jane Doe-Smith']));

        $response->assertRedirect(route('customer-requests.index'));

        $request->refresh();
        $this->assertSame('Jane Doe-Smith', $request->customer_name);
        $this->assertSame($user->id, $request->updated_by);

        $items = $request->items;
        $this->assertCount(2, $items);
        $this->assertSame('Keep me (renamed)', $items[0]->description);
        $this->assertSame('ordered', $items[0]->status, 'editing must not reset a line status');
        $this->assertEquals(5, (float) $items[0]->quantity);
        $this->assertSame('New line', $items[1]->description);
        $this->assertSame('pending', $items[1]->status);
        $this->assertDatabaseMissing('customer_request_items', ['id' => $delete->id]);
    }

    public function test_update_rejects_a_line_from_another_request(): void
    {
        $user = $this->employee();
        $mine = CustomerRequest::factory()->create();
        CustomerRequestItem::factory()->for($mine, 'request')->create();
        $theirs = CustomerRequest::factory()->create();
        $foreign = CustomerRequestItem::factory()->for($theirs, 'request')->create(['description' => 'Not yours']);

        $this->actingAs($user)
            ->from(route('customer-requests.edit', $mine))
            ->put(route('customer-requests.update', $mine), $this->payload([
                ['id' => $foreign->id, 'description' => 'Hijacked', 'quantity' => 1],
            ]))
            ->assertSessionHasErrors(['items.0.id']);

        $this->assertSame('Not yours', $foreign->fresh()->description);
    }

    // ---------------------------------------------------------------- status

    public function test_status_transitions_are_logged_and_close_the_request(): void
    {
        $user = $this->employee();
        $request = CustomerRequest::factory()->create();
        $item = CustomerRequestItem::factory()->for($request, 'request')->create();

        foreach (['ordered', 'put_aside', 'collected'] as $status) {
            $this->actingAs($user)
                ->from(route('customer-requests.index'))
                ->patch(route('customer-requests.items.status', $item), ['status' => $status])
                ->assertRedirect(route('customer-requests.index'))
                ->assertSessionHas('status');
        }

        $item->refresh();
        $this->assertSame('collected', $item->status);
        $this->assertSame($user->id, $item->status_changed_by);
        $this->assertNotNull($item->status_changed_at);
        $this->assertSame(
            ['ordered', 'put_aside', 'collected'],
            $item->statusLogs->pluck('to_status')->all()
        );
        $this->assertSame([$user->id, $user->id, $user->id], $item->statusLogs->pluck('user_id')->all());

        $request->refresh();
        $this->assertNotNull($request->closed_at, 'all lines terminal -> request closes');
        $this->assertSame($user->id, $request->closed_by);
    }

    public function test_illegal_transition_is_rejected(): void
    {
        $user = $this->employee();
        $request = CustomerRequest::factory()->create();
        $item = CustomerRequestItem::factory()->for($request, 'request')->create();

        $this->actingAs($user)
            ->from(route('customer-requests.index'))
            ->patch(route('customer-requests.items.status', $item), ['status' => 'collected'])
            ->assertRedirect(route('customer-requests.index'))
            ->assertSessionHasErrors(['status']);

        $this->assertSame('pending', $item->fresh()->status);
        $this->assertCount(0, $item->statusLogs);

        $this->actingAs($user)
            ->patch(route('customer-requests.items.status', $item), ['status' => 'bogus'])
            ->assertSessionHasErrors(['status']);
    }

    public function test_status_change_returns_json_when_asked(): void
    {
        $user = $this->employee();
        $request = CustomerRequest::factory()->create(['customer_name' => 'Jane Doe']);
        $item = CustomerRequestItem::factory()->for($request, 'request')->status('ordered')->create();

        $response = $this->actingAs($user)->patchJson(route('customer-requests.items.status', $item), ['status' => 'put_aside']);

        $response->assertOk()->assertJson([
            'ok' => true,
            'status' => 'put_aside',
            'label' => 'Put aside',
            'request_closed' => false,
        ]);

        $this->actingAs($user)
            ->patchJson(route('customer-requests.items.status', $item), ['status' => 'pending'])
            ->assertStatus(422)
            ->assertJson(['ok' => false]);
    }

    public function test_request_reopens_when_a_line_is_added_or_moved_back(): void
    {
        $user = $this->employee();
        $service = app(CustomerRequestService::class);

        $request = $service->create(['customer_name' => 'Jane Doe'], [['description' => 'Only line', 'quantity' => 1]], $user);
        $item = $request->items->first();

        $service->changeItemStatus($item, 'not_available', $user);
        $this->assertNotNull($request->fresh()->closed_at);

        $service->changeItemStatus($item->fresh(), 'pending', $user);
        $this->assertNull($request->fresh()->closed_at);

        $service->changeItemStatus($item->fresh(), 'cancelled', $user);
        $this->assertNotNull($request->fresh()->closed_at);

        $service->update($request->fresh(), ['customer_name' => 'Jane Doe'], [
            ['id' => $item->id, 'description' => 'Only line', 'quantity' => 1],
            ['description' => 'Another go', 'quantity' => 1],
        ], $user);
        $this->assertNull($request->fresh()->closed_at, 'adding a pending line reopens the request');
    }

    public function test_cancel_closes_every_open_line(): void
    {
        $user = $this->employee();
        $request = CustomerRequest::factory()->create();
        $a = CustomerRequestItem::factory()->for($request, 'request')->create();
        $b = CustomerRequestItem::factory()->for($request, 'request')->status('put_aside')->create();
        $done = CustomerRequestItem::factory()->for($request, 'request')->status('collected')->create();

        $this->actingAs($user)
            ->post(route('customer-requests.cancel', $request))
            ->assertRedirect(route('customer-requests.index'));

        $this->assertSame('cancelled', $a->fresh()->status);
        $this->assertSame('cancelled', $b->fresh()->status);
        $this->assertSame('collected', $done->fresh()->status);
        $this->assertNotNull($request->fresh()->closed_at);
    }

    // ------------------------------------------------------------- lookups

    public function test_awaiting_arrival_lookup_only_returns_pending_and_ordered_lines(): void
    {
        $jane = CustomerRequest::factory()->create(['customer_name' => 'Jane']);
        CustomerRequestItem::factory()->for($jane, 'request')->forProduct('5000000000017', 'Oat')->status('ordered')->create();
        $bob = CustomerRequest::factory()->create(['customer_name' => 'Bob']);
        CustomerRequestItem::factory()->for($bob, 'request')->forProduct('5000000000017', 'Oat')->status('put_aside')->create();
        CustomerRequestItem::factory()->for($bob, 'request')->forProduct('5000000000024', 'Almond')->create();

        $service = app(CustomerRequestService::class);

        $byBarcode = $service->awaitingArrivalByBarcode(['5000000000017', null, '', 'unknown']);
        // PHP casts numeric-string array keys to int, so compare loosely.
        $this->assertEquals(['5000000000017'], $byBarcode->keys()->all());
        $this->assertSame(['Jane'], $byBarcode['5000000000017']->map(fn ($i) => $i->request->customer_name)->all());

        $this->assertSame([], $service->awaitingArrivalByBarcode([])->all());

        $payload = $service->awaitingArrivalPayload('5000000000024');
        $this->assertCount(1, $payload);
        $this->assertSame('Bob', $payload[0]['customer_name']);
        $this->assertSame('pending', $payload[0]['status']);
    }

    public function test_show_page_lists_status_history(): void
    {
        $user = $this->employee();
        $service = app(CustomerRequestService::class);
        $request = $service->create(['customer_name' => 'Jane Doe'], [['description' => 'Oat milk', 'quantity' => 1]], $user);
        $service->changeItemStatus($request->items->first(), 'ordered', $user, 'Udea order #123');

        $response = $this->actingAs($user)->get(route('customer-requests.show', $request));

        $response->assertOk();
        $response->assertSee('Status history');
        $response->assertSee('Udea order #123');
        $response->assertSee($user->name);
    }
}
