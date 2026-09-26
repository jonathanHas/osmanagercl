<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRequestRequest;
use App\Http\Requests\UpdateCustomerRequestItemStatusRequest;
use App\Models\CustomerRequest;
use App\Models\CustomerRequestItem;
use App\Models\Product;
use App\Services\CustomerRequestService;
use App\Services\ProductThumbnailService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class CustomerRequestController extends Controller
{
    /** The board draws these at 48 px; 112 covers a 2x screen. */
    private const PHOTO_SIZE = 112;

    /** Matches the staff board's "Done" window. */
    private const PHOTO_WINDOW_DAYS = 30;

    public function __construct(
        private CustomerRequestService $service,
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

    /**
     * A small public thumbnail of a product's till photo, for the public board.
     *
     * The board is public, but `products.image` needs a login, so a signed-out
     * tablet showed a placeholder wherever the only picture was the till's own
     * photo. Rather than opening that route, this one is deliberately narrow:
     *
     *  - it serves a 112 px JPEG and never the stored photo;
     *  - only for a product code that appears on a request line whose request is
     *    open, or was closed within the last 30 days — the same lines the board
     *    itself shows. Anything else is 404, so it cannot be used as a general
     *    product-image endpoint;
     *  - it is throttled.
     *
     * Versioned like the fruit & veg thumbnails (cycles 17d/17e): `?v=` is the
     * first 8 hex of the photo's md5, and a match earns a week-long immutable
     * cache, so a board that is already open costs nothing to re-render.
     */
    public function photo(string $code, Request $request, ProductThumbnailService $thumbnails): Response
    {
        abort_unless($this->codeIsOnACurrentRequest($code), 404);

        $product = Product::where('CODE', $code)->first(['ID', 'CODE', 'IMAGE']);

        abort_if($product === null || $product->IMAGE === null || $product->IMAGE === '', 404);

        $jpeg = $thumbnails->jpeg($code, $product->IMAGE, self::PHOTO_SIZE);

        // Null means the encoder could not read the blob. The full photo is not an
        // acceptable fallback here — this route is public and must never serve it.
        abort_if($jpeg === null, 404);

        $versioned = ($version = (string) $request->query('v')) !== ''
            && hash_equals(substr(md5($product->IMAGE), 0, 8), $version);

        $cacheControl = $versioned
            ? 'public, max-age=604800, immutable'
            : 'public, max-age=0, must-revalidate';

        $etag = '"'.md5($jpeg).'"';

        if ($request->headers->get('If-None-Match') === $etag) {
            return response('', 304, ['ETag' => $etag, 'Cache-Control' => $cacheControl]);
        }

        return response($jpeg, 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => $cacheControl,
            'ETag' => $etag,
        ]);
    }

    /**
     * Is this product on a line the board could be showing?
     *
     * Open requests, plus ones closed in the last 30 days, which is the window the
     * staff "Done" view uses. This is the whole of the route's authorisation.
     */
    private function codeIsOnACurrentRequest(string $code): bool
    {
        return CustomerRequestItem::where('product_code', $code)
            ->whereHas('request', fn ($q) => $q
                ->whereNull('closed_at')
                ->orWhere('closed_at', '>=', now()->subDays(self::PHOTO_WINDOW_DAYS))
            )
            ->exists();
    }

    public function show(CustomerRequest $customerRequest): View
    {
        $customerRequest->load(['items.statusLogs.user', 'items.statusChanger', 'creator', 'updater', 'closer']);

        return view('shop.request-show', [
            'customerRequest' => $customerRequest,
            'images' => $this->service->imageUrlsForRequestLines(
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
            $urls = $this->service->imageUrlsForRequestLines(array_column($old, 'product_code'));

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

        $urls = $this->service->imageUrlsForRequestLines(
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
