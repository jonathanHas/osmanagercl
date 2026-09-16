<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreKitchenOrderRequest;
use App\Http\Requests\UpdateKitchenStandingOrderRequest;
use App\Models\KitchenOrder;
use App\Services\KitchenOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Per-supplier kitchen orders (create → confirm → CSV → history) and the
 * standing weekly pre-fill. Access is the enclosing auth middleware, the same
 * as the rest of the Kitchen section.
 */
class KitchenOrderController extends Controller
{
    public function __construct(protected KitchenOrderService $service) {}

    /**
     * Order history, optionally filtered by supplier.
     */
    public function index(Request $request): View
    {
        $supplierId = $request->input('supplier');

        $orders = KitchenOrder::with('user')
            ->latest()
            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
            ->paginate(25)
            ->withQueryString();

        return view('kitchen.orders.index', [
            'orders' => $orders,
            'suppliers' => $this->service->supplierOptions(),
            'selectedSupplierId' => $supplierId !== null && $supplierId !== '' ? (string) $supplierId : null,
        ]);
    }

    /**
     * Create-order page for one supplier (default: the one with most kitchen products).
     */
    public function create(Request $request): View|RedirectResponse
    {
        $suppliers = $this->service->supplierOptions();
        $requested = $request->input('supplier');
        $supplierId = ($requested !== null && $requested !== '') ? (string) $requested : $this->service->defaultSupplierId();

        $selectedSupplier = $suppliers->firstWhere('id', $supplierId);

        if (! $selectedSupplier) {
            if ($requested !== null && $requested !== '' && $this->service->defaultSupplierId()) {
                return redirect()
                    ->route('kitchen.orders.create', ['supplier' => $this->service->defaultSupplierId()])
                    ->with('error', 'That supplier has no kitchen products. Showing the default supplier instead.');
            }

            // No kitchen products with a supplier link at all.
            return view('kitchen.orders.create', [
                'rows' => collect(),
                'lastOrders' => [],
                'standing' => [],
                'suppliers' => $suppliers,
                'selectedSupplier' => null,
            ]);
        }

        $rows = $this->service->productsForSupplier($supplierId);
        $productIds = $rows->map(fn ($row) => $row['product']->ID)->all();

        return view('kitchen.orders.create', [
            'rows' => $rows,
            'lastOrders' => $this->service->lastOrdersByProduct($supplierId, $productIds),
            'standing' => $this->service->standingQuantities(),
            'suppliers' => $suppliers,
            'selectedSupplier' => $selectedSupplier,
        ]);
    }

    /**
     * Confirm (log) an order.
     */
    public function store(StoreKitchenOrderRequest $request): RedirectResponse
    {
        $supplierId = (string) $request->input('supplier_id');

        try {
            $order = $this->service->createOrder(
                $supplierId,
                $request->input('qty', []) ?? [],
                $request->input('notes'),
                $request->user()
            );
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('kitchen.orders.create', ['supplier' => $supplierId])
                ->withInput()
                ->with('error', 'Enter a quantity for at least one product.');
        }

        return redirect()
            ->route('kitchen.orders.show', $order)
            ->with('success', 'Order logged. Download the CSV below.');
    }

    /**
     * A confirmed order.
     */
    public function show(KitchenOrder $kitchenOrder): View
    {
        $kitchenOrder->load(['items', 'user']);

        return view('kitchen.orders.show', ['order' => $kitchenOrder]);
    }

    /**
     * CSV download: Quantity, Supplier Code, Product Name, Case Size.
     */
    public function csv(KitchenOrder $kitchenOrder): Response
    {
        $kitchenOrder->load('items');

        return response($this->service->csv($kitchenOrder))
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="'.$kitchenOrder->csvFilename().'"');
    }

    /**
     * Standing weekly order page.
     */
    public function standing(): View
    {
        return view('kitchen.orders.standing', [
            'groups' => $this->service->allProductsGroupedBySupplier(),
            'standing' => $this->service->standingQuantities(),
        ]);
    }

    /**
     * Save standing quantities.
     */
    public function updateStanding(UpdateKitchenStandingOrderRequest $request): RedirectResponse
    {
        $this->service->saveStandingOrder($request->input('qty', []) ?? [], $request->user());

        return redirect()
            ->route('kitchen.standing-order.edit')
            ->with('success', 'Standing order saved.');
    }
}
