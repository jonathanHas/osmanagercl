<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRequestRequest;
use App\Http\Requests\UpdateCustomerRequestItemStatusRequest;
use App\Models\CustomerRequest;
use App\Models\CustomerRequestItem;
use App\Services\CustomerRequestService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerRequestController extends Controller
{
    public function __construct(private CustomerRequestService $service) {}

    /**
     * The board. Public (no auth) so the shop-floor tablet can show it;
     * staff who are signed in and have the permission also get the controls.
     */
    public function index(Request $request): View
    {
        $canManage = $request->user()?->can('customer-requests.manage') ?? false;
        $showClosed = $canManage && $request->boolean('closed');

        $board = $this->service->board($showClosed);

        return view('customer-requests.index', [
            'due' => $board['due'],
            'open' => $board['open'],
            'closed' => $board['closed'],
            'showClosed' => $showClosed,
            'canManage' => $canManage,
        ]);
    }

    public function create(): View
    {
        return view('customer-requests.create', [
            'customerRequest' => null,
            'seedItems' => $this->seedItems(null),
        ]);
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

        return view('customer-requests.show', ['customerRequest' => $customerRequest]);
    }

    public function edit(CustomerRequest $customerRequest): View
    {
        $customerRequest->load('items');

        return view('customer-requests.edit', [
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
     * POS product typeahead for the request form.
     */
    public function searchProducts(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->searchProducts((string) $request->query('q', '')),
        ]);
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
            return array_values(array_map(fn ($i) => [
                'id' => $i['id'] ?? null,
                'product_code' => $i['product_code'] ?? null,
                'product_name' => $i['product_name'] ?? null,
                'description' => $i['description'] ?? '',
                'quantity' => $i['quantity'] ?? 1,
                'notes' => $i['notes'] ?? '',
                'status' => $i['status'] ?? null,
            ], $old));
        }

        if ($customerRequest === null) {
            return [];
        }

        return $customerRequest->items->map(fn (CustomerRequestItem $i) => [
            'id' => $i->id,
            'product_code' => $i->product_code,
            'product_name' => $i->product_name,
            'description' => $i->description,
            'quantity' => (float) $i->quantity,
            'notes' => $i->notes ?? '',
            'status' => $i->status,
        ])->values()->all();
    }
}
