<?php

namespace Tests\Feature;

use App\Models\CashBagVerification;
use App\Models\CashLodgement;
use App\Models\CashReconciliation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashBagVerificationEditTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->admin = $this->userWithRole('admin');
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->create([
            'role_id' => Role::where('name', $role)->firstOrFail()->id,
        ]);
    }

    public function test_an_unlodged_bag_count_can_be_corrected(): void
    {
        // Reconciliation holding €270 counted, €70 float => €200 available to lodge.
        $reconciliation = $this->makeReconciliation();
        $verification = $this->makeVerification($reconciliation, ['cash_50' => 6]); // €300, €100 over

        $response = $this->actingAs($this->admin)->post(route('management.cash-lodgements.update-bag'), [
            'verification_id' => $verification->id,
            'cash_50' => 4,
        ]);

        $response->assertSessionHas('success');
        $response->assertSessionMissing('error');

        $verification->refresh();
        $this->assertEquals(4, $verification->cash_50);
        $this->assertEquals(200.00, (float) $verification->counted_total);
        $this->assertEquals(200.00, (float) $verification->expected_total);
        $this->assertEquals(0.00, (float) $verification->variance);
        $this->assertEquals($this->admin->id, $verification->last_edited_by);
        $this->assertNotNull($verification->last_edited_at);
    }

    public function test_omitted_denominations_are_saved_as_zero(): void
    {
        $reconciliation = $this->makeReconciliation();
        $verification = $this->makeVerification($reconciliation, ['cash_50' => 4, 'cash_20' => 3]);

        $this->actingAs($this->admin)->post(route('management.cash-lodgements.update-bag'), [
            'verification_id' => $verification->id,
            'cash_50' => 4,
            // cash_20 deliberately omitted - the fix is "there were no €20s at all"
        ]);

        $verification->refresh();
        $this->assertEquals(0, $verification->cash_20);
        $this->assertEquals(200.00, (float) $verification->counted_total);
    }

    public function test_expected_total_is_rebaselined_when_the_reconciliation_has_changed(): void
    {
        $reconciliation = $this->makeReconciliation();
        $verification = $this->makeVerification($reconciliation, ['cash_50' => 4]);

        // The day's cash count is edited after the bag was verified - €50 less available.
        $reconciliation->update(['cash_50' => 3]); // €170 notes + €50 coins - €70 float = €150
        $this->assertEquals(150.00, $reconciliation->fresh()->calculateAvailableToLodge());

        $this->actingAs($this->admin)->post(route('management.cash-lodgements.update-bag'), [
            'verification_id' => $verification->id,
            'cash_50' => 3,
        ]);

        $verification->refresh();
        $this->assertEquals(150.00, (float) $verification->counted_total);
        $this->assertEquals(150.00, (float) $verification->expected_total);
        $this->assertEquals(0.00, (float) $verification->variance);
    }

    public function test_a_lodged_bag_cannot_be_edited(): void
    {
        $reconciliation = $this->makeReconciliation();
        $verification = $this->makeVerification($reconciliation, ['cash_50' => 4]);

        $lodgement = CashLodgement::create([
            'money_id' => $reconciliation->closed_cash_id,
            'lodgement_date' => now()->toDateString(),
            'cash_amount' => 200,
            'cheque_amount' => 0,
            'till_name' => $reconciliation->till_name,
            'till_id' => $reconciliation->till_id,
            'created_by' => $this->admin->id,
        ]);
        $verification->update(['cash_lodgement_id' => $lodgement->id]);

        $response = $this->actingAs($this->admin)->post(route('management.cash-lodgements.update-bag'), [
            'verification_id' => $verification->id,
            'cash_50' => 1,
        ]);

        $response->assertSessionHas('error');

        $verification->refresh();
        $this->assertEquals(4, $verification->cash_50);
        $this->assertEquals(200.00, (float) $verification->counted_total);
        $this->assertNull($verification->last_edited_at);
    }

    public function test_an_employee_cannot_edit_a_bag_count(): void
    {
        $employee = $this->userWithRole('employee');

        $reconciliation = $this->makeReconciliation();
        $verification = $this->makeVerification($reconciliation, ['cash_50' => 4]);

        $this->actingAs($employee)
            ->post(route('management.cash-lodgements.update-bag'), [
                'verification_id' => $verification->id,
                'cash_50' => 1,
            ])
            ->assertStatus(403);

        $this->assertEquals(4, $verification->fresh()->cash_50);
    }

    /**
     * €220 in notes + €50 in coins, €70 held back as float => €200 available to lodge.
     */
    private function makeReconciliation(): CashReconciliation
    {
        return CashReconciliation::create([
            'closed_cash_id' => 'TEST-MONEY-1',
            'date' => now()->toDateString(),
            'till_name' => 'Till 1',
            'till_id' => 1,
            'cash_50' => 4,
            'cash_20' => 1,
            'cash_2' => 20,
            'cash_1' => 10,
            'note_float' => 50,
            'coin_float' => 20,
            'created_by' => $this->admin->id,
        ]);
    }

    private function makeVerification(CashReconciliation $reconciliation, array $denominations): CashBagVerification
    {
        $verification = new CashBagVerification(array_merge([
            'cash_reconciliation_id' => $reconciliation->id,
        ], $denominations));

        $counted = $verification->calculateTotal();
        $expected = $reconciliation->calculateAvailableToLodge();

        $verification->fill([
            'counted_total' => $counted,
            'expected_total' => $expected,
            'variance' => $counted - $expected,
            'verified_by' => $this->admin->id,
            'verified_at' => now(),
        ]);
        $verification->save();

        return $verification;
    }
}
