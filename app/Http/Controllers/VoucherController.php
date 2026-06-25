<?php

namespace App\Http\Controllers;

use App\Models\Voucher;
use App\Models\VoucherTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class VoucherController extends Controller
{
    /**
     * The till screen — scan, activate and redeem vouchers.
     */
    public function index(): View
    {
        return view('vouchers.index');
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
        $voucher->load(['transactions.user', 'creator']);

        return view('vouchers.transactions', compact('voucher'));
    }

    /**
     * Look up a scanned voucher code. Mirrors the stocking.lookup JSON shape.
     */
    public function lookup(Request $request): JsonResponse
    {
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
        ]);
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
        ]);

        $ids = [];
        DB::transaction(function () use ($data, &$ids) {
            for ($i = 0; $i < $data['count']; $i++) {
                $voucher = Voucher::create([
                    'code' => Voucher::generateUniqueCode(),
                    'current_balance' => 0,
                    'status' => Voucher::STATUS_INACTIVE,
                    'created_by' => Auth::id(),
                ]);
                $ids[] = $voucher->id;
            }
        });

        return redirect()->route('vouchers.print', ['ids' => implode(',', $ids)]);
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

        $tmpFile = tempnam(sys_get_temp_dir(), 'zpl_');
        file_put_contents($tmpFile, $zpl);

        $host = config('services.zebra.host', '10.42.1.71');
        $port = config('services.zebra.port', '631');
        $printer = config('services.zebra.name', 'ZTC-GX430t');

        $command = "lp -h {$host}:{$port}/version=1.1 -d {$printer} -o raw {$tmpFile} 2>&1";
        $output = shell_exec($command);

        unlink($tmpFile);

        $success = $output && str_contains($output, 'request id');
        $count = $vouchers->count();

        return response()->json([
            'success' => $success,
            'message' => $success
                ? "Print job sent ({$count} ".($count === 1 ? 'label' : 'labels').')'
                : 'Print failed',
            'output' => trim($output ?? 'No output'),
        ], $success ? 200 : 500);
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
