<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\DeliveryDocument;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\DeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\AliasesMysqlConnection;
use Tests\Concerns\CreatesDepositPosTables;
use Tests\TestCase;

/**
 * /deliveries is docket intake only. Its scan → summary → complete path never
 * wrote stock and was removed on 2026-11-07; stock is received through
 * Receiving (/delivery-legacy) and the Shop screens.
 */
class DeliveryDocketCleanupTest extends TestCase
{
    use AliasesMysqlConnection, CreatesDepositPosTables, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createDepositPosTables();
        // KitchenProduct (used by the docket page) pins the 'mysql' connection.
        $this->aliasMysqlConnectionToTestDatabase();
    }

    private function docket(): Delivery
    {
        return Delivery::create([
            'delivery_number' => 'DEL-TEST-CLEANUP',
            'supplier_id' => 5,
            'delivery_date' => '2026-11-07',
            'status' => 'draft',
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

    public function test_the_docket_module_no_longer_offers_a_receiving_path(): void
    {
        foreach (['deliveries.scan', 'deliveries.process-scan', 'deliveries.adjust-quantity', 'deliveries.stats',
            'deliveries.summary', 'deliveries.complete', 'deliveries.cancel', 'deliveries.export-discrepancies',
            'deliveries.edit', 'deliveries.update'] as $name) {
            $this->assertFalse(Route::has($name), "Route {$name} should be gone");
        }

        $docket = $this->docket();
        $manager = $this->manager();

        $this->actingAs($manager)->get("/deliveries/{$docket->id}/scan")->assertNotFound();
        $this->actingAs($manager)->get("/deliveries/{$docket->id}/edit")->assertNotFound();
        $this->actingAs($manager)->post("/deliveries/{$docket->id}/complete")->assertNotFound();
        $this->actingAs($manager)->post("/api/deliveries/{$docket->id}/scan")->assertNotFound();
    }

    public function test_the_pages_say_what_they_do(): void
    {
        $docket = $this->docket();
        $manager = $this->manager();

        $this->actingAs($manager)->get(route('deliveries.index'))
            ->assertOk()
            ->assertSee('Delivery Dockets')
            ->assertDontSee('Start Scanning');

        $this->actingAs($manager)->get(route('deliveries.show', $docket))
            ->assertOk()
            ->assertSee('Send to Receiving')
            ->assertDontSee('Sync to Legacy')
            ->assertDontSee('Start Scanning');
    }

    public function test_deleting_a_docket_removes_its_document_files(): void
    {
        Storage::fake('private');
        $docket = $this->docket();
        $path = DeliveryDocument::generateFilePath($docket->id, 'docket.pdf');
        Storage::disk('private')->put($path, '%PDF-1.4 test');

        DeliveryDocument::create([
            'delivery_id' => $docket->id,
            'original_filename' => 'docket.pdf',
            'stored_filename' => 'docket.pdf',
            'file_path' => $path,
            'mime_type' => 'application/pdf',
            'file_size' => 13,
            'document_type' => 'invoice_pdf',
            'is_primary' => true,
        ]);

        $this->actingAs($this->manager())
            ->delete(route('deliveries.destroy', $docket))
            ->assertRedirect(route('deliveries.index'));

        $this->assertDatabaseMissing('deliveries', ['id' => $docket->id]);
        $this->assertDatabaseMissing('delivery_documents', ['file_path' => $path]);
        Storage::disk('private')->assertMissing($path);
    }

    public function test_a_hand_added_docket_line_links_the_product_by_supplier_code(): void
    {
        Queue::fake();
        $productId = $this->posProduct('5000000000017', 'Oat drink');
        $this->supplierLink('5000000000017', 'UD-OAT', 5);

        $item = app(DeliveryService::class)->createDeliveryItem($this->docket(), [
            'supplier_code' => 'UD-OAT',
            'description' => 'Oat drink 1L',
            'barcode' => null,
            'units_per_case' => 6,
            'unit_cost' => 1.50,
            'ordered_quantity' => 6,
        ]);

        $this->assertSame($productId, $item->product_id);
        $this->assertFalse($item->is_new_product);
        $this->assertSame('5000000000017', $item->barcode);
    }
}
