<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\UdeaProductCard;
use App\Models\User;
use App\Services\UdeaPalletVolumeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UdeaPalletVolumeSyncTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        return User::factory()->create([
            'role_id' => Role::firstOrCreate(['name' => $role], ['display_name' => ucfirst($role)])->id,
        ]);
    }

    public function test_admin_can_view_the_pallet_volumes_page(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->get(route('tools.udea-pallet-volumes'))
            ->assertOk()
            ->assertSee('Udea Pallet Volumes');
    }

    public function test_non_admin_is_denied(): void
    {
        $this->actingAs($this->userWithRole('employee'))
            ->get(route('tools.udea-pallet-volumes'))
            ->assertForbidden();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('tools.udea-pallet-volumes'))->assertRedirect(route('login'));
    }

    public function test_sync_route_is_admin_only(): void
    {
        $this->actingAs($this->userWithRole('employee'))
            ->post(route('tools.udea-pallet-volumes.sync'))
            ->assertForbidden();
    }

    public function test_page_reports_how_many_products_have_pallet_data(): void
    {
        UdeaProductCard::create([
            'supplier_code' => '5004482',
            'pallet_sve' => 10,
            'pallet_unit_volume' => 0.65,
            'pallet_scraped_at' => now(),
        ]);
        // A price-only row must not be counted as having pallet data.
        UdeaProductCard::create(['supplier_code' => '5005616', 'scraped_at' => now()]);

        $this->actingAs($this->userWithRole('admin'))
            ->get(route('tools.udea-pallet-volumes'))
            ->assertOk()
            ->assertViewHas('totalWithData', 1);
    }

    public function test_dry_run_command_writes_nothing(): void
    {
        $service = $this->mock(UdeaPalletVolumeService::class);
        $service->shouldReceive('sync')->once()->with(true)->andReturn([
            'found' => 3, 'created' => 3, 'updated' => 0, 'skipped' => 0, 'errors' => [],
            'capacities' => ['euro' => 250.0, 'block' => 360.0],
            'capacity_warning' => null, 'total_volume' => 12.5,
        ]);

        $this->artisan('udea:sync-pallet-volumes', ['--dry-run' => true])
            ->expectsOutputToContain('Dry run')
            ->assertExitCode(0);

        $this->assertDatabaseCount('udea_product_cards', 0);
    }

    public function test_command_fails_when_the_basket_is_empty(): void
    {
        $service = $this->mock(UdeaPalletVolumeService::class);
        $service->shouldReceive('sync')->once()->andReturn([
            'found' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => [],
            'capacities' => ['euro' => null, 'block' => null],
            'capacity_warning' => null, 'total_volume' => 0.0,
        ]);

        $this->artisan('udea:sync-pallet-volumes')->assertExitCode(1);
    }

    public function test_command_reports_a_credentials_failure_cleanly(): void
    {
        $service = $this->mock(UdeaPalletVolumeService::class);
        $service->shouldReceive('sync')->once()->andThrow(new \RuntimeException('Udea login failed (HTTP 401).'));

        $this->artisan('udea:sync-pallet-volumes')
            ->expectsOutputToContain('Udea login failed')
            ->assertExitCode(1);
    }
}
