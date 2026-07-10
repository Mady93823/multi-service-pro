<?php

namespace App\Domain\Earnings\Actions;

use App\Domain\Earnings\Enums\PayoutStatus;
use App\Domain\Settings\SettingsRegistry;
use App\Models\Earning;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A payout claims the provider's whole released balance at once (M09) — no
 * partial amounts to reconcile. Claimed rows point back at the request, so
 * "what did this payout settle" is always answerable, and a second request
 * cannot double-spend them.
 *
 * Negative rows are claimed too: a cash-job debt offsets the payout rather
 * than being quietly left behind.
 */
class RequestPayout
{
    public function __construct(private readonly SettingsRegistry $settings) {}

    /**
     * @param  array<string, mixed>  $methodDetails
     */
    public function handle(User $provider, array $methodDetails): PayoutRequest
    {
        if (! $this->settings->boolean('payouts.enabled', true)) {
            throw ValidationException::withMessages([
                'payout' => __('Payouts are currently disabled.'),
            ]);
        }

        return DB::transaction(function () use ($provider, $methodDetails): PayoutRequest {
            $openRequest = PayoutRequest::query()
                ->where('provider_id', $provider->id)
                ->whereIn('status', array_map(fn (PayoutStatus $status): string => $status->value, PayoutStatus::open()))
                ->lockForUpdate()
                ->exists();

            if ($openRequest) {
                throw ValidationException::withMessages([
                    'payout' => __('You already have a payout request in progress.'),
                ]);
            }

            $earnings = Earning::query()
                ->where('provider_id', $provider->id)
                ->claimable()
                ->lockForUpdate()
                ->get();

            $amount = round($earnings->sum(fn (Earning $earning): float => (float) $earning->net), 2);
            $minimum = $this->settings->decimal('payouts.min_amount', 0.0);

            if ($amount <= 0.0) {
                throw ValidationException::withMessages([
                    'payout' => __('You have no balance available to withdraw.'),
                ]);
            }

            if ($amount < $minimum) {
                throw ValidationException::withMessages([
                    'payout' => __('Payouts start at :amount.', [
                        'amount' => Money::format($minimum, $this->currency()),
                    ]),
                ]);
            }

            $payout = PayoutRequest::query()->create([
                'provider_id' => $provider->id,
                'amount' => Money::decimal($amount),
                'status' => PayoutStatus::Requested,
                'method_details' => $methodDetails,
            ]);

            Earning::query()
                ->whereIn('id', $earnings->modelKeys())
                ->update(['payout_request_id' => $payout->id]);

            return $payout;
        });
    }

    private function currency(): string
    {
        return $this->settings->string('localization.currency', 'INR') ?: 'INR';
    }
}
