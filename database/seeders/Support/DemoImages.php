<?php

namespace Database\Seeders\Support;

use App\Domain\Media\Actions\AttachLibraryAsset;
use App\Domain\Media\Actions\UploadMediaAsset;
use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\HasMedia;

/**
 * The demo's photographs, and the one place they enter the application.
 *
 * The files live in `database/seeders/assets/` and are **committed** — a demo
 * that downloads its own pictures is a demo that fails on the client's wifi, in
 * front of the client. They are real photos under the Unsplash licence (free for
 * commercial use, no attribution required); see `assets/README.md`.
 *
 * Every one of them is pushed through `UploadMediaAsset`, the real library
 * entry point (M18/D29), rather than being shoved into each consumer's
 * collection directly. Two reasons: the admin **Media** screen then shows the
 * whole inventory the way it will on a live site, and picking an asset copies
 * the file (D29) exactly as the admin UI does — so the demo exercises the real
 * path instead of a seeder-only shortcut that could rot.
 */
class DemoImages
{
    /** @var array<string, MediaAsset> */
    private array $assets = [];

    public function __construct(
        private readonly UploadMediaAsset $upload,
        private readonly AttachLibraryAsset $attach,
    ) {}

    /** Attach a library photo to any media-holding model. */
    public function attach(HasMedia&Model $model, string $key, string $collection): void
    {
        $model->clearMediaCollection($collection);

        $this->attach->handle($model, $this->asset($key), $collection);
    }

    /**
     * A category's picture is a plain path on the public disk (`categories.image_path`),
     * not a media-library collection — it predates M18 and is the one consumer
     * that never grew one.
     */
    public function publicCopy(string $key, string $directory): string
    {
        $source = $this->path($key);
        $target = storage_path('app/public/'.$directory);

        if (! is_dir($target)) {
            mkdir($target, 0755, true);
        }

        $filename = $key.'.jpg';
        copy($source, $target.'/'.$filename);

        return $directory.'/'.$filename;
    }

    /** Idempotent: the same photo used by four services is one library asset. */
    public function asset(string $key): MediaAsset
    {
        if (isset($this->assets[$key])) {
            return $this->assets[$key];
        }

        $existing = MediaAsset::query()->where('name', $key.'.jpg')->first();

        if ($existing !== null) {
            return $this->assets[$key] = $existing;
        }

        // Seed from a COPY, never from the file in `database/seeders/assets`.
        // Medialibrary *moves* the file it is given — that is correct for a
        // browser upload sitting in PHP's temp dir, and catastrophic here: the
        // first `demo:seed` would carry the repository's own photographs off
        // into `storage/`, and the second would die with "asset is missing".
        $temp = (string) tempnam(sys_get_temp_dir(), 'demo');
        copy($this->path($key), $temp);

        // `test: true` skips the is_uploaded_file() check — this file came from
        // the repository, not from a browser, and that is the only difference.
        $file = new UploadedFile($temp, $key.'.jpg', 'image/jpeg', null, true);

        $asset = $this->upload->handle(null, $file);

        // Medialibrary has already taken the copy; this is only for the paths it
        // decided not to move (a failure part-way through).
        @unlink($temp);

        return $this->assets[$key] = $asset;
    }

    private function path(string $key): string
    {
        $path = database_path('seeders/assets/'.$key.'.jpg');

        if (! is_file($path)) {
            throw new \RuntimeException("Demo asset [{$key}.jpg] is missing from database/seeders/assets.");
        }

        return $path;
    }
}
