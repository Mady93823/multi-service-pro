<?php

namespace App\Domain\Media\Actions;

use App\Domain\Media\StorageConfigurator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * The admin's "Test connection" button (D40): write a probe file to the
 * configured bucket, read it back, delete it. Synchronous on purpose — a bad
 * endpoint must be a form error on the screen that saved it, not a worker
 * log (M23's test-send rule).
 */
class TestStorageConnection
{
    private const PROBE_CONTENT = 'storage connection probe';

    public function __construct(private readonly StorageConfigurator $storage) {}

    public function handle(): void
    {
        if (! $this->storage->isConfigured()) {
            throw ValidationException::withMessages([
                'storage' => __('Save your bucket details first — the test runs against the stored settings.'),
            ]);
        }

        $path = 'probe-'.Str::lower(Str::random(12)).'.txt';

        try {
            $disk = Storage::build($this->storage->diskConfig(throw: true));

            $disk->put($path, self::PROBE_CONTENT);

            if ($disk->get($path) !== self::PROBE_CONTENT) {
                throw new RuntimeException('The probe file did not read back intact.');
            }

            $disk->delete($path);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw ValidationException::withMessages([
                'storage' => __('Could not use the bucket: :message', ['message' => $e->getMessage()]),
            ]);
        }
    }
}
