<?php

namespace App\Providers;

use App\Models\InvoiceAttachment;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Route model binding for invoice attachments
        Route::bind('attachment', function (string $value) {
            return InvoiceAttachment::findOrFail($value);
        });
    }
}
