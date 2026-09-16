<?php

namespace App\Services;

use App\Models\KitchenOrder;
use App\Models\KitchenOrderItem;
use App\Models\KitchenProduct;
use App\Models\KitchenStandingOrderItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Builds, confirms and exports per-supplier kitchen orders, and manages the
 * standing weekly pre-fill.
 *
 * Everything written here goes to the Laravel database only. POS is read for
 * product/supplier data and never written. Quantities are whole cases.
 */
class KitchenOrderService
{
    public function __construct(protected SupplierService $supplierService) {}

    /**
     * Distinct suppliers among kitchen products with counts, sorted by count
     * desc. A product counts under every supplier it has a link to.
     *
     * @return Collection<int, array{id: string, name: string, count: int}>
     */
    public function supplierOptions(): Collection
    {
        $productIds = KitchenProduct::pluck('product_id')->all();

        if (empty($productIds)) {
            return collect();
        }

        $products = Product::whereIn('ID', $productIds)
            ->select('PRODUCTS.ID', 'PRODUCTS.CODE')
            ->with('supplierLinks')
            ->get();

        $counts = [];
        foreach ($products as $product) {
            $seen = [];
            foreach ($product->supplierLinks as $link) {
                $sid = (string) $link->SupplierID;
                if ($sid === '' || isset($seen[$sid])) {
                    continue;
                }
                $seen[$sid] = true;
                $counts[$sid] = ($counts[$sid] ?? 0) + 1;
            }
        }

        if (empty($counts)) {
            return collect();
        }

        $names = Supplier::whereIn('SupplierID', array_keys($counts))
            ->get()
            ->keyBy(fn ($s) => (string) $s->SupplierID);

        return collect($counts)
            ->map(fn ($count, $sid) => [
                'id' => (string) $sid,
                'name' => $names[(string) $sid]->Supplier ?? ('Supplier '.$sid),
                'count' => (int) $count,
            ])
            ->sortBy([['count', 'desc'], ['name', 'asc']])
            ->values();
    }

    /**
     * The supplier with the most kitchen products, or null when none.
     */
    public function defaultSupplierId(): ?string
    {
        $first = $this->supplierOptions()->first();

        return $first ? $first['id'] : null;
    }

    /**
     * Rows for the create page for one supplier, sorted by product name,
     * with an image URL resolved on each product.
     *
     * @return Collection<int, array{product: Product, kitchen_product: KitchenProduct, supplier_code: ?string, case_units: int, stock: float}>
     */
    public function productsForSupplier(string $supplierId): Collection
    {
        return $this->rowsForSupplier($supplierId, true);
    }

    /**
     * Build the per-supplier rows. Image resolution is optional because
     * confirming an order only needs the snapshot fields (code, name, case
     * size), not the thumbnails.
     *
     * @return Collection<int, array{product: Product, kitchen_product: KitchenProduct, supplier_code: ?string, case_units: int, stock: float}>
     */
    protected function rowsForSupplier(string $supplierId, bool $withImages): Collection
    {
        $kitchenProducts = KitchenProduct::all()->keyBy('product_id');

        if ($kitchenProducts->isEmpty()) {
            return collect();
        }

        $supplier = Supplier::find($supplierId);

        if (! $supplier) {
            return collect();
        }

        $products = $this->loadProducts($kitchenProducts->keys()->all());

        $rows = collect();

        foreach ($products as $product) {
            $link = $product->supplierLinks->first(
                fn ($l) => (string) $l->SupplierID === (string) $supplierId
            );

            if (! $link) {
                continue;
            }

            $this->prepareProduct($product, $link, $supplier);

            if ($withImages) {
                $product->image_url = $this->resolveImageUrl($product);
            }

            $rows->push([
                'product' => $product,
                'kitchen_product' => $kitchenProducts[$product->ID],
                'supplier_code' => $this->normaliseCode($link->SupplierCode),
                'case_units' => $this->normaliseCaseUnits($link->CaseUnits),
                'stock' => (float) ($product->stockCurrent?->UNITS ?? 0),
            ]);
        }

        return $rows->sortBy(fn ($row) => mb_strtolower($row['product']->NAME ?? ''))->values();
    }

    /**
     * Last N orders (qty + date) per product for one supplier, newest first.
     * One query; grouped in PHP.
     *
     * @param  array<int, string>  $productIds
     * @return array<string, array<int, array{quantity: int, date: \Carbon\Carbon}>>
     */
    public function lastOrdersByProduct(string $supplierId, array $productIds, int $limit = 3): array
    {
        if (empty($productIds)) {
            return [];
        }

        $rows = KitchenOrderItem::query()
            ->join('kitchen_orders', 'kitchen_orders.id', '=', 'kitchen_order_items.kitchen_order_id')
            ->where('kitchen_orders.supplier_id', $supplierId)
            ->whereIn('kitchen_order_items.product_id', $productIds)
            ->orderByDesc('kitchen_orders.created_at')
            ->orderByDesc('kitchen_orders.id')
            ->get([
                'kitchen_order_items.product_id',
                'kitchen_order_items.quantity',
                'kitchen_orders.created_at as ordered_at',
            ]);

        $result = [];

        foreach ($rows as $row) {
            $pid = (string) $row->product_id;
            if (count($result[$pid] ?? []) >= $limit) {
                continue;
            }
            $result[$pid][] = [
                'quantity' => (int) $row->quantity,
                'date' => \Carbon\Carbon::parse($row->ordered_at),
            ];
        }

        return $result;
    }

