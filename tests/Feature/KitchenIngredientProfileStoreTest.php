<?php

namespace Tests\Feature;

use App\Models\KitchenIngredientProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the JSON contract used by the Quick Create Profile modal on the recipe edit page.
 */
class KitchenIngredientProfileStoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is not installed on this machine.');
        }

        parent::setUp();
    }

    /**
     * Same shape the modal posts, but with a manual cost so the POS database is not needed.
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'manual_cost' => 3.00,
            'name' => 'Pear Elliot',
            'purchase_quantity' => 1,
            'purchase_unit' => 'kg',
            'organic_status' => 'organic',
            'notes' => '',
        ], $overrides);
    }

    public function test_quick_create_stores_profile_and_returns_json(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->postJson(route('kitchen.profiles.store'), $this->payload());

        $response->assertOk()
            ->assertJsonStructure(['id', 'name', 'purchase_size', 'cost_per_base_unit', 'base_unit']);

        $profile = KitchenIngredientProfile::findOrFail($response->json('id'));

        $this->assertSame('Pear Elliot', $profile->name);
        $this->assertSame('organic', $profile->organic_status);
        $this->assertSame('g', $profile->base_unit);
        // €3.00 per 1kg -> €0.003 per gram
        $this->assertEqualsWithDelta(0.003, $profile->cost_per_base_unit, 0.0000001);
    }

    public function test_quick_create_stores_non_organic_status(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->postJson(route('kitchen.profiles.store'), $this->payload([
                'name' => 'Salt',
                'organic_status' => 'non_organic',
            ]));

        $response->assertOk();

        $this->assertSame('non_organic', KitchenIngredientProfile::findOrFail($response->json('id'))->organic_status);
    }

    public function test_quick_create_requires_organic_status(): void
    {
        $payload = $this->payload();
        unset($payload['organic_status']);

        $this->actingAs(User::factory()->create())
            ->postJson(route('kitchen.profiles.store'), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['organic_status']);

        $this->assertDatabaseCount('kitchen_ingredient_profiles', 0);
    }
}
