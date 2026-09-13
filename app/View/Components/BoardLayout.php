<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Full-width, sidebar-free layout for shop-floor boards that must render for
 * guests as well as signed-in staff (the admin layout assumes a user).
 */
class BoardLayout extends Component
{
    public function __construct(public string $title = 'Board') {}

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.board');
    }
}
