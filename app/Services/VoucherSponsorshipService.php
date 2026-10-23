<?php

namespace App\Services;

use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherTransaction;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sponsorship activation: an admin activates an unsold voucher without a sale,
 * for a charity, raffle or fundraiser.
 *
 * Nothing goes through the till, so the accounts never see the money the way
 * they see a sold voucher. The voucher records who it went to (`sponsored_to`,
 * `sponsored_at`), the issue row carries a "Sponsorship: …" note, and the
 * Sales Accounting report lists the period's sponsored vouchers so they can be
 * booked as a sponsorship expense (see docs/features/voucher-management.md,
 * "Sponsorship activation").
 */
class VoucherSponsorshipService
{
    public function __construct(
        protected VoucherPosProductService $posProducts,
    ) {}

    /**
     * Activate $voucher with $amount as a sponsorship to $beneficiary.
     *
     * Only an unsold (`inactive`, not deleted) voucher qualifies. Runs under a
     * row lock like every other balance change; the till product is brought to
     * €0.00 after the commit.
     *
     * @return array{success: bool, message?: string, status?: string, current_balance?: float}
     */
    public function sponsor(Voucher $voucher, float $amount, string $beneficiary, ?string $note, User $admin): array
    {
        $amount = round($amount, 2);
        $beneficiary = trim($beneficiary);
        $note = trim((string) $note);

        $result = DB::transaction(function () use ($voucher, $amount, $beneficiary, $note, $admin) {
            $locked = Voucher::withTrashed()->whereKey($voucher->getKey())->lockForUpdate()->first();

            if (! $locked) {
                return ['success' => false, 'message' => 'Voucher not found.'];
            }
            if ($locked->trashed()) {
                return ['success' => false, 'message' => 'This voucher was deleted. It cannot be sponsored.'];
            }
            if ($locked->status !== Voucher::STATUS_INACTIVE) {
                return ['success' => false, 'message' => 'Only an unsold (inactive) voucher can be sponsored.'];
            }

            $locked->initial_value = $amount;
            $locked->current_balance = $amount;
            $locked->status = Voucher::STATUS_ACTIVE;
            $locked->sponsored_to = $beneficiary;
            $locked->sponsored_at = now();
            if (! $locked->created_by) {
                $locked->created_by = $admin->id;
            }
            $locked->save();

            $locked->transactions()->create([
                'type' => VoucherTransaction::TYPE_ISSUE,
                'source' => VoucherTransaction::SOURCE_MANUAL,
                'amount' => $amount,
                'balance_after' => $locked->current_balance,
                'note' => VoucherTransaction::SPONSORSHIP_NOTE_PREFIX.$beneficiary.($note !== '' ? ' · '.$note : ''),
                'user_id' => $admin->id,
            ]);

            return [
                'success' => true,
                'status' => $locked->status,
                'current_balance' => (float) $locked->current_balance,
            ];
        });

        if ($result['success']) {
            // Reload: the instance changed inside the transaction is out of scope.
            $this->posProducts->sync(Voucher::find($voucher->getKey()));
        }

        return $result;
    }

    /**
     * Sponsored vouchers issued in the period (by sponsorship date, inclusive),
     * oldest first, for the Sales Accounting report. Deleted vouchers are left
     * out, as they are everywhere else.
     *
     * @return Collection<int, Voucher>
     */
    public function issuedBetween(Carbon $start, Carbon $end): Collection
    {
        return Voucher::query()
            ->sponsored()
            ->whereBetween('sponsored_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->orderBy('sponsored_at')
            ->get();
    }
}
