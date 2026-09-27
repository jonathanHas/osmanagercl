<?php

namespace Database\Factories;

use App\Models\ShopDevice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ShopDevice>
 */
class ShopDeviceFactory extends Factory
{
    protected $model = ShopDevice::class;

    /**
     * The raw token of the most recently generated device, so a test can put
     * it in a cookie. Set by token().
     */
    public function definition(): array
    {
        return [
            'name' => 'Counter tablet',
            'token_hash' => ShopDevice::hashToken(Str::random(40)),
        ];
    }

    /**
     * Build the device around a known raw token.
     */
    public function token(string $token): static
    {
        return $this->state(fn () => ['token_hash' => ShopDevice::hashToken($token)]);
    }

    public function revoked(): static
    {
        return $this->state(fn () => ['revoked_at' => now()]);
    }
}
