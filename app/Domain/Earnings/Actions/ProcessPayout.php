<?php

namespace App\Domain\Earnings\Actions;

use App\Domain\Earnings\Enums\EarningStatus;
use App\Domain\Earnings\Enums\PayoutStatus;
use App\Models\PayoutRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The admin side of a payout (M09). Every decision is terminal or one step
 * along `requested → approved → paid`; a rejected request releases the
 * earnings it had claimed so the provider can request again.
 *
 * Money leaves the platform through the admin's own bank transfer — the
 * reference column records it. The provider's wallet is never touched: a
 * payout is not a wallet movement, it is money leaving the system.
 */
class ProcessPayout
{
    public function approve(PayoutRequest $payout, User $admin): PayoutRequest
    {
        return $this->decide($payout, [PayoutStatus::Requested], function (PayoutRequest $locked) use ($admin): void {
            $locked->forceFill([
                'status' => PayoutStatus::Approved,
                'processed_by' => $admin->id,
                'processed_at' => now(),
            ])->save();
        });
    }

    public function markPaid(PayoutRequest $payout, User $admin, string $reference): PayoutRequest
    {
        return $this->decide($payout, PayoutStatus::open(), function (PayoutRequest $locked) use ($admin, $reference): void {
            $locked->forceFill([
                'status' => PayoutStatus::Paid,
                'processed_by' => $admin->id,
                'processed_at' => now(),
                'reference' => $reference,
            ])->save();

            $locked->earnings()->update(['status' => EarningStatus::PaidOut->value]);
        });
    }

    public function reject(PayoutRequest $payout, User $admin, string $reason): PayoutRequest
    {
        return $this->decide($payout, PayoutStatus::open(), function (PayoutRequest $locked) use ($admin, $reason): void {
            // Unclaim first: these rows must be free before the request stops
            // being open, or the provider is left with a balance they cannot
            // reach.
            $locked->earnings()->update(['payout_request_id' => null]);

            $locked->forceFill([
                'status' => PayoutStatus::Rejected,
                'processed_by' => $admin->id,
                'processed_at' => now(),
                'note' => $reason,
            ])->save();
        });
    }

    /**
     * @param  list<PayoutStatus>  $from
     * @param  callable(PayoutRequest): void  $apply
     */
    private function decide(PayoutRequest $payout, array $from, callable $apply): PayoutRequest
    {
        return DB::transaction(function () use ($payout, $from, $apply): PayoutRequest {
            /** @var PayoutRequest $locked */
            $locked = PayoutRequest::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, $from, true)) {
                throw ValidationException::withMessages([
                    'status' => __('This payout request has already been processed.'),
                ]);
            }

            $apply($locked);

            return $locked;
        });
    }
}
