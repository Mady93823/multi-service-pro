<?php

namespace App\Http\Presenters;

use App\Http\Resources\BannerResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\FaqResource;
use App\Http\Resources\ServiceResource;
use App\Http\Resources\SponsorResource;
use App\Http\Resources\TestimonialResource;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Faq;
use App\Models\Service;
use App\Models\Sponsor;
use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Blocks resolve on the server and may hand back models (M20). Turning those
 * into the wire shape the storefront already speaks is an HTTP concern, so it
 * happens here — the domain stays free of `App\Http` (arch rule), and a block
 * never re-implements a resource that already exists.
 */
class BlockPresenter
{
    /** @var array<class-string<Model>, class-string<JsonResource>> */
    private const RESOURCES = [
        Service::class => ServiceResource::class,
        Category::class => CategoryResource::class,
        Banner::class => BannerResource::class,
        Faq::class => FaqResource::class,
        Testimonial::class => TestimonialResource::class,
        Sponsor::class => SponsorResource::class,
    ];

    /**
     * @param  list<array{id: int, type: string, data: array<string, mixed>}>  $blocks
     * @return list<array{id: int, type: string, props: array<string, mixed>}>
     */
    public function collection(array $blocks): array
    {
        return array_map(fn (array $block): array => [
            'id' => $block['id'],
            'type' => $block['type'],
            /** @var array<string, mixed> */
            'props' => $this->present($block['data']),
        ], $blocks);
    }

    private function present(mixed $value): mixed
    {
        if ($value instanceof Model) {
            $resource = self::RESOURCES[$value::class] ?? null;

            return $resource === null ? null : new $resource($value);
        }

        if ($value instanceof Collection) {
            return $value->map(fn (mixed $item): mixed => $this->present($item))->all();
        }

        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->present($item), $value);
        }

        return $value;
    }
}
