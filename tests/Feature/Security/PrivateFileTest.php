<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route as RouteInstance;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\Support\SupportFixtures;

test('the framework publishes no route into the private disk', function () {
    // The starter ships `'serve' => true` on the local disk, which registers
    // `GET /storage/{path}` *and* `PUT /storage/{path}` against the folder that
    // holds KYC documents, bank receipts and ticket attachments. Both are gated
    // by a signature instead of by our policies — a second door that no
    // BookingPolicy or SupportTicketPolicy has any say over. It is closed.
    $names = array_map(
        fn (RouteInstance $route): ?string => $route->getName(),
        Route::getRoutes()->getRoutes(),
    );

    expect($names)
        ->not->toContain('storage.local')
        ->not->toContain('storage.local.upload');
});

/**
 * The policies decide *who* may read a private file. These decide *what the
 * browser may do with it*, which is a different question with a worse answer if
 * we get it wrong: a file served inline runs in our own origin. KYC documents,
 * bank receipts and ticket attachments all accept PDFs (`UploadRules::document`),
 * and a PDF is a script host.
 */
test('a document is served as a download, never inline', function () {
    Storage::fake('local');

    [$ticket, $owner] = SupportFixtures::openTicket();

    $this->actingAs($owner)->post(route('support.tickets.reply', $ticket), [
        'body' => 'Receipt attached.',
        'attachments' => [UploadedFile::fake()->create('receipt.pdf', 40, 'application/pdf')],
    ])->assertSessionHasNoErrors();

    $media = $ticket->messages()->latest('id')->first()?->getFirstMedia('attachments');

    $response = $this->actingAs($owner)
        ->get(route('support.attachments.show', ['ticket' => $ticket->id, 'media' => $media?->id]))
        ->assertOk()
        // Without this a browser may decide our application/pdf was really HTML
        // and run it — the second half of the same hole.
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->headers->get('Content-Disposition'))
        ->toStartWith('attachment')
        ->toContain('receipt.pdf');
});

test('an image is still served inline, or the pages that show it break', function () {
    Storage::fake('local');

    [$ticket, $owner] = SupportFixtures::openTicket();

    $this->actingAs($owner)->post(route('support.tickets.reply', $ticket), [
        'body' => 'Photo attached.',
        'attachments' => [UploadedFile::fake()->image('issue.png')],
    ])->assertSessionHasNoErrors();

    $media = $ticket->messages()->latest('id')->first()?->getFirstMedia('attachments');

    $response = $this->actingAs($owner)
        ->get(route('support.attachments.show', ['ticket' => $ticket->id, 'media' => $media?->id]))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->headers->get('Content-Disposition'))->toStartWith('inline');
});
