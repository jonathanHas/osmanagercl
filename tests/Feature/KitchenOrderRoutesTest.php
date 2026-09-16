<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The kitchen route group ends in a /{recipe} wildcard. If the order routes
 * were registered after it, /kitchen/orders would bind {recipe} = 'orders'
 * and 404. Same guard as KitchenWholesalePageTest. Needs no database.
 */
class KitchenOrderRoutesTest extends TestCase
{
    public function test_the_order_urls_resolve_ahead_of_the_recipe_wildcard(): void
    {
        $expected = [
            '/kitchen/orders' => 'kitchen.orders.index',
            '/kitchen/orders/create' => 'kitchen.orders.create',
            '/kitchen/standing-order' => 'kitchen.standing-order.edit',
        ];

        foreach ($expected as $uri => $name) {
            $route = Route::getRoutes()->match(Request::create($uri, 'GET'));

            $this->assertSame($name, $route->getName(), "{$uri} matched {$route->getName()}");
        }
    }

    public function test_the_order_pages_require_authentication(): void
    {
        $this->get(route('kitchen.orders.index'))->assertRedirect(route('login'));
        $this->get(route('kitchen.orders.create'))->assertRedirect(route('login'));
        $this->get(route('kitchen.standing-order.edit'))->assertRedirect(route('login'));
    }

    public function test_writes_require_authentication(): void
    {
        $this->postJson(route('kitchen.orders.store'), ['supplier_id' => '5'])->assertUnauthorized();
        $this->putJson(route('kitchen.standing-order.update'), ['qty' => []])->assertUnauthorized();
    }
}