    /**
     * Standing quantities keyed by POS product id.
     *
     * @return array<string, int>
     */
    public function standingQuantities(): array
    {
        return KitchenStandingOrderItem::pluck('quantity', 'product_id')
            ->map(fn ($q) => (int) $q)
            ->all();
    }

    /**
     * Confirm an order. Rebuilds the product list server-side so names, codes
     * and case sizes are snapshots of POS data, never posted values.
     *
     * @param  array<string, mixed>  $qtyByProductId
     *
     * @throws InvalidArgumentException when no line has a quantity > 0
     */
    public function createOrder(string $supplierId, array $qtyByProductId, ?string $notes, User $user): KitchenOrder
    {
        $supplier = Supplier::find($supplierId);

        if (! $supplier) {
            throw new InvalidArgumentException('Unknown supplier.');
        }

        $lines = [];

        foreach ($this->rowsForSupplier($supplierId, false) as $row) {
            $product = $row['product'];
            $qty = (int) ($qtyByProductId[$product->ID] ?? 0);

            if ($qty <= 0) {
                continue;
            }

            $lines[] = [
                'product_id' => $product->ID,
                'supplier_code' => $row['supplier_code'],
                'product_name' => (string) $product->NAME,
                'case_units' => $row['case_units'],
                'quantity' => $qty,
            ];
        }

        if (empty($lines)) {
            throw new InvalidArgumentException('Enter a quantity for at least one product.');
        }

        $notes = $notes !== null ? trim($notes) : null;

        return DB::transaction(function () use ($supplier, $supplierId, $lines, $notes, $user) {
            $order = KitchenOrder::create([
                'user_id' => $user->id,
                'supplier_id' => (string) $supplierId,
                'supplier_name' => (string) $supplier->Supplier,
                'notes' => $notes === '' ? null : $notes,
            ]);

            $order->items()->createMany($lines);

            $order->update([
                'total_cases' => array_sum(array_column($lines, 'quantity')),
                'line_count' => count($lines),
            ]);

            return $order->load('items');
        });
    }

