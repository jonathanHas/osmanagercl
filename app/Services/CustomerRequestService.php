<?php

namespace App\Services;

use App\Models\CustomerRequest;
use App\Models\CustomerRequestItem;
use App\Models\Product;
use App\Models\User;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for customer requests (pre-orders and sourcing asks).
 *
 * Every status write goes through changeItemStatus() so the request header's
 * closed_at never drifts from its lines and every move is logged with a user.
 */
class CustomerRequestService
{
    /**
     * Create a request with its lines. Lines that reference a POS product get
     * the current product name snapshotted so the request stays readable if
     * the product is later renamed or removed.
     *
     * @param  array<string, mixed>  $data  customer_name, customer_phone, wanted_on, notes
     * @param  array<int, array<string, mixed>>  $items  product_code?, product_name?, description, quantity, notes?
     */
    public function create(array $data, array $items, User $user): CustomerRequest
    {
        return DB::transaction(function () use ($data, $items, $user) {
            $request = CustomerRequest::create(array_merge($this->headerFields($data), [
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]));

            $items = $this->snapshotProductNames($items);

            foreach (array_values($items) as $position => $item) {
                $this->createItem($request, $item, $position, $user);
            }

            $request->refreshClosedState($user);

            return $request->load('items');
        });
    }

    /**
     * Update the header and sync the lines.
     *
     * Lines with an id are updated in place (status untouched), lines without an
     * id are added as pending, and existing lines missing from the payload are
     * deleted. Adding a line to a closed request reopens it.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $items
     */
    public function update(CustomerRequest $request, array $data, array $items, User $user): CustomerRequest
    {
        return DB::transaction(function () use ($request, $data, $items, $user) {
            $request->fill($this->headerFields($data));
            $request->updated_by = $user->id;
            $request->save();

            $items = $this->snapshotProductNames($items);
            $keptIds = [];

            foreach (array_values($items) as $position => $item) {
                $id = isset($item['id']) && $item['id'] !== '' ? (int) $item['id'] : null;

                if ($id !== null) {
                    /** @var CustomerRequestItem|null $existing */
                    $existing = $request->items()->whereKey($id)->first();
                    if ($existing === null) {
                        throw new DomainException('One of the lines does not belong to this request.');
                    }

                    $existing->fill($this->lineFields($item, $position))->save();
                    $keptIds[] = $existing->id;
                } else {
                    $keptIds[] = $this->createItem($request, $item, $position, $user)->id;
                }
            }

            $request->items()->whereNotIn('id', $keptIds)->delete();

            $request->unsetRelation('items');
            $request->refreshClosedState($user);

            return $request->load('items');
        });
    }

    /**
     * Move a line to a new status, recording who did it and when.
     *
     * @throws DomainException when the move is not allowed from the current status
     */
    public function changeItemStatus(CustomerRequestItem $item, string $to, User $user, ?string $note = null): CustomerRequestItem
    {
        if (! in_array($to, CustomerRequestItem::STATUSES, true)) {
            throw new DomainException("Unknown status '{$to}'.");
        }

        if ($to === $item->status) {
            return $item;
        }

        if (! $item->canTransitionTo($to)) {
            throw new DomainException(sprintf(
                'Cannot move "%s" from %s to %s.',
                $item->label(),
                $item->statusLabel(),
                CustomerRequestItem::labelFor($to)
            ));
        }

        return DB::transaction(function () use ($item, $to, $user, $note) {
            $from = $item->status;

            $item->forceFill([
                'status' => $to,
                'status_changed_at' => now(),
                'status_changed_by' => $user->id,
            ])->save();

            $item->statusLogs()->create([
                'from_status' => $from,
                'to_status' => $to,
                'user_id' => $user->id,
                'note' => $note,
            ]);

            $request = $item->request;
            $request->forceFill(['updated_by' => $user->id])->save();
            $request->refreshClosedState($user);

            return $item->fresh(['request']);
        });
    }

    /**
     * Cancel every line that is still open, which closes the request.
     */
    public function cancel(CustomerRequest $request, User $user, ?string $note = null): CustomerRequest
    {
        return DB::transaction(function () use ($request, $user, $note) {
            foreach ($request->items()->open()->get() as $item) {
                if ($item->canTransitionTo(CustomerRequestItem::STATUS_CANCELLED)) {
                    $this->changeItemStatus($item, CustomerRequestItem::STATUS_CANCELLED, $user, $note);
                }
            }

            $request->unsetRelation('items');
            $request->refreshClosedState($user);

            return $request->fresh(['items']);
        });
    }

    /**
     * Data for the board: open requests split into "due today / overdue" and
     * "everything else", plus recently closed ones when asked for.
     *
     * @return array{due: Collection<int, CustomerRequest>, open: Collection<int, CustomerRequest>, closed: Collection<int, CustomerRequest>}
     */
    public function board(bool $includeClosed = false): array
    {
        $open = CustomerRequest::open()
            ->with(['items.statusChanger', 'creator'])
            ->orderByRaw('wanted_on IS NULL')
            ->orderBy('wanted_on')
            ->orderBy('created_at')
            ->get();

        [$due, $rest] = $open->partition(fn (CustomerRequest $r) => $r->isDue());

        $closed = collect();
        if ($includeClosed) {
            $closed = CustomerRequest::closed()
                ->where('closed_at', '>=', now()->subDays(30))
                ->with(['items.statusChanger', 'creator', 'closer'])
                ->orderByDesc('closed_at')
                ->get();
        }

        return [
            'due' => $due->values(),
            'open' => $rest->values(),
            'closed' => $closed,
        ];
    }

