<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Services\CustomerRequestService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Shop mode Home: the task tiles a member of shop-floor staff may use.
 *
 * Tiles come from config/shop.php and are filtered by permission, so a user
 * never sees a task they cannot open.
 */
class ShopHomeController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $tiles = collect(config('shop.tiles'))
            ->filter(fn (array $tile) => $user->hasAnyPermission($tile['permissions']))
            ->map(fn (array $tile) => [...$tile, 'count' => $this->badgeCount($tile['badge'])])
            ->values()
            ->all();

        $hour = now()->hour;
        $greeting = match (true) {
            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            default => 'Good evening',
        };

        $firstName = explode(' ', trim($user->name))[0];
        $greeting = $firstName === '' ? $greeting : "{$greeting}, {$firstName}";

        $today = now()->format('l j F');

        return view('shop.home', compact('tiles', 'greeting', 'today'));
    }

    /**
     * Resolve a tile's badge key to a count. Config cannot hold closures.
     */
    private function badgeCount(?string $badge): ?int
    {
        return match ($badge) {
            'requests' => app(CustomerRequestService::class)->dashboardCounts()['due'],
            default => null,
        };
    }
}
