<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class KitchenWholesalePageTest extends TestCase
{
    /**
     * The kitchen route group ends in a /{recipe} wildcard. If the wholesale
     * routes were registered after it, /kitchen/wholesale would bind
     * {recipe} = 'wholesale' and 404 - the page would simply never load.
     *
     * Needs no database, so it runs even without pdo_sqlite.
     */
    public function test_the_wholesale_url_resolves_ahead_of_the_recipe_wildcard(): void
    {
        $route = Route::getRoutes()->match(
            \Illuminate\Http\Request::create('/kitchen/wholesale', 'GET')
        );

        $this->assertSame('kitchen.wholesale.index', $route->getName());
    }

    public function test_the_wholesale_page_requires_authentication(): void
    {
        $this->get(route('kitchen.wholesale.index'))->assertRedirect(route('login'));
    }

    public function test_setting_a_price_requires_authentication(): void
    {
        $this->postJson(route('kitchen.wholesale.store', 1), ['price_inc_vat' => 10.00])
            ->assertUnauthorized();
    }
}
