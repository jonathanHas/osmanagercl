<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use App\Models\OrderSession;
use App\Models\Supplier;
use App\Services\OrderService;
use App\Services\SalesDataSyncService;
use App\Services\SupplierService;
use App\Support\SpecialOrderCategories;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    protected OrderService $orderService;

    protected SalesDataSyncService $salesDataSyncService;

    protected SupplierService $supplierService;

    public function __construct(OrderService $orderService, SalesDataSyncService $salesDataSyncService, SupplierService $supplierService)
    {
        $this->orderService = $orderService;
        $this->salesDataSyncService = $salesDataSyncService;
        $this->supplierService = $supplierService;
    }

    /**
     * Display a listing of order sessions.
     */
    public function index(Request $request): View
    {
        $selectedSupplierId = $request->input('supplier_id');

        $query = OrderSession::with(['supplier', 'user'])
            ->orderBy('created_at', 'desc');

        if ($selectedSupplierId !== null && $selectedSupplierId !== '') {
            $query->where('supplier_id', $selectedSupplierId);
        }

        $orders = $query->paginate(20)->withQueryString();

        $supplierIdsWithOrders = OrderSession::query()
            ->select('supplier_id')
            ->distinct()
            ->pluck('supplier_id');

        $availableSuppliers = Supplier::whereIn('SupplierID', $supplierIdsWithOrders)
            ->orderBy('Supplier')
            ->get();

        return view('orders.index', compact('orders', 'availableSuppliers', 'selectedSupplierId'));
    }

    /**
     * Show the form for creating a new order.
     */
    public function create(): View
    {
        $suppliers = Supplier::orderBy('Supplier')->get();

        $specialCategoryGroups = SpecialOrderCategories::mapSuppliers($suppliers);

        return view('orders.create', compact('suppliers', 'specialCategoryGroups'));
    }

    /**
     * Generate a new order session with suggestions.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'supplier_id' => 'required|exists:App\Models\Supplier,SupplierID',
            'order_date' => 'required|date|after_or_equal:today',
            'coverage_end_date' => 'required|date',
            'sales_history_weeks' => 'nullable|integer|min:1|max:26',
            'category_overrides' => 'nullable|array',
            'category_overrides.*.coverage_end_date' => 'nullable|date|after_or_equal:order_date',
            'christmas_comparison_enabled' => 'nullable|boolean',
            'christmas_start_date' => 'nullable|required_if:christmas_comparison_enabled,true|date',
            'christmas_end_date' => 'nullable|required_if:christmas_comparison_enabled,true|date|after_or_equal:christmas_start_date',
            'comparison_years' => 'nullable|array',
            'comparison_years.*' => 'integer|min:2020|max:'.date('Y'),
        ]);

        // Extend timeout for large order generation (300-500 products with Christmas data)
        // This is a temporary fix while we implement bulk pre-fetching optimization
        set_time_limit(300);

        $orderDate = Carbon::parse($request->order_date);
        $coverageEndDate = Carbon::parse($request->coverage_end_date);

        if ($coverageEndDate->lessThan($orderDate)) {
            return back()
                ->withErrors(['coverage_end_date' => 'Coverage end must be on or after the delivery date.'])
                ->withInput();
        }

        $specialGroups = SpecialOrderCategories::forSupplier((string) $request->supplier_id);
        $rawOverrides = $request->input('category_overrides', []);
        $categoryOverrides = $this->normaliseCategoryOverrides($rawOverrides, $specialGroups, $orderDate);

        $coverageDays = $orderDate->diffInDays($coverageEndDate) + 1;
        $salesHistoryWeeks = (int) $request->input('sales_history_weeks', 8);

        // Handle Christmas comparison parameters
        $christmasEnabled = $request->boolean('christmas_comparison_enabled', false);
        $christmasConfig = null;

        if ($christmasEnabled && $request->filled(['christmas_start_date', 'christmas_end_date', 'comparison_years'])) {
            $christmasConfig = [
                'comparison_years' => array_map('intval', $request->input('comparison_years', [])),
                'date_range' => [
                    'start' => $request->input('christmas_start_date'),
                    'end' => $request->input('christmas_end_date'),
                ],
                'calculation_mode' => 'max', // Always use max mode as per plan
            ];
        }

        try {
            $importLog = $this->salesDataSyncService->ensureDailySummariesAreFresh($salesHistoryWeeks);

            if ($importLog !== null) {
                session()->flash('info', sprintf(
                    'Sales data imported for %s through %s.',
                    optional($importLog->start_date)->format('M j, Y'),
                    optional($importLog->end_date)->format('M j, Y')
                ));
            }
        } catch (\Throwable $exception) {
            Log::warning('Automatic sales import failed prior to order generation', [
                'error' => $exception->getMessage(),
            ]);

            session()->flash('warning', 'We could not refresh sales data automatically; using the most recent import instead.');
        }

        $orderSession = $this->orderService->generateOrderSuggestions(
            $request->supplier_id,
            $orderDate,
            [
                'coverage_days' => $coverageDays,
                'coverage_ends_on' => $coverageEndDate,
                'sales_history_weeks' => $salesHistoryWeeks,
                'category_overrides' => $categoryOverrides,
                'category_groups' => $specialGroups,
                'christmas_comparison_enabled' => $christmasEnabled,
                'christmas_window_config' => $christmasConfig,
            ]
        );

        return redirect()->route('orders.show', $orderSession)
            ->with('success', 'Order suggestions generated successfully.');
    }

    /**
     * Generate a new order session with SSE progress feedback.
     */
    public function storeWithProgress(Request $request): StreamedResponse
    {
        $request->validate([
            'supplier_id' => 'required|exists:App\Models\Supplier,SupplierID',
            'order_date' => 'required|date|after_or_equal:today',
            'coverage_end_date' => 'required|date',
            'sales_history_weeks' => 'nullable|integer|min:1|max:26',
            'category_overrides' => 'nullable|array',
            'category_overrides.*.coverage_end_date' => 'nullable|date|after_or_equal:order_date',
            'christmas_comparison_enabled' => 'nullable|boolean',
            'christmas_start_date' => 'nullable|required_if:christmas_comparison_enabled,true|date',
            'christmas_end_date' => 'nullable|required_if:christmas_comparison_enabled,true|date|after_or_equal:christmas_start_date',
            'comparison_years' => 'nullable|array',
            'comparison_years.*' => 'integer|min:2020|max:'.date('Y'),
        ]);

        return new StreamedResponse(function () use ($request) {
            try {
                set_time_limit(300);

                $orderDate = Carbon::parse($request->order_date);
                $coverageEndDate = Carbon::parse($request->coverage_end_date);

                if ($coverageEndDate->lessThan($orderDate)) {
                    $this->sendStreamEvent('error', message: 'Coverage end must be on or after the delivery date.');

                    return;
                }

                $specialGroups = SpecialOrderCategories::forSupplier((string) $request->supplier_id);
                $rawOverrides = $request->input('category_overrides', []);
                $categoryOverrides = $this->normaliseCategoryOverrides($rawOverrides, $specialGroups, $orderDate);

                $coverageDays = $orderDate->diffInDays($coverageEndDate) + 1;
                $salesHistoryWeeks = (int) $request->input('sales_history_weeks', 8);

                $christmasEnabled = $request->boolean('christmas_comparison_enabled', false);
                $christmasConfig = null;

                if ($christmasEnabled && $request->filled(['christmas_start_date', 'christmas_end_date', 'comparison_years'])) {
                    $christmasConfig = [
                        'comparison_years' => array_map('intval', $request->input('comparison_years', [])),
                        'date_range' => [
                            'start' => $request->input('christmas_start_date'),
                            'end' => $request->input('christmas_end_date'),
                        ],
                        'calculation_mode' => 'max',
                    ];
                }

                $progressCallback = function (string $step, string $message, int $progress) {
                    $this->sendStreamEvent('progress', $step, $message, $progress);
                };

                $this->sendStreamEvent('progress', 'importing_sales', 'Checking sales data freshness...', 5);

                try {
                    $importLog = $this->salesDataSyncService->ensureDailySummariesAreFresh($salesHistoryWeeks, $progressCallback);

                    if ($importLog !== null) {
                        session()->flash('info', sprintf(
                            'Sales data imported for %s through %s.',
                            optional($importLog->start_date)->format('M j, Y'),
                            optional($importLog->end_date)->format('M j, Y')
                        ));
                    }
                } catch (\Throwable $exception) {
                    Log::warning('Automatic sales import failed prior to order generation', [
                        'error' => $exception->getMessage(),
                    ]);
                    session()->flash('warning', 'We could not refresh sales data automatically; using the most recent import instead.');
                }

                $orderSession = $this->orderService->generateOrderSuggestions(
                    $request->supplier_id,
                    $orderDate,
                    [
                        'coverage_days' => $coverageDays,
                        'coverage_ends_on' => $coverageEndDate,
                        'sales_history_weeks' => $salesHistoryWeeks,
                        'category_overrides' => $categoryOverrides,
                        'category_groups' => $specialGroups,
                        'christmas_comparison_enabled' => $christmasEnabled,
                        'christmas_window_config' => $christmasConfig,
                    ],
                    $progressCallback
                );

                $this->sendStreamEvent('complete', redirect_url: route('orders.show', $orderSession), message: 'Order generated successfully!');
            } catch (\Throwable $exception) {
                Log::error('Order generation failed during streaming', [
                    'error' => $exception->getMessage(),
                    'trace' => $exception->getTraceAsString(),
                ]);
                $this->sendStreamEvent('error', message: 'Order generation failed: '.$exception->getMessage());
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Send an SSE event.
     */
    private function sendStreamEvent(string $type, ?string $step = null, ?string $message = null, ?int $progress = null, ?string $redirect_url = null): void
    {
        $data = ['type' => $type];

        if ($step !== null) {
            $data['step'] = $step;
        }
        if ($message !== null) {
            $data['message'] = $message;
        }
        if ($progress !== null) {
            $data['progress'] = $progress;
        }
        if ($redirect_url !== null) {
            $data['redirect_url'] = $redirect_url;
        }

        echo 'data: '.json_encode($data)."\n\n";

        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }

    /**
     * Display the order review interface.
     */
    public function show(OrderSession $order): View|RedirectResponse
    {
        // If Christmas mode is enabled, redirect to Christmas review page
        if ($order->christmas_comparison_enabled) {
            return redirect()->route('orders.christmas-review', $order);
        }

        $context = $this->prepareOrderSessionContext($order);

        return view('orders.show', [
            'order' => $order,
            'statistics' => $context['statistics'],
            'categoryGroups' => $context['categoryGroups'],
            'supplierService' => $this->supplierService,
        ]);
    }

    /**
     * Display the order review interface using the A2 layout experiment.
     */
    public function showLayoutA2(OrderSession $order): View|RedirectResponse
    {
        // If Christmas mode is enabled, redirect to Christmas review page
        if ($order->christmas_comparison_enabled) {
            return redirect()->route('orders.christmas-review', $order);
        }

        $context = $this->prepareOrderSessionContext($order);

        return view('orders.show-layout-a2', [
            'order' => $order,
            'statistics' => $context['statistics'],
            'categoryGroups' => $context['categoryGroups'],
            'supplierService' => $this->supplierService,
        ]);
    }

    /**
     * Display the order review interface using the dense A2 layout.
     */
    public function showLayoutA2Dense(OrderSession $order): View
    {
        $context = $this->prepareOrderSessionContext($order);

        return view('orders.show-layout-a2-dense', [
            'order' => $order,
            'statistics' => $context['statistics'],
            'categoryGroups' => $context['categoryGroups'],
            'supplierService' => $this->supplierService,
        ]);
    }

    /**
     * Display the Christmas comparison review page.
     */
    public function showChristmasReview(OrderSession $order): View|RedirectResponse
    {
        // If Christmas mode is not enabled, redirect to normal review
        if (! $order->christmas_comparison_enabled) {
            return redirect()->route('orders.show', $order);
        }

        $context = $this->prepareOrderSessionContext($order);

        return view('orders.show-christmas', [
            'order' => $order,
            'statistics' => $context['statistics'],
            'categoryGroups' => $context['categoryGroups'],
            'supplierService' => $this->supplierService,
        ]);
    }

    /**
     * Display the order review interface in grid view layout.
     */
    public function gridView(OrderSession $order): View
    {
        // Eager load all necessary relationships
        $order->load([
            'items' => function ($query) {
                $query->with(['product.supplier']);
            },
            'supplier',
            'user',
        ]);

        $statistics = $this->orderService->getOrderStatistics($order);
        $categoryGroups = SpecialOrderCategories::forSupplier((string) $order->supplier_id);

        return view('orders.grid-view', compact('order', 'statistics', 'categoryGroups') + ['supplierService' => $this->supplierService]);
    }

    /**
     * Update category-specific coverage overrides and rebuild an order session.
     */
    public function updateCategoryCoverage(Request $request, OrderSession $order): RedirectResponse
    {
        if (! $order->isEditable()) {
            return back()->with('error', 'Only draft orders can be recalculated.');
        }

        $order->loadMissing('supplier');

        $categoryGroups = SpecialOrderCategories::forSupplier((string) $order->supplier_id);

        if (empty($categoryGroups)) {
            return back()->with('error', 'This supplier does not support category-specific coverage.');
        }

        $existingOverrides = $order->coverage_overrides ?? [];
        $orderDate = $order->order_date instanceof Carbon
            ? $order->order_date->copy()
            : Carbon::parse($order->order_date ?? now());
        $orderDateRule = $orderDate->format('Y-m-d');

        $singleGroupKey = $request->input('category_key');

        if ($singleGroupKey !== null) {
            if (! array_key_exists($singleGroupKey, $categoryGroups)) {
                return back()->with('error', 'Unknown category override selection.');
            }

            $request->validate([
                'coverage_end_date' => 'nullable|date|after_or_equal:'.$orderDateRule,
            ]);

            $clearOverride = $request->boolean('clear');
            $newOverrides = $existingOverrides;
            $updatedPayload = $clearOverride
                ? null
                : $this->buildOverrideEntry(
                    $request->input('coverage_end_date'),
                    $categoryGroups[$singleGroupKey],
                    $orderDate
                );

            if ($updatedPayload === null) {
                unset($newOverrides[$singleGroupKey]);
            } else {
                $newOverrides[$singleGroupKey] = $updatedPayload;
            }

            $existingValue = $existingOverrides[$singleGroupKey] ?? null;
            $newValue = $newOverrides[$singleGroupKey] ?? null;

            if ($existingValue === $newValue) {
                $label = $categoryGroups[$singleGroupKey]['label'] ?? $singleGroupKey;

                return back()->with('info', 'No changes detected for '.$label.'.');
            }

            $this->orderService->regenerateOrderSession($order, [
                'coverage_days' => $order->coverage_days,
                'coverage_ends_on' => $order->coverage_ends_on,
                'sales_history_weeks' => $order->sales_history_weeks,
                'category_overrides' => $newOverrides,
                'category_groups' => $categoryGroups,
                'order_date' => $orderDate,
                'rebuild_groups' => [$singleGroupKey],
            ]);

            $label = $categoryGroups[$singleGroupKey]['label'] ?? ucfirst($singleGroupKey);

            return redirect()
                ->route('orders.show', $order->fresh())
                ->with('success', 'Updated coverage for '.$label.'.');
        }

        $request->validate([
            'category_overrides' => 'required|array',
            'category_overrides.*.coverage_end_date' => 'nullable|date|after_or_equal:'.$orderDateRule,
        ]);

        $rawOverrides = $request->input('category_overrides', []);
        $normalisedOverrides = $this->normaliseCategoryOverrides(
            $rawOverrides,
            $categoryGroups,
            $orderDate
        );

        $mergedOverrides = $existingOverrides;
        foreach ($normalisedOverrides as $key => $payload) {
            $mergedOverrides[$key] = $payload;
        }

        $changedGroups = [];
        foreach ($categoryGroups as $key => $group) {
            $before = $existingOverrides[$key] ?? null;
            $after = $mergedOverrides[$key] ?? null;
            if ($before !== $after) {
                $changedGroups[] = $key;
            }
        }

        if (empty($changedGroups)) {
            return back()->with('info', 'No category coverage updates detected.');
        }

        $this->orderService->regenerateOrderSession($order, [
            'coverage_days' => $order->coverage_days,
            'coverage_ends_on' => $order->coverage_ends_on,
            'sales_history_weeks' => $order->sales_history_weeks,
            'category_overrides' => $mergedOverrides,
            'category_groups' => $categoryGroups,
            'order_date' => $orderDate,
            'rebuild_groups' => $changedGroups,
        ]);

        return redirect()
            ->route('orders.show', $order->fresh())
            ->with('success', 'Category coverage updated and order regenerated.');
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
     * Load shared relationships and derived data for order detail views.
     *
     * @return array{statistics: array<string, mixed>, categoryGroups: array<string, mixed>}
     */
    protected function prepareOrderSessionContext(OrderSession $order): array
    {
        $order->load([
            'items.product.supplierLinks.supplier',
            'items.product.stocking',
            'items.product.orderSettings',
            'supplier',
            'user',
        ]);

        $statistics = $this->orderService->getOrderStatistics($order);
        $categoryGroups = SpecialOrderCategories::forSupplier((string) $order->supplier_id);

        return [
            'statistics' => $statistics,
            'categoryGroups' => $categoryGroups,
        ];
    }

    /**
     * Normalise category override payload against known groups.
     *
     * @param  array<string, array<string, string|null>>  $rawOverrides
     * @param  array<string, array<string, mixed>>  $categoryGroups
     * @return array<string, array<string, string|int>>
     */
    protected function normaliseCategoryOverrides(array $rawOverrides, array $categoryGroups, Carbon $orderDate): array
    {
        $normalised = [];

        foreach ($rawOverrides as $key => $payload) {
            if (! array_key_exists($key, $categoryGroups)) {
                continue;
            }

            $entry = $this->buildOverrideEntry(
                $payload['coverage_end_date'] ?? null,
                $categoryGroups[$key],
                $orderDate
            );

            if ($entry !== null) {
                $normalised[$key] = $entry;
            }
        }

        return $normalised;
    }

    /**
     * Build a normalised override entry for a category group.
     */
    protected function buildOverrideEntry(?string $requestedDate, array $groupDefinition, Carbon $orderDate): ?array
    {
        if ($requestedDate === null || $requestedDate === '') {
            return null;
        }

        try {
            $overrideEnd = Carbon::parse($requestedDate)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }

        if ($overrideEnd->lessThan($orderDate)) {
            return null;
        }

        $coverageDays = $orderDate->diffInDays($overrideEnd) + 1;

        $entry = [
            'coverage_ends_on' => $overrideEnd->toDateString(),
            'coverage_days' => $coverageDays,
        ];

        if (! empty($groupDefinition['label'])) {
            $entry['label'] = $groupDefinition['label'];
        }

        return $entry;
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
        $coverageDays = $order->coverage_days ?? 7;
        $salesHistoryWeeks = $order->sales_history_weeks ?? 8;
        $coverageEndDate = $newOrderDate->copy()->addDays(max(1, $coverageDays) - 1);

        $newOrderSession = $this->orderService->generateOrderSuggestions(
            $order->supplier_id,
            $newOrderDate,
            [
                'coverage_days' => $coverageDays,
                'coverage_ends_on' => $coverageEndDate,
                'sales_history_weeks' => $salesHistoryWeeks,
            ]
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

    /**
     * Update review priority for an individual order item (AJAX).
     */
    public function updateItemPriority(Request $request, OrderItem $orderItem): JsonResponse
    {
        $request->validate([
            'priority' => 'required|in:safe,standard,review',
            'apply_to_product' => 'sometimes|boolean',
        ]);

        $priority = $request->input('priority');
        $applyToProduct = $request->boolean('apply_to_product', true);

        $orderItem->review_priority = $priority;
        $orderItem->auto_approved = $priority === 'safe';
        $orderItem->save();

        $setting = null;

        if ($applyToProduct && $orderItem->product_id) {
            $setting = $this->orderService->updateProductPriority(
                $orderItem->product_id,
                $priority
            );
        }

        return response()->json([
            'success' => true,
            'message' => sprintf(
                'Marked %s as %s',
                $orderItem->product?->NAME ?? 'product',
                ucfirst($priority)
            ),
            'item' => [
                'id' => $orderItem->id,
                'review_priority' => $orderItem->review_priority,
                'auto_approved' => (bool) $orderItem->auto_approved,
            ],
            'setting' => $setting,
        ]);
    }

    /**
     * Mockup with real Vico data for UI testing.
     */
    public function mockupVicoLive(): View
    {
        $supplierId = '84'; // Vico supplier ID
        $orderDate = now()->addDays(3);

        // Generate temporary order session
        $orderSession = $this->orderService->generateOrderSuggestions($supplierId, $orderDate, [
            'coverage_days' => 14,
            'coverage_ends_on' => $orderDate->copy()->addDays(13),
            'sales_history_weeks' => 8,
        ]);

        return view('orders.mockup-vico-live', compact('orderSession'));
    }
}
