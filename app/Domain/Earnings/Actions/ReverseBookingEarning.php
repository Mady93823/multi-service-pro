<?php

namespace App\Domain\Earnings\Actions;

use App\Domain\Earnings\Enums\EarningStatus;
use App\Domain\Earnings\Enums\EarningType;
use App\Models\Booking;
use App\Models\Earning;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A refunded booking earns the provider nothing and costs them nothing (M09).
 *
 * The ledger is append-only, so nothing is edited: an opposing row negates the
 * job row column for column. Because a cash job's net is negative, negating it
 * *credits* the provider — the platform forgives the commission on a job it
 * refunded. The cash the provider physically holds is a real-world recovery
 * outside the ledger's scope (ADR D16).
 */
class ReverseBookingEarning
{
    public function handle(Booking $booking, ?string $note = null): ?Earning
    {
        return DB::transaction(function () use ($booking, $note): ?Earning {
            Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            $reversal = $this->find($booking, EarningType::Reversal);

            if ($reversal !== null) {
                return $reversal;
            }

            $job = $this->find($booking, EarningType::Job);

            if ($job === null) {
                return null;
            }

            return Earning::query()->create([
                'provider_id' => $job->provider_id,
                'booking_id' => $job->booking_id,
                'type' => EarningType::Reversal,
                'gross' => Money::decimal(-(float) $job->gross),
                'commission' => Money::decimal(-(float) $job->commission),
                'collected_amount' => Money::decimal(-(float) $job->collected_amount),
                'net' => Money::decimal(-(float) $job->net),
                'commission_rate' => $job->commission_rate,
                'note' => $note,
                ...$this->mirror($job),
            ]);
        });
    }

    /**
     * While the job row is unclaimed the pair should net to zero together, so
     * the reversal copies its release window. Once it has been paid out the
     * correction can only land on the provider's next balance.
     *
     * @return array{status: EarningStatus, available_at: Carbon|null}
     */
    private function mirror(Earning $job): array
    {
        if ($job->status === EarningStatus::PaidOut) {
            return ['status' => EarningStatus::Available, 'available_at' => now()];
        }

        return ['status' => $job->status, 'available_at' => $job->available_at];
    }

    private function find(Booking $booking, EarningType $type): ?Earning
    {
        return Earning::query()
            ->where('booking_id', $booking->id)
            ->where('type', $type->value)
            ->first();
    }
}
