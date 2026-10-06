<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\OrderSession;
use App\Services\OrderService;
use App\Services\Shop\OrderReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Shop mode supplier order review (design screen 20): a list of recent
 * sessions, the review screen, its JSON feed, the per-item case stepper and
 * the CSV export. Everything else about an order (priorities, adding
 * products, coverage overrides, completing) stays on the office page.
 */
class OrderReviewController extends Controller
{
    public function __construct(private OrderReviewService $review) {}

    public function index(): View
    {
        return view('shop.orders', $this->review->listing());
    }

    public function show(OrderSession $order): View
    {
        return view('shop.order-review', [
            'order' => $order,
            'header' => $this->review->header($order),
        ]);
    }

    public function items(OrderSession $order): JsonResponse
    {
        return response()->json([
            'order' => $this->review->header($order),
            'items' => $this->review->rows($order),
        ]);
    }

    public function updateItem(Request $request, OrderSession $order, OrderItem $item): JsonResponse
    {
        $validated = $request->validate([
            'cases' => 'required|integer|min:0|max:9999',
        ]);

        try {
            $result = $this->review->update($order, $item, (int) $validated['cases']);
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        }

        return response()->json(['success' => true] + $result);
    }

    /**
     * The same file the office export gives (OrderController::export()).
     */
    public function export(OrderSession $order, OrderService $orders): Response
    {
        $csv = $orders->exportToCsv($order);

        $filename = sprintf(
            'order_%s_%s.csv',
            $order->supplier->Supplier ?? 'supplier',
            $order->order_date ? $order->order_date->format('Y-m-d') : 'no-date'
        );

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }
}
