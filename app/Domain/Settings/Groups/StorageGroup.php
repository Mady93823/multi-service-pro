<?php

namespace App\Domain\Settings\Groups;

/**
 * Where uploaded media lives (D40): the local public disk, or any
 * S3-compatible bucket — Cloudflare R2, AWS S3, DigitalOcean Spaces, MinIO.
 * Private files (KYC, proofs, attachments) never move; only the public media
 * disk is switchable, and switching affects new uploads only.
 */
class StorageGroup extends SettingsGroup
{
    /** Write-only, M08's rule: the screen gets `s3_secret_set`, never the value. */
    private const SECRETS = [
        's3_secret' => 'storage.s3_secret',
    ];

    public function key(): string
    {
        return 'storage';
    }

    public function label(): string
    {
        return __('Storage');
    }

    public function description(): string
    {
        return __('Keep uploaded images on this server, or move new uploads to an S3-compatible bucket such as Cloudflare R2.');
    }

    public function keys(): array
    {
        return [
            'storage.driver',
            'storage.s3_key',
            'storage.s3_secret',
            'storage.s3_bucket',
            'storage.s3_region',
            'storage.s3_endpoint',
            'storage.s3_url',
            'storage.s3_path_style',
        ];
    }

    public function rules(array $input): array
    {
        return [
            'driver' => ['required', 'in:local,s3'],
            's3_key' => ['nullable', 'string', 'max:191'],
            's3_bucket' => ['nullable', 'string', 'max:191'],
            's3_region' => ['nullable', 'string', 'max:64'],
            's3_endpoint' => ['nullable', 'url:http,https', 'max:255'],
            's3_url' => ['nullable', 'url:http,https', 'max:255'],
            's3_path_style' => ['boolean'],
            // Write-only. Blank keeps the stored secret; remove_* erases it.
            's3_secret' => ['nullable', 'string', 'max:191'],
            'remove_s3_secret' => ['boolean'],
        ];
    }

    public function values(): array
    {
        $values = [
            'driver' => $this->settings->string('storage.driver', 'local'),
            's3_key' => $this->settings->string('storage.s3_key'),
            's3_bucket' => $this->settings->string('storage.s3_bucket'),
            's3_region' => $this->settings->string('storage.s3_region', 'auto'),
            's3_endpoint' => $this->settings->string('storage.s3_endpoint'),
            's3_url' => $this->settings->string('storage.s3_url'),
            's3_path_style' => $this->settings->boolean('storage.s3_path_style', false),
        ];

        foreach (self::SECRETS as $field => $settingKey) {
            $values[$field.'_set'] = $this->settings->string($settingKey) !== '';
        }

        return $values;
    }

    public function apply(array $data, array $files = []): void
    {
        $this->settings->set('storage.driver', $data['driver']);
        $this->settings->set('storage.s3_key', $data['s3_key'] ?? null);
        $this->settings->set('storage.s3_bucket', $data['s3_bucket'] ?? null);
        $this->settings->set('storage.s3_region', $data['s3_region'] ?? null);
        $this->settings->set('storage.s3_endpoint', $data['s3_endpoint'] ?? null);
        $this->settings->set('storage.s3_url', $data['s3_url'] ?? null);
        $this->settings->set('storage.s3_path_style', $this->toggle($data, 's3_path_style'));

        foreach (self::SECRETS as $field => $settingKey) {
            $submitted = $data[$field] ?? null;

            if ($this->toggle($data, 'remove_'.$field)) {
                $this->settings->set($settingKey, null);
            } elseif (is_string($submitted) && $submitted !== '') {
                $this->settings->set($settingKey, $submitted);
            }
        }
    }
}
