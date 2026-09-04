<?php

namespace Tests\Feature;

use App\Models\CustomerInvoice;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the "filled in an invoice, pressed Issue & save, landed back on a blank
 * page with nothing saved" report.
 *
 * Two bugs were stacked: ordinary user mistakes failed validation, and the view
 * never read old() — so the bounce wiped every line item, the customer and the
 * dates. These tests pin both the rules and the repopulation.
 */
class CustomerInvoiceSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        // hasAnyPermission() short-circuits for the admin role, which is enough
        // to clear the permission:customer-invoices.manage route middleware.
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);

        return User::factory()->create(['role_id' => $role->id]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function payload(array $items, array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'Acme Ltd',
            'customer_email' => 'billing@acme.test',
            'issue_date' => '2026-09-04',
            'due_date' => '2026-10-04',
            'discount_percent' => 0,
            'notes' => 'Thanks for your order.',
            'issue' => '0',
            'items_json' => json_encode($items),
            'items_count' => count($items),
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function line(array $overrides = []): array
    {
        return array_merge([
            'pos_product_id' => null,
            'pos_product_code' => null,
            'description' => 'Widget',
            'quantity' => 2,
            'unit_price' => 10.0,
            'vat_rate' => 0.23,
        ], $overrides);
    }

    public function test_store_creates_and_issues_an_invoice(): void
    {
        $response = $this->actingAs($this->actor())
            ->post(route('customer-invoices.store'), $this->payload(
                [$this->line(), $this->line(['description' => 'Gadget'])],
                ['issue' => '1']
            ));

        $invoice = CustomerInvoice::first();

        $this->assertNotNull($invoice);
        $response->assertRedirect(route('customer-invoices.show', $invoice));
        $this->assertSame(CustomerInvoice::STATUS_ISSUED, $invoice->status);
        $this->assertSame('INV-2026-00001', $invoice->invoice_number);
        $this->assertSame(2, $invoice->items()->count());
        $this->assertSame('Acme Ltd', $invoice->customer_name);
    }

    public function test_store_saves_a_draft_without_issuing(): void
    {
        $this->actingAs($this->actor())
            ->post(route('customer-invoices.store'), $this->payload([$this->line()]));

        $invoice = CustomerInvoice::first();

        $this->assertSame(CustomerInvoice::STATUS_DRAFT, $invoice->status);
        $this->assertNull($invoice->invoice_number);
    }

    /**
     * The regression test for the actual bug: a rejected save must keep the input.
     */
    public function test_store_flashes_all_input_back_on_validation_failure(): void
    {
        $response = $this->actingAs($this->actor())
            ->post(route('customer-invoices.store'), $this->payload([
                $this->line(),
                $this->line(['description' => '']),
            ]));

        $response->assertSessionHasErrors('items.1.description');
        $this->assertSame(0, CustomerInvoice::count());

        $old = session('_old_input');
        $flashedItems = json_decode($old['items_json'], true);
        $this->assertCount(2, $flashedItems, 'Both line items must survive the bounce.');
        $this->assertSame('Widget', $flashedItems[0]['description']);
        $this->assertSame('Acme Ltd', $old['customer_name']);
        $this->assertSame('2026-09-04', $old['issue_date']);
        $this->assertSame('Thanks for your order.', $old['notes']);
    }

    public function test_create_page_repopulates_from_old_input(): void
    {
        $response = $this->actingAs($this->actor())
            ->from(route('customer-invoices.create'))
            ->post(route('customer-invoices.store'), $this->payload([
                $this->line(['description' => 'Restored Widget']),
                $this->line(['description' => '']),
            ]))
            ->assertRedirect(route('customer-invoices.create'));

        // Follow the bounce and confirm the composer is seeded from old input.
        $page = $this->actingAs($this->actor())->get(route('customer-invoices.create'));

        $page->assertOk();
        $page->assertSee('Restored Widget', false);
        $page->assertSee('Acme Ltd', false);
        $page->assertSee('Thanks for your order.', false);
    }

    public function test_edit_page_repopulates_from_old_input_not_the_model(): void
    {
        $user = $this->actor();

        $invoice = CustomerInvoice::create([
            'customer_name' => 'Original Customer',
            'status' => CustomerInvoice::STATUS_DRAFT,
            'issue_date' => '2026-09-01',
            'created_by' => $user->id,
        ]);
        $invoice->items()->create($this->line(['description' => 'Original Line', 'position' => 0]));

        $this->actingAs($user)
            ->from(route('customer-invoices.edit', $invoice))
            ->put(route('customer-invoices.update', $invoice), $this->payload(
                [$this->line(['description' => ''])],
                ['customer_name' => 'Edited Customer']
            ))
            ->assertSessionHasErrors('items.0.description');

        $page = $this->actingAs($user)->get(route('customer-invoices.edit', $invoice));

        $page->assertOk();
        $page->assertSee('Edited Customer', false);
        $page->assertDontSee('Original Customer', false);
    }

    public function test_items_are_accepted_from_items_json(): void
    {
        $this->actingAs($this->actor())
            ->post(route('customer-invoices.store'), $this->payload([
                $this->line(['description' => 'From JSON', 'quantity' => 3]),
            ]))
            ->assertSessionHasNoErrors();

        $invoice = CustomerInvoice::first();

        $this->assertSame(1, $invoice->items()->count());
        $this->assertSame('From JSON', $invoice->items()->first()->description);
        $this->assertEquals(3, $invoice->items()->first()->quantity);
    }

    public function test_truncated_items_are_rejected_with_a_clear_error(): void
    {
        $response = $this->actingAs($this->actor())
            ->post(route('customer-invoices.store'), $this->payload(
                [$this->line(), $this->line(), $this->line()],
                ['items_count' => 10]   // client sent 10, only 3 arrived
            ));

        $response->assertSessionHasErrors('items');
        $this->assertSame(0, CustomerInvoice::count());
        $this->assertStringContainsString(
            'reached the server',
            session('errors')->first('items')
        );
    }

    public function test_corrupted_items_json_reports_transit_corruption(): void
    {
        $response = $this->actingAs($this->actor())
            ->post(route('customer-invoices.store'), $this->payload([$this->line()], [
                'items_json' => '[{"description": "truncated mid-',
            ]));

        $response->assertSessionHasErrors('items');
        $this->assertSame(0, CustomerInvoice::count());
    }

    public function test_unknown_item_keys_are_stripped(): void
    {
        $this->actingAs($this->actor())
            ->post(route('customer-invoices.store'), $this->payload([
                $this->line([
                    '_grossFocused' => true,
                    '_grossDraft' => '99.99',
                    'net_amount' => 999999,
                    'invoice_id' => 424242,
                ]),
            ]))
            ->assertSessionHasNoErrors();

        $item = CustomerInvoice::first()->items()->first();

        // net is recomputed from qty x unit price, not taken from the payload.
        $this->assertEquals(20.0, $item->net_amount);
        $this->assertNotEquals(424242, $item->invoice_id);
    }

    public function test_zero_quantity_is_rejected(): void
    {
        $this->actingAs($this->actor())
            ->post(route('customer-invoices.store'), $this->payload([
                $this->line(['quantity' => 0]),
            ]))
            ->assertSessionHasErrors('items.0.quantity');

        $this->assertSame(0, CustomerInvoice::count());
    }

    public function test_due_date_before_issue_date_is_rejected(): void
    {
        $this->actingAs($this->actor())
            ->post(route('customer-invoices.store'), $this->payload(
                [$this->line()],
                ['issue_date' => '2026-09-04', 'due_date' => '2026-09-01']
            ))
            ->assertSessionHasErrors('due_date');
    }

    public function test_missing_customer_name_is_rejected(): void
    {
        $this->actingAs($this->actor())
            ->post(route('customer-invoices.store'), $this->payload(
                [$this->line()],
                ['customer_name' => '']
            ))
            ->assertSessionHasErrors('customer_name');
    }

    public function test_an_invoice_with_no_items_is_rejected(): void
    {
        $this->actingAs($this->actor())
            ->post(route('customer-invoices.store'), $this->payload([]))
            ->assertSessionHasErrors('items');
    }

    /**
     * The old per-item inputs blew past max_input_vars around 165 lines.
     * The JSON transport must carry far more than that in one request.
     */
    public function test_a_large_invoice_saves_without_truncation(): void
    {
        $items = [];
        for ($i = 0; $i < 300; $i++) {
            $items[] = $this->line(['description' => "Line {$i}"]);
        }

        $this->actingAs($this->actor())
            ->post(route('customer-invoices.store'), $this->payload($items))
            ->assertSessionHasNoErrors();

        $this->assertSame(300, CustomerInvoice::first()->items()->count());
    }
}
