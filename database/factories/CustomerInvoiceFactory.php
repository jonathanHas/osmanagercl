<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerInvoice>
 */
class CustomerInvoiceFactory extends Factory
{
    protected $model = CustomerInvoice::class;

    public function definition(): array
    {
        $total = $this->faker->randomFloat(2, 10, 500);

        return [
            'invoice_number' => 'INV-'.$this->faker->unique()->numberBetween(10000, 99999),
            'customer_id' => Customer::factory(),
            // Snapshot field — resolved from the customer when one is supplied.
            'customer_name' => fn (array $attrs) => Customer::find($attrs['customer_id'])?->name
                ?? $this->faker->company(),
            'issue_date' => $this->faker->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'subtotal' => $total,
            'vat_total' => 0,
            'total' => $total,
            'status' => CustomerInvoice::STATUS_ISSUED,
        ];
    }

    /** Totals are the only thing most allocation tests care about. */
    public function total(float $total): static
    {
        return $this->state(fn () => ['subtotal' => $total, 'vat_total' => 0, 'total' => $total]);
    }

    public function issuedOn(string $date): static
    {
        return $this->state(fn () => ['issue_date' => $date]);
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => CustomerInvoice::STATUS_DRAFT,
            'invoice_number' => null,
        ]);
    }

    public function void(): static
    {
        return $this->state(fn () => [
            'status' => CustomerInvoice::STATUS_VOID,
            'voided_at' => now(),
        ]);
    }
}
