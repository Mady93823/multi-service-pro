<?php

use App\Domain\Media\Actions\AttachLibraryAsset;
use App\Domain\Media\Actions\DeleteMediaAsset;
use App\Domain\Media\Actions\UploadMediaAsset;
use App\Models\Banner;
use App\Models\MediaAsset;
use App\Models\SupportTicketMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
});

function libraryAsset(?User $uploader = null, string $name = 'hero.jpg'): MediaAsset
{
    return app(UploadMediaAsset::class)->handle(
        $uploader ?? User::factory()->admin()->create(),
        UploadedFile::fake()->image($name, 1200, 600),
    );
}

test('guests and non-admins cannot reach the library', function () {
    $this->get('/admin/media')->assertRedirect('/login');

    $this->actingAs(User::factory()->customer()->create())->get('/admin/media')->assertForbidden();
    $this->actingAs(User::factory()->provider()->create())->post('/admin/media', [])->assertForbidden();
    $this->actingAs(User::factory()->customer()->create())->get(route('admin.media.picker'))->assertForbidden();
});

test('an admin uploads files into the library on the public disk', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.media.store'), [
            'files' => [
                UploadedFile::fake()->image('one.jpg'),
                UploadedFile::fake()->image('two.png'),
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $assets = MediaAsset::query()->get();

    expect($assets)->toHaveCount(2)
        ->and($assets->first()->uploaded_by)->toBe($admin->id);

    $file = $assets->first()->file();

    expect($file)->not->toBeNull()
        ->and($file->disk)->toBe('public');

    Storage::disk('public')->assertExists($file->getPathRelativeToRoot());
});

test('the library rejects what it cannot safely serve', function () {
    $admin = User::factory()->admin()->create();

    // An SVG is a script container, and the library's images are rendered
    // inline on the storefront.
    $this->actingAs($admin)
        ->post(route('admin.media.store'), ['files' => [UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml')]])
        ->assertSessionHasErrors('files.0');

    $this->actingAs($admin)
        ->post(route('admin.media.store'), ['files' => [UploadedFile::fake()->create('payload.exe', 10)]])
        ->assertSessionHasErrors('files.0');

    $this->actingAs($admin)
        ->post(route('admin.media.store'), [])
        ->assertSessionHasErrors('files');

    expect(MediaAsset::query()->count())->toBe(0);
});

test('the manager lists the library and never private-disk uploads', function () {
    $admin = User::factory()->admin()->create();
    libraryAsset($admin, 'brochure.jpg');

    // A ticket attachment: same medialibrary, private disk, customer data.
    $message = SupportTicketMessage::factory()->create();
    $message->addMedia(UploadedFile::fake()->image('id-card.jpg'))->toMediaCollection('attachments');

    $this->actingAs($admin)
        ->get('/admin/media')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/media/index')
            ->where('assets.meta.total', 1)
            ->where('assets.data.0.name', 'brochure.jpg')
            ->where('stats.files', 1))
        // The private file must not appear anywhere in the payload.
        ->assertDontSee('id-card');
});

test('picking an asset copies it, leaving the library file intact', function () {
    $asset = libraryAsset();
    $banner = Banner::factory()->create();

    app(AttachLibraryAsset::class)->handle($banner, $asset, 'image');

    $copy = $banner->getFirstMedia('image');
    $original = $asset->fresh()->file();

    expect($copy)->not->toBeNull()
        ->and($original)->not->toBeNull()
        // Two rows, two files — a shared row would blank the banner the day the
        // library is tidied (D29).
        ->and($copy->id)->not->toBe($original->id)
        ->and($copy->getCustomProperty(MediaAsset::USAGE_PROPERTY))->toBe($asset->id)
        ->and($asset->usageCount())->toBe(1);

    Storage::disk('public')->assertExists($original->getPathRelativeToRoot());
});

test('a banner can be created from a library asset, and an upload joins the library', function () {
    $admin = User::factory()->admin()->create();
    $asset = libraryAsset($admin);

    $this->actingAs($admin)
        ->post(route('admin.banners.store'), [
            'title' => 'Picked from library',
            'placement' => 'home_hero',
            'is_active' => true,
            'media_asset_id' => $asset->id,
        ])
        ->assertRedirect(route('admin.banners.index'))
        ->assertSessionHasNoErrors();

    $banner = Banner::query()->where('title', 'Picked from library')->sole();

    expect($banner->getMedia('image'))->toHaveCount(1)
        ->and($asset->usageCount())->toBe(1);

    // Uploading on the banner form still works — and the file lands in the
    // library, so the manager knows about every image on the site.
    $before = MediaAsset::query()->count();

    $this->actingAs($admin)
        ->post(route('admin.banners.store'), [
            'title' => 'Uploaded directly',
            'placement' => 'home_strip',
            'is_active' => true,
            'image' => UploadedFile::fake()->image('fresh.jpg', 1600, 500),
        ])
        ->assertSessionHasNoErrors();

    expect(MediaAsset::query()->count())->toBe($before + 1);
});

test('a banner needs a picture from one source or the other', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.banners.store'), [
            'title' => 'No picture',
            'placement' => 'home_hero',
            'is_active' => true,
        ])
        ->assertSessionHasErrors('image');
});

