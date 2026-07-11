<?php

use App\Domain\Support\Enums\TicketStatus;
use App\Models\User;
use App\Notifications\TicketReplyNotification;
use Illuminate\Support\Facades\Notification;
use Tests\Support\SupportFixtures;

test('an admin reply flips the ticket to pending, auto-assigns and notifies the owner', function () {
    Notification::fake();

    [$ticket, $owner] = SupportFixtures::openTicket();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.tickets.reply', $ticket), [
        'body' => 'We are looking into this.',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $ticket->refresh();

    expect($ticket->status)->toBe(TicketStatus::Pending)
        ->and($ticket->assigned_to)->toBe($admin->id)
        ->and($ticket->messages()->where('is_staff', true)->count())->toBe(1);

    Notification::assertSentTo($owner, TicketReplyNotification::class, function (TicketReplyNotification $notification, array $channels) use ($ticket) {
        // The ≤2s in-app gate rides the broadcast channel (M11/Reverb).
        return $notification->ticket->is($ticket)
            && in_array('database', $channels, true)
            && in_array('broadcast', $channels, true);
    });
});

test('an owner reply reopens the ticket and notifies the assigned admin', function () {
    Notification::fake();

    [$ticket, $owner] = SupportFixtures::openTicket();
    $admin = User::factory()->admin()->create();
    $ticket->update(['status' => TicketStatus::Resolved, 'assigned_to' => $admin->id, 'resolved_at' => now()]);

    $this->actingAs($owner)->post(route('support.tickets.reply', $ticket), [
        'body' => 'It is still not fixed.',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($ticket->refresh()->status)->toBe(TicketStatus::Open);

    Notification::assertSentTo($admin, TicketReplyNotification::class);
    Notification::assertNotSentTo($owner, TicketReplyNotification::class);
});

test('an unassigned ticket takes an owner reply without notifying anyone', function () {
    Notification::fake();

    [$ticket, $owner] = SupportFixtures::openTicket();

    $this->actingAs($owner)->post(route('support.tickets.reply', $ticket), [
        'body' => 'Adding more detail.',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($ticket->refresh()->messages()->count())->toBe(2);

    Notification::assertNothingSent();
});

test('nobody can reply to a closed ticket — not even an admin', function () {
    [$ticket, $owner] = SupportFixtures::openTicket();
    $admin = User::factory()->admin()->create();
    $ticket->update(['status' => TicketStatus::Closed, 'closed_at' => now()]);

    $this->actingAs($owner)->post(route('support.tickets.reply', $ticket), [
        'body' => 'Please reopen.',
    ])->assertForbidden();

    $this->actingAs($admin)->post(route('admin.tickets.reply', $ticket), [
        'body' => 'Following up.',
    ])->assertForbidden();

    expect($ticket->messages()->count())->toBe(1);
});

test('only the ticket participants can reply', function () {
    [$ticket] = SupportFixtures::openTicket();
    $stranger = User::factory()->customer()->create();

    $this->actingAs($stranger)->post(route('support.tickets.reply', $ticket), [
        'body' => 'Let me in.',
    ])->assertForbidden();
});

test('a reply needs a body', function () {
    [$ticket, $owner] = SupportFixtures::openTicket();

    $this->actingAs($owner)->post(route('support.tickets.reply', $ticket), [
        'body' => '',
    ])->assertSessionHasErrors('body');
});
