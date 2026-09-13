<?php

namespace Database\Factories;

use App\Models\CustomerRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerRequest>
 */
class CustomerRequestFactory extends Factory
{
    protected $model = CustomerRequest::class;

    public function definition(): array
    {
        return [
            'customer_name' => $this->faker->name(),
            'customer_phone' => $this->faker->phoneNumber(),
            'wanted_on' => $this->faker->dateTimeBetween('now', '+2 weeks')->format('Y-m-d'),
            'notes' => null,
        ];
    }

    public function wantedOn(?string $date): static
    {
        return $this->state(fn () => ['wanted_on' => $date]);
    }

    public function closed(): static
    {
        return $this->state(fn () => ['closed_at' => now()]);
    }
}
