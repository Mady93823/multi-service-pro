<?php

namespace Database\Seeders;

use App\Models\BankAccount;
use App\Models\PayoutAccount;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo payment plumbing (M22): one bank account customers could transfer into,
 * and a payout destination for the demo provider. `payments.offline_enabled`
 * stays off — the method only appears once an admin switches it on, which is
 * the fresh-install default we want a buyer to see.
 */
class PaymentSeeder extends Seeder
{
    public function run(): void
    {
        BankAccount::query()->firstOrCreate(
            ['label' => 'UrbanServe — Current A/C'],
            [
                'account_name' => 'UrbanServe Services Pvt Ltd',
                'account_number' => '502001234567890',
                'ifsc' => 'HDFC0001234',
                'upi_id' => 'urbanserve@hdfcbank',
                'notes' => 'Add your booking code as the transfer remark.',
                'is_active' => true,
                'sort_order' => 0,
            ],
        );

        $provider = User::query()->where('email', 'provider@demo.test')->first();

        if ($provider !== null) {
            PayoutAccount::query()->firstOrCreate(
                ['provider_id' => $provider->id, 'type' => 'upi'],
                [
                    'label' => 'Primary UPI',
                    'upi_id' => 'demo.provider@upi',
                    'is_default' => true,
                    'is_verified' => true,
                    'verified_at' => now(),
                ],
            );
        }
    }
}
