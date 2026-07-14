# Demo photographs

These are the pictures the showcase demo (`php artisan demo:seed`) puts on the storefront.

**They are committed on purpose.** A demo that downloads its own images is a demo that fails on the client's wifi, in front of the client.

## Licence

All photographs are from [Unsplash](https://unsplash.com) and are used under the [Unsplash Licence](https://unsplash.com/license): free to use for commercial and non-commercial purposes, no permission or attribution required.

They are **demo content**, not product assets. A buyer replaces them with their own on day one — nothing in the application code references a filename here.

## Adding one

Drop a `.jpg` in this folder and reference it by its filename (without the extension) from `Database\Seeders\Support\DemoImages`. Keep them raster and reasonably sized; the media library generates its own conversions.
