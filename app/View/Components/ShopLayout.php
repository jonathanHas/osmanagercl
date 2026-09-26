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
        /**
         * Seconds between meta-refreshes for a signed-out viewer. The counter
         * tablet sits on a public board all day with nobody to reload it; a
         * signed-in user keeps the stale-session check instead.
         */
        public ?int $guestRefresh = null,
    ) {}

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.shop');
    }
}
