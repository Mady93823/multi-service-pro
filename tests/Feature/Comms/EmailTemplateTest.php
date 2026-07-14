<?php

use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Comms\Enums\NotificationEvent;
use App\Models\Booking;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Notifications\BookingStatusNotification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * ADR D25: an email template is an OPTIONAL layer. The shipped default sits
 * underneath, and a template that is missing, switched off or broken must never
 * cost the recipient the message.
 */
function templateBooking(User $customer): Booking
{
    return Booking::factory()->create(['customer_id' => $customer->id]);
}

function bookingMail(User $customer): MailMessage
{
    $booking = templateBooking($customer);

    return (new BookingStatusNotification($booking, BookingStatus::Completed))->toMail($customer);
}

test('with no template at all, the shipped default email is sent', function () {
    $customer = User::factory()->customer()->create();

    $mail = bookingMail($customer);

    expect($mail->subject)->toBe('Service complete')
        // The default is Laravel's own markdown mail, not our template wrapper.
        ->and($mail->view)->not->toBe('mail.template')
        ->and($mail->introLines)->not->toBeEmpty();
});

test('an enabled template replaces the subject and body, placeholders and all', function () {
    $customer = User::factory()->customer()->create(['name' => 'Asha']);

    EmailTemplate::factory()->create([
        'event_key' => NotificationEvent::BookingStatus->value,
        'subject' => 'Your booking {{ code }}',
        'body' => 'Hi {{ name }} — {{ body }}',
    ]);

    $booking = templateBooking($customer);
    $mail = (new BookingStatusNotification($booking, BookingStatus::Completed))->toMail($customer);

    expect($mail->subject)->toBe('Your booking '.$booking->code)
        ->and($mail->view)->toBe('mail.template');

    $html = (string) $mail->viewData['content'];

    expect($html)->toContain('Hi Asha')
        ->and($html)->toContain($booking->code);
});

test('an unknown placeholder renders as nothing, never as itself', function () {
    $customer = User::factory()->customer()->create();

    EmailTemplate::factory()->create([
        'subject' => 'Hello',
        'body' => 'Secret: {{ database_password }} end',
    ]);

    $html = (string) bookingMail($customer)->viewData['content'];

    expect($html)->toContain('Secret:')
        ->and($html)->not->toContain('database_password')
        ->and($html)->not->toContain('{{');
});

test('raw HTML in a template body is stripped, not rendered', function () {
    $customer = User::factory()->customer()->create();

    EmailTemplate::factory()->create([
        'subject' => 'Hello',
        'body' => 'Careful <script>alert(1)</script> **bold**',
    ]);

    $html = (string) bookingMail($customer)->viewData['content'];

    expect($html)->not->toContain('<script')
        ->and($html)->toContain('<strong>bold</strong>');
});

test('a switched-off template falls back to the shipped default', function () {
    $customer = User::factory()->customer()->create();

    EmailTemplate::factory()->disabled()->create([
        'subject' => 'Custom subject',
        'body' => 'Custom body',
    ]);

    expect(bookingMail($customer)->subject)->toBe('Service complete');
});

test('a template that renders to nothing falls back rather than sending an empty email', function () {
    $customer = User::factory()->customer()->create();

    // Every placeholder is unknown, so the body renders empty — a broken
    // template, and the whole point of D25 is that this still delivers.
    EmailTemplate::factory()->create([
        'subject' => 'Still here',
        'body' => '{{ nope }}',
    ]);

    $mail = bookingMail($customer);

    expect($mail->subject)->toBe('Service complete')
        ->and($mail->view)->not->toBe('mail.template');
});

test('a template for one event leaves the others on their defaults', function () {
    $customer = User::factory()->customer()->create();

    EmailTemplate::factory()->create([
        'event_key' => NotificationEvent::TicketReply->value,
        'subject' => 'Ticket news',
        'body' => 'Body',
    ]);

    expect(bookingMail($customer)->subject)->toBe('Service complete');
});

test('an admin edits and then removes a template', function () {
    $admin = User::factory()->admin()->create();
    $event = NotificationEvent::BookingStatus->value;

    $this->actingAs($admin)->get(route('admin.email-templates.index'))->assertOk();

    $this->actingAs($admin)
        ->put(route('admin.email-templates.update', $event), [
            'subject' => 'Booking {{ code }}',
            'body' => 'Hello {{ name }}',
            'is_enabled' => true,
        ])
        ->assertRedirect();

    expect(EmailTemplate::query()->where('event_key', $event)->exists())->toBeTrue();

    $this->actingAs($admin)
        ->delete(route('admin.email-templates.destroy', $event))
        ->assertRedirect(route('admin.email-templates.index'));

    // Removing an override is not a way to break the email — it restores the default.
    expect(EmailTemplate::query()->where('event_key', $event)->exists())->toBeFalse();
});

test('the preview renders through the same markdown path a real send uses', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->postJson(
        route('admin.email-templates.preview', NotificationEvent::BookingStatus->value),
        ['subject' => 'Booking {{ code }}', 'body' => '**Hi** {{ name }} <script>x</script>'],
    );

    $response->assertOk();

    expect($response->json('subject'))->toBe('Booking BK-2026-000123')
        ->and($response->json('html'))->toContain('<strong>Hi</strong>')
        ->and($response->json('html'))->not->toContain('<script');
});

test('an unknown event is a 404, and a customer cannot touch templates at all', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('admin.email-templates.edit', 'not_an_event'))->assertNotFound();

    $this->actingAs(User::factory()->customer()->create())
        ->get(route('admin.email-templates.index'))
        ->assertForbidden();
});
