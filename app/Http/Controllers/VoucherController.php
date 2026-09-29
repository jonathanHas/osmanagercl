<?php

namespace App\Http\Controllers;

use App\Models\Voucher;
use App\Models\VoucherTillRedemption;
use App\Models\VoucherTransaction;
use App\Services\VoucherPosProductService;
use App\Services\VoucherTillSyncService;
use App\Services\ZebraPrintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class VoucherController extends Controller
{
    public function __construct(
        protected ZebraPrintService $zebraPrint,
        protected VoucherPosProductService $posProducts,
        protected VoucherTillSyncService $tillSync,
    ) {}

    /**
     * The till screen — scan, activate and redeem vouchers.
     */
    public function index(Request $request): View
    {
        $exceptionCount = $request->user()->can('vouchers.manage')
            ? VoucherTillRedemption::exceptions()->unreviewed()->count()
            : 0;

        return view('vouchers.index', compact('exceptionCount'));
    }

    /**
     * Paginated list of all vouchers (management view).
     */
    public function list(Request $request): View
    {
        $query = Voucher::query()->with('creator')->withCount('transactions');

        if ($term = trim((string) $request->query('q', ''))) {
            $query->where('code', 'like', '%'.$term.'%');
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $vouchers = $query->latest()->paginate(25)->withQueryString();

        return view('vouchers.list', compact('vouchers'));
    }

    /**
     * Per-voucher transaction log.
     */
    public function transactions(Voucher $voucher): View
    {
        $voucher->load(['transactions.user', 'transactions.tillRedemption', 'creator']);

        return view('vouchers.transactions', compact('voucher'));
    }

    /**
     * Look up a scanned voucher code. Mirrors the stocking.lookup JSON shape.
     */
    public function lookup(Request $request): JsonResponse
    {
        // Pick up till redemptions first so the balance shown is current.
        $this->tillSync->syncIfDue();

        $data = $request->validate([
            'code' => ['required', 'string', 'max:64'],
        ]);

        $voucher = Voucher::where('code', $data['code'])->first();

        if (! $voucher) {
            // Unknown code — the client should offer to activate it.
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found' => true,
            'code' => $voucher->code,
            'status' => $voucher->status,
            'initial_value' => (float) $voucher->initial_value,
            'current_balance' => (float) $voucher->current_balance,
            'issued_at' => $this->issuedAt($voucher),
            'history' => $this->history($voucher),
            // Vouchers cycle 3: the value an unsold voucher is sold for at the till.
            'face_value' => $voucher->face_value !== null ? (float) $voucher->face_value : null,
            'for_sale' => $voucher->isForSale(),
        ]);
    }

    /**
     * When the voucher was first loaded with a balance. A reactivated voucher has
     * more than one `issue` row, so take the earliest: `transactions()` is ordered
     * newest-first, and a second orderBy would append rather than replace it, so
     * this asks its own question.
     */
    private function issuedAt(Voucher $voucher): ?string
    {
        $first = VoucherTransaction::where('voucher_id', $voucher->id)
            ->where('type', VoucherTransaction::TYPE_ISSUE)
            ->orderBy('created_at')
            ->first();

        return $first?->created_at?->toIso8601String();
    }

    /**
     * The voucher's recent movements, newest first, for the Shop screen.
     *
     * `amount` is signed for display — money on is positive, money off negative,
     * and a status change carries no money at all. The decimal:2 casts hand back
     * strings, so everything is cast to float here rather than in the client.
     *
     * @return array<int, array<string, mixed>>
     */
    private function history(Voucher $voucher): array
    {
        return $voucher->transactions()
            ->with(['user', 'tillRedemption'])
            ->limit(20)
            ->get()
            ->map(fn (VoucherTransaction $t) => [
                'type' => $t->type,
                'label' => match ($t->type) {
                    VoucherTransaction::TYPE_ISSUE => $t->source === VoucherTransaction::SOURCE_TILL
                        ? 'Sold at till'
                        : 'Issued',
                    VoucherTransaction::TYPE_DEDUCT => $t->source === VoucherTransaction::SOURCE_TILL
                        ? 'Redeemed at till'
                        : 'Redeemed',
                    VoucherTransaction::TYPE_DEACTIVATE => 'Deactivated',
                    VoucherTransaction::TYPE_ACTIVATE => 'Reactivated',
                    default => ucfirst($t->type),
                },
                'amount' => match ($t->type) {
                    VoucherTransaction::TYPE_ISSUE => (float) $t->amount,
                    VoucherTransaction::TYPE_DEDUCT => -(float) $t->amount,
                    default => 0.0,
                },
                'balance_after' => (float) $t->balance_after,
                'user' => $t->source === VoucherTransaction::SOURCE_TILL
                    ? 'Till #'.($t->tillRedemption?->ticket_number ?? '?')
                    : ($t->user?->name ?? 'Office'),
                'at' => $t->created_at?->toIso8601String(),
                'source' => $t->source,
                'ticket_number' => $t->tillRedemption?->ticket_number,
            ])
            ->all();
    }

    /**
     * Activate a voucher (or create-then-activate an unknown code) with a starting balance.
     */
    public function activate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64'],
            'starting_balance' => ['required', 'numeric', 'gt:0', 'max:100000'],
        ]);

        $result = DB::transaction(function () use ($data) {
            $voucher = Voucher::where('code', $data['code'])->lockForUpdate()->first();

            if (! $voucher) {
                // Unknown printed code — create it on the fly.
                $voucher = new Voucher(['code' => $data['code']]);
                $voucher->created_by = Auth::id();
            } elseif ($voucher->status !== Voucher::STATUS_INACTIVE) {
                return ['success' => false, 'message' => 'Voucher is already active or exhausted.'];
            }

            $voucher->initial_value = $data['starting_balance'];
            $voucher->current_balance = $data['starting_balance'];
            $voucher->status = Voucher::STATUS_ACTIVE;
            if (! $voucher->created_by) {
                $voucher->created_by = Auth::id();
            }
            $voucher->save();

            $voucher->transactions()->create([
                'type' => VoucherTransaction::TYPE_ISSUE,
                'amount' => $data['starting_balance'],
                'balance_after' => $voucher->current_balance,
                'user_id' => Auth::id(),
            ]);

            return [
                'success' => true,
                'status' => $voucher->status,
                'current_balance' => (float) $voucher->current_balance,
            ];
        });

        if ($result['success']) {
            $this->syncPosProduct($data['code']);
        }

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Deduct an amount from a voucher. Locks the row to prevent double-spending.
     */
    public function deduct(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:100000'],
        ]);

        $result = DB::transaction(function () use ($data) {
            $voucher = Voucher::where('code', $data['code'])->lockForUpdate()->first();

            if (! $voucher) {
                return ['success' => false, 'message' => 'Voucher not found.'];
            }

            if ($voucher->status === Voucher::STATUS_INACTIVE) {
                return ['success' => false, 'message' => 'Voucher has not been activated.'];
            }

            if ($voucher->status === Voucher::STATUS_DEACTIVATED) {
                return ['success' => false, 'message' => 'Voucher has been deactivated.'];
            }

            if ($voucher->status === Voucher::STATUS_EXHAUSTED || (float) $voucher->current_balance <= 0) {
                return ['success' => false, 'message' => 'Voucher is exhausted.'];
            }

            // Re-read balance under the lock — round to avoid float drift.
            $amount = round((float) $data['amount'], 2);
            $balance = round((float) $voucher->current_balance, 2);

            if ($amount > $balance) {
                return [
                    'success' => false,
                    'message' => 'Amount exceeds the remaining balance of €'.number_format($balance, 2).'.',
                    'current_balance' => $balance,
                ];
            }

            $voucher->current_balance = round($balance - $amount, 2);
            if ((float) $voucher->current_balance <= 0) {
                $voucher->status = Voucher::STATUS_EXHAUSTED;
            }
            $voucher->save();

            $voucher->transactions()->create([
                'type' => VoucherTransaction::TYPE_DEDUCT,
                'amount' => $amount,
                'balance_after' => $voucher->current_balance,
                'user_id' => Auth::id(),
            ]);

            return [
                'success' => true,
                'status' => $voucher->status,
                'new_balance' => (float) $voucher->current_balance,
            ];
        });

        if ($result['success']) {
            $this->syncPosProduct($data['code']);
        }

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Admin: deactivate an active voucher (preserves balance, blocks redemption).
     */
    public function deactivate(Request $request, Voucher $voucher): JsonResponse
    {
        return $this->changeStatus(
            $request,
            $voucher,
            Voucher::STATUS_ACTIVE,
            Voucher::STATUS_DEACTIVATED,
            VoucherTransaction::TYPE_DEACTIVATE,
            'Only active vouchers can be deactivated.',
            'Voucher deactivated.'
        );
    }

    /**
     * Admin: reactivate a deactivated voucher (restores it to active with its balance).
     */
    public function reactivate(Request $request, Voucher $voucher): JsonResponse
    {
        return $this->changeStatus(
            $request,
            $voucher,
            Voucher::STATUS_DEACTIVATED,
            Voucher::STATUS_ACTIVE,
            VoucherTransaction::TYPE_ACTIVATE,
            'Only deactivated vouchers can be reactivated.',
            'Voucher reactivated.'
        );
    }

    /**
     * Shared admin status-change flow with row locking and audit logging.
     */
    private function changeStatus(
        Request $request,
        Voucher $voucher,
        string $requiredStatus,
        string $newStatus,
        string $logType,
        string $invalidMessage,
        string $successMessage
    ): JsonResponse {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $result = DB::transaction(function () use ($voucher, $requiredStatus, $newStatus, $logType, $invalidMessage, $successMessage, $data) {
            $locked = Voucher::whereKey($voucher->getKey())->lockForUpdate()->first();

            if (! $locked) {
                return ['success' => false, 'message' => 'Voucher not found.'];
            }

            if ($locked->status !== $requiredStatus) {
                return ['success' => false, 'message' => $invalidMessage];
            }

            $locked->status = $newStatus;
            $locked->save();

            $locked->transactions()->create([
                'type' => $logType,
                'amount' => 0,
                'balance_after' => $locked->current_balance,
                'note' => $data['note'] ?? null,
                'user_id' => Auth::id(),
            ]);

            return ['success' => true, 'message' => $successMessage, 'status' => $locked->status];
        });

        if ($result['success']) {
            $this->syncPosProduct($voucher->code);
        }

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Show the "generate vouchers" form.
     */
    public function generateForm(): View
    {
        return view('vouchers.generate');
    }

    /**
     * Generate N inactive vouchers, then redirect to the printable barcode sheet.
     */
    public function generate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:200'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:'.config('vouchers.max_face_value'), 'decimal:0,2'],
        ]);

        $ids = [];
        DB::transaction(function () use ($data, &$ids) {
            for ($i = 0; $i < $data['count']; $i++) {
                $voucher = Voucher::create([
                    'code' => Voucher::generateUniqueCode(),
                    // Unsold: selling it at the till activates it with this value.
                    'face_value' => round((float) $data['amount'], 2),
                    'current_balance' => 0,
                    'status' => Voucher::STATUS_INACTIVE,
                    'created_by' => Auth::id(),
                ]);
                $ids[] = $voucher->id;
            }
        });

        // Each printed label needs its POS product, or the till says "product not found".
        $summary = $this->posProducts->syncMany(Voucher::whereIn('id', $ids)->get());

        $redirect = redirect()->route('vouchers.print', ['ids' => implode(',', $ids)]);

        if ($summary['failed'] > 0) {
            $redirect->with('warning', "{$summary['failed']} voucher products could not be created on the till; run vouchers:sync-pos-products");
        }

        return $redirect;
    }

    /**
     * Zebra label preview + print page for the given voucher ids.
     */
    public function print(Request $request): View
    {
        $vouchers = $this->resolveVouchers((string) $request->query('ids', ''));

        $labels = $vouchers->map(fn (Voucher $v) => [
            'id' => $v->id,
            'code' => $v->code,
            // On screen only; the printed label does not show the amount (owner decision 7).
            'face_value' => $v->face_value !== null ? (float) $v->face_value : null,
            'zpl' => $v->toZplLabel(),
        ])->values();

        $ids = $vouchers->pluck('id')->implode(',');

        return view('vouchers.print', compact('labels', 'ids'));
    }

    /**
     * Send the given vouchers' labels to the Zebra printer (CUPS raw print over IPP).
     * Mirrors ZebraLabelController::print().
     */
    public function printZebra(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required'],
        ]);

        $idString = is_array($data['ids']) ? implode(',', $data['ids']) : (string) $data['ids'];
        $vouchers = $this->resolveVouchers($idString);

        if ($vouchers->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No vouchers to print.'], 422);
        }

        // One distinct ^XA…^XZ block per voucher (unique codes — no ^PQ).
        $zpl = $vouchers->map(fn (Voucher $v) => $v->toZplLabel())->implode('');

        $result = $this->zebraPrint->sendRaw($zpl);
        $count = $vouchers->count();

        return response()->json([
            'success' => $result->success,
            'message' => $result->success
                ? "Print job sent ({$count} ".($count === 1 ? 'label' : 'labels').')'
                : ($result->timedOut ? "Couldn't confirm the print job — the printer may not have responded." : 'Print failed'),
            'output' => $result->output,
        ], $result->success ? 200 : 500);
    }

    /**
     * Manager list of till redemptions that need a look (anything not `applied`).
     */
    public function exceptions(Request $request): View
    {
        $showAll = $request->boolean('all');

        $redemptions = VoucherTillRedemption::with(['voucher', 'reviewer'])
            ->exceptions()
            ->when(! $showAll, fn ($q) => $q->unreviewed())
            ->latest('sold_at')
            ->paginate(50)
            ->withQueryString();

        return view('vouchers.exceptions', compact('redemptions', 'showAll'));
    }

    /**
     * Mark a till exception as reviewed, optionally with a note.
     */
    public function markReviewed(Request $request, VoucherTillRedemption $redemption): RedirectResponse
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $note = trim((string) ($data['note'] ?? ''));
        if ($note !== '') {
            $note = substr(trim(($redemption->note ? $redemption->note.' · ' : '').$note), 0, 500);
        }

        $redemption->forceFill([
            'reviewed_at' => now(),
            'reviewed_by' => Auth::id(),
            'note' => $note !== '' ? $note : $redemption->note,
        ])->save();

        return back()->with('status', 'Till #'.$redemption->ticket_number.' marked reviewed.');
    }

    /**
     * Rename (or create) the voucher's POS product after a balance or status
     * change. Reloads by code: the instance changed inside the transaction is
     * out of scope. Never throws (see VoucherPosProductService::sync).
     */
    private function syncPosProduct(string $code): void
    {
        if ($voucher = Voucher::where('code', $code)->first()) {
            $this->posProducts->sync($voucher);
        }
    }

    /**
     * Resolve a comma-separated id string to an ordered voucher collection.
     */
    private function resolveVouchers(string $idString)
    {
        $ids = collect(explode(',', $idString))
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id);

        return Voucher::whereIn('id', $ids)->orderBy('id')->get();
    }
}
