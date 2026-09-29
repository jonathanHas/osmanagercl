<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherTillRedemption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Concerns\CreatesVoucherPosTables;
use Tests\TestCase;

/**
 * The manager page for till voucher redemptions that were not simply applied.
 */
class VoucherTillExceptionsTest extends TestCase
{
    use CreatesVoucherPosTables, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('vouchers.sync.on_lookup', false);
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

    private function manager(): User
    {
        return $this->userWith('manager', ['vouchers.redeem', 'vouchers.manage'], 'Tom Byrne');
    }

    private function redemption(array $attributes = []): VoucherTillRedemption
    {
        static $n = 0;
        $n++;

        return VoucherTillRedemption::create($attributes + [
            'pos_ticket_id' => 'ticket-'.$n,
            'pos_product_id' => 'product-'.$n,
            'voucher_code' => 'GV7KQFM2RA9T',
            'ticket_number' => 430000 + $n,
            'sold_at' => now()->subMinutes($n),
            'voucher_tender' => 30,
            'ticket_total' => 49.20,
            'amount_deducted' => 20,
            'shortfall' => 10,
            'status' => VoucherTillRedemption::STATUS_PARTIAL,
        ]);
    }

    public function test_employees_cannot_see_the_exceptions(): void
    {
        $employee = $this->userWith('employee', ['vouchers.redeem']);

        $this->actingAs($employee)->get(route('vouchers.exceptions'))->assertForbidden();
    }

    public function test_managers_see_unreviewed_exceptions(): void
    {
        $voucher = Voucher::create(['code' => 'GV7KQFM2RA9T', 'current_balance' => 0, 'status' => Voucher::STATUS_EXHAUSTED]);
        $this->redemption(['voucher_id' => $voucher->id, 'ticket_number' => 430025]);
        $this->redemption(['status' => VoucherTillRedemption::STATUS_APPLIED, 'ticket_number' => 430099, 'shortfall' => 0]);

        $this->actingAs($this->manager())->get(route('vouchers.exceptions'))
            ->assertOk()
            ->assertSee('430025')
            ->assertSee('Partial')
            ->assertSee('€30.00')
            ->assertSee('€10.00')
            ->assertSee('€49.20')
            ->assertSee(route('vouchers.transactions', $voucher), false)
            // An applied redemption is not an exception.
            ->assertDontSee('430099');
    }

    public function test_the_empty_state(): void
    {
        $this->actingAs($this->manager())->get(route('vouchers.exceptions'))
            ->assertOk()
            ->assertSee('Nothing to review');
    }

    public function test_marking_reviewed_hides_the_row_unless_all_is_asked_for(): void
    {
        $manager = $this->manager();
        $row = $this->redemption(['ticket_number' => 430025, 'note' => 'Voucher scanned but not paid with the Voucher tender.']);

        $this->actingAs($manager)
            ->from(route('vouchers.exceptions'))
            ->post(route('vouchers.exceptions.reviewed', $row), ['note' => 'Customer paid cash, fine'])
            ->assertRedirect(route('vouchers.exceptions'))
            ->assertSessionHas('status', 'Till #430025 marked reviewed.');

        // Spend the flash (it names the ticket) before looking for the row.
        $this->actingAs($manager)->get(route('vouchers.exceptions'))->assertSee('Till #430025 marked reviewed.');

        $row->refresh();
        $this->assertNotNull($row->reviewed_at);
        $this->assertSame($manager->id, $row->reviewed_by);
        $this->assertSame('Voucher scanned but not paid with the Voucher tender. · Customer paid cash, fine', $row->note);

        $this->actingAs($manager)->get(route('vouchers.exceptions'))->assertDontSee('430025');
        $this->actingAs($manager)->get(route('vouchers.exceptions', ['all' => 1]))
            ->assertSee('430025')
            ->assertSee('Tom Byrne');
    }

    public function test_employees_cannot_mark_reviewed(): void
    {
        $row = $this->redemption();
        $employee = $this->userWith('employee', ['vouchers.redeem']);

        $this->actingAs($employee)->post(route('vouchers.exceptions.reviewed', $row))->assertForbidden();
        $this->assertNull($row->fresh()->reviewed_at);
    }

    public function test_the_till_screen_header_counts_unreviewed_exceptions_for_managers(): void
    {
        $this->redemption();
        $this->redemption(['status' => VoucherTillRedemption::STATUS_NO_TENDER]);
        $this->redemption(['reviewed_at' => now()]);
        $this->redemption(['status' => VoucherTillRedemption::STATUS_APPLIED]);

        $this->actingAs($this->manager())->get(route('vouchers.index'))
            ->assertOk()
            ->assertSee('Till exceptions (2)');

        $this->actingAs($this->userWith('employee', ['vouchers.redeem']))->get(route('vouchers.index'))
            ->assertOk()
            ->assertDontSee('Till exceptions (');
    }

    public function test_the_header_link_is_hidden_when_there_is_nothing_to_review(): void
    {
        $this->actingAs($this->manager())->get(route('vouchers.index'))
            ->assertOk()
            ->assertDontSee('Till exceptions (');
    }

    public function test_the_sync_command_prints_its_counts(): void
    {
        $this->createVoucherPosTables();

        $this->artisan('vouchers:sync-till')
            ->expectsOutputToContain('no_tender')
            ->assertExitCode(0);
    }

    // --- Vouchers cycle 3 ---

    public function test_a_flagged_sale_shows_with_its_amount_and_an_activation_does_not(): void
    {
        $this->redemption([
            'ticket_number' => 430200,
            'status' => VoucherTillRedemption::STATUS_SALE_FLAGGED,
            'sale_amount' => 40,
            'amount_deducted' => 0,
            'shortfall' => 0,
            'voucher_tender' => 0,
            'note' => 'Quantity 2 on one voucher (charged €40.00). Not activated: each voucher is scanned itself.',
        ]);
        $this->redemption([
            'ticket_number' => 430201,
            'status' => VoucherTillRedemption::STATUS_ACTIVATED,
            'sale_amount' => 20,
            'amount_deducted' => 0,
            'shortfall' => 0,
        ]);

        $this->actingAs($this->manager())->get(route('vouchers.exceptions'))
            ->assertOk()
            ->assertSee('430200')
            ->assertSee('Sale not activated')
            ->assertSee('Quantity 2 on one voucher')
            ->assertSee('€40.00')
            ->assertDontSee('430201');
    }
}
