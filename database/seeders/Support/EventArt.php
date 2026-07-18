<?php

namespace Database\Seeders\Support;

use App\Domain\Media\Actions\UploadMediaAsset;
use App\Models\MediaAsset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Drawn covers for the Event Management catalog (weddings, birthdays, kitty
 * parties). Drawn with GD rather than photographed for the same reason
 * DemoGraphics draws faces and logos: a committed stock photo of somebody's
 * real wedding is somebody's real wedding. These read as deliberate flat
 * illustration — gradient sky, bunting, balloons, confetti — in colours picked
 * to sit next to the default brand palette.
 *
 * Two exits, mirroring how the two consumers store pictures (DemoImages):
 * `asset()` uploads through the real media-library entry point for service
 * galleries; `categoryCover()` writes a plain file on the public disk because
 * `categories.image_path` predates M18 and never grew a collection.
 */
class EventArt
{
    private const WIDTH = 1200;

    private const HEIGHT = 675;

    /** @var array<string, array{array{int, int, int}, array{int, int, int}}> */
    private const SCENES = [
        'celebration' => [[76, 29, 149], [219, 39, 119]],  // deep violet → pink
        'wedding' => [[157, 23, 77], [217, 119, 6]],       // rose → amber
        'birthday' => [[49, 46, 129], [8, 145, 178]],      // indigo → cyan
        'kitty' => [[15, 118, 110], [101, 163, 13]],       // teal → lime
    ];

    public function __construct(private readonly UploadMediaAsset $upload) {}

    /** A library asset for service galleries. Idempotent by asset name. */
    public function asset(string $scene): MediaAsset
    {
        $name = 'event-'.$scene.'.png';

        $existing = MediaAsset::query()->where('name', $name)->first();

        if ($existing !== null) {
            return $existing;
        }

        // Upload from the drawn temp file; medialibrary moves what it is given,
        // which is exactly right for a temp file (the DemoImages lesson does not
        // apply — nothing here is a repository asset).
        $path = $this->draw($scene);
        $asset = $this->upload->handle(null, new UploadedFile($path, $name, 'image/png', null, true));

        @unlink($path);

        return $asset;
    }

    /** A plain public-disk file for `categories.image_path`. Idempotent by path. */
    public function categoryCover(string $scene): string
    {
        $path = 'categories/images/event-'.$scene.'.png';

        if (! Storage::disk('public')->exists($path)) {
            $drawn = $this->draw($scene);
            Storage::disk('public')->put($path, (string) file_get_contents($drawn));
            @unlink($drawn);
        }

        return $path;
    }

    /** Draw the scene to a temp PNG and return its path. */
    private function draw(string $scene): string
    {
        [$from, $to] = self::SCENES[$scene] ?? self::SCENES['celebration'];

        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagealphablending($image, true);

        // Diagonal gradient, one scanline at a time.
        for ($y = 0; $y < self::HEIGHT; $y++) {
            $ratio = $y / self::HEIGHT;
            $colour = (int) imagecolorallocate(
                $image,
                (int) ($from[0] + ($to[0] - $from[0]) * $ratio),
                (int) ($from[1] + ($to[1] - $from[1]) * $ratio),
                (int) ($from[2] + ($to[2] - $from[2]) * $ratio),
            );
            imageline($image, 0, $y, self::WIDTH, $y, $colour);
        }

        $this->bokeh($image, $scene);
        $this->confetti($image, $scene);
        $this->bunting($image);

        match ($scene) {
            'wedding' => $this->rings($image),
            'birthday' => $this->balloons($image),
            'kitty' => $this->stringLights($image),
            default => $this->burst($image),
        };

        $path = (string) tempnam(sys_get_temp_dir(), 'eventart');
        imagepng($image, $path);
        imagedestroy($image);

        return $path;
    }

    /** Large translucent discs behind everything — depth without detail. */
    private function bokeh(\GdImage $image, string $seed): void
    {
        mt_srand(crc32($seed)); // deterministic: the same scene always draws the same picture

        $glow = (int) imagecolorallocatealpha($image, 255, 255, 255, 112);

        for ($i = 0; $i < 7; $i++) {
            $diameter = mt_rand(120, 340);
            imagefilledellipse($image, mt_rand(0, self::WIDTH), mt_rand(0, self::HEIGHT), $diameter, $diameter, $glow);
        }
    }

