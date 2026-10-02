<?php

namespace App\Providers;

use App\Models\InvoiceAttachment;
use App\Models\Product;
use App\Observers\ProductObserver;
use App\Services\BookStack\ScreenHelp;
use App\Services\Shop\ShopDeviceService;
use App\Support\UiMode;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(UiMode::class);
        $this->app->scoped(ShopDeviceService::class);
        // Memoises the BookStack screen index for one request (help buttons).
        $this->app->scoped(ScreenHelp::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Send an already-authenticated user to the home screen of their mode
        // (shop floor or office) rather than always to the dashboard.
        RedirectIfAuthenticated::redirectUsing(function ($request) {
            return app(UiMode::class)->landingUrl($request->user());
        });

        // Route model binding for invoice attachments
        Route::bind('attachment', function (string $value) {
            return InvoiceAttachment::findOrFail($value);
        });

        // Keep kitchen ingredient profile costs in step with product cost prices
        Product::observe(ProductObserver::class);

    }
}
