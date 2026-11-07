<?php

namespace Tests\Feature\Cash;

use App\Models\CashReconciliation;
use App\Models\Role;
use App\Models\User;
use App\Services\Cash\TillCountService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPosTillFixture;
use Tests\TestCase;

class RecalculateCashVariancesTest extends TestCase
{
    use BuildsPosTillFixture;
    use RefreshDatabase;

    public function test_the_command_reports_in_dry_run_and_repairs_when_run_live(): void
    {
        $this->setUpPos();
        $role = Role::firstOrCreate(['name' => 'employee'], ['display_name' => 'Employee']);
        $user = User::factory()->create(['role_id' => $role->id]);
        $service = app(TillCountService::class);
        $till = $this->till('Till 2');

        $this->closeTill('Till 2', 'M0', Carbon::parse('2026-10-13 18:39'), [['cash', 400]]);
        $this->closeTill('Till 2', 'M1', Carbon::parse('2026-10-14 18:47'), [['cash', 379.04]]);

        $base = ['coin_float' => null, 'card' => 0, 'money_added' => 0, 'payments' => [], 'note' => ''];
        $service->save($service->session('M0'), $till, $user, $user, ['cash_50' => 10, 'cash_2' => 50, 'cash_1' => 18, 'note_float' => 190] + $base);
        $today = $service->save($service->session('M1'), $till, $user, $user, [
            'cash_50' => 3, 'cash_20' => 11, 'cash_10' => 21, 'cash_5' => 9,
            'cash_2' => 18, 'cash_1' => 7, 'cash_50c' => 23, 'cash_20c' => 7, 'cash_10c' => 45,
            'note_float' => 150,
        ] + $base);

        // The old page's damage: frozen against no previous float.
        $today->forceFill(['variance' => 306.36, 'previous_float' => 0])->saveQuietly();

        $this->artisan('cash:recalculate-variances', ['--dry-run' => true])
            ->expectsOutputToContain('1 of 2 rows change')
            ->expectsOutputToContain('Nothing saved')
            ->assertSuccessful();

        $this->assertSame(306.36, (float) CashReconciliation::find($today->id)->variance);

        $this->artisan('cash:recalculate-variances')
            ->expectsOutputToContain('Saved.')
            ->assertSuccessful();

        $fresh = CashReconciliation::find($today->id);
        $this->assertSame(-1.64, (float) $fresh->variance);
        $this->assertSame(308.0, (float) $fresh->previous_float);
    }
}
