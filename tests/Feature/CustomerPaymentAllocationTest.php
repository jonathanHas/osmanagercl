<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\CustomerPayment;
use App\Models\Role;
use App\Models\User;
use App\Services\CustomerPaymentService;
use App\Services\CustomerStatementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Covers matching customer payments to invoices: the allocation invariants shared
 * by every write path, re-allocating an already-recorded payment, and pulling
 * unapplied credit onto an invoice from the invoice side.
 */
class CustomerPaymentAllocationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The create page lists tills from the POS connection; seed the cache the
        // repository reads so tests never touch that database.
        Cache::put('available_tills', collect([1 => 'TILL-1']), 300);
    }

    private function actor(): User
    {
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function service(): CustomerPaymentService
    {
        return app(CustomerPaymentService::class);
    }

    private function invoice(Customer $c, float $total, array $extra = []): CustomerInvoice
    {
        return CustomerInvoice::factory()->total($total)->create(array_merge([
            'customer_id' => $c->id,
            'customer_name' => $c->name,
        ], $extra));
    }

    private function payment(Customer $c, float $amount, array $extra = []): CustomerPayment
    {
        return CustomerPayment::factory()->amount($amount)->create(array_merge([
            'customer_id' => $c->id,
        ], $extra));
    }

    // ---------------------------------------------------------------- Phase 0

    public function test_create_page_renders_the_allocation_table(): void
    {
        $this->actingAs($this->actor())
            ->get(route('customer-payments.create'))
            ->assertOk()
            ->assertSee('Apply to invoices')
            ->assertSee('Auto-allocate')
            ->assertSee('allocations[${idx}][customer_invoice_id]', false);
    }

    public function test_store_persists_allocations_and_leaves_the_remainder_as_credit(): void
    {
        $c = Customer::factory()->create();
        $inv = $this->invoice($c, 40);

        $this->actingAs($this->actor())
            ->post(route('customer-payments.store'), [
                'customer_id' => $c->id,
                'payment_date' => '2026-03-01',
                'amount' => 100,
                'method' => CustomerPayment::METHOD_ONLINE,
                'allocations' => [
                    ['customer_invoice_id' => $inv->id, 'amount' => 40],
                    // Untouched row — the controller strips these before validation.
                    ['customer_invoice_id' => $inv->id, 'amount' => ''],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $payment = CustomerPayment::latest('id')->firstOrFail();

        $this->assertSame(40.0, $payment->total_allocated);
        $this->assertSame(60.0, $payment->unallocated_amount);
        $this->assertSame(0.0, $inv->fresh()->outstanding_amount);
    }

    // ------------------------------------------------- Allocation invariants

    public function test_record_payment_rejects_per_invoice_over_allocation(): void
    {
        $c = Customer::factory()->create();
        $inv = $this->invoice($c, 50);

        $this->actingAs($this->actor())
            ->post(route('customer-payments.store'), [
                'customer_id' => $c->id,
                'payment_date' => '2026-03-01',
                'amount' => 500,
                'method' => CustomerPayment::METHOD_ONLINE,
                'allocations' => [['customer_invoice_id' => $inv->id, 'amount' => 500]],
            ])
            ->assertSessionHasErrors('allocations');

        // Nothing at all is written — the whole record is one transaction.
        $this->assertDatabaseCount('customer_payments', 0);
        $this->assertDatabaseCount('customer_payment_allocations', 0);
    }

    public function test_record_payment_rejects_allocating_to_a_void_invoice(): void
    {
        $c = Customer::factory()->create();
        $inv = $this->invoice($c, 50, ['status' => CustomerInvoice::STATUS_VOID]);

        $this->expectException(\DomainException::class);

        $this->service()->recordPayment([
            'customer_id' => $c->id,
            'payment_date' => '2026-03-01',
            'amount' => 50,
            'method' => CustomerPayment::METHOD_ONLINE,
        ], [['customer_invoice_id' => $inv->id, 'amount' => 50]]);
    }

    public function test_duplicate_invoice_rows_are_merged_not_double_counted(): void
    {
        $c = Customer::factory()->create();
        $inv = $this->invoice($c, 50);

        // Two rows of €30 against a €50 invoice: each passes a naive per-row
        // check, but together they exceed the invoice.
        $this->expectException(\DomainException::class);

        $this->service()->recordPayment([
            'customer_id' => $c->id,
            'payment_date' => '2026-03-01',
            'amount' => 100,
            'method' => CustomerPayment::METHOD_ONLINE,
        ], [
            ['customer_invoice_id' => $inv->id, 'amount' => 30],
            ['customer_invoice_id' => $inv->id, 'amount' => 30],
        ]);
    }

    public function test_duplicate_rows_within_headroom_collapse_to_one_allocation(): void
    {
        $c = Customer::factory()->create();
        $inv = $this->invoice($c, 50);

        $payment = $this->service()->recordPayment([
            'customer_id' => $c->id,
            'payment_date' => '2026-03-01',
            'amount' => 100,
            'method' => CustomerPayment::METHOD_ONLINE,
        ], [
            ['customer_invoice_id' => $inv->id, 'amount' => 20],
            ['customer_invoice_id' => $inv->id, 'amount' => 25],
        ]);

        $this->assertCount(1, $payment->allocations);
        $this->assertSame(45.0, $payment->total_allocated);
    }

    public function test_allocations_still_rejected_when_they_exceed_the_payment_amount(): void
    {
        $c = Customer::factory()->create();
        $a = $this->invoice($c, 100);
        $b = $this->invoice($c, 100);

        $this->expectExceptionMessage('exceed payment amount');

        $this->service()->recordPayment([
            'customer_id' => $c->id,
            'payment_date' => '2026-03-01',
            'amount' => 150,
            'method' => CustomerPayment::METHOD_ONLINE,
        ], [
            ['customer_invoice_id' => $a->id, 'amount' => 100],
            ['customer_invoice_id' => $b->id, 'amount' => 100],
        ]);
    }

    public function test_allocating_another_customers_invoice_is_rejected(): void
    {
        $c = Customer::factory()->create();
        $other = Customer::factory()->create();
        $inv = $this->invoice($other, 50);

        $this->expectExceptionMessage('same customer');

        $this->service()->recordPayment([
            'customer_id' => $c->id,
            'payment_date' => '2026-03-01',
            'amount' => 50,
            'method' => CustomerPayment::METHOD_ONLINE,
        ], [['customer_invoice_id' => $inv->id, 'amount' => 50]]);
    }

    public function test_headroom_ignores_voided_payments_and_the_excluded_payment(): void
    {
        $c = Customer::factory()->create();
        $inv = $this->invoice($c, 100);

        $live = $this->payment($c, 40);
        $live->allocations()->create(['customer_invoice_id' => $inv->id, 'amount' => 40]);

        $dead = $this->payment($c, 60, ['voided_at' => now()]);
        $dead->allocations()->create(['customer_invoice_id' => $inv->id, 'amount' => 60]);

        // The voided payment's row never counts.
        $this->assertSame(60.0, $this->service()->headroomFor([$inv->id])[$inv->id]);

        // Excluding the live payment gives back the headroom it currently uses.
        $this->assertSame(
            100.0,
            $this->service()->headroomFor([$inv->id], $live->id)[$inv->id]
        );
    }

    public function test_auto_allocate_applies_oldest_first(): void
    {
        $c = Customer::factory()->create();
        $old = $this->invoice($c, 60, ['issue_date' => '2026-01-01']);
        $new = $this->invoice($c, 60, ['issue_date' => '2026-02-01']);

        $allocations = $this->service()->autoAllocate($c->fresh(), 80);

        $this->assertSame([
            ['customer_invoice_id' => $old->id, 'amount' => 60.0],
            ['customer_invoice_id' => $new->id, 'amount' => 20.0],
        ], $allocations);
    }

    // ------------------------------------------------------- Re-allocation

    public function test_reallocating_replaces_the_allocation_set_wholesale(): void
    {
        $c = Customer::factory()->create();
        $a = $this->invoice($c, 50);
        $b = $this->invoice($c, 50);

        $payment = $this->payment($c, 50);
        $payment->allocations()->create(['customer_invoice_id' => $a->id, 'amount' => 50]);

        $this->actingAs($this->actor())
            ->put(route('customer-payments.allocations.update', $payment), [
                'allocations' => [['customer_invoice_id' => $b->id, 'amount' => 50]],
            ])
            ->assertRedirect(route('customer-payments.show', $payment))
            ->assertSessionHasNoErrors();

        $this->assertSame(50.0, $a->fresh()->outstanding_amount);
        $this->assertSame(0.0, $b->fresh()->outstanding_amount);
        $this->assertDatabaseCount('customer_payment_allocations', 1);
        $this->assertNotNull($payment->fresh()->last_matched_at);
    }

    public function test_reallocation_headroom_excludes_the_payment_being_edited(): void
    {
        $c = Customer::factory()->create();
        $inv = $this->invoice($c, 100);

        $payment = $this->payment($c, 100);
        $payment->allocations()->create(['customer_invoice_id' => $inv->id, 'amount' => 100]);

        // The invoice reads as €0 outstanding because THIS payment covers it.
        // Re-posting the same €100 must still be allowed.
        $this->assertSame(0.0, $inv->fresh()->outstanding_amount);

        $this->service()->reallocate($payment, [
            ['customer_invoice_id' => $inv->id, 'amount' => 100],
        ]);

        $this->assertSame(0.0, $inv->fresh()->outstanding_amount);
        $this->assertSame(100.0, $payment->fresh()->total_allocated);
    }

    public function test_reallocation_rejects_when_another_payment_already_covers_the_invoice(): void
    {
        $c = Customer::factory()->create();
        $inv = $this->invoice($c, 100);

        $other = $this->payment($c, 100);
        $other->allocations()->create(['customer_invoice_id' => $inv->id, 'amount' => 100]);

        $payment = $this->payment($c, 100);

        $this->expectExceptionMessage('only €0.00 is outstanding');

        $this->service()->reallocate($payment, [
            ['customer_invoice_id' => $inv->id, 'amount' => 100],
        ]);
    }

    public function test_reallocation_rejects_a_voided_payment(): void
    {
        $c = Customer::factory()->create();
        $inv = $this->invoice($c, 50);
        $payment = $this->payment($c, 50, ['voided_at' => now()]);

        $this->actingAs($this->actor())
            ->put(route('customer-payments.allocations.update', $payment), [
                'allocations' => [['customer_invoice_id' => $inv->id, 'amount' => 50]],
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('customer_payment_allocations', 0);
    }

    public function test_a_failed_reallocation_writes_nothing(): void
    {
        $c = Customer::factory()->create();
        $a = $this->invoice($c, 50);
        $b = $this->invoice($c, 10);

        $payment = $this->payment($c, 50);
        $payment->allocations()->create(['customer_invoice_id' => $a->id, 'amount' => 50]);

        try {
            // Second row busts invoice b's headroom, so the whole set is rejected.
            $this->service()->reallocate($payment, [
                ['customer_invoice_id' => $a->id, 'amount' => 30],
                ['customer_invoice_id' => $b->id, 'amount' => 20],
            ]);
            $this->fail('Expected a DomainException.');
        } catch (\DomainException $e) {
            // expected
        }

        $this->assertDatabaseCount('customer_payment_allocations', 1);
        $this->assertSame(50.0, $payment->fresh()->total_allocated);
        $this->assertSame(0.0, $a->fresh()->outstanding_amount);
    }

    public function test_auto_match_action_applies_oldest_first_and_leaves_the_remainder(): void
    {
        $c = Customer::factory()->create();
        $old = $this->invoice($c, 60, ['issue_date' => '2026-01-01']);
        $new = $this->invoice($c, 10, ['issue_date' => '2026-02-01']);
        $payment = $this->payment($c, 100);

        $this->actingAs($this->actor())
            ->post(route('customer-payments.allocations.auto', $payment))
            ->assertRedirect(route('customer-payments.show', $payment))
            ->assertSessionHasNoErrors();

        $this->assertSame(0.0, $old->fresh()->outstanding_amount);
        $this->assertSame(0.0, $new->fresh()->outstanding_amount);
        $this->assertSame(30.0, $payment->fresh()->unallocated_amount);
    }

    public function test_the_allocation_edit_page_renders_prefilled_amounts(): void
    {
        $c = Customer::factory()->create();
        $inv = $this->invoice($c, 100);
        $payment = $this->payment($c, 100);
        $payment->allocations()->create(['customer_invoice_id' => $inv->id, 'amount' => 40]);

        $response = $this->actingAs($this->actor())
            ->get(route('customer-payments.allocations.edit', $payment))
            ->assertOk()
            ->assertSee('Edit allocations')
            ->assertSee($inv->invoice_number);

        // Rows are seeded into x-data via Js::from, which \u-escapes the quotes.
        $html = str_replace('\u0022', '"', $response->getContent());
        // Headroom excluding this payment (100), and its own current row (40).
        $this->assertStringContainsString('"outstanding":100', $html);
        $this->assertStringContainsString('"allocated":40', $html);
    }

    public function test_the_allocation_table_offers_one_click_selection(): void
    {
        $c = Customer::factory()->create();
        $this->invoice($c, 100);
        $payment = $this->payment($c, 100);

        // Both pages share the component, but only the create form may grow the
        // amount to fit an invoice the user ticks.
        $edit = $this->actingAs($this->actor())
            ->get(route('customer-payments.allocations.edit', $payment))
            ->assertOk()
            ->assertSee('toggleInvoice(inv)', false)
            ->assertSee('exact match')
            ->getContent();
        $this->assertStringContainsString('"amountIsEditable":false', str_replace('\u0022', '"', $edit));

        $create = $this->actingAs($this->actor())
            ->get(route('customer-payments.create'))
            ->assertOk()
            ->assertSee('toggleInvoice(inv)', false)
            ->getContent();
        $this->assertStringContainsString('"amountIsEditable":true', str_replace('\u0022', '"', $create));
    }

    public function test_allocatable_invoices_endpoint_includes_invoices_this_payment_already_pays(): void
    {
        $c = Customer::factory()->create();
        $inv = $this->invoice($c, 100);
        $payment = $this->payment($c, 100);
        $payment->allocations()->create(['customer_invoice_id' => $inv->id, 'amount' => 100]);

        // The plain open-invoices endpoint correctly hides a settled invoice...
        $this->actingAs($this->actor())
            ->getJson(route('customer-payments.api.open-invoices', $c))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // ...but the payment that settled it must still be able to edit it.
        $this->actingAs($this->actor())
            ->getJson(route('customer-payments.api.allocatable-invoices', $payment))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            // Whole euro amounts encode as JSON integers.
            ->assertJsonPath('data.0.outstanding', 100)
            ->assertJsonPath('data.0.allocated', 100);
    }

    // --------------------------------------------------------- Apply credit

    public function test_apply_credit_settles_an_invoice_from_multiple_payments(): void
    {
        $c = Customer::factory()->create();
        $inv = $this->invoice($c, 50);
        $first = $this->payment($c, 30, ['payment_date' => '2026-01-01']);
        $second = $this->payment($c, 30, ['payment_date' => '2026-02-01']);

        $this->actingAs($this->actor())
            ->post(route('customer-invoices.apply-credit.store', $inv))
            ->assertRedirect(route('customer-invoices.show', $inv))
            ->assertSessionHasNoErrors();

        $this->assertSame(0.0, $inv->fresh()->outstanding_amount);
        $this->assertSame(30.0, $first->fresh()->total_allocated);
        $this->assertSame(20.0, $second->fresh()->total_allocated);
        $this->assertSame(10.0, $second->fresh()->unallocated_amount);
    }

    public function test_apply_credit_ignores_voided_payments(): void
    {
        $c = Customer::factory()->create();
        $inv = $this->invoice($c, 50);
        $this->payment($c, 50, ['voided_at' => now()]);

        $result = $this->service()->applyCreditToInvoice($inv);

        $this->assertSame(0.0, $result['applied']);
        $this->assertSame(50.0, $inv->fresh()->outstanding_amount);
    }

    public function test_apply_credit_never_exceeds_the_invoice_total(): void
    {
        $c = Customer::factory()->create();
        $inv = $this->invoice($c, 40);
        $payment = $this->payment($c, 500);

        $result = $this->service()->applyCreditToInvoice($inv);

        $this->assertSame(40.0, $result['applied']);
        $this->assertSame(460.0, $payment->fresh()->unallocated_amount);
    }

    public function test_apply_credit_tops_up_an_existing_allocation_row(): void
    {
        $c = Customer::factory()->create();
        $inv = $this->invoice($c, 50);
        $payment = $this->payment($c, 50);
        $payment->allocations()->create(['customer_invoice_id' => $inv->id, 'amount' => 20]);

        $this->service()->applyCreditToInvoice($inv->fresh());

        // One row per (payment, invoice) pair, incremented rather than duplicated.
        $this->assertDatabaseCount('customer_payment_allocations', 1);
        $this->assertSame(50.0, $payment->fresh()->total_allocated);
    }

    public function test_apply_credit_is_a_no_op_when_there_is_no_credit(): void
    {
        $c = Customer::factory()->create();
        $inv = $this->invoice($c, 50);

        $result = $this->service()->applyCreditToInvoice($inv);

        $this->assertSame(0.0, $result['applied']);
        $this->assertSame([], $result['rows']);
    }

    public function test_apply_credit_page_offers_the_customers_credit(): void
    {
        $c = Customer::factory()->create();
        $inv = $this->invoice($c, 50);
        $this->payment($c, 80);

        $this->actingAs($this->actor())
            ->get(route('customer-invoices.apply-credit', $inv))
            ->assertOk()
            ->assertSee('Unapplied credit')
            ->assertSee('€80.00')   // available
            ->assertSee('€50.00');  // capped at the invoice
    }

    // ------------------------------------------------------------ Access

    public function test_allocation_routes_require_the_manage_permission(): void
    {
        $c = Customer::factory()->create();
        $inv = $this->invoice($c, 50);
        $payment = $this->payment($c, 50);

        $role = Role::firstOrCreate(['name' => 'viewer'], ['display_name' => 'Viewer']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user)->get(route('customer-payments.allocations.edit', $payment))->assertForbidden();
        $this->actingAs($user)->put(route('customer-payments.allocations.update', $payment))->assertForbidden();
        $this->actingAs($user)->post(route('customer-payments.allocations.auto', $payment))->assertForbidden();
        $this->actingAs($user)->get(route('customer-invoices.apply-credit', $inv))->assertForbidden();
        $this->actingAs($user)->post(route('customer-invoices.apply-credit.store', $inv))->assertForbidden();
    }

    // ------------------------------------------------------- Visibility

    public function test_unallocated_filter_returns_only_payments_with_credit(): void
    {
        $c = Customer::factory()->create();
        $inv = $this->invoice($c, 50);

        $matched = $this->payment($c, 50);
        $matched->allocations()->create(['customer_invoice_id' => $inv->id, 'amount' => 50]);

        $unmatched = $this->payment($c, 75);

        $partial = $this->payment($c, 100);
        $partial->allocations()->create(['customer_invoice_id' => $inv->id, 'amount' => 10]);

        $this->actingAs($this->actor())
            ->get(route('customer-payments.index', ['allocation' => 'unallocated']))
            ->assertOk()
            ->assertSee("customer-payments/{$unmatched->id}")
            ->assertDontSee("customer-payments/{$matched->id}")
            ->assertDontSee("customer-payments/{$partial->id}");

        $this->actingAs($this->actor())
            ->get(route('customer-payments.index', ['allocation' => 'partial']))
            ->assertOk()
            ->assertSee("customer-payments/{$partial->id}")
            ->assertDontSee("customer-payments/{$unmatched->id}")
            ->assertDontSee("customer-payments/{$matched->id}");
    }

    public function test_unapplied_credit_totals_ignore_voided_payments(): void
    {
        $c = Customer::factory()->create();
        $this->payment($c, 40);
        $this->payment($c, 999, ['voided_at' => now()]);

        $this->assertSame([$c->id => 40.0], $this->service()->unappliedCreditTotals());
    }

    public function test_debtors_report_exposes_unapplied_credit_per_customer(): void
    {
        $c = Customer::factory()->create();
        $this->invoice($c, 100, ['issue_date' => '2026-01-10']);
        $this->payment($c, 30, ['payment_date' => '2026-01-20']);

        $data = app(CustomerStatementService::class)->debtors(Carbon::parse('2026-03-01'));

        $row = collect($data['rows'])->firstWhere('customer.id', $c->id);

        $this->assertSame(30.0, $row['unapplied_credit']);
        $this->assertSame(30.0, $data['totals']['credit']);
        // The row's aged total exceeds its balance by exactly the unapplied credit.
        $this->assertSame(
            round($row['aging']['total'] - $row['unapplied_credit'], 2),
            $row['balance']
        );
    }

    public function test_debtors_csv_includes_the_credit_column(): void
    {
        $c = Customer::factory()->create();
        $this->invoice($c, 100, ['issue_date' => '2026-01-10']);
        $this->payment($c, 30, ['payment_date' => '2026-01-20']);

        $csv = $this->actingAs($this->actor())
            ->get(route('customers.debtors.export', ['as_of' => '2026-03-01']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Unapplied credit', $csv);
        $this->assertStringContainsString('30.00', $csv);
    }
}
