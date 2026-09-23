<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Shop mode shell: a sidebar-free, touch-first layout for shop-floor staff.
 *
 * Everything it renders is scoped under the `.shop` root class, so none of the
 * manager/admin interface is affected. Screens render their own
 * `<main class="shop-page">` inside the slot.
 */
class ShopLayout extends Component
{
    public function __construct(
        public string $title = 'Shop',
        /** URL for the back button. Null shows the brand mark instead (Home). */
        public ?string $back = null,
        /** Show a "Staff sign in" button rather than assuming a user. */
        public bool $guestSafe = false,
        /** Drop the top bar entirely (the Locked screen). */
        public bool $bare = false,
    ) {}

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.shop');
    }
}
