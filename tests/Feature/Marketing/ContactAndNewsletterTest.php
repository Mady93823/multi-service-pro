<?php

use App\Domain\Settings\SettingsRegistry;
use App\Models\Subscriber;
use App\Models\SupportTicket;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/**
 * The contact form opens a support ticket (M16) instead of filling a second
 * inbox, and the footer signup writes one subscriber row per address.
 */
beforeEach(function () {
    Subscriber::query()->delete();
});

it('opens an admin-only ticket from a guest contact submission', function () {
    $this->post(route('contact.store'), [
        'name' => 'Rita Guest',
        'email' => 'rita@example.test',
        'subject' => 'Do you cover Whitefield?',
        'message' => 'Asking before I sign up.',
    ])->assertRedirect();

    $ticket = SupportTicket::query()->where('subject', 'Do you cover Whitefield?')->firstOrFail();

    expect($ticket->user_id)->toBeNull()
        ->and($ticket->guest_name)->toBe('Rita Guest')
        ->and($ticket->guest_email)->toBe('rita@example.test')
        ->and($ticket->messages()->count())->toBe(1);

    // A guest ticket belongs to nobody, so no signed-in user may read it.
    $this->actingAs(User::factory()->customer()->create())
        ->get(route('support.tickets.show', $ticket))
        ->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.tickets.show', $ticket))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $inertia) => $inertia
            ->where('ticket.is_guest', true)
            ->where('ticket.user.name', 'Rita Guest'));
});

it('files a signed-in visitor’s contact message under their own tickets', function () {
    $customer = User::factory()->customer()->create();

    $this->actingAs($customer)->post(route('contact.store'), [
        'name' => $customer->name,
        'email' => $customer->email,
        'subject' => 'Reschedule question',
        'message' => 'Can I move tomorrow’s slot?',
    ])->assertRedirect();

    $ticket = SupportTicket::query()->where('subject', 'Reschedule question')->firstOrFail();

    expect($ticket->user_id)->toBe($customer->id);

    $this->actingAs($customer)->get(route('support.tickets.show', $ticket))->assertOk();
});

it('rejects a contact submission that trips the honeypot', function () {
    $this->post(route('contact.store'), [
        'name' => 'Bot',
        'email' => 'bot@example.test',
        'subject' => 'Cheap pills',
        'message' => 'Buy now',
        'website' => 'http://spam.test',
    ])->assertSessionHasErrors('website');

    expect(SupportTicket::query()->where('subject', 'Cheap pills')->exists())->toBeFalse();
});

it('subscribes an address once, and re-subscribes one that had opted out', function () {
    $this->post(route('newsletter.store'), ['email' => 'Reader@Example.test'])->assertRedirect();
    $this->post(route('newsletter.store'), ['email' => 'reader@example.test'])->assertRedirect();

    $subscribers = Subscriber::query()->where('email', 'reader@example.test')->get();

    expect($subscribers)->toHaveCount(1);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->delete(route('admin.subscribers.destroy', $subscribers->first()))
        ->assertRedirect();

    // Unsubscribing keeps the row — the opt-out is the thing worth remembering.
    expect($subscribers->first()->refresh()->unsubscribed_at)->not->toBeNull();

    $this->post(route('newsletter.store'), ['email' => 'reader@example.test'])->assertRedirect();

    expect(Subscriber::query()->where('email', 'reader@example.test')->count())->toBe(1)
        ->and(Subscriber::query()->where('email', 'reader@example.test')->first()->unsubscribed_at)->toBeNull();
});

it('404s the newsletter endpoint while the signup is switched off', function () {
    app(SettingsRegistry::class)->set('appearance.newsletter_enabled', false);

    $this->post(route('newsletter.store'), ['email' => 'reader@example.test'])->assertNotFound();

    expect(Subscriber::query()->count())->toBe(0);
});

it('exports the subscriber list through the existing report pipeline', function () {
    Subscriber::factory()->create(['email' => 'listed@example.test']);

    $response = $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.reports.export', 'subscribers'));

    $response->assertOk();

    expect($response->streamedContent())->toContain('listed@example.test');
});
