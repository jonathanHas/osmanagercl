<?php

namespace Tests\Feature;

use App\Mail\CustomerStatementMail;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\CustomerPayment;
use App\Models\Role;
use App\Models\User;
use App\Services\CustomerStatementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Covers the statement ledger, the aging fallback that keys off customer payment
 * terms (invoice due_date is optional and in practice usually blank), and the
 * bulk email run.
 */
class CustomerStatementTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function service(): CustomerStatementService
    {
        return app(CustomerStatementService::class);
    }

    private function invoice(Customer $c, string $issue, float $total, array $extra = []): CustomerInvoice
    {
        return CustomerInvoice::create(array_merge([
            'customer_id' => $c->id,
            'customer_name' => $c->name,
            'invoice_number' => 'INV-'.fake()->unique()->numberBetween(10000, 99999),
            'issue_date' => $issue,
            'subtotal' => $total,
            'vat_total' => 0,
            'total' => $total,
            'status' => CustomerInvoice::STATUS_ISSUED,
        ], $extra));
    }

    private function payment(Customer $c, string $date, float $amount, array $extra = []): CustomerPayment
    {
        return CustomerPayment::create(array_merge([
            'customer_id' => $c->id,
            'payment_date' => $date,
            'amount' => $amount,
            'method' => CustomerPayment::METHOD_ONLINE,
        ], $extra));
    }

    public function test_running_balance_matches_the_customer_balance_accessor(): void
    {
        $c = Customer::factory()->create();
        $this->invoice($c, '2026-01-10', 100);
        $this->invoice($c, '2026-02-10', 50);
        $this->payment($c, '2026-02-15', 30);

        $ctx = $this->service()->build($c, null, Carbon::parse('2026-03-01'));

        $this->assertSame(120.0, $ctx['closing_balance']);
        $this->assertSame($c->fresh()->balance, $ctx['closing_balance']);
    }

    public function test_void_invoices_and_voided_payments_are_excluded(): void
    {
        $c = Customer::factory()->create();
        $this->invoice($c, '2026-01-10', 100);
        $this->invoice($c, '2026-01-11', 999, ['status' => CustomerInvoice::STATUS_VOID]);
        $this->payment($c, '2026-01-20', 40);
        $this->payment($c, '2026-01-21', 500, ['voided_at' => now()]);

        $ctx = $this->service()->build($c, null, Carbon::parse('2026-02-01'));

        $this->assertSame(60.0, $ctx['closing_balance']);
        $this->assertCount(2, $ctx['events']);
    }

    public function test_opening_balance_covers_everything_strictly_before_from(): void
    {
        $c = Customer::factory()->create();
        $this->invoice($c, '2026-01-10', 100);   // before window
        $this->invoice($c, '2026-02-01', 25);    // exactly on the boundary — in window

        $ctx = $this->service()->build($c, Carbon::parse('2026-02-01'), Carbon::parse('2026-02-28'));

        $this->assertSame(100.0, $ctx['opening_balance']);
        $this->assertCount(1, $ctx['events']);
        $this->assertSame(125.0, $ctx['closing_balance']);
    }

    public function test_aging_falls_back_to_customer_payment_terms_when_due_date_is_null(): void
    {
        $c = Customer::factory()->create(['payment_terms_days' => 30]);
        $this->invoice($c, '2026-01-01', 100); // due 2026-01-31 by fallback

        $ctx = $this->service()->build($c, null, Carbon::parse('2026-02-15')); // 15 days overdue

        $this->assertTrue($ctx['open_invoices'][0]['due_date_is_derived']);
        $this->assertSame('2026-01-31', $ctx['open_invoices'][0]['due_date']->toDateString());
        $this->assertSame(15, $ctx['open_invoices'][0]['days_overdue']);
        $this->assertSame(100.0, $ctx['aging']['d1_30']);
        $this->assertSame(0.0, $ctx['aging']['current']);
    }

    public function test_an_explicit_due_date_overrides_the_terms_fallback(): void
    {
        $c = Customer::factory()->create(['payment_terms_days' => 30]);
        $this->invoice($c, '2026-01-01', 100, ['due_date' => '2026-03-01']);

        $ctx = $this->service()->build($c, null, Carbon::parse('2026-02-15'));

        $this->assertFalse($ctx['open_invoices'][0]['due_date_is_derived']);
        $this->assertSame(0, $ctx['open_invoices'][0]['days_overdue']);
        $this->assertSame(100.0, $ctx['aging']['current']);
    }

    public function test_aging_buckets_sum_to_the_total_outstanding(): void
    {
        $c = Customer::factory()->create(['payment_terms_days' => 0]);
        $this->invoice($c, '2026-03-01', 10);   // current
        $this->invoice($c, '2026-02-01', 20);   // ~29 days
        $this->invoice($c, '2025-12-01', 40);   // 90+

        $ctx = $this->service()->build($c, null, Carbon::parse('2026-03-01'));

        $this->assertSame(70.0, $ctx['aging']['total']);
        $this->assertSame($ctx['closing_balance'], $ctx['aging']['total']);
    }

    public function test_overpayment_shows_as_credit_not_negative_outstanding(): void
    {
        $c = Customer::factory()->create();
        $inv = $this->invoice($c, '2026-01-10', 100);
        $pay = $this->payment($c, '2026-01-11', 150);
        $pay->allocations()->create(['customer_invoice_id' => $inv->id, 'amount' => 100]);

        $ctx = $this->service()->build($c, null, Carbon::parse('2026-02-01'));

        $this->assertSame(-50.0, $ctx['closing_balance']);
        $this->assertSame([], $ctx['open_invoices']);          // clamped at 0, not negative
        $this->assertSame(50.0, $ctx['unallocated_credit']);   // surfaces as account credit
    }

    /**
     * A payment recorded without allocations leaves the invoice looking unpaid,
     * so the aged total alone would overstate the debt. The statement reports the
     * unapplied credit separately, and the two must reconcile to the balance.
     */
    public function test_unallocated_payments_reconcile_aged_total_to_the_balance(): void
    {
        $c = Customer::factory()->create();
        $this->invoice($c, '2026-01-10', 100);
        $this->payment($c, '2026-01-11', 40); // deliberately not allocated

        $ctx = $this->service()->build($c, null, Carbon::parse('2026-02-01'));

        $this->assertSame(100.0, $ctx['aging']['total']);
        $this->assertSame(40.0, $ctx['unallocated_credit']);
        $this->assertSame(60.0, $ctx['closing_balance']);
        $this->assertSame(
            $ctx['closing_balance'],
            round($ctx['aging']['total'] - $ctx['unallocated_credit'], 2)
        );
    }

    public function test_paid_invoices_drop_off_the_open_item_list(): void
    {
        $c = Customer::factory()->create();
        $inv = $this->invoice($c, '2026-01-10', 100);
        $pay = $this->payment($c, '2026-01-20', 100);
        $pay->allocations()->create(['customer_invoice_id' => $inv->id, 'amount' => 100]);

        $ctx = $this->service()->build($c, null, Carbon::parse('2026-02-01'));

        $this->assertSame([], $ctx['open_invoices']);
        $this->assertSame(0.0, $ctx['closing_balance']);
    }

    public function test_debtors_totals_match_the_sum_of_customer_balances(): void
    {
        $a = Customer::factory()->create();
        $b = Customer::factory()->create();
        Customer::factory()->create(); // settled, must not appear

        $this->invoice($a, '2026-01-10', 100);
        $this->invoice($b, '2026-01-10', 250);
        $this->payment($b, '2026-01-15', 50);

        $data = $this->service()->debtors(Carbon::parse('2026-02-01'));

        $this->assertCount(2, $data['rows']);
        $this->assertSame(300.0, $data['totals']['balance']);
        $this->assertSame(200.0, $data['rows'][0]['balance']); // sorted desc
    }

    public function test_statement_pages_render_and_pdf_downloads(): void
    {
        $c = Customer::factory()->create();
        $this->invoice($c, '2026-01-10', 100);

        $this->actingAs($this->actor());

        $this->get(route('customers.statement', $c))->assertOk()->assertSee('Account activity');
        $this->get(route('customers.statement.print', $c))->assertOk()->assertSee('Aged balance');
        $this->get(route('customers.debtors'))->assertOk()->assertSee($c->name);

        $pdf = $this->get(route('customers.statement.pdf', $c));
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));
    }

    public function test_debtors_csv_export_includes_a_totals_row(): void
    {
        $c = Customer::factory()->create();
        $this->invoice($c, '2026-01-10', 100);

        $this->actingAs($this->actor());
        $res = $this->get(route('customers.debtors.export'));
        $res->assertOk();

        $csv = $res->streamedContent();
        $this->assertStringContainsString('TOTAL', $csv);
        $this->assertStringContainsString($c->name, $csv);
    }

    public function test_bulk_run_skips_customers_without_email_or_balance(): void
    {
        Mail::fake();

        $owing = Customer::factory()->receivesStatements()->create();
        $this->invoice($owing, '2026-01-10', 100);

        $settled = Customer::factory()->receivesStatements()->create();

        $noEmail = Customer::factory()->receivesStatements()->withoutEmail()->create();
        $this->invoice($noEmail, '2026-01-10', 100);

        $notOptedIn = Customer::factory()->create();
        $this->invoice($notOptedIn, '2026-01-10', 100);

        $result = $this->service()->sendStatements(Carbon::parse('2026-02-01'));

        $this->assertSame(1, $result['sent']);
        Mail::assertQueued(CustomerStatementMail::class, 1);
        $this->assertNotNull($owing->fresh()->statement_last_sent_at);
        $this->assertNull($settled->fresh()->statement_last_sent_at);
        $this->assertNull($notOptedIn->fresh()->statement_last_sent_at);
    }

    public function test_dry_run_queues_nothing(): void
    {
        Mail::fake();

        $c = Customer::factory()->receivesStatements()->create();
        $this->invoice($c, '2026-01-10', 100);

        $result = $this->service()->sendStatements(Carbon::parse('2026-02-01'), dryRun: true);

        $this->assertSame(1, $result['sent']);
        Mail::assertNothingQueued();
        $this->assertNull($c->fresh()->statement_last_sent_at);
    }

    public function test_emailing_a_customer_without_an_address_is_rejected(): void
    {
        Mail::fake();
        $c = Customer::factory()->withoutEmail()->create();

        $this->actingAs($this->actor())
            ->post(route('customers.statement.email', $c))
            ->assertRedirect();

        Mail::assertNothingQueued();
    }

    /**
     * The debtors/index balances are aggregated in SQL. Before that, the
     * outstanding-balances widget loaded every customer with invoices and
     * allocations and then fired two more queries per row via the balance
     * accessor, so query count scaled with the customer list.
     */
    public function test_balance_query_count_does_not_scale_with_customer_count(): void
    {
        $this->actingAs($this->actor());

        $makeCustomers = function (int $n) {
            foreach (range(1, $n) as $i) {
                $c = Customer::factory()->create();
                $this->invoice($c, '2026-01-10', 100);
            }
        };

        $countFor = function (string $url): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get($url)->assertOk();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $makeCustomers(3);
        $smallIndex = $countFor(route('customers.index'));
        $smallPayments = $countFor(route('customer-payments.index'));

        $makeCustomers(17); // 20 customers in total
        $largeIndex = $countFor(route('customers.index'));
        $largePayments = $countFor(route('customer-payments.index'));

        $this->assertLessThanOrEqual($smallIndex, $largeIndex);
        $this->assertLessThanOrEqual($smallPayments, $largePayments);
        $this->assertLessThan(10, $largeIndex);
        $this->assertLessThan(10, $largePayments);
    }

    public function test_customer_form_exposes_payment_terms_and_statement_opt_in(): void
    {
        $c = Customer::factory()->create();

        $this->actingAs($this->actor())
            ->get(route('customers.edit', $c))
            ->assertOk()
            ->assertSee('Payment terms')
            ->assertSee('Email statements of account');
    }

    public function test_owing_filter_returns_only_customers_with_a_balance(): void
    {
        $owing = Customer::factory()->create();
        $this->invoice($owing, '2026-01-10', 100);
        $settled = Customer::factory()->create();

        $this->actingAs($this->actor());
        $res = $this->get(route('customers.index', ['owing' => 1]));

        $res->assertOk()->assertSee($owing->name)->assertDontSee($settled->name);
    }
}