    private function confetti(\GdImage $image, string $seed): void
    {
        mt_srand(crc32($seed.'confetti'));

        foreach ([[255, 255, 255, 40], [255, 214, 90, 55], [255, 255, 255, 75]] as [$r, $g, $b, $alpha]) {
            $colour = (int) imagecolorallocatealpha($image, $r, $g, $b, $alpha);

            for ($i = 0; $i < 26; $i++) {
                $x = mt_rand(0, self::WIDTH);
                $y = mt_rand(0, self::HEIGHT);
                $size = mt_rand(4, 11);

                mt_rand(0, 1) === 0
                    ? imagefilledellipse($image, $x, $y, $size, $size, $colour)
                    : imagefilledrectangle($image, $x, $y, $x + $size, $y + (int) ($size / 2), $colour);
            }
        }
    }

    /** A row of pennant triangles hanging from the top edge. */
    private function bunting(\GdImage $image): void
    {
        $count = 10;
        $step = self::WIDTH / $count;

        for ($i = 0; $i < $count; $i++) {
            $left = (int) ($i * $step);
            $right = (int) ($left + $step);
            $mid = (int) ($left + $step / 2);
            $sag = 26 + (int) (14 * sin(M_PI * ($i + 0.5) / $count));

            $colour = (int) imagecolorallocatealpha($image, 255, 255, 255, $i % 2 === 0 ? 28 : 64);
            imagefilledpolygon($image, [$left, $sag, $right, $sag, $mid, $sag + 58], $colour);
        }

        imagesetthickness($image, 3);
        imageline($image, 0, 28, self::WIDTH, 28, (int) imagecolorallocatealpha($image, 255, 255, 255, 40));
    }

    /** Wedding: two interlocked rings, off-centre right. */
    private function rings(\GdImage $image): void
    {
        $gold = (int) imagecolorallocatealpha($image, 255, 214, 90, 15);
        $white = (int) imagecolorallocatealpha($image, 255, 255, 255, 25);

        // GD ignores imagesetthickness() for ellipses — a thick ring is a band
        // of concentric 1px ellipses.
        for ($offset = 0; $offset < 14; $offset++) {
            imageellipse($image, 760, 380, 240 + $offset, 240 + $offset, $gold);
            imageellipse($image, 920, 380, 240 + $offset, 240 + $offset, $white);
        }
    }

    /** Birthday: three balloons drifting up, strings trailing. */
    private function balloons(\GdImage $image): void
    {
        $balloons = [
            [820, 300, 150, [255, 255, 255, 30]],
            [980, 240, 120, [255, 214, 90, 25]],
            [900, 440, 100, [255, 255, 255, 55]],
        ];

        imagesetthickness($image, 3);

        foreach ($balloons as [$x, $y, $size, [$r, $g, $b, $alpha]]) {
            $colour = (int) imagecolorallocatealpha($image, $r, $g, $b, $alpha);
            imagefilledellipse($image, $x, $y, $size, (int) ($size * 1.15), $colour);
            imageline($image, $x, $y + (int) ($size * 0.6), $x - 14, $y + (int) ($size * 0.6) + 130, $colour);
        }
    }

    /** Kitty party: a swag of fairy lights across the frame. */
    private function stringLights(\GdImage $image): void
    {
        $wire = (int) imagecolorallocatealpha($image, 255, 255, 255, 45);
        $bulb = (int) imagecolorallocatealpha($image, 255, 214, 90, 20);

        // Same GD limitation as the rings: arcs stay 1px, so stack them.
        for ($offset = 0; $offset < 4; $offset++) {
            imagearc($image, (int) (self::WIDTH / 2), -220 + $offset, 1400, 1000, 25, 155, $wire);
        }

        for ($i = 0; $i < 8; $i++) {
            $angle = deg2rad(30 + $i * 17);
            $x = (int) (self::WIDTH / 2 + 700 * cos($angle));
            $y = (int) (-220 + 500 * sin($angle));
            imagefilledellipse($image, $x, $y + 16, 26, 26, $bulb);
        }
    }

    /** Celebration: a starburst of rays from the lower-right corner. */
    private function burst(\GdImage $image): void
    {
        $ray = (int) imagecolorallocatealpha($image, 255, 255, 255, 96);

        imagesetthickness($image, 6);

        for ($i = 0; $i < 12; $i++) {
            $angle = deg2rad(150 + $i * 9);
            $x = (int) (1050 + 900 * cos($angle));
            $y = (int) (620 + 900 * sin($angle));
            imageline($image, 1050, 620, $x, $y, $ray);
        }

        imagefilledellipse($image, 1050, 620, 90, 90, (int) imagecolorallocatealpha($image, 255, 214, 90, 30));
    }
}
