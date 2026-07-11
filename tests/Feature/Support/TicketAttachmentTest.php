<?php

use App\Domain\Settings\SettingsRegistry;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\SupportFixtures;

test('an attachment is stored on the private disk and served to the owner only', function () {
    Storage::fake('local');

    [$ticket, $owner] = SupportFixtures::openTicket();

    $this->actingAs($owner)->post(route('support.tickets.reply', $ticket), [
        'body' => 'Here is a photo of the issue.',
        'attachments' => [UploadedFile::fake()->image('issue.png')],
    ])->assertSessionHasNoErrors();

    $media = $ticket->messages()->latest('id')->first()?->getFirstMedia('attachments');

    expect($media)->not->toBeNull()
        ->and($media?->disk)->toBe('local');

    $url = route('support.attachments.show', ['ticket' => $ticket->id, 'media' => $media?->id]);

    $this->actingAs($owner)->get($url)->assertOk();

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->get($url)->assertOk();

    $stranger = User::factory()->customer()->create();
    $this->actingAs($stranger)->get($url)->assertForbidden();

    $this->post(route('logout'));
    $this->get($url)->assertRedirect(route('login'));
});

test('a media id from another ticket 404s even for the owner', function () {
    Storage::fake('local');

    [$ticket, $owner] = SupportFixtures::openTicket();
    [$foreign, $foreignOwner] = SupportFixtures::openTicket();

    $this->actingAs($foreignOwner)->post(route('support.tickets.reply', $foreign), [
        'body' => 'Foreign attachment.',
        'attachments' => [UploadedFile::fake()->image('other.png')],
    ])->assertSessionHasNoErrors();

    $media = $foreign->messages()->latest('id')->first()?->getFirstMedia('attachments');

    // The owner of $ticket passes the policy for their own ticket, but the
    // media hangs off $foreign — the cross-check must 404.
    $this->actingAs($owner)
        ->get(route('support.attachments.show', ['ticket' => $ticket->id, 'media' => $media?->id]))
        ->assertNotFound();
});

test('attachment count and type are validated from settings', function () {
    Storage::fake('local');

    [$ticket, $owner] = SupportFixtures::openTicket();

    app(SettingsRegistry::class)->set('support.max_attachments', 1);

    $this->actingAs($owner)->post(route('support.tickets.reply', $ticket), [
        'body' => 'Too many files.',
        'attachments' => [
            UploadedFile::fake()->image('one.png'),
            UploadedFile::fake()->image('two.png'),
        ],
    ])->assertSessionHasErrors('attachments');

    $this->actingAs($owner)->post(route('support.tickets.reply', $ticket), [
        'body' => 'Wrong type.',
        'attachments' => [UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload')],
    ])->assertSessionHasErrors('attachments.0');

    expect($ticket->messages()->count())->toBe(1);
});
