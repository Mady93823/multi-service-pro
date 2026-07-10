<?php

namespace App\Http\Controllers\Customer;

use App\Domain\Payments\WalletService;
use App\Domain\Referrals\Actions\EnsureReferralCode;
use App\Domain\Settings\SettingsRegistry;
use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WalletController extends Controller
{
    public function show(
        Request $request,
        WalletService $wallets,
        SettingsRegistry $settings,
        EnsureReferralCode $referralCode,
    ): Response {
        /** @var User $user */
        $user = $request->user();

        $wallet = $wallets->for($user);

        // Refer & earn card (M12) — rewards land in this wallet, so the
        // program lives on this page.
        $referrals = null;

        if ($settings->boolean('referrals.enabled', true)) {
            $code = $referralCode->handle($user);

            $referrals = [
                'code' => $code,
                'share_url' => route('register', ['ref' => $code]),
                'reward_amount' => number_format($settings->decimal('referrals.reward_amount', 0.0), 2, '.', ''),
                'entries' => Referral::query()
                    ->where('referrer_id', $user->id)
                    ->with('referee:id,name')
                    ->latest()
                    ->limit(10)
                    ->get()
                    ->map(fn (Referral $referral): array => [
                        'id' => $referral->id,
                        'referee_name' => $referral->referee?->name,
                        'status' => $referral->status->value,
                        'reward_amount' => $referral->reward_amount !== null ? (string) $referral->reward_amount : null,
                        'created_at' => $referral->created_at?->format('j M Y'),
                    ])
                    ->all(),
            ];
        }

        $transactions = $wallet->transactions()
            ->latest('id')
            ->paginate(20)
            ->through(fn (WalletTransaction $txn): array => [
                'id' => $txn->id,
                'type' => $txn->type,
                'direction' => $txn->direction,
                'amount' => $txn->amount,
                'balance_after' => $txn->balance_after,
                'note' => $txn->note,
                'created_at' => $txn->created_at->toIso8601String(),
            ]);

        return Inertia::render('customer/wallet', [
            'balance' => $wallet->balance,
            'transactions' => $transactions,
            'referrals' => $referrals,
        ]);
    }
}
