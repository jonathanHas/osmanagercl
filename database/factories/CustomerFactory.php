<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'address_line1' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'postcode' => $this->faker->postcode(),
            'country' => 'IE',
            'default_discount_percent' => 0,
            'payment_terms_days' => 30,
            'send_statements' => false,
        ];
    }

    public function receivesStatements(): static
    {
        return $this->state(fn () => ['send_statements' => true]);
    }

    public function withoutEmail(): static
    {
        return $this->state(fn () => ['email' => null]);
    }
}
