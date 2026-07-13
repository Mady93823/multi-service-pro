<?php

namespace App\Domain\Blocks;

/**
 * What a block knows about the request it is being rendered for (M20).
 *
 * Pictures are pre-resolved: the block's own media copies, keyed by the library
 * asset id its payload stores (D29). A block never touches the disk itself.
 */
final class BlockContext
{
    /**
     * @param  array<int, array{url: string, thumb_url: string, card_url: string}>  $images
     */
    public function __construct(
        public readonly ?int $zoneId = null,
        private readonly array $images = [],
    ) {}

    /**
     * @return array{url: string, thumb_url: string, card_url: string}|null
     */
    public function image(mixed $assetId): ?array
    {
        $id = is_numeric($assetId) ? (int) $assetId : 0;

        return $this->images[$id] ?? null;
    }

    public function imageUrl(mixed $assetId, string $conversion = 'card'): ?string
    {
        $image = $this->image($assetId);

        if ($image === null) {
            return null;
        }

        return match ($conversion) {
            'thumb' => $image['thumb_url'],
            'card' => $image['card_url'],
            default => $image['url'],
        };
    }
}
