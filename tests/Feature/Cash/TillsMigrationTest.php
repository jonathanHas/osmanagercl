<?php

namespace Tests\Feature\Cash;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The tills migration gives every host a fixed id and rewrites the positional
 * till_id that reconciliations, customer payments and lodgements carried.
 */
class TillsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'database/migrations/2026_11_07_000001_create_tills_table.php';

    public function test_hosts_are_numbered_alphabetically_and_till_ids_rewritten_by_name(): void
    {
        $user = User::factory()->create(['role_id' => Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id]);

        $migration = require base_path(self::MIGRATION);
        $migration->down();
        $this->assertFalse(Schema::hasTable('tills'));

        $row = fn (string $host, $tillId, string $date) => [
            'id' => (string) Str::uuid(), 'closed_cash_id' => (string) Str::uuid(), 'date' => $date,
            'till_name' => $host, 'till_id' => $tillId, 'created_by' => $user->id,
            'created_at' => now(), 'updated_at' => now(),
        ];
        DB::table('cash_reconciliations')->insert([
            $row('Till 2', 2, '2026-10-01'),
            $row('OrgStore', 1, '2026-10-01'),
            $row('Till 2', 6, '2026-10-02'),   // the drifted number
            $row('OrgStore', 6, '2026-10-02'),
        ]);
        $customerId = DB::table('customers')->insertGetId(['name' => 'A Customer', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('customer_payments')->insert([
            'customer_id' => $customerId, 'payment_date' => '2026-10-02', 'amount' => 10, 'method' => 'cash_till',
            'till_id' => '6', 'till_name' => 'Till 2', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $migration->up();

        $tills = DB::table('tills')->orderBy('id')->get();
        $this->assertSame(['OrgStore', 'Till 2'], $tills->pluck('pos_host')->all());
        $this->assertSame([1, 2], $tills->pluck('id')->map(fn ($id) => (int) $id)->all());

        $this->assertSame([1, 1], DB::table('cash_reconciliations')->where('till_name', 'OrgStore')->pluck('till_id')->map(fn ($v) => (int) $v)->all());
        $this->assertSame([2, 2], DB::table('cash_reconciliations')->where('till_name', 'Till 2')->pluck('till_id')->map(fn ($v) => (int) $v)->all());
        $this->assertSame('2', (string) DB::table('customer_payments')->value('till_id'));
    }
}
