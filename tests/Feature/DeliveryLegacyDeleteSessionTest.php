<?php

namespace Tests\Feature;

use App\Models\DeliveryLabelPrint;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AliasesMysqlConnection;
use Tests\Concerns\CreatesLegacyDeliveryPosTables;
use Tests\TestCase;

/**
 * Deleting a legacy scan session from /delivery-legacy: empty sessions go at
 * once, sessions with scans need the confirmed flag, completed ones never go.
 */
class DeliveryLegacyDeleteSessionTest extends TestCase
{
    use AliasesMysqlConnection;
    use CreatesLegacyDeliveryPosTables;
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension is required.');
        }

        parent::setUp();

        $this->aliasMysqlConnectionToTestDatabase();

        $this->createLegacyDeliveryPosTables();
        $this->seedLegacyDelivery();
    }

    private function employee(): User
    {
        $role = Role::firstOrCreate(['name' => 'employee'], ['display_name' => 'Employee']);
        $permission = Permission::firstOrCreate(
            ['name' => 'deliveries.process'],
            ['display_name' => 'deliveries.process', 'module' => 'Deliveries']
        );
        $role->givePermissionTo($permission);

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function sessionExists(string $id): bool
    {
        return DB::connection('pos')->table('deliveriesScan')->where('ID', $id)->exists();
    }

    private function scanCount(string $id): int
    {
        return DB::connection('pos')->table('deliveriesScanItems')->where('delID', $id)->count();
    }

    public function test_empty_session_is_deleted_without_confirmation(): void
    {
        DB::connection('pos')->table('deliveriesScan')->insert([
            'ID' => 'd-empty', 'supID' => '999', 'dateUpload' => now(), 'status' => 0,
        ]);

        $this->actingAs($this->employee())
            ->delete(route('delivery-legacy.delete-session'), ['sessionId' => 'd-empty'])
            ->assertRedirect(route('delivery-legacy.index'))
            ->assertSessionHas('success');

        $this->assertFalse($this->sessionExists('d-empty'));
        $this->assertTrue($this->sessionExists('d-1'));
    }

    public function test_session_with_scans_is_kept_without_confirmation(): void
    {
        $this->actingAs($this->employee())
            ->delete(route('delivery-legacy.delete-session'), ['sessionId' => 'd-1'])
            ->assertRedirect(route('delivery-legacy.index'))
            ->assertSessionHas('error');

        $this->assertTrue($this->sessionExists('d-1'));
        $this->assertSame(3, $this->scanCount('d-1'));
    }

    public function test_confirmed_delete_removes_session_scans_and_label_prints(): void
    {
        $user = $this->employee();
        DeliveryLabelPrint::create([
            'delivery_id' => 'd-1',
            'supplier_id' => 999,
            'barcode' => '5000000000017',
            'quantity' => 1,
            'batch_uuid' => 'batch-1',
            'idempotency_key' => 'key-1',
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->delete(route('delivery-legacy.delete-session'), ['sessionId' => 'd-1', 'confirmed' => 1])
            ->assertRedirect(route('delivery-legacy.index'))
            ->assertSessionHas('success');

        $this->assertFalse($this->sessionExists('d-1'));
        $this->assertSame(0, $this->scanCount('d-1'));
        $this->assertSame(0, DeliveryLabelPrint::where('delivery_id', 'd-1')->count());
    }

    public function test_completed_session_cannot_be_deleted(): void
    {
        DB::connection('pos')->table('deliveriesScan')->where('ID', 'd-1')->update(['status' => 1]);

        $this->actingAs($this->employee())
            ->delete(route('delivery-legacy.delete-session'), ['sessionId' => 'd-1', 'confirmed' => 1])
            ->assertSessionHas('error');

        $this->assertTrue($this->sessionExists('d-1'));
        $this->assertSame(3, $this->scanCount('d-1'));
    }
}
