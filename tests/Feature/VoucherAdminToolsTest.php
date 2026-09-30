<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Concerns\CreatesVoucherPosTables;
use Tests\TestCase;

/**
 * Admin changeover tools (vouchers cycle 4): routes, access, the switch, and the list page.
 */
class VoucherAdminToolsTest extends TestCase
{
    use CreatesVoucherPosTables, RefreshDatabase;

    private const ROUTES = [
        'vouchers.bulk.deactivate',
        'vouchers.bulk.reactivate',
        'vouchers.bulk.delete',
        'vouchers.bulk.restore',
        'vouchers.bulk.for-sale',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('vouchers.sync.on_lookup', false);
        $this->createVoucherPosTables();
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function userWith(string $roleName, array $permissions, string $name = 'Maya Jensen'): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName)]);

        foreach ($permissions as $permissionName) {
            $role->givePermissionTo(Permission::firstOrCreate(
                ['name' => $permissionName],
                ['display_name' => $permissionName, 'module' => 'Vouchers']
            ));
        }

        return User::factory()->create(['role_id' => $role->id, 'name' => $name]);
    }

    private function admin(): User
    {
        return $this->userWith('admin', ['vouchers.redeem', 'vouchers.manage'], 'Ada Admin');
    }

    private function manager(): User
    {
        return $this->userWith('manager', ['vouchers.redeem', 'vouchers.manage'], 'Tom Byrne');
    }

    private function voucher(string $code, string $status = Voucher::STATUS_ACTIVE, float $balance = 10): Voucher
    {
        return Voucher::create([
            'code' => $code,
            'initial_value' => $status === Voucher::STATUS_INACTIVE ? null : $balance,
            'current_balance' => $balance,
            'status' => $status,
        ]);
    }

    // --- Access ---

    public function test_managers_and_employees_are_forbidden_and_guests_sent_to_login(): void
    {
        $v = $this->voucher('GVACTIVE0001');
        $manager = $this->manager();
        $employee = $this->userWith('employee', ['vouchers.redeem']);

        foreach (self::ROUTES as $route) {
            $this->actingAs($manager)->post(route($route), ['ids' => [$v->id], 'note' => 'Test note'])->assertForbidden();
            $this->actingAs($employee)->post(route($route), ['ids' => [$v->id], 'note' => 'Test note'])->assertForbidden();
        }

        auth()->logout();
        $this->post(route('vouchers.bulk.deactivate'), ['ids' => [$v->id]])->assertRedirect('/login');

        $this->assertSame(Voucher::STATUS_ACTIVE, $v->fresh()->status);
    }

    public function test_an_admin_deactivates_and_the_flash_names_done_and_skipped(): void
    {
        $a = $this->voucher('GVACTIVE0001');
        $b = $this->voucher('GVDEACT00001', Voucher::STATUS_DEACTIVATED);

        $this->actingAs($this->admin())
            ->from(route('vouchers.list'))
            ->post(route('vouchers.bulk.deactivate'), ['ids' => [$a->id, $b->id], 'note' => 'Changeover'])
            ->assertRedirect(route('vouchers.list'))
            ->assertSessionHas('status', 'Deactivated 1 voucher. Skipped 1: GVDEACT00001 (not active).');

        $this->assertSame(Voucher::STATUS_DEACTIVATED, $a->fresh()->status);
    }

    public function test_the_flash_lists_at_most_ten_skipped_codes(): void
    {
        $ids = [];
        foreach (range(1, 12) as $i) {
            $ids[] = $this->voucher(sprintf('GVINACT%05d', $i), Voucher::STATUS_INACTIVE, 0)->id;
        }

        $this->actingAs($this->admin())
            ->post(route('vouchers.bulk.deactivate'), ['ids' => $ids])
            ->assertSessionHas('status', fn ($s) => str_starts_with($s, 'Deactivated 0 vouchers. Skipped 12: GVINACT00001 (not active)')
                && str_ends_with($s, 'GVINACT00010 (not active) and 2 more.'));
    }

    public function test_every_action_works_for_an_admin(): void
    {
        $admin = $this->admin();
        $v = $this->voucher('GVACTIVE0001', balance: 20);

        $this->actingAs($admin)->post(route('vouchers.bulk.for-sale'), ['ids' => [$v->id]])
            ->assertSessionHas('status', 'Made for sale: 1 voucher.');
        $this->assertTrue($v->fresh()->isForSale());

        $this->actingAs($admin)->post(route('vouchers.bulk.delete'), ['ids' => [$v->id], 'note' => 'Test label'])
            ->assertSessionHas('status', 'Deleted 1 voucher.');
        $this->assertTrue(Voucher::withTrashed()->find($v->id)->trashed());

        $this->actingAs($admin)->post(route('vouchers.bulk.restore'), ['ids' => [$v->id]])
            ->assertSessionHas('status', 'Restored 1 voucher.');
        $this->assertFalse($v->fresh()->trashed());

        $w = $this->voucher('GVDEACT00001', Voucher::STATUS_DEACTIVATED);
        $this->actingAs($admin)->post(route('vouchers.bulk.reactivate'), ['ids' => [$w->id]])
            ->assertSessionHas('status', 'Reactivated 1 voucher.');
        $this->assertSame(Voucher::STATUS_ACTIVE, $w->fresh()->status);
    }

    // --- Validation ---

    public function test_validation(): void
    {
        $admin = $this->admin();
        $v = $this->voucher('GVDEACT00001', Voucher::STATUS_DEACTIVATED);

        $this->actingAs($admin)->post(route('vouchers.bulk.deactivate'), ['ids' => []])->assertSessionHasErrors('ids');
        $this->actingAs($admin)->post(route('vouchers.bulk.deactivate'), [])->assertSessionHasErrors('ids');
        $this->actingAs($admin)->post(route('vouchers.bulk.deactivate'), ['ids' => range(1, 201)])->assertSessionHasErrors('ids');
        $this->actingAs($admin)->post(route('vouchers.bulk.deactivate'), ['ids' => ['abc']])->assertSessionHasErrors('ids.0');

        $this->actingAs($admin)->post(route('vouchers.bulk.delete'), ['ids' => [$v->id]])->assertSessionHasErrors('note');
        $this->actingAs($admin)->post(route('vouchers.bulk.delete'), ['ids' => [$v->id], 'note' => 'ab'])->assertSessionHasErrors('note');
        $this->assertFalse($v->fresh()->trashed());
    }

    // --- The switch ---

    public function test_with_the_switch_off_the_routes_are_404_and_the_list_has_no_tools(): void
    {
        Config::set('vouchers.admin_tools', false);
        $admin = $this->admin();
        $v = $this->voucher('GVACTIVE0001');

        foreach (self::ROUTES as $route) {
            $this->actingAs($admin)->post(route($route), ['ids' => [$v->id], 'note' => 'Test note'])->assertNotFound();
        }
        $this->assertSame(Voucher::STATUS_ACTIVE, $v->fresh()->status);

        $this->actingAs($admin)->get(route('vouchers.list'))
            ->assertOk()
            ->assertDontSee('voucher-bulk', false)
            ->assertDontSee('Make for sale')
            ->assertDontSee('Deactivate selected')
            ->assertDontSee('value="deleted"', false);
    }

    // --- List page ---

    public function test_the_list_page_has_the_tools_for_an_admin_only(): void
    {
        $v = $this->voucher('GVACTIVE0001');

        $this->actingAs($this->admin())->get(route('vouchers.list'))
            ->assertOk()
            ->assertSee('id="voucher-bulk"', false)
            ->assertSee('name="ids[]" form="voucher-bulk" value="'.$v->id.'"', false)
            ->assertSee('data-bulk-all', false)
            ->assertSee('Make for sale')
            ->assertSee('Deactivate selected')
            ->assertSee('Delete selected')
            ->assertSee('value="deleted"', false);

        $this->actingAs($this->manager())->get(route('vouchers.list'))
            ->assertOk()
            ->assertDontSee('voucher-bulk', false)
            ->assertDontSee('Make for sale')
            ->assertDontSee('value="deleted"', false);
    }

    public function test_per_page(): void
    {
        foreach (range(1, 30) as $i) {
            $this->voucher(sprintf('GVPAGE%06d', $i));
        }
        $admin = $this->admin();

        $this->assertSame(100, $this->actingAs($admin)->get(route('vouchers.list', ['per_page' => 100]))->viewData('vouchers')->perPage());
        $this->assertSame(25, $this->actingAs($admin)->get(route('vouchers.list', ['per_page' => 7]))->viewData('vouchers')->perPage());
        $this->assertSame(25, $this->actingAs($admin)->get(route('vouchers.list'))->viewData('vouchers')->perPage());
    }

    public function test_the_deleted_view_is_for_admins_only(): void
    {
        $kept = $this->voucher('GVKEPT000001');
        $gone = $this->voucher('GVGONE000001', Voucher::STATUS_DEACTIVATED);
        $gone->delete();

        $this->actingAs($this->admin())->get(route('vouchers.list', ['status' => 'deleted']))
            ->assertOk()
            ->assertSee('GVGONE000001')
            ->assertDontSee('GVKEPT000001')
            ->assertSee('Restore selected')
            // No "Make for sale" button on the Deleted view (the words also appear in the dialog script).
            ->assertDontSee("ask('forSale')", false);

        // A manager asking for deleted vouchers gets the normal list.
        $this->actingAs($this->manager())->get(route('vouchers.list', ['status' => 'deleted']))
            ->assertOk()
            ->assertSee('GVKEPT000001')
            ->assertDontSee('GVGONE000001');

        // And the normal list never shows a deleted voucher.
        $this->actingAs($this->admin())->get(route('vouchers.list'))
            ->assertSee('GVKEPT000001')
            ->assertDontSee('GVGONE000001');
    }
}
