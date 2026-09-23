<?php

namespace Tests\Feature\Shop;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The access matrix: which role may reach which page.
 *
 * Only 403 matters here. Many of these pages read the POS connection and would
 * 500 in a test database, so "open" asserts merely "not forbidden" — a 500 is
 * some other cycle's problem, a 403 is this one's.
 */
class RoutePermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Real grants, so employee/manager carry exactly what production gives them.
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function user(string $role): User
    {
        return User::factory()->withRole($role)->create();
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function matrixProvider(): array
    {
        $cases = [
            // Employee: manager/office work is closed.
            ['employee', 'get', '/orders', '403'],
            ['employee', 'get', '/order-manager', '403'],
            ['employee', 'get', '/invoices', '403'],
            ['employee', 'get', '/rtd', '403'],
            ['employee', 'get', '/kitchen', '403'],
            ['employee', 'get', '/settings', '403'],
            ['employee', 'get', '/sales-import', '403'],
            ['employee', 'get', '/products/create', '403'],
            ['employee', 'get', '/products/1/edit', '403'],
            ['employee', 'get', '/deliveries', '403'],
            ['employee', 'get', '/deliveries/create', '403'],
            ['employee', 'get', '/barrel-codes', '403'],
            ['employee', 'post', '/categories/visibility/toggle', '403'],
            ['employee', 'post', '/labels/clear-all', '403'],
            ['employee', 'get', '/fruit-veg/orders', '403'],
            ['employee', 'get', '/tests/hub', '403'],
            ['employee', 'get', '/debug/stock', '403'],

            // Employee: the shop-floor tasks stay open.
            ['employee', 'get', '/products', 'open'],
            ['employee', 'get', '/stocking', 'open'],
            ['employee', 'get', '/stock-review', 'open'],
            ['employee', 'get', '/delivery-legacy', 'open'],
            ['employee', 'get', '/delivery-legacy/match', 'open'],
            ['employee', 'get', '/labels', 'open'],
            ['employee', 'get', '/labels/translate', 'open'],
            ['employee', 'get', '/fruit-veg/availability', 'open'],
            ['employee', 'get', '/fruit-veg/prices', 'open'],
            ['employee', 'get', '/till-review', 'open'],
            ['employee', 'get', '/coffee', 'open'],
            ['employee', 'get', '/categories', 'open'],

            // Barista: KDS and nothing else.
            ['barista', 'get', '/products', '403'],
            ['barista', 'get', '/stocking', '403'],
            ['barista', 'get', '/delivery-legacy', '403'],
            ['barista', 'get', '/labels', '403'],
            ['barista', 'get', '/fruit-veg/availability', '403'],
            ['barista', 'get', '/till-review', '403'],
            ['barista', 'get', '/coffee', '403'],
            ['barista', 'get', '/categories', '403'],
            ['barista', 'get', '/kds', 'open'],

            // Manager: office work opens up; admin-only tooling does not.
            ['manager', 'get', '/orders', 'open'],
            ['manager', 'get', '/invoices', 'open'],
            ['manager', 'get', '/rtd', 'open'],
            ['manager', 'get', '/kitchen', 'open'],
            ['manager', 'get', '/deliveries', 'open'],
            ['manager', 'get', '/deliveries/create', 'open'],
            ['manager', 'get', '/delivery-legacy', 'open'],
            ['manager', 'get', '/products/create', 'open'],
            ['manager', 'get', '/products/1/edit', 'open'],
            ['manager', 'post', '/labels/clear-all', 'open'],
            ['manager', 'get', '/settings', '403'],
            ['manager', 'get', '/sales-import', '403'],
            ['manager', 'get', '/tests/hub', '403'],
            ['manager', 'get', '/debug/stock', '403'],

            // Admin: everything.
            ['admin', 'get', '/settings', 'open'],
            ['admin', 'get', '/sales-import', 'open'],
            ['admin', 'get', '/tests/hub', 'open'],
            ['admin', 'get', '/debug/stock', 'open'],
        ];

        $named = [];
        foreach ($cases as [$role, $method, $uri, $expect]) {
            $named["{$role} {$method} {$uri} => {$expect}"] = [$role, $method, $uri, $expect];
        }

        return $named;
    }

    #[DataProvider('matrixProvider')]
    public function test_route_access(string $role, string $method, string $uri, string $expect): void
    {
        $response = $this->actingAs($this->user($role))->{$method}($uri);

        if ($expect === '403') {
            $response->assertForbidden();

            return;
        }

        $this->assertNotSame(
            403,
            $response->status(),
            "{$role} should not be forbidden from {$method} {$uri}."
        );
    }

    public function test_guest_is_sent_to_login_rather_than_forbidden(): void
    {
        $this->get('/orders')->assertRedirect('/login');
    }
}