    /**
     * CSV body for a confirmed order. Header is exactly
     * "Quantity,Supplier Code,Product Name,Case Size".
     *
     * Not fputcsv: PHP quotes any field containing a space, which would turn
     * the header into Quantity,"Supplier Code",... . Fields are quoted only
     * when they contain a comma, quote or line break (RFC 4180).
     */
    public function csv(KitchenOrder $order): string
    {
        $lines = [$this->csvLine(['Quantity', 'Supplier Code', 'Product Name', 'Case Size'])];

        $items = $order->items
            ->filter(fn ($item) => (int) $item->quantity > 0)
            ->sortBy(fn ($item) => mb_strtolower((string) $item->product_name))
            ->values();

        foreach ($items as $item) {
            $lines[] = $this->csvLine([
                (int) $item->quantity,
                $item->supplier_code ?? '',
                (string) $item->product_name,
                (int) $item->case_units,
            ]);
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  array<int, string|int>  $fields
     */
    protected function csvLine(array $fields): string
    {
        return implode(',', array_map(function ($field) {
            $field = (string) $field;

            if (preg_match('/[",\r\n]/', $field)) {
                return '"'.str_replace('"', '""', $field).'"';
            }

            return $field;
        }, $fields));
    }

    /**
     * Save standing quantities. qty > 0 upserts; 0/blank deletes; ids not
     * posted are untouched.
     *
     * @param  array<string, mixed>  $qtyByProductId
     */
    public function saveStandingOrder(array $qtyByProductId, User $user): void
    {
        DB::transaction(function () use ($qtyByProductId, $user) {
            foreach ($qtyByProductId as $productId => $qty) {
                $productId = (string) $productId;
                $qty = (int) ($qty ?? 0);

                if ($qty > 0) {
                    KitchenStandingOrderItem::updateOrCreate(
                        ['product_id' => $productId],
                        ['quantity' => $qty, 'updated_by' => $user->id]
                    );
                } else {
                    KitchenStandingOrderItem::where('product_id', $productId)->delete();
                }
            }
        });
    }

    /**
     * Every kitchen product for the standing-order page, grouped by supplier
     * name (first link's supplier). "No supplier link" and "Missing POS
     * product" groups come last and are not orderable.
     *
     * @return Collection<string, Collection<int, array{product: ?Product, kitchen_product: KitchenProduct, supplier_code: ?string, case_units: int, image_url: ?string, orderable: bool}>>
     */
    public function allProductsGroupedBySupplier(): Collection
    {
        $kitchenProducts = KitchenProduct::all()->keyBy('product_id');

        if ($kitchenProducts->isEmpty()) {
            return collect();
        }

        $products = $this->loadProducts($kitchenProducts->keys()->all())->keyBy('ID');

        $supplierIds = $products
            ->flatMap(fn ($p) => $p->supplierLinks->pluck('SupplierID'))
            ->map(fn ($id) => (string) $id)
            ->filter()
            ->unique()
            ->values();

        $suppliers = $supplierIds->isEmpty()
            ? collect()
            : Supplier::whereIn('SupplierID', $supplierIds->all())->get()->keyBy(fn ($s) => (string) $s->SupplierID);

        $noLink = 'No supplier link';
        $missing = 'Missing POS product';

        $grouped = [];

        foreach ($kitchenProducts as $productId => $kitchenProduct) {
            $product = $products[$productId] ?? null;

            if (! $product) {
                $grouped[$missing][] = [
                    'product' => null,
                    'kitchen_product' => $kitchenProduct,
                    'supplier_code' => null,
                    'case_units' => 1,
                    'image_url' => null,
                    'orderable' => false,
                ];

                continue;
            }

            $link = $product->supplierLinks->first();
            $supplier = $link ? ($suppliers[(string) $link->SupplierID] ?? null) : null;

            if (! $link || ! $supplier) {
                $product->image_url = $product->has_image ? route('products.image', $product->ID) : null;

                $grouped[$noLink][] = [
                    'product' => $product,
                    'kitchen_product' => $kitchenProduct,
                    'supplier_code' => $link ? $this->normaliseCode($link->SupplierCode) : null,
                    'case_units' => $link ? $this->normaliseCaseUnits($link->CaseUnits) : 1,
                    'image_url' => $product->image_url,
                    'orderable' => false,
                ];

                continue;
            }

            $this->prepareProduct($product, $link, $supplier);
            $product->image_url = $this->resolveImageUrl($product);

            $grouped[(string) $supplier->Supplier][] = [
                'product' => $product,
                'kitchen_product' => $kitchenProduct,
                'supplier_code' => $this->normaliseCode($link->SupplierCode),
                'case_units' => $this->normaliseCaseUnits($link->CaseUnits),
                'image_url' => $product->image_url,
                'orderable' => true,
            ];
        }

        $sortRows = fn (array $rows) => collect($rows)
            ->sortBy(fn ($row) => mb_strtolower($row['product']->NAME ?? ''))
            ->values();

        $orderable = collect($grouped)
            ->except([$noLink, $missing])
            ->sortKeys(SORT_NATURAL | SORT_FLAG_CASE)
            ->map($sortRows);

        $result = $orderable;

        foreach ([$noLink, $missing] as $tail) {
            if (! empty($grouped[$tail])) {
                $result = $result->put($tail, $sortRows($grouped[$tail]));
            }
        }

        return $result;
    }

    /**
     * Load POS products with the relations the image resolver needs.
     *
     * Only the columns this flow reads are selected (ID, NAME, CODE) plus a
     * has_image flag computed in SQL, so the IMAGE blob itself is never
     * transferred. The blob is served separately by the products.image route.
     *
     * @param  array<int, string>  $productIds
     * @return Collection<int, Product>
     */
    protected function loadProducts(array $productIds): Collection
    {
        return Product::whereIn('ID', $productIds)
            ->select(['PRODUCTS.ID', 'PRODUCTS.NAME', 'PRODUCTS.CODE'])
            ->addSelect(DB::raw('(CASE WHEN IMAGE IS NOT NULL AND LENGTH(IMAGE) > 0 THEN 1 ELSE 0 END) as has_image'))
            ->with(['supplierLinks', 'stockCurrent'])
            ->get();
    }

    /**
     * Pin the chosen link + supplier on the product. supplierLink() hasOne is
     * non-deterministic when a barcode has several links, so the link for the
     * selected supplier is set explicitly before anything reads it.
     */
    protected function prepareProduct(Product $product, $link, Supplier $supplier): void
    {
        $product->setRelation('supplierLink', $link);
        $product->setRelation('supplier', $supplier);
    }

    /**
     * One image URL per product: POS blob first, then the supplier CDN.
     * Requires prepareProduct() to have run (the CDN lookup reads
     * $product->supplier and $product->supplierLink).
     */
    protected function resolveImageUrl(Product $product): ?string
    {
        return $product->has_image
            ? route('products.image', $product->ID)
            : $this->supplierService->getExternalImageUrl($product);
    }

    protected function normaliseCode(?string $code): ?string
    {
        $code = $code !== null ? trim($code) : '';

        return $code === '' ? null : $code;
    }

    protected function normaliseCaseUnits($units): int
    {
        return max(1, (int) $units);
    }
}
