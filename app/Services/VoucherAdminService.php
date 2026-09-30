<?php

namespace App\Services;

use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherTillRedemption;
use App\Models\VoucherTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Admin changeover tools (vouchers cycle 4): bulk make-for-sale, deactivate,
 * reactivate, delete and restore.
 *
 * Built to be removed later without touching the rest of the voucher code:
 * nothing else calls this service. The single-voucher deactivate / reactivate
 * in VoucherController::changeStatus() is deliberately left as it is.
 *
 * Every action handles each voucher in its own transaction, under a row lock,
 * and writes one voucher_transactions row with the admin as user. A voucher
 * that does not qualify is skipped with a reason and never stops the rest.
 * No balance is ever altered except by make-for-sale, which turns a balance
 * nobody paid for back into the voucher's sale value.
 */
class VoucherAdminService
{
    public function __construct(
        protected VoucherPosProductService $posProducts,
    ) {}

    /**
     * @param  array<int, int|string>  $ids
     * @return array{done: int, skipped: array<int, array{code: string, reason: string}>}
     */
    public function deactivate(array $ids, ?string $note, User $admin): array
    {
        return $this->each($ids, function (Voucher $v) use ($note, $admin) {
            if ($v->trashed()) {
                return 'deleted';
            }
            if ($v->status !== Voucher::STATUS_ACTIVE) {
                return 'not active';
            }

            $v->status = Voucher::STATUS_DEACTIVATED;
            $v->save();
            $this->log($v, VoucherTransaction::TYPE_DEACTIVATE, 0, $note, $admin);

            return null;
        });
    }

    /**
     * @param  array<int, int|string>  $ids
     * @return array{done: int, skipped: array<int, array{code: string, reason: string}>}
     */
    public function reactivate(array $ids, ?string $note, User $admin): array
    {
        return $this->each($ids, function (Voucher $v) use ($note, $admin) {
            if ($v->trashed()) {
                return 'deleted';
            }
            if ($v->status !== Voucher::STATUS_DEACTIVATED) {
                return 'not deactivated';
            }

            $v->status = Voucher::STATUS_ACTIVE;
            $v->save();
            $this->log($v, VoucherTransaction::TYPE_ACTIVATE, 0, $note, $admin);

            return null;
        });
    }

    /**
     * Soft delete. An active voucher must be deactivated first (two deliberate
     * steps before a customer's balance disappears). The voucher's open till
     * exceptions are marked reviewed at the same moment.
     *
     * @param  array<int, int|string>  $ids
     * @return array{done: int, skipped: array<int, array{code: string, reason: string}>}
     */
    public function delete(array $ids, ?string $note, User $admin): array
    {
        return $this->each($ids, function (Voucher $v) use ($note, $admin) {
            if ($v->trashed()) {
                return 'already deleted';
            }
            if ($v->status === Voucher::STATUS_ACTIVE) {
                return 'active: deactivate it first';
            }

            $this->log($v, VoucherTransaction::TYPE_DELETE, 0, $note, $admin);
            $v->delete();

            VoucherTillRedemption::where('voucher_id', $v->id)
                ->whereNull('reviewed_at')
                ->update(['reviewed_at' => now(), 'reviewed_by' => $admin->id]);

            return null;
        });
    }

    /**
     * @param  array<int, int|string>  $ids
     * @return array{done: int, skipped: array<int, array{code: string, reason: string}>}
     */
    public function restore(array $ids, ?string $note, User $admin): array
    {
        return $this->each($ids, function (Voucher $v) use ($note, $admin) {
            if (! $v->trashed()) {
                return 'not deleted';
            }

            $v->restore();
            $this->log($v, VoucherTransaction::TYPE_RESTORE, 0, $note, $admin);

            return null;
        });
    }

    /**
     * Return a hand-activated voucher nobody has used to unsold, with its
     * balance as its sale value, so it sells through the till like a new one.
     * Undone by a manager activating it by hand on /vouchers.
     *
     * @param  array<int, int|string>  $ids
     * @return array{done: int, skipped: array<int, array{code: string, reason: string}>}
     */
    public function makeForSale(array $ids, ?string $note, User $admin): array
    {
        return $this->each($ids, function (Voucher $v) use ($note, $admin) {
            if ($v->trashed()) {
                return 'deleted';
            }
            if (! in_array($v->status, [Voucher::STATUS_ACTIVE, Voucher::STATUS_DEACTIVATED], true)) {
                return 'not active or deactivated';
            }

            $balance = round((float) $v->current_balance, 2);

            if ($balance <= 0) {
                return 'no balance';
            }
            if ($v->transactions()->where('type', VoucherTransaction::TYPE_DEDUCT)->exists()) {
                return 'has been spent from';
            }
            if ($v->initial_value === null || round((float) $v->initial_value, 2) !== $balance) {
                return 'balance differs from its initial value';
            }

            $v->face_value = $balance;
            $v->current_balance = 0;
            $v->initial_value = null;
            $v->status = Voucher::STATUS_INACTIVE;
            $v->save();
            $this->log($v, VoucherTransaction::TYPE_FOR_SALE, $balance, $note, $admin);

            return null;
        });
    }

    /**
     * Run $action on each voucher under its own lock and transaction. The action
     * returns null when done, or the reason it was skipped. After each commit the
     * voucher's till product is brought in line (sync() never throws).
     *
     * @param  array<int, int|string>  $ids
     * @param  callable(Voucher): ?string  $action
     * @return array{done: int, skipped: array<int, array{code: string, reason: string}>}
     */
    private function each(array $ids, callable $action): array
    {
        $done = 0;
        $skipped = [];

        foreach (array_unique(array_map('intval', $ids)) as $id) {
            try {
                $result = DB::transaction(function () use ($id, $action) {
                    $voucher = Voucher::withTrashed()->whereKey($id)->lockForUpdate()->first();

                    if (! $voucher) {
                        return ['code' => '#'.$id, 'reason' => 'not found'];
                    }

                    $reason = $action($voucher);

                    return $reason === null ? $voucher : ['code' => $voucher->code, 'reason' => $reason];
                });
            } catch (\Throwable $e) {
                Log::error('Voucher admin action failed', ['voucher_id' => $id, 'error' => $e->getMessage()]);
                $skipped[] = ['code' => '#'.$id, 'reason' => 'error'];

                continue;
            }

            if (is_array($result)) {
                $skipped[] = $result;

                continue;
            }

            $done++;
            $this->posProducts->sync(Voucher::withTrashed()->find($result->id));
        }

        return ['done' => $done, 'skipped' => $skipped];
    }

    private function log(Voucher $v, string $type, float $amount, ?string $note, User $admin): void
    {
        $v->transactions()->create([
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $v->current_balance,
            'note' => $note !== null && trim($note) !== '' ? trim($note) : null,
            'user_id' => $admin->id,
        ]);
    }
}
