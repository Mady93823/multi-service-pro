<?php

use App\Domain\Bookings\BookingStateMachine;
use App\Domain\Bookings\Enums\BookingActor;
use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Dispatch\Enums\OfferStatus;
use App\Domain\Dispatch\Events\BookingOffered;
use App\Models\Booking;
use App\Models\FcmToken;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Notifications\BookingStatusNotification;
use App\Notifications\FcmChannel;
use App\Notifications\NewJobOfferNotification;
use Illuminate\Support\Facades\Notification;

function notifyProvider(): User
{
    $user = User::factory()->provider()->create();
    ProviderProfile::factory()->approved()->online()->for($user)->create();

    return $user;
}

function notifyAdvance(Booking $booking, BookingStatus $to, User $provider): void
{
    app(BookingStateMachine::class)->transition($booking, $to, BookingActor::Provider, $provider);
}

test('a booking status change notifies the customer', function () {
    Notification::fake();

    $provider = notifyProvider();
    $booking = Booking::factory()->status(BookingStatus::Assigned)->withProvider($provider)->create();

    notifyAdvance($booking, BookingStatus::Accepted, $provider);

    Notification::assertSentTo(
        $booking->customer,
        BookingStatusNotification::class,
        fn (BookingStatusNotification $notification) => $notification->status === BookingStatus::Accepted,
    );
});

test('noisy interim transitions do not notify the customer', function () {
    Notification::fake();

    $booking = Booking::factory()->status(BookingStatus::Placed)->create();

    app(BookingStateMachine::class)->transition($booking, BookingStatus::Searching, BookingActor::System);

    Notification::assertNothingSent();
});

test('the notification lands in the database for the in-app centre', function () {
    $provider = notifyProvider();
    $booking = Booking::factory()->status(BookingStatus::Assigned)->withProvider($provider)->create();

    notifyAdvance($booking, BookingStatus::Accepted, $provider);

    $notification = $booking->customer->notifications()->sole();

    expect($notification->data['type'])->toBe('booking_status')
        ->and($notification->data['status'])->toBe('accepted')
        ->and($notification->data['booking_id'])->toBe($booking->id)
        ->and($notification->read_at)->toBeNull();
});

test('an offered provider is notified of the new job', function () {
    Notification::fake();

    $provider = notifyProvider();
    $booking = Booking::factory()->status(BookingStatus::Searching)->create();
    $offer = $booking->dispatchOffers()->create([
        'provider_id' => $provider->id,
        'strategy' => 'nearest',
        'status' => OfferStatus::Offered->value,
        'round' => 1,
        'offered_at' => now(),
        'expires_at' => now()->addMinute(),
    ]);

    BookingOffered::dispatch($booking, collect([$offer]));

    Notification::assertSentTo($provider, NewJobOfferNotification::class);
});

test('the notification centre lists the users own notifications and marks them read', function () {
    $provider = notifyProvider();
    $booking = Booking::factory()->status(BookingStatus::Assigned)->withProvider($provider)->create();
    notifyAdvance($booking, BookingStatus::Accepted, $provider);

    $customer = $booking->customer;

    $this->actingAs($customer)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('notifications/index')
            ->has('entries.data', 1)
            // The bell feed is a shared prop and must survive alongside the page's own list.
            ->where('notifications.unread_count', 1));

    $this->post(route('notifications.read-all'))->assertRedirect();

    expect($customer->unreadNotifications()->count())->toBe(0);
});

test('a user cannot read another users notification', function () {
    $provider = notifyProvider();
    $booking = Booking::factory()->status(BookingStatus::Assigned)->withProvider($provider)->create();
    notifyAdvance($booking, BookingStatus::Accepted, $provider);

    $notification = $booking->customer->notifications()->sole();

    $this->actingAs(User::factory()->customer()->create())
        ->post(route('notifications.read', $notification->id))
        ->assertRedirect();

    expect($notification->fresh()->read_at)->toBeNull();
});

test('a device push token is registered against the user', function () {
    $user = User::factory()->customer()->create();

    $this->actingAs($user)
        ->postJson(route('fcm-tokens.store'), ['token' => 'device-token-abc'])
        ->assertOk();

    expect(FcmToken::query()->where('user_id', $user->id)->where('token', 'device-token-abc')->exists())->toBeTrue();
});

test('the fcm channel stays out of the way while firebase is unconfigured', function () {
    $booking = Booking::factory()->create();
    $notification = new BookingStatusNotification($booking, BookingStatus::Accepted);

    expect(FcmChannel::isConfigured())->toBeFalse()
        ->and($notification->via($booking->customer))->toBe(['database', 'broadcast']);
});
