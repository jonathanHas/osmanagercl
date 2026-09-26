<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRequestRequest;
use App\Http\Requests\UpdateCustomerRequestItemStatusRequest;
use App\Models\CustomerRequest;
use App\Models\CustomerRequestItem;
use App\Services\CustomerRequestService;
use App\Services\ProductSearch\ProductSearchService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerRequestController extends Controller
{
    public function __construct(
        private CustomerRequestService $service,
        private ProductSearchService $productSearch,
    ) {}

    /**
     * The board. Public (no auth) so the shop-floor tablet can show it;
     * staff who are signed in and have the permission also get the controls.
     */
    public function index(Request $request): View
    {
        $canManage = $request->user()?->can('customer-requests.manage') ?? false;

        // Staff pick a view with ?show=; ?closed=1 is kept as an alias for the
        // Done view so old links and bookmarks still work. Guests get the board
        // view: everything still outstanding, put-aside lines included.
        $view = $request->boolean('closed') ? 'done' : (string) $request->query('show', 'open');

        if (! in_array($view, ['open', 'aside', 'done'], true)) {
            $view = 'open';
        }

        if (! $canManage) {
            $view = 'board';
        }

        // The "new request" form lives in a sheet on the board. It opens on ?new=1
        // and re-opens by itself when a submission bounced back with errors.
        $openNew = $canManage && ($request->boolean('new') || session()->hasOldInput('customer_name'));

        return view('shop.requests', [
            'rows' => $this->service->boardRows($view),
            'view' => $view,
            'canManage' => $canManage,
            'openNew' => $openNew,
            'seedItems' => $canManage ? $this->seedItems(null) : [],
            // The typeahead endpoint is behind auth; guests never render the form.
            'searchUrl' => $canManage ? route('api.products.search') : null,
        ]);
    }

    /**
     * Kept for links/bookmarks: the form itself is a modal on the board.
     */
    public function create(): RedirectResponse
    {
        return redirect()->route('customer-requests.index', ['new' => 1]);
    }

    public function store(CustomerRequestRequest $request): RedirectResponse
    {
        $customerRequest = $this->service->create(
            $request->requestPayload(),
            $request->itemsPayload(),
            $request->user()
        );

        return redirect()
            ->route('customer-requests.index')
            ->with('status', "Request for {$customerRequest->customer_name} saved.");
    }

    public function show(CustomerRequest $customerRequest): View
    {
        $customerRequest->load(['items.statusLogs.user', 'items.statusChanger', 'creator', 'updater', 'closer']);

        return view('shop.request-show', [
            'customerRequest' => $customerRequest,
            'images' => $this->productSearch->imageUrlsByCode(
                $customerRequest->items->pluck('product_code')->all()
            ),
        ]);
    }

    public function edit(CustomerRequest $customerRequest): View
    {
        $customerRequest->load('items');

        return view('shop.request-edit', [
            'customerRequest' => $customerRequest,
            'seedItems' => $this->seedItems($customerRequest),
        ]);
    }

    public function update(CustomerRequestRequest $request, CustomerRequest $customerRequest): RedirectResponse
    {
        try {
            $this->service->update(
                $customerRequest,
                $request->requestPayload(),
                $request->itemsPayload(),
                $request->user()
            );
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['items' => $e->getMessage()]);
        }

        return redirect()
            ->route('customer-requests.index')
            ->with('status', "Request for {$customerRequest->customer_name} updated.");
    }

    public function cancel(Request $request, CustomerRequest $customerRequest): RedirectResponse
    {
        $this->service->cancel($customerRequest, $request->user());

        return redirect()
            ->route('customer-requests.index')
            ->with('status', "Request for {$customerRequest->customer_name} cancelled.");
    }

    /**
     * Move one line to a new status. Redirects back for the plain forms on the
     * board; returns JSON for the delivery screen's "Mark put aside" button.
     */
    public function updateItemStatus(UpdateCustomerRequestItemStatusRequest $request, CustomerRequestItem $item): RedirectResponse|JsonResponse
    {
        try {
            $item = $this->service->changeItemStatus(
                $item,
                $request->validated('status'),
                $request->user(),
                $request->validated('note')
            );
        } catch (DomainException $e) {
            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['status' => $e->getMessage()]);
        }

        $message = sprintf('%s marked as %s for %s.', $item->label(), strtolower($item->statusLabel()), $item->request->customer_name);

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'status' => $item->status,
                'label' => $item->statusLabel(),
                'request_closed' => ! $item->request->isOpen(),
                'message' => $message,
            ]);
        }

        return back()->with('status', $message);
    }

    /**
     * Lines to seed the Alpine form with: a failed submission's old() input
     * wins, then the model's lines, then nothing.
     *
     * @return array<int, array<string, mixed>>
     */
    private function seedItems(?CustomerRequest $customerRequest): array
    {
        $old = old('items');
        if (is_array($old)) {
            $urls = $this->productSearch->imageUrlsByCode(array_column($old, 'product_code'));

            return array_values(array_map(fn ($i) => [
                'id' => $i['id'] ?? null,
                'product_code' => $i['product_code'] ?? null,
                'product_name' => $i['product_name'] ?? null,
                'description' => $i['description'] ?? '',
                'quantity' => $i['quantity'] ?? 1,
                'notes' => $i['notes'] ?? '',
                'status' => $i['status'] ?? null,
                'product' => $this->seedProduct($i['product_code'] ?? null, $urls),
            ], $old));
        }

        if ($customerRequest === null) {
            return [];
        }

        $urls = $this->productSearch->imageUrlsByCode(
            $customerRequest->items->pluck('product_code')->all()
        );

        return $customerRequest->items->map(fn (CustomerRequestItem $i) => [
            'id' => $i->id,
            'product_code' => $i->product_code,
            'product_name' => $i->product_name,
            'description' => $i->description,
            'quantity' => (float) $i->quantity,
            'notes' => $i->notes ?? '',
            'status' => $i->status,
            'product' => $this->seedProduct($i->product_code, $urls),
        ])->values()->all();
    }

    /**
     * The shape `productImages()` expects for a seeded line, so an edited request
     * shows its pictures without the user re-picking each product. `withKey()` in
     * request-edit.js already passes `product` through; until now nothing set it.
     *
     * Keyed by code because that is what a request line stores — `productImages()`
     * falls back from `id` to `code` (cycle 17c).
     *
     * @param  array<string, string|null>  $urls
     * @return array<string, mixed>|null
     */
    private function seedProduct(?string $code, array $urls): ?array
    {
        if (! $code) {
            return null;
        }

        return ['code' => $code, 'image_url' => $urls[$code] ?? null];
    }
}
