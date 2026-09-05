<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerPayment>
 */
class CustomerPaymentFactory extends Factory
{
    protected $model = CustomerPayment::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'payment_date' => $this->faker->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'amount' => $this->faker->randomFloat(2, 10, 500),
            // Online needs no till, which keeps most tests free of POS fixtures.
            'method' => CustomerPayment::METHOD_ONLINE,
        ];
    }

    public function amount(float $amount): static
    {
        return $this->state(fn () => ['amount' => $amount]);
    }

    public function receivedOn(string $date): static
    {
        return $this->state(fn () => ['payment_date' => $date]);
    }

    public function voided(): static
    {
        return $this->state(fn () => ['voided_at' => now()]);
    }
}
