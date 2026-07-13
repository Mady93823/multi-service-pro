<?php

namespace App\Http\Controllers\Provider;

use App\Domain\Earnings\Actions\RequestPayout;
use App\Domain\Earnings\EarningsLedger;
use App\Domain\Earnings\Enums\PayoutStatus;
use App\Domain\Settings\SettingsRegistry;
use App\Http\Controllers\Controller;
use App\Http\Requests\Provider\RequestPayoutRequest;
use App\Http\Resources\PayoutAccountResource;
use App\Models\Earning;
use App\Models\PayoutAccount;
use App\Models\PayoutRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EarningController extends Controller
{
    public function index(Request $request, EarningsLedger $ledger, SettingsRegistry $settings): Response
    {
        $provider = $request->user();
        abort_if($provider === null, 403);

        $earnings = Earning::query()
            ->where('provider_id', $provider->id)
            ->with('booking:id,code')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('provider/earnings', [
            'summary' => $ledger->summary($provider),
            'entries' => $earnings->through(fn (Earning $earning): array => [
                'id' => $earning->id,
                'type' => $earning->type->value,
                'booking_code' => $earning->booking?->code,
                'gross' => $earning->gross,
                'commission' => $earning->commission,
                'net' => $earning->net,
                'commission_rate' => $earning->commission_rate,
                'status' => $earning->status->value,
                'available_at' => $earning->available_at?->toIso8601String(),
                'created_at' => $earning->created_at?->toIso8601String(),
            ]),
            'payouts' => PayoutRequest::query()
                ->where('provider_id', $provider->id)
                ->latest('id')
                ->limit(10)
                ->get()
                ->map(fn (PayoutRequest $payout): array => [
                    'id' => $payout->id,
                    'amount' => $payout->amount,
                    'status' => $payout->status->value,
                    'reference' => $payout->reference,
                    'note' => $payout->note,
                    'created_at' => $payout->created_at?->toIso8601String(),
                    'processed_at' => $payout->processed_at?->toIso8601String(),
                ])->all(),
            // The payout dialog picks one of these instead of retyping bank
            // details every time (M22).
            'accounts' => PayoutAccountResource::collection(
                PayoutAccount::query()
                    ->where('provider_id', $provider->id)
                    ->orderByDesc('is_default')
                    ->orderBy('id')
                    ->get(),
            ),
            'payouts_enabled' => $settings->boolean('payouts.enabled', true),
            'minimum_payout' => $settings->decimal('payouts.min_amount', 0.0),
            // A request is barred while one is already open; the button says so.
            'has_open_payout' => PayoutRequest::query()
                ->where('provider_id', $provider->id)
                ->whereIn('status', array_map(
                    fn (PayoutStatus $status): string => $status->value,
                    PayoutStatus::open(),
                ))
                ->exists(),
        ]);
    }

    public function requestPayout(RequestPayoutRequest $request, RequestPayout $action): RedirectResponse
    {
        $provider = $request->user();
        abort_if($provider === null, 403);

        /** @var PayoutAccount $account */
        $account = PayoutAccount::query()->findOrFail((int) $request->validated('payout_account_id'));

        $action->handle($provider, $account);

        return back()->with('success', __('Payout requested. You will be notified once it is processed.'));
    }
}
