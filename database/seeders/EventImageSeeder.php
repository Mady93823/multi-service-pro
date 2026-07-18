<?php

namespace Database\Seeders;

use App\Domain\Media\Actions\AttachLibraryAsset;
use App\Models\Category;
use App\Models\Service;
use Database\Seeders\Support\EventArt;
use Illuminate\Database\Seeder;

/**
 * Dresses the Event Management tree (CatalogSeeder) with drawn covers: category
 * cards get an `image_path`, event services get a gallery image through the
 * real media-library path (so the admin Media screen lists them, D29).
 *
 * Idempotent — an image already in place is left alone, so re-seeding never
 * duplicates library rows or overwrites a cover the admin replaced.
 */
class EventImageSeeder extends Seeder
{
    /** Category slug (CatalogSeeder) → drawn scene. */
    private const CATEGORY_SCENES = [
        'event-management' => 'celebration',
        'wedding-marriage' => 'wedding',
        'birthday-parties' => 'birthday',
        'kitty-parties-get-togethers' => 'kitty',
    ];

    /** Service slug (CatalogSeeder) → drawn scene. */
    private const SERVICE_SCENES = [
        'wedding-decoration-package' => 'wedding',
        'wedding-catering-per-100-guests' => 'celebration',
        'birthday-party-setup' => 'birthday',
        'kitty-party-hosting' => 'kitty',
    ];

    public function run(): void
    {
        // The test DB seeds on every run and parallel workers share storage/ —
        // 800 tests do not need pictures (the lang/ landmine, applied to disk).
        if (app()->runningUnitTests()) {
            return;
        }

        // Covers only decorate; a host without GD must still install (D35).
        if (! extension_loaded('gd')) {
            return;
        }

        $art = app(EventArt::class);
        $attach = app(AttachLibraryAsset::class);

        foreach (self::CATEGORY_SCENES as $slug => $scene) {
            $category = Category::query()->where('slug', $slug)->first();

            if ($category === null || $category->image_path !== null) {
                continue;
            }

            $category->update(['image_path' => $art->categoryCover($scene)]);
        }

        foreach (self::SERVICE_SCENES as $slug => $scene) {
            $service = Service::query()->where('slug', $slug)->first();

            if ($service === null || $service->getFirstMedia('images') !== null) {
                continue;
            }

            $attach->handle($service, $art->asset($scene), 'images');
        }
    }
}
