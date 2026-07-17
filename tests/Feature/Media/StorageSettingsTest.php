<?php

use App\Domain\Media\Actions\UploadMediaAsset;
use App\Domain\Media\StorageConfigurator;
use App\Domain\Settings\SettingsRegistry;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\SettingsFixtures;

/**
 * Admin-configurable media storage (D40). The rule under every test here:
 * switching storage affects NEW uploads only — a media row remembers the disk
 * it was written to — and anything less than a full S3 configuration degrades
 * to the local public disk (D35).
 */
function storageS3Settings(): void
{
    $settings = app(SettingsRegistry::class);
    $settings->set('storage.driver', 's3');
    $settings->set('storage.s3_key', 'r2-key');
    $settings->set('storage.s3_secret', 'r2-secret');
    $settings->set('storage.s3_bucket', 'media-bucket');
    $settings->set('storage.s3_endpoint', 'https://account.r2.cloudflarestorage.com');
    $settings->set('storage.s3_url', 'https://pub-abc.r2.dev');
}

test('a fresh install stores media on the local public disk', function () {
    app(StorageConfigurator::class)->apply();

    expect(config('media-library.disk_name'))->toBe('public')
        ->and(app(StorageConfigurator::class)->isConfigured())->toBeFalse();
});

test('a fully configured bucket becomes the default media disk', function () {
    storageS3Settings();

    app(StorageConfigurator::class)->apply();

    expect(config('media-library.disk_name'))->toBe(StorageConfigurator::DISK)
        ->and(config('filesystems.disks.'.StorageConfigurator::DISK.'.bucket'))->toBe('media-bucket')
        ->and(config('filesystems.disks.'.StorageConfigurator::DISK.'.url'))->toBe('https://pub-abc.r2.dev')
        // Runtime never throws — a flaky bucket degrades to a broken image.
        ->and(config('filesystems.disks.'.StorageConfigurator::DISK.'.throw'))->toBeFalse();
});

test('a half-configured bucket degrades to local instead of breaking uploads', function () {
    $settings = app(SettingsRegistry::class);
    $settings->set('storage.driver', 's3');
    $settings->set('storage.s3_key', 'r2-key');
    // No secret, no bucket, no endpoint.

    app(StorageConfigurator::class)->apply();

    expect(config('media-library.disk_name'))->toBe('public');
});

test('a new upload lands on the active bucket', function () {
    storageS3Settings();
    app(StorageConfigurator::class)->apply();
    Storage::fake(StorageConfigurator::DISK);

    $asset = app(UploadMediaAsset::class)->handle(
        User::factory()->admin()->create(),
        UploadedFile::fake()->image('photo.jpg', 600, 400),
    );

    expect($asset->media()->first()?->disk)->toBe(StorageConfigurator::DISK);
});

test('switching storage strands nothing: old media keep the disk they were written to', function () {
    Storage::fake('public');

    // Upload while local...
    $asset = app(UploadMediaAsset::class)->handle(
        User::factory()->admin()->create(),
        UploadedFile::fake()->image('old.jpg', 600, 400),
    );

    $before = $asset->media()->first()?->disk;

    // ...then the admin points new uploads at a bucket.
    storageS3Settings();
    app(StorageConfigurator::class)->apply();

    expect($before)->toBe('public')
        ->and($asset->media()->first()?->disk)->toBe('public');
});

test('the storage settings screen saves and keeps the secret write-only', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put(route('admin.settings.update', 'storage'), SettingsFixtures::payload('storage', [
            'driver' => 's3',
            's3_key' => 'r2-key',
            's3_secret' => 'r2-secret',
            's3_bucket' => 'media-bucket',
            's3_endpoint' => 'https://account.r2.cloudflarestorage.com',
        ]))
        ->assertRedirect();

    $settings = app(SettingsRegistry::class);

    expect($settings->string('storage.driver'))->toBe('s3')
        ->and($settings->string('storage.s3_secret'))->toBe('r2-secret');

    // A later save with a blank secret keeps the stored one (M08's rule).
    $this->actingAs($admin)
        ->put(route('admin.settings.update', 'storage'), SettingsFixtures::payload('storage', [
            'driver' => 's3',
            's3_key' => 'r2-key-2',
            's3_secret' => '',
        ]))
        ->assertRedirect();

    expect($settings->string('storage.s3_secret'))->toBe('r2-secret')
        ->and($settings->string('storage.s3_key'))->toBe('r2-key-2');
});

test('the storage probe refuses to run until the bucket is fully configured', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->from(route('admin.settings.edit', 'storage'))
        ->post(route('admin.settings.storage.test'))
        ->assertRedirect(route('admin.settings.edit', 'storage'))
        ->assertSessionHasErrors('storage');
});
