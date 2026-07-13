<?php

namespace Database\Factories;

use App\Models\BlogPost;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BlogPost>
 */
class BlogPostFactory extends Factory
{
    protected $model = BlogPost::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = $this->faker->unique()->sentence(4);

        return [
            'blog_category_id' => null,
            'author_id' => null,
            'title' => $title,
            'slug' => Str::slug($title),
            'excerpt' => $this->faker->sentence(),
            'body' => "## {$title}\n\n".$this->faker->paragraph(),
            'tags' => ['home', 'tips'],
            'is_featured' => false,
            'is_published' => true,
            'published_at' => now()->subDay(),
            'meta_title' => null,
            'meta_description' => null,
        ];
    }

    /** A draft: no publication moment at all. */
    public function draft(): self
    {
        return $this->state(fn (): array => ['is_published' => false, 'published_at' => null]);
    }

    /** Published, but not yet — invisible until its moment arrives. */
    public function scheduled(): self
    {
        return $this->state(fn (): array => ['is_published' => true, 'published_at' => now()->addWeek()]);
    }

    public function featured(): self
    {
        return $this->state(fn (): array => ['is_featured' => true]);
    }
}
