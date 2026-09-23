<?php

namespace Tests\Feature\Shop;

use App\Models\Role;
use App\Models\User;
use App\Support\UiMode;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Switching a device between the shop-floor and office interfaces.
 */
class UiModeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The dashboard reads product statistics across the POS connection, so the
     * one test that renders it needs those (empty) tables to exist.
     */
    private function createPosTables(): void
    {
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
            $table->decimal('PRICESELL', 10, 4)->default(0);
            $table->boolean('ISSERVICE')->default(false);
        });

        $pos->create('STOCKCURRENT', function (Blueprint $table) {
            $table->string('PRODUCT');
            $table->decimal('UNITS', 10, 2)->default(0);
        });

        $pos->create('stocking', function (Blueprint $table) {
            $table->string('Barcode');
        });
    }

    private function manager(): User
    {
        $role = Role::firstOrCreate(['name' => 'manager'], ['display_name' => 'Manager']);

        return User::factory()->create(['role_id' => $role->id]);
    }

    public function test_switching_to_shop_sets_the_cookie_and_redirects(): void
    {
        $response = $this->actingAs($this->manager())->post('/ui-mode/shop');

        $response->assertRedirect(route('shop.home'))
            ->assertCookie(UiMode::COOKIE, 'shop');
    }

    public function test_switching_to_office_redirects_to_the_dashboard(): void
    {
        $response = $this->actingAs($this->manager())->post('/ui-mode/office');

        $response->assertRedirect(route('dashboard'))
            ->assertCookie(UiMode::COOKIE, 'office');
    }

    public function test_an_unknown_mode_is_not_a_route(): void
    {
        $this->actingAs($this->manager())->post('/ui-mode/other')->assertNotFound();
    }

    public function test_the_dashboard_is_marked_as_the_admin_shell(): void
    {
        $this->createPosTables();

        $this->actingAs($this->manager())->get('/dashboard')
            ->assertOk()
            ->assertSee('data-shell="admin"', false);
    }
}
