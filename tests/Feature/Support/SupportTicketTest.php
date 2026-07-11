<?php

use App\Domain\Support\Enums\TicketStatus;
use App\Models\Booking;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\Support\SupportFixtures;

test('a customer can open a ticket linked to their booking with an attachment', function () {
    Storage::fake('local');

    $customer = User::factory()->customer()->create();
    $booking = Booking::factory()->create(['customer_id' => $customer->id]);

    $response = $this->actingAs($customer)->post(route('support.tickets.store'), [
        'subject' => 'Cleaner skipped a room',
        'category' => 'booking',
        'priority' => 'high',
        'booking_id' => $booking->id,
        'message' => 'The second bedroom was not cleaned at all.',
        'attachments' => [UploadedFile::fake()->image('proof.jpg')],
    ]);

    $response->assertRedirect()->assertSessionHasNoErrors();

    $ticket = SupportTicket::query()->where('user_id', $customer->id)->sole();

    expect($ticket->status)->toBe(TicketStatus::Open)
        ->and($ticket->booking_id)->toBe($booking->id)
        ->and($ticket->code)->toBe(sprintf('TKT-%06d', $ticket->id))
        ->and($ticket->messages()->count())->toBe(1);

    $message = $ticket->messages()->sole();

    expect($message->is_staff)->toBeFalse()
        ->and($message->getMedia('attachments'))->toHaveCount(1);
});

test('a provider can open a standalone ticket', function () {
    $provider = User::factory()->provider()->create();

    $this->actingAs($provider)->post(route('support.tickets.store'), [
        'subject' => 'KYC document stuck in review',
        'category' => 'account',
        'message' => 'My address proof has been pending for a week.',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $ticket = SupportTicket::query()->where('user_id', $provider->id)->sole();

    expect($ticket->booking_id)->toBeNull()
        ->and($ticket->priority->value)->toBe('normal');
});

test('someone else\'s booking cannot be linked', function () {
    $customer = User::factory()->customer()->create();
    $otherBooking = Booking::factory()->create();

    $this->actingAs($customer)->post(route('support.tickets.store'), [
        'subject' => 'Issue',
        'category' => 'booking',
        'booking_id' => $otherBooking->id,
        'message' => 'Trying to reference a stranger\'s booking.',
    ])->assertSessionHasErrors('booking_id');

    expect(SupportTicket::query()->where('user_id', $customer->id)->exists())->toBeFalse();
});

test('subject, category and message are required', function () {
    $customer = User::factory()->customer()->create();

    $this->actingAs($customer)->post(route('support.tickets.store'), [
        'category' => 'not-a-category',
    ])->assertSessionHasErrors(['subject', 'category', 'message']);
});

test('the ticket list shows only the owner\'s tickets', function () {
    [$ticket, $owner] = SupportFixtures::openTicket();
    [$foreign] = SupportFixtures::openTicket();

    $this->actingAs($owner)
        ->get(route('support.tickets.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('support/index')
            ->where('tickets.data.0.code', $ticket->code)
            ->where('tickets.meta.total', 1));

    expect($foreign->user_id)->not->toBe($owner->id);
});

test('a ticket is hidden from other users but visible to its owner and admins', function () {
    [$ticket, $owner] = SupportFixtures::openTicket();
    $stranger = User::factory()->customer()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($owner)->get(route('support.tickets.show', $ticket))->assertOk();
    $this->actingAs($stranger)->get(route('support.tickets.show', $ticket))->assertForbidden();
    $this->actingAs($admin)->get(route('admin.tickets.show', $ticket))->assertOk();
});

test('guests are redirected and admins have no customer help centre', function () {
    $this->get(route('support.tickets.index'))->assertRedirect(route('login'));

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->get(route('support.tickets.index'))->assertForbidden();
});