test('an asset in use cannot be deleted', function () {
    $admin = User::factory()->admin()->create();
    $asset = libraryAsset($admin);
    app(AttachLibraryAsset::class)->handle(Banner::factory()->create(), $asset, 'image');

    $this->actingAs($admin)
        ->delete(route('admin.media.destroy', $asset))
        ->assertSessionHasErrors('asset');

    expect(MediaAsset::query()->whereKey($asset->id)->exists())->toBeTrue();

    expect(fn () => app(DeleteMediaAsset::class)->handle($asset))
        ->toThrow(ValidationException::class);
});

test('an unused asset is deleted with its file', function () {
    $admin = User::factory()->admin()->create();
    $asset = libraryAsset($admin);
    $path = $asset->file()->getPathRelativeToRoot();

    $this->actingAs($admin)
        ->delete(route('admin.media.destroy', $asset))
        ->assertSessionHas('success');

    expect(MediaAsset::query()->whereKey($asset->id)->exists())->toBeFalse();
    Storage::disk('public')->assertMissing($path);
});

test('the picker answers JSON and can upload without losing the form behind it', function () {
    $admin = User::factory()->admin()->create();
    libraryAsset($admin, 'existing.jpg');

    $this->actingAs($admin)
        ->getJson(route('admin.media.picker', ['search' => 'existing']))
        ->assertOk()
        ->assertJsonPath('data.0.name', 'existing.jpg');

    // A search that matches nothing returns an empty list, not everything.
    $this->actingAs($admin)
        ->getJson(route('admin.media.picker', ['search' => 'nothing-like-this']))
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->actingAs($admin)
        ->postJson(route('admin.media.picker.store'), ['files' => [UploadedFile::fake()->image('from-dialog.jpg')]])
        ->assertCreated()
        ->assertJsonPath('data.0.name', 'from-dialog.jpg');

    expect(MediaAsset::query()->where('name', 'from-dialog.jpg')->exists())->toBeTrue();
});

test('prune lists unused files and only deletes them when forced', function () {
    $admin = User::factory()->admin()->create();

    $used = libraryAsset($admin, 'used.jpg');
    app(AttachLibraryAsset::class)->handle(Banner::factory()->create(), $used, 'image');

    $unused = libraryAsset($admin, 'unused.jpg');
    // Both assets are new; the default 30-day window must protect them.
    $this->artisan('media:prune-library')
        ->expectsOutput('Nothing to prune.')
        ->assertSuccessful();

    expect(MediaAsset::query()->count())->toBe(2);

    // Dry run is the default even when the window has passed.
    $this->artisan('media:prune-library --days=0')->assertSuccessful();

    expect(MediaAsset::query()->whereKey($unused->id)->exists())->toBeTrue();

    $this->artisan('media:prune-library --days=0 --force')->assertSuccessful();

    expect(MediaAsset::query()->whereKey($unused->id)->exists())->toBeFalse()
        // The used one survives, force or not.
        ->and(MediaAsset::query()->whereKey($used->id)->exists())->toBeTrue();
});
