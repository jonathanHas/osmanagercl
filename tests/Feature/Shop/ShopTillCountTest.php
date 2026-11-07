<?php

namespace Tests\Feature\Shop;

use App\Models\Permission;
use App\Models\Role;
use App\Models\ShopDevice;
use App\Models\User;
use App\Services\Cash\TillCountService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPosTillFixture;
use Tests\TestCase;

/**
 * The Shop till count screen and its office twin: who may open them, what they
 * show for a closed, counted and open till, and the Home tile.
 */
class ShopTillCountTest extends TestCase
{
    use BuildsPosTillFixture;
    use RefreshDatabase;

    private User $employee;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPos();

        $employeeRole = Role::firstOrCreate(['name' => 'employee'], ['display_name' => 'Employee']);
        $managerRole = Role::firstOrCreate(['name' => 'manager'], ['display_name' => 'Manager']);
        Role::firstOrCreate(['name' => 'barista'], ['display_name' => 'Barista']);

        foreach (['cash_reconciliation.view', 'cash_reconciliation.create'] as $name) {
            $permission = Permission::firstOrCreate(['name' => $name], ['display_name' => $name, 'module' => 'Cash']);
            $employeeRole->permissions()->syncWithoutDetaching($permission->id);
            $managerRole->permissions()->syncWithoutDetaching($permission->id);
        }

        $this->employee = User::factory()->withPin('2580')->create(['role_id' => $employeeRole->id, 'name' => 'Katelyn Byrne']);
        $this->manager = User::factory()->create(['role_id' => $managerRole->id, 'name' => 'Tess Manager']);

        Carbon::setTestNow('2026-10-14 19:30:00');
        $this->closeTill('OrgStore', 'A1', Carbon::parse('2026-10-14 17:54'), [['cash', 697.63], ['magcard', 1673.81]]);
        $this->closeTill('Till 2', 'B1', Carbon::parse('2026-10-14 18:47'), [['cash', 379.04], ['magcard', 926.01], ['paperin', 25]]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_an_employee_opens_the_screen_on_the_first_uncounted_till(): void
    {
        $response = $this->actingAs($this->employee)->get(route('shop.till-count'));

        $response->assertOk()
            ->assertSee('Till count')
            ->assertSee('Who is counting?')
            ->assertSee('Count the drawer')
            ->assertSee('Closed 17:54')     // OrgStore, the first uncounted till
            ->assertSee('Not counted')
            ->assertDontSee('vouchers €25.00'); // Till 2's voucher tender shows only on Till 2

        $this->actingAs($this->employee)->get(route('shop.till-count', ['till' => $this->till('Till 2')->id]))
            ->assertOk()
            ->assertSee('Closed 18:47')
            ->assertSee('vouchers €25.00');
    }

    public function test_the_screen_carries_the_state_and_people_the_component_needs(): void
    {
        $response = $this->actingAs($this->employee)
            ->withCookies([config('shop.device_cookie') => tap('device-token-for-tests-0123456789abcdef', fn ($t) => ShopDevice::factory()->token($t)->create())])
            ->get(route('shop.till-count', ['till' => $this->till('Till 2')->id, 'date' => '2026-10-14']));

        $response->assertOk();

        preg_match('/data-state="([^"]+)"/', $response->getContent(), $m);
        $state = json_decode(html_entity_decode($m[1]), true);

        $this->assertSame('B1', $state['state']['session']['id']);
        $this->assertSame(379.04, $state['state']['pos']['cash']);
        $this->assertSame(926.01, $state['state']['pos']['card']);
        $this->assertFalse($state['state']['counted']);
        $this->assertNull($state['state']['previous_float']);
        $this->assertNull($state['identity']);

        $people = collect($state['people'])->keyBy('name');
        $this->assertTrue($people['Katelyn Byrne']['pin'], 'An employee with a PIN on a trusted device identifies by PIN.');
        $this->assertFalse($people['Tess Manager']['pin'], 'A manager has no PIN and uses a password.');
    }

    public function test_a_counted_till_says_who_counted_it(): void
    {
        $service = app(TillCountService::class);
        $service->save($service->session('B1'), $this->till('Till 2'), $this->employee, $this->manager, [
            'cash_50' => 10, 'note_float' => 100, 'coin_float' => null, 'card' => 0, 'money_added' => 0, 'payments' => [], 'note' => '',
        ]);

        $this->actingAs($this->manager)->get(route('shop.till-count', ['till' => $this->till('Till 2')->id]))
            ->assertOk()
            ->assertSee('Counted by')
            ->assertSee('Katelyn Byrne')
            ->assertSee('To change it, say who you are below.');
    }

    public function test_an_open_till_asks_for_it_to_be_closed_first(): void
    {
        $this->openTill('OrgStore', 'A-open', Carbon::parse('2026-10-14 17:54'));

        $this->actingAs($this->employee)->get(route('shop.till-count', ['till' => $this->till('OrgStore')->id, 'date' => '2026-10-15']))
            ->assertOk()
            ->assertSee('OrgStore is still open')
            ->assertSee('Last close: Wed 14 Oct');
    }

    public function test_a_barista_is_forbidden(): void
    {
        $barista = User::factory()->create(['role_id' => Role::where('name', 'barista')->first()->id]);

        $this->actingAs($barista)->get(route('shop.till-count'))->assertForbidden();
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        $this->get(route('shop.till-count'))->assertRedirect(route('login'));
    }

    public function test_home_shows_the_tile_with_the_number_of_uncounted_tills(): void
    {
        $response = $this->actingAs($this->employee)->get(route('shop.home'));

        $response->assertOk()
            ->assertSee('Till count')
            ->assertSee(route('shop.till-count'), false)
            ->assertSee('aria-label="2 waiting"', false);
    }

    public function test_the_office_page_renders_the_same_form_with_the_history(): void
    {
        $service = app(TillCountService::class);
        $service->save($service->session('A1'), $this->till('OrgStore'), $this->employee, $this->manager, [
            'cash_50' => 10, 'note_float' => 100, 'coin_float' => null, 'card' => 0, 'money_added' => 0, 'payments' => [], 'note' => '',
        ]);

        $response = $this->actingAs($this->manager)->get(route('cash-reconciliation.index', ['date' => '2026-10-14', 'till_id' => $this->till('OrgStore')->id]));

        $response->assertOk()
            ->assertSee('shop shop--embed', false)
            ->assertSee('x-data="shopTillCount()"', false)
            ->assertSee('Recent counts')
            ->assertSee('Katelyn Byrne')
            ->assertSee('Import from old system')
            // The Shop bundle is pushed into the layout head (Figtree weights the Shop needs).
            ->assertSee('Figtree:wght@500;600;700;800', false);
    }
}
