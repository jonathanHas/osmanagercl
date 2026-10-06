<?php

namespace App\Services\Shop;

use App\Models\KitchenProduct;
use App\Models\OrderItem;
use App\Models\OrderSession;
use App\Services\OrderService;
use Illuminate\Support\Collection;

/**
 * Shop mode order review (design screen 20): what the tablet shows of a
 * supplier order, and the one write it may make.
 *
 * Everything here reads what generation stored: stock is the generation-time
 * snapshot in `context_data.current_stock`, sales are `context_data.weekly_sales`.
 * There is no live POS query per row. Writes go through
 * `OrderService::updateOrderItemCases()` so the learning log and the session
 * totals stay right.
 */
class OrderReviewService
{
    /** How many drafts and completed sessions the Orders list shows. */
    public const DRAFT_LIMIT = 20;

    public const COMPLETED_LIMIT = 5;

    public function __construct(private OrderService $orderService) {}

    /**
     * @return array{drafts: Collection, completed: Collection, draft_total: int}
     */
    public function listing(): array
    {
        $base = fn () => OrderSession::with('supplier', 'user')->withCount('items');

        return [
            'drafts' => $base()->where('status', 'draft')->latest('created_at')->limit(self::DRAFT_LIMIT)->get(),
            'completed' => $base()->completed()->latest('created_at')->limit(self::COMPLETED_LIMIT)->get(),
            'draft_total' => OrderSession::where('status', 'draft')->count(),
        ];
    }

    public function header(OrderSession $order): array
    {
        $order->loadMissing('supplier', 'user');

        $weeksAfterDelivery = null;
        if ($order->order_date && $order->coverage_ends_on) {
            $weeksAfterDelivery = round($order->order_date->diffInDays($order->coverage_ends_on) / 7, 1);
        }

        $items = $order->items();

        return [
            'id' => $order->id,
            'supplier' => $order->supplier?->Supplier ?: 'Unknown supplier',
            'delivery_date' => $order->order_date?->format('D j M'),
            'created_by' => self::firstName($order->user?->name),
            'status' => ucfirst((string) $order->status),
            'editable' => $order->isEditable(),
            'lasts_until' => $order->coverage_ends_on?->format('D j M'),
            'weeks_after_delivery' => $weeksAfterDelivery,
            'history_weeks' => (int) $order->sales_history_weeks,
            'total_value' => (float) $order->total_value,
            'ordered_count' => (clone $items)->where('final_quantity', '>', 0)->count(),
            'item_count' => (clone $items)->count(),
            'export_url' => route('shop.orders.export', $order),
        ];
    }

    public function rows(OrderSession $order): array
    {
        $kitchenIds = $this->kitchenIds();

        return $order->items()
            ->with('product.stocking')
            ->orderBy('id')
            ->get()
            ->map(fn (OrderItem $item) => $this->row($order, $item, $kitchenIds))
            ->all();
    }

    /**
     * @param  array<string, true>  $kitchenIds  product ids, as keys
     */
    public function row(OrderSession $order, OrderItem $item, array $kitchenIds): array
    {
        $item->loadMissing('product.stocking');

        $product = $item->product;
        $context = is_array($item->context_data) ? $item->context_data : [];

        $weekly = array_values(array_map(
            fn ($week) => (float) (is_array($week) ? ($week['units'] ?? 0) : $week),
            is_array($context['weekly_sales'] ?? null) ? $context['weekly_sales'] : []
        ));

        $tags = [];
        if ($product && ! $product->stocking) {
            $tags[] = 'Destocked';
        }
        if (isset($kitchenIds[$item->product_id])) {
            $tags[] = 'Kitchen';
        }

        $targetWeeks = $context['target_weeks']
            ?? ($order->coverage_days ? $order->coverage_days / 7 : 1);

        return [
            'id' => $item->id,
            'url' => route('shop.orders.item', [$order, $item]),
            'name' => $product?->NAME ?? 'Unknown product',
            'code' => $product?->CODE ?? (string) $item->product_id,
            'group' => $item->case_units > 1 ? 'case' : 'unit',
            'case_units' => (int) $item->case_units,
            'unit_cost' => (float) $item->unit_cost,
            'priority' => $item->added_via_search ? 'added' : $item->review_priority,
            'tags' => $tags,
            'stock' => (float) ($context['current_stock'] ?? 0),
            'suggested_cases' => (float) $item->suggested_cases,
            'final_cases' => (float) $item->final_cases,
            'total_cost' => (float) $item->total_cost,
            'weekly_sales' => $weekly,
            'avg_weekly' => (float) ($context['avg_weekly_sales'] ?? 0),
            'peak_weekly' => (float) ($context['peak_weekly_sales'] ?? ($weekly ? max($weekly) : 0)),
            'sold' => (float) ($context['weekly_sales_total'] ?? array_sum($weekly)),
            'target_weeks' => (float) ($targetWeeks ?: 1),
        ];
    }

    /**
     * @throws \DomainException when the order is no longer a draft
     */
    public function update(OrderSession $order, OrderItem $item, int $cases): array
    {
        if (! $order->isEditable()) {
            throw new \DomainException('Order is not editable');
        }

        $item = $this->orderService->updateOrderItemCases($item, $cases);

        return [
            'item' => $this->row($order, $item, $this->kitchenIds()),
            'order' => $this->header($order->fresh()),
        ];
    }

    /**
     * @return array<string, true>
     */
    private function kitchenIds(): array
    {
        return KitchenProduct::pluck('product_id')
            ->mapWithKeys(fn ($id) => [(string) $id => true])
            ->all();
    }

    private static function firstName(?string $name): string
    {
        $first = strtok(trim((string) $name), ' ');

        return $first === false || $first === '' ? 'unknown' : $first;
    }
}
