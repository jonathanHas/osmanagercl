<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\DeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AliasesMysqlConnection;
use Tests\Concerns\CreatesDepositPosTables;
use Tests\TestCase;

/**
 * The parser's deposit-code reconciliation is kept on the delivery and shown
 * on the delivery page (deposit cycle 3).
 */
class DeliveryDepositReconciliationTest extends TestCase
{
    use AliasesMysqlConnection, CreatesDepositPosTables, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createDepositPosTables();
        // KitchenProduct (used by the delivery page) pins the 'mysql' connection.
        $this->aliasMysqlConnectionToTestDatabase();
    }

    private function delivery(array $importData = []): Delivery
    {
        return Delivery::create([
            'delivery_number' => 'DEL-TEST-1',
            'supplier_id' => 5,
            'delivery_date' => '2026-07-15',
            'status' => 'draft',
            'import_data' => $importData,
        ]);
    }

    private function manager(): User
    {
        $role = Role::firstOrCreate(['name' => 'manager'], ['display_name' => 'Manager']);
        $role->givePermissionTo(Permission::firstOrCreate(
            ['name' => 'deliveries.manage'],
            ['display_name' => 'deliveries.manage', 'module' => 'Deliveries']
        ));

        return User::factory()->create(['role_id' => $role->id]);
    }

    public function test_record_merges_into_import_data_and_keeps_other_keys(): void
    {
        $delivery = $this->delivery(['filename' => 'Order_1.pdf', 'format' => 'pdf_direct']);

        app(DeliveryService::class)->recordDepositReconciliation($delivery, [
            'items' => [
                ['code' => '313', 'qty' => 60],
                ['code' => '69', 'qty' => 2],
                ['code' => '313', 'qty' => 6],
            ],
            'line_units' => ['313' => 54, '9936' => 6],
        ], [
            'Price mismatch for 88927: 1×9.79×1.0=9.79, actual=4.88',
            'Deposit code 313: product lines total 54 units, barrels section says 66',
            'Deposit code 9936: product lines total 6 units, not in the barrels section',
        ]);

        $data = $delivery->fresh()->import_data;
        $this->assertSame('Order_1.pdf', $data['filename']);
        $this->assertSame('pdf_direct', $data['format']);
        $recon = $data['deposit_reconciliation'];
        $this->assertSame(['313' => 54, '9936' => 6], $recon['line_units']);
        $this->assertSame(['313' => 66, '69' => 2], $recon['section']);
        $this->assertSame([
            'Deposit code 313: product lines total 54 units, barrels section says 66',
            'Deposit code 9936: product lines total 6 units, not in the barrels section',
        ], $recon['warnings']);
        $this->assertNotEmpty($recon['checked_at']);
    }

    public function test_record_skips_when_there_is_nothing_to_compare(): void
    {
        $delivery = $this->delivery(['filename' => 'Order_2.pdf']);

        app(DeliveryService::class)->recordDepositReconciliation($delivery, ['items' => [], 'total' => 0], ['Deposit code 313: x']);

        $this->assertSame(['filename' => 'Order_2.pdf'], $delivery->fresh()->import_data);
    }

    public function test_delivery_page_shows_the_table_and_the_warnings(): void
    {
        $delivery = $this->delivery([
            'filename' => 'Order_1.pdf',
            'deposit_reconciliation' => [
                'line_units' => ['313' => 54, '315' => 12, '9936' => 6],
                'section' => ['313' => 60, '315' => 12, '69' => 2],
                'warnings' => [
                    'Deposit code 313: product lines total 54 units, barrels section says 60',
                    'Deposit code 9936: product lines total 6 units, not in the barrels section',
                ],
                'checked_at' => '2026-10-04 10:00:00',
            ],
        ]);

        $this->actingAs($this->manager())
            ->get(route('deliveries.show', $delivery))
            ->assertOk()
            ->assertSee('Deposit codes on product lines')
            ->assertSee('2 deposit mismatches')
            ->assertSee('differs by 6')
            ->assertSee('matches')
            ->assertSee('not in section')
            ->assertSee('Deposit code 313: product lines total 54 units, barrels section says 60');
    }

    public function test_delivery_page_without_reconciliation_has_no_table(): void
    {
        $delivery = $this->delivery(['filename' => 'Order_1.pdf']);

        $this->actingAs($this->manager())
            ->get(route('deliveries.show', $delivery))
            ->assertOk()
            ->assertDontSee('Deposit codes on product lines');
    }
}
