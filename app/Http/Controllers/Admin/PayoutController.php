<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Activity\ActivityLogger;
use App\Domain\Earnings\Actions\ProcessPayout;
use App\Domain\Earnings\Actions\VerifyPayoutAccount;
use App\Domain\Earnings\Enums\PayoutStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MarkPayoutPaidRequest;
use App\Http\Requests\Admin\RejectPayoutRequest;
use App\Http\Resources\PayoutAccountResource;
use App\Models\PayoutAccount;
use App\Models\PayoutRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayoutController extends Controller
{
    public function __construct(
        private readonly ProcessPayout $payouts,
        private readonly ActivityLogger $activity,
    ) {}

    public function index(Request $request): Response
    {
        $status = (string) $request->string('status');

        $payouts = PayoutRequest::query()
            ->with(['provider:id,name,email', 'processedBy:id,name', 'payoutAccount'])
            ->withCount('earnings')
            ->when(
                PayoutStatus::tryFrom($status) !== null,
                fn ($query) => $query->where('status', $status),
            )
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/payouts/index', [
            'payouts' => $payouts->through(fn (PayoutRequest $payout): array => [
                'id' => $payout->id,
                'provider' => [
                    'id' => $payout->provider?->id,
                    'name' => $payout->provider?->name,
                    'email' => $payout->provider?->email,
                ],
                'amount' => $payout->amount,
                'status' => $payout->status->value,
                // The snapshot is what was actually claimed; the account row
                // (M22) is only there to say whether a human ever checked it.
                'method_details' => $payout->method_details,
                'account' => $payout->payoutAccount === null
                    ? null
                    : (new PayoutAccountResource($payout->payoutAccount))->resolve(),
                'earnings_count' => $payout->earnings_count,
                'reference' => $payout->reference,
                'note' => $payout->note,
                'processed_by' => $payout->processedBy?->name,
                'processed_at' => $payout->processed_at?->toIso8601String(),
                'created_at' => $payout->created_at?->toIso8601String(),
            ]),
            'filters' => ['status' => $status],
            'statuses' => array_map(fn (PayoutStatus $case): string => $case->value, PayoutStatus::cases()),
        ]);
    }

    public function approve(Request $request, PayoutRequest $payout): RedirectResponse
    {
        $admin = $request->user();
        abort_if($admin === null, 403);

        $this->payouts->approve($payout, $admin);

        $this->activity->log($admin, 'payout.approve', $payout, ['amount' => (float) $payout->amount]);

        return back()->with('success', __('Payout approved.'));
    }

    public function pay(MarkPayoutPaidRequest $request, PayoutRequest $payout): RedirectResponse
    {
        $admin = $request->user();
        abort_if($admin === null, 403);

        $this->payouts->markPaid($payout, $admin, (string) $request->validated('reference'));

        $this->activity->log($admin, 'payout.pay', $payout, [
            'amount' => (float) $payout->amount,
            'reference' => (string) $request->validated('reference'),
        ]);

        return back()->with('success', __('Payout marked as paid.'));
    }

    /**
     * Tick a provider's payout destination as checked against their KYC (M22).
     * Advisory, not a gate — but it is the record that someone looked before the
     * money left. Editing the account clears the flag again.
     */
    public function verifyAccount(Request $request, PayoutAccount $account, VerifyPayoutAccount $action): RedirectResponse
    {
        $admin = $request->user();
        abort_if($admin === null, 403);

        $verified = $request->boolean('verified', true);

        $action->handle($account, $verified);

        $this->activity->log($admin, 'payout_account.verify', $account, ['verified' => $verified]);

        return back()->with('success', $verified
            ? __('Payout account marked as verified.')
            : __('Payout account verification removed.'));
    }

    public function reject(RejectPayoutRequest $request, PayoutRequest $payout): RedirectResponse
    {
        $admin = $request->user();
        abort_if($admin === null, 403);

        $this->payouts->reject($payout, $admin, (string) $request->validated('note'));

        $this->activity->log($admin, 'payout.reject', $payout, [
            'amount' => (float) $payout->amount,
            'note' => (string) $request->validated('note'),
        ]);

        return back()->with('success', __('Payout rejected.'));
    }
}
