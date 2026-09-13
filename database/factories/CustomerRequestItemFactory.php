<?php

namespace Database\Factories;

use App\Models\CustomerRequest;
use App\Models\CustomerRequestItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerRequestItem>
 */
class CustomerRequestItemFactory extends Factory
{
    protected $model = CustomerRequestItem::class;

    public function definition(): array
    {
        $name = $this->faker->words(3, true);

        return [
            'customer_request_id' => CustomerRequest::factory(),
            'product_code' => null,
            'product_name' => null,
            'description' => $name,
            'quantity' => 1,
            'notes' => null,
            'position' => 0,
            'status' => CustomerRequestItem::STATUS_PENDING,
            'status_changed_at' => now(),
        ];
    }

    public function forProduct(string $code, string $name): static
    {
        return $this->state(fn () => [
            'product_code' => $code,
            'product_name' => $name,
            'description' => $name,
        ]);
    }

    public function status(string $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
