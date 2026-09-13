<?php

namespace Tests\Feature;

use App\Models\CustomerRequest;
use App\Models\CustomerRequestItem;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CustomerRequestDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The dashboard closure also computes product statistics from the POS
        // database, so give it empty tables to count.
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
            $table->integer('ISSERVICE')->default(0);
        });
        $pos->create('stocking', function (Blueprint $table) {
            $table->string('Barcode');
        });
        $pos->create('STOCKCURRENT', function (Blueprint $table) {
            $table->string('PRODUCT');
            $table->decimal('UNITS', 10, 2)->default(0);
        });
    }

    private function admin(): User
    {
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);

        return User::factory()->create(['role_id' => $role->id, 'email_verified_at' => now()]);
    }

    public function test_dashboard_banner_is_hidden_when_nothing_needs_attention(): void
    {
        $future = CustomerRequest::factory()->create(['wanted_on' => today()->addWeek()]);
        CustomerRequestItem::factory()->for($future, 'request')->create();

        $this->actingAs($this->admin())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Customer Requests Need Attention');
    }

    public function test_dashboard_banner_shows_due_and_put_aside_counts(): void
    {
        $overdue = CustomerRequest::factory()->create(['wanted_on' => today()->subDay()]);
        CustomerRequestItem::factory()->for($overdue, 'request')->create();

        $today = CustomerRequest::factory()->create(['wanted_on' => today()]);
        CustomerRequestItem::factory()->for($today, 'request')->status('put_aside')->create();
        CustomerRequestItem::factory()->for($today, 'request')->status('put_aside')->create();

        $closed = CustomerRequest::factory()->create(['wanted_on' => today()->subDay(), 'closed_at' => now()]);
        CustomerRequestItem::factory()->for($closed, 'request')->status('collected')->create();

        $response = $this->actingAs($this->admin())->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Customer Requests Need Attention');
        $response->assertSee('2 requests are due today or overdue');
        $response->assertSee('2 items are put aside awaiting collection');
        $response->assertSee(route('customer-requests.index'));
    }
}
