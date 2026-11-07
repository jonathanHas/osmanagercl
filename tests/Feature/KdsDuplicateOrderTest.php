<?php

namespace Tests\Feature;

use App\Jobs\MonitorCoffeeOrdersJob;
use App\Models\KdsOrder;
use App\Models\KdsOrderItem;
use App\Models\KdsProduct;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A POS ticket must reach the KDS queue exactly once.
 *
 * Both importers check for the ticket and then insert it. Every open KDS
 * screen polls every 2 s, so two checks can pass before either inserts. The
 * race is reproduced here by inserting the competing row from a query
 * listener, i.e. after the importer's existence check and before its insert.
 */
class KdsDuplicateOrderTest extends TestCase
{
    use RefreshDatabase;

    private const TICKET = 'ticket-uuid-1';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.connections.pos', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        DB::purge('pos');

        $schema = DB::connection('pos')->getSchemaBuilder();
        $schema->create('PRODUCTS', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('NAME')->nullable();
            $table->string('DISPLAY')->nullable();
        });
        $schema->create('PEOPLE', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('NAME')->nullable();
        });
        $schema->create('TICKETS', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->integer('TICKETID')->nullable();
            $table->integer('TICKETTYPE')->default(0);
            $table->string('PERSON')->nullable();
            $table->string('CUSTOMER')->nullable();
        });
        $schema->create('RECEIPTS', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->dateTime('DATENEW');
        });
        $schema->create('TICKETLINES', function (Blueprint $table) {
            $table->string('TICKET');
            $table->integer('LINE');
            $table->string('PRODUCT')->nullable();
            $table->decimal('UNITS', 8, 3)->default(1);
            $table->text('ATTRIBUTES')->nullable();
        });

        $pos = DB::connection('pos');
        $pos->table('PRODUCTS')->insert(['ID' => 'LATTE', 'NAME' => 'Latte', 'DISPLAY' => 'Latte']);
        $pos->table('PEOPLE')->insert(['ID' => 'P1', 'NAME' => 'Sam']);
        $pos->table('TICKETS')->insert(['ID' => self::TICKET, 'TICKETID' => 4242, 'TICKETTYPE' => 0, 'PERSON' => 'P1']);
        $pos->table('RECEIPTS')->insert(['ID' => self::TICKET, 'DATENEW' => now()->subMinute()->toDateTimeString()]);
        $pos->table('TICKETLINES')->insert(['TICKET' => self::TICKET, 'LINE' => 0, 'PRODUCT' => 'LATTE', 'UNITS' => 1]);

        KdsProduct::create([
            'product_id' => 'LATTE', 'product_name' => 'Latte', 'is_active' => true, 'trigger_mode' => 'primary',
        ]);
    }

    private function barista(): User
    {
        $role = Role::firstOrCreate(['name' => 'barista'], ['display_name' => 'Barista']);
        $role->givePermissionTo(Permission::firstOrCreate(
            ['name' => 'kds.access'],
            ['display_name' => 'Access KDS', 'module' => 'KDS']
        ));

        return User::factory()->create(['role_id' => $role->id]);
    }

    /**
     * Stand in for a second KDS screen that inserts the same ticket (with its
     * item) straight after this importer's existence check has come back
     * empty, which is the gap the duplicates came through.
     */
    private function anotherScreenWinsTheRace(): void
    {
        $done = false;

        DB::listen(function ($query) use (&$done) {
            $isExistenceCheck = $query->connectionName !== 'pos'
                && str_starts_with($query->sql, 'select')
                && str_contains($query->sql, 'kds_orders')
                && str_contains($query->sql, 'ticket_id');

            if ($done || ! $isExistenceCheck) {
                return;
            }
            $done = true;

            $id = DB::table('kds_orders')->insertGetId([
                'ticket_id' => self::TICKET,
                'ticket_number' => 4242,
                'status' => 'new',
                'order_time' => now()->subMinute(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('kds_order_items')->insert([
                'kds_order_id' => $id,
                'product_id' => 'LATTE',
                'product_name' => 'Latte',
                'quantity' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function test_realtime_check_imports_a_ticket_once(): void
    {
        $user = $this->barista();

        $this->actingAs($user)->getJson(route('kds.realtime-check'))
            ->assertOk()
            ->assertJson(['success' => true, 'orders_created' => 1]);

        $this->actingAs($user)->getJson(route('kds.realtime-check'))
            ->assertOk()
            ->assertJson(['success' => true, 'orders_created' => 0]);

        $this->assertSame(1, KdsOrder::where('ticket_id', self::TICKET)->count());
        $this->assertSame(1, KdsOrderItem::count());
    }

    public function test_realtime_check_does_not_duplicate_a_ticket_another_screen_just_inserted(): void
    {
        $this->anotherScreenWinsTheRace();

        $this->actingAs($this->barista())->getJson(route('kds.realtime-check'))
            ->assertOk()
            ->assertJson(['success' => true, 'orders_created' => 0]);

        $this->assertSame(1, KdsOrder::where('ticket_id', self::TICKET)->count());
        $this->assertSame(1, KdsOrderItem::count(), 'The losing request must not add its own copy of the items.');
    }

    public function test_monitor_job_does_not_duplicate_a_ticket_inserted_mid_run(): void
    {
        $this->anotherScreenWinsTheRace();

        (new MonitorCoffeeOrdersJob)->handle();

        $this->assertSame(1, KdsOrder::where('ticket_id', self::TICKET)->count());
        $this->assertSame(1, KdsOrderItem::count());
    }

    public function test_database_rejects_a_second_order_for_the_same_ticket(): void
    {
        $row = ['ticket_id' => 'T-DUP', 'ticket_number' => 1, 'status' => 'new', 'order_time' => now()];
        KdsOrder::create($row);

        $this->expectException(UniqueConstraintViolationException::class);
        KdsOrder::create($row);
    }
}
