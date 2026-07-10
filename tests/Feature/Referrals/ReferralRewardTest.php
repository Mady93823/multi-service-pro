<?php

use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Bookings\Enums\PaymentMethod;
use App\Domain\Payments\WalletService;
use App\Domain\Referrals\Enums\ReferralStatus;
use App\Domain\Settings\SettingsRegistry;
use App\Models\Booking;
use App\Models\Referral;
use App\Models\Service;
use App\Models\User;
use App\Notifications\ReferralRewardNotification;
use Illuminate\Support\Facades\Notification;
use Tests\Support\EarningsFixtures;

/**
 * @return array{0: Referral, 1: Booking}
 */
function pendingReferralWithJob(): array
{
    $referrer = User::factory()->customer()->create();
    $referee = User::factory()->customer()->create();

    $referral = Referral::factory()->create([
        'referrer_id' => $referrer->id,
        'referee_id' => $referee->id,
    ]);

    $booking = EarningsFixtures::booking(PaymentMethod::Wallet, Service::factory()->create(['price' => 500]));
    $booking->forceFill(['customer_id' => $referee->id])->save();

    return [$referral, $booking];
}

test("the referee's first completed booking rewards the referrer's wallet", function () {
    Notification::fake();
    [$referral, $booking] = pendingReferralWithJob();

    EarningsFixtures::complete($booking);

    $referral->refresh();
    expect($referral->status)->toBe(ReferralStatus::Rewarded)
        ->and((string) $referral->reward_amount)->toBe('100.00') // shipped default
        ->and($referral->rewarded_at)->not->toBeNull();

    $referrer = $referral->referrer;
    expect(app(WalletService::class)->balance($referrer))->toBe(100.0);

    $transaction = app(WalletService::class)->for($referrer)->transactions()->sole();
    expect($transaction->type)->toBe('referral_reward')
        ->and($transaction->reference_type)->toBe(Referral::class)
        ->and($transaction->reference_id)->toBe($referral->id);

    Notification::assertSentTo($referrer, ReferralRewardNotification::class);
});

test('a second completed booking does not pay twice', function () {
    Notification::fake();
    [$referral, $booking] = pendingReferralWithJob();
    EarningsFixtures::complete($booking);

    $second = EarningsFixtures::booking(PaymentMethod::Wallet);
    $second->forceFill(['customer_id' => $referral->referee_id])->save();
    EarningsFixtures::complete($second);

    $referrer = $referral->referrer;
    expect(app(WalletService::class)->balance($referrer))->toBe(100.0)
        ->and(app(WalletService::class)->for($referrer)->transactions()->where('type', 'referral_reward')->count())->toBe(1);
});

test('a disabled program leaves the referral pending', function () {
    [$referral, $booking] = pendingReferralWithJob();
    app(SettingsRegistry::class)->set('referrals.enabled', false);

    EarningsFixtures::complete($booking);

    expect($referral->refresh()->status)->toBe(ReferralStatus::Pending)
        ->and(app(WalletService::class)->balance($referral->referrer))->toBe(0.0);
});

test('a zero reward amount pauses payouts but keeps the referral pending', function () {
    [$referral, $booking] = pendingReferralWithJob();
    app(SettingsRegistry::class)->set('referrals.reward_amount', '0');

    EarningsFixtures::complete($booking);

    expect($referral->refresh()->status)->toBe(ReferralStatus::Pending)
        ->and(app(WalletService::class)->balance($referral->referrer))->toBe(0.0);
});

test('completing a booking for a customer who was never referred is a no-op', function () {
    $booking = EarningsFixtures::booking(PaymentMethod::Wallet);

    EarningsFixtures::complete($booking);

    expect($booking->refresh()->status)->toBe(BookingStatus::Completed);
});
