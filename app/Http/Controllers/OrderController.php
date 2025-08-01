<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use App\Models\OrderSession;
use App\Models\Supplier;
use App\Services\OrderService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class OrderController extends Controller
{
    protected OrderService $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * Display a listing of order sessions.
     */
    public function index(): View
    {
        $orders = OrderSession::with(['supplier', 'user'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('orders.index', compact('orders'));
    }

    /**
     * Show the form for creating a new order.
     */
    public function create(): View
    {
        $suppliers = Supplier::orderBy('Supplier')->get();

        return view('orders.create', compact('suppliers'));
    }

    /**
     * Generate a new order session with suggestions.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'supplier_id' => 'required|exists:App\Models\Supplier,SupplierID',
            'order_date' => 'required|date|after_or_equal:today',
        ]);

        $orderDate = Carbon::parse($request->order_date);
        $orderSession = $this->orderService->generateOrderSuggestions(
            $request->supplier_id,
            $orderDate
        );

        return redirect()->route('orders.show', $orderSession)
            ->with('success', 'Order suggestions generated successfully.');
    }

    /**
     * Display the order review interface.
     */
    public function show(OrderSession $order): View
    {
        $order->load([
            'items.product.supplierLinks',
            'supplier',
            'user',
        ]);

        $statistics = $this->orderService->getOrderStatistics($order);

        return view('orders.show', compact('order', 'statistics'));
    }

    /**
     * Update order session details.
     */
    public function update(Request $request, OrderSession $order): RedirectResponse
    {
        $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        $order->update($request->only('notes'));

        return back()->with('success', 'Order updated successfully.');
    }

    /**
     * Delete an order session.
     */
    public function destroy(OrderSession $order): RedirectResponse
    {
        if (! $order->isEditable()) {
            return back()->with('error', 'Cannot delete a completed order.');
        }

        $order->delete();

        return redirect()->route('orders.index')
            ->with('success', 'Order deleted successfully.');
    }

    /**
     * Update individual order item quantity (AJAX).
     */
    public function updateQuantity(Request $request, OrderItem $orderItem): JsonResponse
    {
        $request->validate([
            'quantity' => 'required|numeric|min:0',
            'reason' => 'nullable|string|max:200',
        ]);

        if (! $orderItem->orderSession->isEditable()) {
            return response()->json(['error' => 'Order is not editable'], 403);
        }

        $updatedItem = $this->orderService->updateOrderItemQuantity(
            $orderItem,
            $request->quantity,
            $request->reason
        );

        return response()->json([
            'success' => true,
            'item' => [
                'id' => $updatedItem->id,
                'final_quantity' => $updatedItem->final_quantity,
                'total_cost' => $updatedItem->total_cost,
                'was_adjusted' => $updatedItem->wasAdjusted(),
                'adjustment_percentage' => round($updatedItem->getAdjustmentPercentage(), 1),
            ],
            'order_totals' => [
                'total_items' => $updatedItem->orderSession->total_items,
                'total_value' => $updatedItem->orderSession->total_value,
            ],
        ]);
    }

    /**
     * Export order to CSV.
     */
    public function export(OrderSession $order): Response
    {
        $csv = $this->orderService->exportToCsv($order);

        $filename = sprintf(
            'order_%s_%s.csv',
            $order->supplier->Supplier ?? 'supplier',
            $order->order_date ? $order->order_date->format('Y-m-d') : 'no-date'
        );

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    /**
     * Complete/submit an order.
     */
    public function complete(OrderSession $order): RedirectResponse
    {
        if (! $order->isEditable()) {
            return back()->with('error', 'Order is already completed.');
        }

        $this->orderService->completeOrderSession($order);

        return redirect()->route('orders.show', $order)
            ->with('success', 'Order completed successfully.');
    }

    /**
     * Duplicate an existing order.
     */
    public function duplicate(OrderSession $order): RedirectResponse
    {
        $newOrderDate = Carbon::now()->addDays(7); // Default next week

        $newOrderSession = $this->orderService->generateOrderSuggestions(
            $order->supplier_id,
            $newOrderDate
        );

        return redirect()->route('orders.show', $newOrderSession)
            ->with('success', 'Order duplicated with updated suggestions.');
    }

    /**
     * Get order statistics (AJAX).
     */
    public function statistics(OrderSession $order): JsonResponse
    {
        $statistics = $this->orderService->getOrderStatistics($order);

        return response()->json($statistics);
    }

    /**
     * Bulk update multiple items (AJAX).
     */
    public function bulkUpdate(Request $request, OrderSession $order): JsonResponse
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:order_items,id',
            'items.*.quantity' => 'required|numeric|min:0',
            'action_reason' => 'nullable|string|max:200',
        ]);

        if (! $order->isEditable()) {
            return response()->json(['error' => 'Order is not editable'], 403);
        }

        $updatedItems = [];

        foreach ($request->items as $itemData) {
            $orderItem = OrderItem::find($itemData['id']);

            if ($orderItem && $orderItem->order_session_id === $order->id) {
                $updatedItem = $this->orderService->updateOrderItemQuantity(
                    $orderItem,
                    $itemData['quantity'],
                    $request->action_reason
                );

                $updatedItems[] = [
                    'id' => $updatedItem->id,
                    'final_quantity' => $updatedItem->final_quantity,
                    'total_cost' => $updatedItem->total_cost,
                ];
            }
        }

        $order->refresh();

        return response()->json([
            'success' => true,
            'updated_items' => $updatedItems,
            'order_totals' => [
                'total_items' => $order->total_items,
                'total_value' => $order->total_value,
            ],
        ]);
    }

    /**
     * Auto-approve safe items (AJAX).
     */
    public function autoApproveSafeItems(OrderSession $order): JsonResponse
    {
        if (! $order->isEditable()) {
            return response()->json(['error' => 'Order is not editable'], 403);
        }

        $safeItems = $order->items()->safe()->get();
        $approvedCount = 0;

        foreach ($safeItems as $item) {
            if (! $item->auto_approved) {
                $item->update(['auto_approved' => true]);
                $approvedCount++;
            }
        }

        return response()->json([
            'success' => true,
            'approved_count' => $approvedCount,
            'message' => "Auto-approved {$approvedCount} safe items",
        ]);
    }

    /**
     * Update product priority setting (AJAX).
     */
    public function updateProductPriority(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|string',
            'priority' => 'required|in:safe,standard,review',
        ]);

        $setting = $this->orderService->updateProductPriority(
            $request->product_id,
            $request->priority
        );

        return response()->json([
            'success' => true,
            'message' => "Product priority updated to {$request->priority}",
            'setting' => $setting,
        ]);
    }

    /**
     * Update individual order item case quantity (AJAX).
     */
    public function updateCaseQuantity(Request $request, OrderItem $orderItem): JsonResponse
    {
        $request->validate([
            'cases' => 'required|numeric|min:0',
            'reason' => 'nullable|string|max:200',
        ]);

        if (! $orderItem->orderSession->isEditable()) {
            return response()->json(['error' => 'Order is not editable'], 403);
        }

        $updatedItem = $this->orderService->updateOrderItemCases(
            $orderItem,
            $request->cases,
            $request->reason
        );

        return response()->json([
            'success' => true,
            'item' => [
                'id' => $updatedItem->id,
                'final_cases' => $updatedItem->final_cases,
                'final_quantity' => $updatedItem->final_quantity,
                'total_cost' => $updatedItem->total_cost,
                'was_adjusted' => $updatedItem->wasAdjusted(),
                'adjustment_percentage' => round($updatedItem->getAdjustmentPercentage(), 1),
            ],
            'order_totals' => [
                'total_items' => $updatedItem->orderSession->total_items,
                'total_value' => $updatedItem->orderSession->total_value,
            ],
        ]);
    }

    /**
     * Update individual order item cost (AJAX).
     */
    public function updateItemCost(Request $request, OrderItem $orderItem): JsonResponse
    {
        $request->validate([
            'cost' => 'required|numeric|min:0',
        ]);

        if (! $orderItem->orderSession->isEditable()) {
            return response()->json(['error' => 'Order is not editable'], 403);
        }

        $updatedItem = $this->orderService->updateOrderItemCost(
            $orderItem,
            $request->cost
        );

        return response()->json([
            'success' => true,
            'item' => [
                'id' => $updatedItem->id,
                'unit_cost' => $updatedItem->unit_cost,
                'total_cost' => $updatedItem->total_cost,
            ],
            'order_totals' => [
                'total_items' => $updatedItem->orderSession->total_items,
                'total_value' => $updatedItem->orderSession->total_value,
            ],
        ]);
    }
}