    /**
     * Counts for the dashboard banner.
     *
     * @return array{due: int, put_aside: int}
     */
    public function dashboardCounts(): array
    {
        return [
            'due' => CustomerRequest::dueTodayOrOverdue()->count(),
            'put_aside' => CustomerRequestItem::where('status', CustomerRequestItem::STATUS_PUT_ASIDE)->count(),
        ];
    }

    /**
     * Open lines (pending / ordered) for the given barcodes, grouped by barcode.
     * Used by the delivery screens to flag "put this aside for X".
     *
     * @param  array<int, string|null>  $barcodes
     * @return Collection<string, Collection<int, CustomerRequestItem>>
     */
    public function awaitingArrivalByBarcode(array $barcodes): Collection
    {
        $barcodes = array_values(array_unique(array_filter(array_map(
            fn ($b) => $b === null ? null : (string) $b,
            $barcodes
        ), fn ($b) => $b !== null && $b !== '')));

        if ($barcodes === []) {
            return collect();
        }

        return CustomerRequestItem::awaitingArrival()
            ->forBarcodes($barcodes)
            ->with('request')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (CustomerRequestItem $item) => (string) $item->product_code);
    }

    /**
     * Plain array version of awaitingArrivalByBarcode() for JSON responses.
     *
     * @return array<int, array<string, mixed>>
     */
    public function awaitingArrivalPayload(string $barcode): array
    {
        return $this->awaitingArrivalByBarcode([$barcode])
            ->get($barcode, collect())
            ->map(fn (CustomerRequestItem $item) => [
                'id' => $item->id,
                'request_id' => $item->customer_request_id,
                'customer_name' => $item->request?->customer_name,
                'customer_phone' => $item->request?->customer_phone,
                'quantity' => (float) $item->quantity,
                'wanted_on' => $item->request?->wanted_on?->toDateString(),
                'status' => $item->status,
                'status_label' => $item->statusLabel(),
            ])
            ->values()
            ->all();
    }

    /**
     * POS product lookup for the request form typeahead.
     *
     * @return array<int, array{code: string, name: string, price: float|null}>
     */
    public function searchProducts(string $q, int $limit = 20): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return [];
        }

        return Product::query()
            ->search($q)
            ->orderByRaw('CASE WHEN CODE = ? THEN 0 ELSE 1 END', [$q])
            ->orderBy('NAME')
            ->limit($limit)
            ->get(['ID', 'CODE', 'NAME', 'PRICESELL'])
            ->map(fn (Product $p) => [
                'code' => (string) $p->CODE,
                'name' => (string) $p->NAME,
                'price' => $p->PRICESELL !== null ? (float) $p->PRICESELL : null,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function headerFields(array $data): array
    {
        return [
            'customer_name' => trim((string) ($data['customer_name'] ?? '')),
            'customer_phone' => self::nullIfBlank($data['customer_phone'] ?? null),
            'wanted_on' => self::nullIfBlank($data['wanted_on'] ?? null),
            'notes' => self::nullIfBlank($data['notes'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function lineFields(array $item, int $position): array
    {
        $code = self::nullIfBlank($item['product_code'] ?? null);
        $name = self::nullIfBlank($item['product_name'] ?? null);
        $description = trim((string) ($item['description'] ?? ''));

        // Fall back to the snapshotted name, then the barcode, so a stocked line
        // posted with only its code still has something to show.
        if ($description === '') {
            $description = $name ?? $code ?? '';
        }

        return [
            'product_code' => $code,
            'product_name' => $code !== null ? $name : null,
            'description' => $description,
            'quantity' => (float) ($item['quantity'] ?? 1),
            'notes' => self::nullIfBlank($item['notes'] ?? null),
            'position' => $position,
        ];
    }

    private function createItem(CustomerRequest $request, array $item, int $position, User $user): CustomerRequestItem
    {
        $line = $request->items()->create(array_merge($this->lineFields($item, $position), [
            'status' => CustomerRequestItem::STATUS_PENDING,
            'status_changed_at' => now(),
            'status_changed_by' => $user->id,
        ]));

        $line->statusLogs()->create([
            'from_status' => null,
            'to_status' => CustomerRequestItem::STATUS_PENDING,
            'user_id' => $user->id,
        ]);

        return $line;
    }

    /**
     * Fill product_name from the POS for any line that has a product_code but no
     * name (one query for all lines).
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function snapshotProductNames(array $items): array
    {
        $codes = collect($items)
            ->filter(fn ($i) => self::nullIfBlank($i['product_code'] ?? null) !== null && self::nullIfBlank($i['product_name'] ?? null) === null)
            ->map(fn ($i) => (string) $i['product_code'])
            ->unique()
            ->values();

        if ($codes->isEmpty()) {
            return $items;
        }

        $names = Product::whereIn('CODE', $codes->all())->pluck('NAME', 'CODE');

        foreach ($items as $k => $item) {
            $code = self::nullIfBlank($item['product_code'] ?? null);
            if ($code !== null && self::nullIfBlank($item['product_name'] ?? null) === null && $names->has($code)) {
                $items[$k]['product_name'] = $names->get($code);
                if (self::nullIfBlank($item['description'] ?? null) === null) {
                    $items[$k]['description'] = $names->get($code);
                }
            }
        }

        return $items;
    }

    private static function nullIfBlank(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
