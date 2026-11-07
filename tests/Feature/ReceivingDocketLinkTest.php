<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AliasesMysqlConnection;
use Tests\Concerns\CreatesLegacyDeliveryPosTables;
use Tests\TestCase;

/**
 * A docket (/deliveries) is received through one Receiving scan session.
 * Sending the docket writes its lines into the POS `delivery` table tagged
 * with the session, Receiving reads only its own session's lines, and
 * completing the session marks the docket received (ReceivingService).
 *
 * The fixture (CreatesLegacyDeliveryPosTables::seedLegacyDelivery) is session
 * d-1 for supplier 999 with two invoice lines (S1 → p1, S2 → p2) and scans of
 * 12, 6 and an unknown barcode.
 */
class ReceivingDocketLinkTest extends TestCase
{
    use AliasesMysqlConnection, CreatesLegacyDeliveryPosTables, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createLegacyDeliveryPosTables();
        $this->seedLegacyDelivery();
        $this->aliasMysqlConnectionToTestDatabase();
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function userWith(array $permissions): User
    {
        $role = Role::firstOrCreate(['name' => implode('-', $permissions)], ['display_name' => 'Role']);
        foreach ($permissions as $name) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $name], ['display_name' => $name, 'module' => 'Deliveries']));
        }

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function manager(): User
    {
        return $this->userWith(['deliveries.manage', 'deliveries.process']);
    }

    private function employee(): User
    {
        return $this->userWith(['deliveries.process']);
    }

    /**
     * A docket for supplier 999 with the fixture's two lines: S1 12 units in
     * cases of 6, S2 8 loose units.
     */
    private function docket(string $number = 'DEL-20261107-1'): Delivery
    {
        $delivery = Delivery::create([
            'delivery_number' => $number,
            'supplier_id' => 999,
            'delivery_date' => '2026-11-07',
            'status' => 'draft',
        ]);

        DeliveryItem::create([
            'delivery_id' => $delivery->id, 'supplier_code' => 'S1', 'description' => 'Oat drink 1 L',
            'barcode' => '5000000000017', 'quantity_type' => 'case',
            'units_per_case' => 6, 'unit_cost' => 1.2, 'ordered_quantity' => 2, 'case_ordered_quantity' => 2,
            'invoice_delivered_quantity' => 12, 'total_cost' => 14.4, 'received_quantity' => 0, 'status' => 'pending',
        ]);
        DeliveryItem::create([
            'delivery_id' => $delivery->id, 'supplier_code' => 'S2', 'description' => 'Leeks',
            'barcode' => '5000000000024', 'quantity_type' => 'unit',
            'units_per_case' => 1, 'unit_cost' => 0.8, 'ordered_quantity' => 8, 'unit_ordered_quantity' => 8,
            'invoice_delivered_quantity' => 8, 'total_cost' => 6.4, 'received_quantity' => 0, 'status' => 'pending',
        ]);

        return $delivery;
    }

    private function openSession(string $id): void
    {
        DB::connection('pos')->table('deliveriesScan')->insert(['ID' => $id, 'supID' => '999', 'dateUpload' => now(), 'status' => 0]);
    }

    private function linesFor(string $sessionId): int
    {
        return DB::connection('pos')->table('delivery')->where('delID', $sessionId)->count();
    }

    public function test_sending_a_docket_starts_its_own_session_and_leaves_other_sessions_lines_alone(): void
    {
        $docket = $this->docket();

        $response = $this->actingAs($this->manager())
            ->post(route('deliveries.sync-legacy', $docket), ['scan_session_id' => 'new']);

        $docket->refresh();
        $this->assertNotNull($docket->scan_session_id);
        $this->assertNotSame('d-1', $docket->scan_session_id);
        $response->assertRedirect(route('delivery-legacy.match', ['delID' => $docket->scan_session_id, 'supplierID' => 999]));

        $this->assertSame(2, $this->linesFor($docket->scan_session_id), 'The docket lines are tagged with the new session');
        $this->assertSame(2, $this->linesFor('d-1'), 'The other session keeps its lines (no more truncate)');
        $this->assertDatabaseHas('deliveriesScan', ['ID' => $docket->scan_session_id, 'supID' => '999', 'status' => 0], 'pos');
    }

    public function test_resending_replaces_only_that_sessions_lines_and_a_completed_session_is_refused(): void
    {
        $this->openSession('d-2');
        $docket = $this->docket();

        $this->actingAs($this->manager())->post(route('deliveries.sync-legacy', $docket), ['scan_session_id' => 'd-2']);
        $this->assertSame('d-2', $docket->refresh()->scan_session_id);
        $this->assertSame(2, $this->linesFor('d-2'));

        DeliveryItem::create([
            'delivery_id' => $docket->id, 'supplier_code' => 'S3', 'description' => 'Added by hand',
            'quantity_type' => 'unit', 'units_per_case' => 1, 'unit_cost' => 2, 'ordered_quantity' => 3,
            'unit_ordered_quantity' => 3, 'invoice_delivered_quantity' => 3, 'total_cost' => 6, 'received_quantity' => 0, 'status' => 'pending',
        ]);

        // Re-send (no session given: the docket's own session is reused).
        $this->actingAs($this->manager())->post(route('deliveries.sync-legacy', $docket));
        $this->assertSame(3, $this->linesFor('d-2'), 'Replaced, not appended');
        $this->assertSame(2, $this->linesFor('d-1'));

        DB::connection('pos')->table('deliveriesScan')->where('ID', 'd-2')->update(['status' => 1]);
        $this->actingAs($this->manager())
            ->post(route('deliveries.sync-legacy', $docket))
            ->assertSessionHasErrors('sync');
    }

    public function test_receiving_reads_only_its_own_sessions_invoice_lines(): void
    {
        $this->openSession('d-2');

        $own = $this->actingAs($this->employee())
            ->getJson(route('delivery-legacy.items', ['delID' => 'd-1', 'supplierID' => 999]))
            ->assertOk()->json('rows');
        $this->assertCount(3, $own, 'd-1: two invoice lines plus one unexpected scan');

        $empty = $this->actingAs($this->employee())
            ->getJson(route('delivery-legacy.items', ['delID' => 'd-2', 'supplierID' => 999]))
            ->assertOk()->json('rows');
        $this->assertSame([], $empty, 'A session with no docket has nothing to compare against');
    }

    public function test_completing_the_session_marks_the_docket_received_and_undo_puts_it_back(): void
    {
        $docket = $this->docket();
        $docket->forceFill(['scan_session_id' => 'd-1'])->save();

        $this->actingAs($this->employee())
            ->post(route('delivery-legacy.complete'), ['delID' => 'd-1', 'supplierID' => '999'])
            ->assertRedirect(route('delivery-legacy.match', ['delID' => 'd-1', 'supplierID' => '999']))
            ->assertSessionHas('success', fn ($message) => str_contains($message, 'Docket DEL-20261107-1 marked received'));

        $docket->refresh();
        $this->assertSame('completed', $docket->status);
        $this->assertNotNull($docket->received_at);

        $oat = $docket->items->firstWhere('supplier_code', 'S1');
        $leeks = $docket->items->firstWhere('supplier_code', 'S2');
        $this->assertSame(12, $oat->total_received_units);
        $this->assertSame(2, (int) $oat->case_received_quantity, 'Case-type line keeps the case/unit split');
        $this->assertSame('complete', $oat->status);
        $this->assertSame(6, $leeks->total_received_units);
        $this->assertSame(6, (int) $leeks->received_quantity);
        $this->assertSame('partial', $leeks->status);
        $this->assertEqualsWithDelta(12 * 1.2 + 6 * 0.8, (float) $docket->total_received, 0.001);

        // Stock was written as before: the docket bookkeeping rides on it.
        $this->assertEquals(15, DB::connection('pos')->table('STOCKCURRENT')->where('PRODUCT', 'p1')->value('UNITS'));

        $this->actingAs($this->manager())
            ->post(route('delivery-legacy.undo-complete'), ['delID' => 'd-1', 'supplierID' => '999'])
            ->assertSessionHas('success', fn ($message) => str_contains($message, 'back to draft'));

        $docket->refresh();
        $this->assertSame('draft', $docket->status);
        $this->assertNull($docket->received_at);
        $this->assertSame(0, $docket->items->firstWhere('supplier_code', 'S1')->total_received_units);
        $this->assertSame('pending', $docket->items->firstWhere('supplier_code', 'S2')->status);
    }

    public function test_deleting_a_session_removes_its_lines_and_unlinks_the_docket(): void
    {
        $this->openSession('d-2');
        $docket = $this->docket();
        $this->actingAs($this->manager())->post(route('deliveries.sync-legacy', $docket), ['scan_session_id' => 'd-2']);
        $this->assertSame(2, $this->linesFor('d-2'));

        $this->actingAs($this->manager())
            ->delete(route('delivery-legacy.delete-session'), ['sessionId' => 'd-2'])
            ->assertRedirect(route('delivery-legacy.index'));

        $this->assertSame(0, $this->linesFor('d-2'));
        $this->assertNull($docket->refresh()->scan_session_id);
        $this->assertSame('draft', $docket->status);
    }

    public function test_the_pages_show_the_link_both_ways(): void
    {
        $docket = $this->docket();
        $manager = $this->manager();

        // Not yet sent: the card offers a new session or the open docket-less one.
        $this->actingAs($manager)->get(route('deliveries.show', $docket))
            ->assertOk()
            ->assertSee('Send to Receiving')
            ->assertSee('Start a new scan session')
            ->assertSee('Open session #d-1');

        $docket->forceFill(['scan_session_id' => 'd-1'])->save();

        $this->actingAs($manager)->get(route('deliveries.show', $docket))
            ->assertOk()
            ->assertSee('Open in Receiving')
            ->assertSee('3 items scanned')
            ->assertDontSee('Start a new scan session');

        $this->actingAs($manager)->get(route('delivery-legacy.index'))
            ->assertOk()
            ->assertSee('Docket DEL-20261107-1');

        $this->actingAs($manager)->get(route('delivery-legacy.match', ['delID' => 'd-1', 'supplierID' => 999]))
            ->assertOk()
            ->assertSee('DEL-20261107-1');

        $this->actingAs($this->employee())->get(route('shop.deliveries'))
            ->assertOk()
            ->assertSee('Docket DEL-20261107-1');
    }
}
