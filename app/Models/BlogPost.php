<?php

namespace App\Models;

use Database\Factories\BlogPostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A blog post (M21). `body` is markdown source — render it through
 * MarkdownRenderer, never anywhere else (D20).
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string $body
 * @property string|null $excerpt
 * @property list<string>|null $tags
 * @property bool $is_published
 * @property bool $is_featured
 * @property Carbon|null $published_at
 */
class BlogPost extends Model implements HasMedia
{
    /** @use HasFactory<BlogPostFactory> */
    use HasFactory, InteractsWithMedia;

    public const COLLECTION = 'cover';

    protected $fillable = [
        'blog_category_id',
        'author_id',
        'title',
        'slug',
        'excerpt',
        'body',
        'tags',
        'is_featured',
        'is_published',
        'published_at',
        'meta_title',
        'meta_description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'is_featured' => 'boolean',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::COLLECTION)->useDisk('public')->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')->nonQueued()->width(400)->format('webp');
        $this->addMediaConversion('card')->nonQueued()->width(800)->format('webp');
        $this->addMediaConversion('hero')->nonQueued()->width(1600)->format('webp');
    }

    /**
     * @return BelongsTo<BlogCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'blog_category_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Live to the public: published **and** its moment has come. A scheduled
     * post is invisible until then — not merely absent from the index (M21's
     * gate: a scheduled post 404s).
     *
     * @param  Builder<BlogPost>  $query
     * @return Builder<BlogPost>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * @param  Builder<BlogPost>  $query
     * @return Builder<BlogPost>
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        if ($term === '') {
            return $query;
        }

        return $query->where(fn (Builder $inner) => $inner
            ->where('title', 'like', "%{$term}%")
            ->orWhere('excerpt', 'like', "%{$term}%")
            ->orWhere('body', 'like', "%{$term}%"));
    }

    public function coverUrl(string $conversion = 'card'): ?string
    {
        $media = $this->getFirstMedia(self::COLLECTION);

        return $media?->getUrl($conversion);
    }
}
