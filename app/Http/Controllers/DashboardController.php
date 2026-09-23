<?php

namespace App\Http\Controllers;

use App\Models\AmazonInvoicePending;
use App\Repositories\ProductRepository;
use App\Services\CustomerRequestService;
use Illuminate\View\View;

/**
 * Office mode home screen.
 */
class DashboardController extends Controller
{
    public function index(): View
    {
        $productRepository = new ProductRepository;
        $statistics = $productRepository->getStatistics();

        // Add Amazon pending count for dashboard widget
        $amazonPendingCount = AmazonInvoicePending::pending()->count();

        // Customer requests due today / overdue and items put aside awaiting collection
        $customerRequestCounts = app(CustomerRequestService::class)->dashboardCounts();

        return view('dashboard', compact('statistics', 'amazonPendingCount', 'customerRequestCounts'));
    }
}
