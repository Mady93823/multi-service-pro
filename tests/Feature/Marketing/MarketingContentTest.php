<?php

use App\Domain\Marketing\Enums\PopupAudience;
use App\Models\MediaAsset;
use App\Models\Popup;
use App\Models\Review;
use App\Models\Sponsor;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

function marketingAdmin(): User
{
    return User::factory()->admin()->create();
}

/** Test helpers are global in Pest — hence the prefix (MediaLibraryTest owns `libraryAsset`). */
function marketingAsset(): MediaAsset
{
    Storage::fake('public');

    $asset = MediaAsset::query()->create(['name' => 'logo.png']);
    $asset->addMedia(UploadedFile::fake()->image('logo.png'))->toMediaCollection(MediaAsset::COLLECTION);

    return $asset;
}

beforeEach(function () {
    // The seeder ships demo testimonials — scope, never count globally.
    Testimonial::query()->delete();
    Sponsor::query()->delete();
    Popup::query()->delete();
});

it('shows active testimonials and sponsors on the storefront', function () {
    Testimonial::factory()->create(['name' => 'Ananya', 'quote' => 'Brilliant service.']);
    Testimonial::factory()->create(['name' => 'Hidden One', 'is_active' => false]);
    Sponsor::factory()->create(['name' => 'Acme Corp']);
    Sponsor::factory()->create(['name' => 'Dormant Ltd', 'is_active' => false]);

    // Since M20 the home page is blocks; the testimonial and sponsor sections
    // are two of them (seeded on the reserved `home` page).
    /** @var array{props: array{blocks: list<array<string, mixed>>}} $page */
    $page = $this->get(route('home'))->viewData('page');
    $blocks = collect($page['props']['blocks']);

    expect($blocks->firstWhere('type', 'testimonials')['props']['testimonials'])
        ->toHaveCount(1)
        ->and($blocks->firstWhere('type', 'testimonials')['props']['testimonials'][0]['name'])->toBe('Ananya')
        ->and($blocks->firstWhere('type', 'sponsors')['props']['sponsors'])->toHaveCount(1)
        ->and($blocks->firstWhere('type', 'sponsors')['props']['sponsors'][0]['name'])->toBe('Acme Corp');
});

it('promotes a review to a testimonial once, copying the quote', function () {
    $review = Review::factory()->create(['rating' => 5, 'comment' => 'Spotless work.']);
    $admin = marketingAdmin();

    $this->actingAs($admin)->post(route('admin.reviews.promote', $review))->assertRedirect();
    $this->actingAs($admin)->post(route('admin.reviews.promote', $review))->assertRedirect();

    $testimonials = Testimonial::query()->where('review_id', $review->id)->get();

    expect($testimonials)->toHaveCount(1)
        ->and($testimonials->first()->quote)->toBe('Spotless work.');

    // The quote is a copy: editing the review afterwards must not rewrite the
    // storefront copy behind the admin's back.
    $review->update(['comment' => 'Actually terrible.']);

    expect($testimonials->first()->refresh()->quote)->toBe('Spotless work.');
});

it('refuses to promote a hidden review', function () {
    $review = Review::factory()->create(['is_hidden' => true, 'hidden_reason' => 'abuse']);

    $this->actingAs(marketingAdmin())
        ->post(route('admin.reviews.promote', $review))
        ->assertRedirect();

    expect(Testimonial::query()->where('review_id', $review->id)->exists())->toBeFalse();
});

it('creates a sponsor from a library asset and refuses one without a logo', function () {
    $admin = marketingAdmin();
    $asset = marketingAsset();

    $this->actingAs($admin)
        ->post(route('admin.sponsors.store'), [
            'name' => 'Acme',
            'link_url' => 'https://acme.test',
            'sort_order' => 1,
            'is_active' => true,
            'media_asset_id' => $asset->id,
        ])
        ->assertRedirect();

    $sponsor = Sponsor::query()->where('name', 'Acme')->firstOrFail();

    // Picking copies the file into the sponsor's own collection (D29).
    expect($sponsor->getFirstMedia('logo'))->not->toBeNull();

    $this->actingAs($admin)
        ->post(route('admin.sponsors.store'), ['name' => 'Logoless', 'sort_order' => 1])
        ->assertSessionHasErrors('media_asset_id');
});

it('refuses a script-sink link on a sponsor', function () {
    $asset = marketingAsset();

    $this->actingAs(marketingAdmin())
        ->post(route('admin.sponsors.store'), [
            'name' => 'Gotcha',
            'link_url' => 'javascript:alert(1)',
            'media_asset_id' => $asset->id,
        ])
        ->assertSessionHasErrors('link_url');
});

it('shows the live popup to its audience only, as sanitized HTML', function () {
    Popup::factory()->create([
        'title' => 'Guests only',
        'body' => '**Bold** <script>alert(1)</script>',
        'audience' => PopupAudience::Guests->value,
    ]);

    $this->get(route('home'))
        ->assertInertia(fn (AssertableInertia $inertia) => $inertia
            ->where('site.popup.title', 'Guests only')
            ->where('site.popup.html', "<p><strong>Bold</strong> alert(1)</p>\n"));

    $this->actingAs(User::factory()->customer()->create())
        ->get(route('home'))
        ->assertInertia(fn (AssertableInertia $inertia) => $inertia->where('site.popup', null));
});

it('never shows a popup outside its schedule window', function () {
    Popup::factory()->create(['title' => 'Expired', 'ends_at' => now()->subDay()]);
    Popup::factory()->create(['title' => 'Not yet', 'starts_at' => now()->addWeek()]);

    $this->get(route('home'))
        ->assertInertia(fn (AssertableInertia $inertia) => $inertia->where('site.popup', null));
});
