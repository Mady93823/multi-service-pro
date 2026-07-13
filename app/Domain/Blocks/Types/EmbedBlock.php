<?php

namespace App\Domain\Blocks\Types;

use App\Domain\Blocks\Block;
use App\Domain\Blocks\BlockContext;
use App\Domain\Blocks\BlockField;
use Closure;

/**
 * A third-party embed — an <iframe> the storefront renders, which is exactly
 * the kind of field that becomes a stored XSS if it takes what it is given.
 *
 * So it does not: the admin pastes a normal page URL, and the block derives the
 * embed URL itself from an **allowlist of hosts**. A URL we cannot derive an
 * embed for is rejected on write; one that stops being derivable later (an
 * allowlist entry removed) makes the block **render nothing** rather than an
 * iframe pointing somewhere unknown.
 */
class EmbedBlock extends Block
{
    public function type(): string
    {
        return 'embed';
    }

    public function label(): string
    {
        return __('Video or map');
    }

    public function fields(): array
    {
        return [
            BlockField::text('heading', __('Heading')),
            BlockField::text('url', __('Link'), '', __('YouTube, Vimeo, Google Maps or OpenStreetMap.')),
            BlockField::text('caption', __('Caption')),
        ];
    }

    public function rules(): array
    {
        return [
            'heading' => ['nullable', 'string', 'max:120'],
            'url' => [
                'required',
                'string',
                'max:2048',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || self::embedUrl($value) === null) {
                        $fail(__('That link cannot be embedded. Use YouTube, Vimeo, Google Maps or OpenStreetMap.'));
                    }
                },
            ],
            'caption' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function data(array $payload, BlockContext $context): ?array
    {
        $src = self::embedUrl($this->text($payload, 'url'));

        if ($src === null) {
            return null;
        }

        return [
            'heading' => $this->nullableText($payload, 'heading'),
            'src' => $src,
            'caption' => $this->nullableText($payload, 'caption'),
        ];
    }

    /**
     * The embed URL for a pasted link, or null when the host is not allowed.
     */
    public static function embedUrl(string $url): ?string
    {
        $parts = parse_url(trim($url));

        if (! is_array($parts) || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');
        parse_str((string) ($parts['query'] ?? ''), $query);

        return match (true) {
            in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true) => self::youtube(
                is_string($query['v'] ?? null) ? $query['v'] : ltrim(str_replace(['/embed/', '/shorts/'], '/', $path), '/'),
            ),
            $host === 'youtu.be' => self::youtube(ltrim($path, '/')),
            in_array($host, ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'], true) => self::vimeo($path),
            // Both of these hand out a ready-made embed URL; we take it as it is
            // and never invent one from a page URL.
            in_array($host, ['google.com', 'www.google.com', 'maps.google.com'], true) && str_starts_with($path, '/maps/embed') => $url,
            $host === 'www.openstreetmap.org' && str_starts_with($path, '/export/embed.html') => $url,
            default => null,
        };
    }

    private static function youtube(string $id): ?string
    {
        return preg_match('/^[A-Za-z0-9_-]{6,20}$/', $id) === 1
            ? 'https://www.youtube-nocookie.com/embed/'.$id
            : null;
    }

    private static function vimeo(string $path): ?string
    {
        preg_match('#/(?:video/)?(\d{6,12})#', $path, $matches);

        return isset($matches[1]) ? 'https://player.vimeo.com/video/'.$matches[1] : null;
    }
}
