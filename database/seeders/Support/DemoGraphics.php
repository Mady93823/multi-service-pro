<?php

namespace Database\Seeders\Support;

use App\Domain\Media\Actions\UploadMediaAsset;
use App\Models\MediaAsset;
use Illuminate\Http\UploadedFile;

/**
 * The two pictures the demo cannot borrow: a customer's face and a sponsor's
 * logo.
 *
 * A stock photo of a stranger presented as "Priya, Indomitable Customer" is a
 * fabricated endorsement attached to a real person's face, and a real company's
 * logo on a sponsors row is somebody else's trademark. Neither belongs in a demo
 * that will be screen-shared, so both are drawn here instead: an initials disc
 * and a wordmark, in the brand colour. They read as deliberate design rather
 * than as a missing image, which is the whole point.
 */
class DemoGraphics
{
    /** Brand-ish palette, picked to sit next to the default primary. */
    private const COLOURS = [
        [79, 70, 229],   // indigo
        [13, 148, 136],  // teal
        [219, 39, 119],  // pink
        [217, 119, 6],   // amber
        [4, 120, 87],    // emerald
        [124, 58, 237],  // violet
    ];

    public function __construct(private readonly UploadMediaAsset $upload) {}

    public function avatar(string $name): MediaAsset
    {
        return $this->remember('avatar-'.md5($name), fn (): string => $this->drawAvatar($name));
    }

    public function wordmark(string $name): MediaAsset
    {
        return $this->remember('logo-'.md5($name), fn (): string => $this->drawWordmark($name));
    }

    private function remember(string $key, callable $draw): MediaAsset
    {
        $existing = MediaAsset::query()->where('name', $key.'.png')->first();

        if ($existing !== null) {
            return $existing;
        }

        $path = $draw();
        $asset = $this->upload->handle(null, new UploadedFile($path, $key.'.png', 'image/png', null, true));

        @unlink($path);

        return $asset;
    }

    private function drawAvatar(string $name): string
    {
        $size = 400;
        $image = imagecreatetruecolor($size, $size);
        [$r, $g, $b] = self::COLOURS[abs(crc32($name)) % count(self::COLOURS)];

        imagefill($image, 0, 0, (int) imagecolorallocate($image, $r, $g, $b));

        $initials = $this->initials($name);
        $white = (int) imagecolorallocate($image, 255, 255, 255);

        // The bundled GD font is small, so it is scaled up rather than blurred:
        // draw once, then copy the glyphs across at 6×.
        $glyphs = imagecreatetruecolor(40, 20);
        imagefill($glyphs, 0, 0, (int) imagecolorallocate($glyphs, $r, $g, $b));
        imagestring($glyphs, 5, 6, 2, $initials, (int) imagecolorallocate($glyphs, 255, 255, 255));
        imagecopyresampled($image, $glyphs, 100, 150, 0, 0, 200, 100, 40, 20);
        imagedestroy($glyphs);

        unset($white);

        return $this->write($image);
    }

    private function drawWordmark(string $name): string
    {
        $width = 480;
        $height = 160;
        $image = imagecreatetruecolor($width, $height);

        imagefill($image, 0, 0, (int) imagecolorallocate($image, 255, 255, 255));

        [$r, $g, $b] = self::COLOURS[abs(crc32($name)) % count(self::COLOURS)];

        $glyphs = imagecreatetruecolor(180, 20);
        imagefill($glyphs, 0, 0, (int) imagecolorallocate($glyphs, 255, 255, 255));
        imagestring($glyphs, 5, 2, 2, strtoupper($name), (int) imagecolorallocate($glyphs, $r, $g, $b));
        imagecopyresampled($image, $glyphs, 40, 55, 0, 0, 400, 50, 180, 20);
        imagedestroy($glyphs);

        return $this->write($image);
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $letters = array_map(fn (string $part): string => strtoupper(substr($part, 0, 1)), array_slice($parts, 0, 2));

        return implode('', $letters) ?: 'U';
    }

    /** @param \GdImage $image */
    private function write($image): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'demo');

        imagepng($image, $path);
        imagedestroy($image);

        return $path;
    }
}
