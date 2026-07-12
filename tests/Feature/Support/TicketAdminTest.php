<?php

use App\Domain\Settings\SettingsRegistry;
use App\Domain\Support\Enums\TicketStatus;
use App\Models\ActivityLog;
use App\Models\User;
use App\Notifications\TicketStatusNotification;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Tests\Support\SettingsFixtures;
use Tests\Support\SupportFixtures;

test('a fresh ticket reaches the admin queue', function () {
    [$ticket] = SupportFixtures::openTicket();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.tickets.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/tickets/index')
            ->where('tickets.data.0.code', $ticket->code));
});

test('the queue filters by status and assignee', function () {
    $admin = User::factory()->admin()->create();

    [$open] = SupportFixtures::openTicket();
    [$mine] = SupportFixtures::openTicket();
    $mine->update(['status' => TicketStatus::Pending, 'assigned_to' => $admin->id]);

    $this->actingAs($admin)
        ->get(route('admin.tickets.index', ['status' => 'open']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('tickets.meta.total', 1)
            ->where('tickets.data.0.code', $open->code));

    $this->actingAs($admin)
        ->get(route('admin.tickets.index', ['assigned' => 'me']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('tickets.meta.total', 1)
            ->where('tickets.data.0.code', $mine->code));

    $this->actingAs($admin)
        ->get(route('admin.tickets.index', ['assigned' => 'unassigned']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('tickets.meta.total', 1)
            ->where('tickets.data.0.code', $open->code));
});

test('a ticket can be assigned to an admin and the assignment is audited', function () {
    [$ticket] = SupportFixtures::openTicket();
    $admin = User::factory()->admin()->create();
    $colleague = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.tickets.assign', $ticket), [
        'assigned_to' => $colleague->id,
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($ticket->refresh()->assigned_to)->toBe($colleague->id);

    $log = ActivityLog::query()
        ->where('action', 'support.ticket.assigned')
        ->where('subject_id', $ticket->id)
        ->sole();

    expect($log->actor_id)->toBe($admin->id);
});

test('tickets cannot be assigned to a non-admin', function () {
    [$ticket, $owner] = SupportFixtures::openTicket();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.tickets.assign', $ticket), [
        'assigned_to' => $owner->id,
    ])->assertSessionHasErrors('assigned_to');

    expect($ticket->refresh()->assigned_to)->toBeNull();
});

test('resolving requires a note, notifies the owner and is audited', function () {
    Notification::fake();

    [$ticket, $owner] = SupportFixtures::openTicket();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.tickets.resolve', $ticket), [])
        ->assertSessionHasErrors('resolution_note');

    $this->actingAs($admin)->post(route('admin.tickets.resolve', $ticket), [
        'resolution_note' => 'Refund issued to the wallet.',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $ticket->refresh();

    expect($ticket->status)->toBe(TicketStatus::Resolved)
        ->and($ticket->resolved_at)->not->toBeNull()
        ->and($ticket->resolution_note)->toBe('Refund issued to the wallet.');

    Notification::assertSentTo($owner, TicketStatusNotification::class);

    expect(ActivityLog::query()->where('action', 'support.ticket.resolved')->where('subject_id', $ticket->id)->exists())->toBeTrue();
});

test('closing is final and read-only', function () {
    Notification::fake();

    [$ticket, $owner] = SupportFixtures::openTicket();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.tickets.close', $ticket), [
        'resolution_note' => 'No further action possible.',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $ticket->refresh();

    expect($ticket->status)->toBe(TicketStatus::Closed)
        ->and($ticket->closed_at)->not->toBeNull();

    Notification::assertSentTo($owner, TicketStatusNotification::class);

    // Closing twice, resolving after close and re-assigning are all refused.
    $this->actingAs($admin)->post(route('admin.tickets.close', $ticket), [])->assertSessionHasErrors('resolution_note');
    $this->actingAs($admin)->post(route('admin.tickets.resolve', $ticket), ['resolution_note' => 'Late.'])
        ->assertSessionHasErrors('resolution_note');
    $this->actingAs($admin)->post(route('admin.tickets.assign', $ticket), ['assigned_to' => $admin->id])
        ->assertSessionHasErrors('assigned_to');
});

test('the admin ticket page exposes canned responses from settings', function () {
    [$ticket] = SupportFixtures::openTicket();
    $admin = User::factory()->admin()->create();

    app(SettingsRegistry::class)->set('support.canned_responses', [
        ['title' => 'Apology', 'body' => 'We are sorry for the inconvenience.'],
    ]);

    $this->actingAs($admin)
        ->get(route('admin.tickets.show', $ticket))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/tickets/show')
            ->where('canned_responses.0.title', 'Apology'));
});

test('non-admins cannot touch the admin queue', function () {
    [$ticket, $owner] = SupportFixtures::openTicket();

    $this->actingAs($owner)->get(route('admin.tickets.index'))->assertForbidden();
    $this->actingAs($owner)->post(route('admin.tickets.close', $ticket), [])->assertForbidden();
});

test('canned responses round-trip through the settings save', function () {
    $admin = User::factory()->admin()->create();

    $payload = SettingsFixtures::payload('support', [
        'support_max_attachments' => 5,
        'support_canned_responses' => [
            ['title' => 'Greeting', 'body' => 'Hi! Thanks for reaching out.'],
        ],
    ]);

    $this->actingAs($admin)->put(route('admin.settings.update', 'support'), $payload)
        ->assertRedirect()->assertSessionHasNoErrors();

    $settings = app(SettingsRegistry::class);

    expect($settings->integer('support.max_attachments'))->toBe(5)
        ->and($settings->get('support.canned_responses'))->toBe([
            ['title' => 'Greeting', 'body' => 'Hi! Thanks for reaching out.'],
        ]);
});

test('sorting keeps the most recently active ticket on top', function () {
    $admin = User::factory()->admin()->create();

    [$stale] = SupportFixtures::openTicket();
    $stale->update(['last_reply_at' => now()->subDay()]);
    [$fresh] = SupportFixtures::openTicket();
    $fresh->update(['last_reply_at' => now()]);

    // Filtered to `open` so the seeded demo tickets (pending + closed,
    // landmine 6) stay out of the ordering assertion.
    $this->actingAs($admin)
        ->get(route('admin.tickets.index', ['status' => 'open']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('tickets.data.0.code', $fresh->code)
            ->where('tickets.data.1.code', $stale->code));
});
