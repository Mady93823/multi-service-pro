<?php

namespace App\Domain\Media;

use App\Domain\Settings\SettingsRegistry;

/**
 * Media storage lives in settings, not `.env` (D40, the MailConfigurator
 * idiom). A buyer points the platform at Cloudflare R2 / any S3-compatible
 * bucket from the browser; this pushes that choice onto medialibrary's
 * default disk at boot.
 *
 * Only the *public* media disk moves. Private collections (KYC, payment
 * proofs, review photos, ticket attachments, problem photos) pin `local`
 * explicitly — they are policy-gated files served through the app, and a
 * public bucket is the opposite of that.
 *
 * Half-configured degrades to local (D35): a missing bucket must never break
 * an upload, and switching drivers never rewrites history — every media row
 * remembers the disk it was written to, so old files keep serving from local
 * while new uploads land in the bucket.
 */
class StorageConfigurator
{
    public const DISK = 's3media';

    public function __construct(private readonly SettingsRegistry $settings) {}

    public function isConfigured(): bool
    {
        return $this->settings->string('storage.driver', 'local') === 's3'
            && $this->settings->string('storage.s3_key') !== ''
            && $this->settings->string('storage.s3_secret') !== ''
            && $this->settings->string('storage.s3_bucket') !== ''
            && $this->settings->string('storage.s3_endpoint') !== '';
    }

    public function apply(): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        config([
            'filesystems.disks.'.self::DISK => $this->diskConfig(),
            'media-library.disk_name' => self::DISK,
        ]);
    }

    /**
     * The disk definition the settings describe. `$throw` is true for the
     * admin's connection probe — a test that swallows its own failure tests
     * nothing — and false at runtime, where a flaky bucket must degrade to a
     * broken image, never a 500 (M07's rule).
     *
     * @return array<string, mixed>
     */
    public function diskConfig(bool $throw = false): array
    {
        $url = $this->settings->string('storage.s3_url');
        $region = $this->settings->string('storage.s3_region');

        return [
            'driver' => 's3',
            'key' => $this->settings->string('storage.s3_key'),
            'secret' => $this->settings->string('storage.s3_secret'),
            'region' => $region !== '' ? $region : 'auto',
            'bucket' => $this->settings->string('storage.s3_bucket'),
            'endpoint' => $this->settings->string('storage.s3_endpoint'),
            'url' => $url !== '' ? $url : null,
            'use_path_style_endpoint' => $this->settings->boolean('storage.s3_path_style', false),
            'throw' => $throw,
        ];
    }
}
