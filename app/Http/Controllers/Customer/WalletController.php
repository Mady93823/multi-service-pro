<?php

namespace App\Http\Controllers\Customer;

use App\Domain\Payments\WalletService;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WalletController extends Controller
{
    public function show(Request $request, WalletService $wallets): Response
    {
        /** @var User $user */
        $user = $request->user();

        $wallet = $wallets->for($user);

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
        ]);
    }
}
