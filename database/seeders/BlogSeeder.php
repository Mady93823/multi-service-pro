<?php

namespace Database\Seeders;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo blog content (M21). Replaceable copy — the point is that a fresh install
 * has a blog with something in it, and a menu link that goes somewhere.
 */
class BlogSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::query()->role('admin')->first();

        $categories = collect([
            ['name' => 'Home care', 'description' => 'Keeping a home in shape between visits.'],
            ['name' => 'Company news', 'description' => 'What we are building, and where.'],
        ])->map(fn (array $data, int $index): BlogCategory => BlogCategory::query()->firstOrCreate(
            ['slug' => Str::slug($data['name'])],
            [...$data, 'sort_order' => $index + 1, 'is_active' => true],
        ));

        $posts = [
            [
                'title' => 'Five things to check before a deep clean',
                'category' => 0,
                'is_featured' => true,
                'excerpt' => 'A little preparation makes a deep clean twice as effective — here is what to do the night before.',
                'body' => "## Clear the surfaces\n\nA professional cleans surfaces, not clutter. Ten minutes of tidying buys you an hour of real cleaning.\n\n## Point out the problem spots\n\nThe stain you have stopped noticing is the one that needs the most time. Say so at the start of the visit.\n\n## Check the water and power\n\nMost equipment needs both. A quick check avoids an interrupted job.",
            ],
            [
                'title' => 'How our professionals are verified',
                'category' => 1,
                'is_featured' => false,
                'excerpt' => 'Every professional completes document verification and approval before taking a single job.',
                'body' => "## Documents first\n\nIdentity and address proof are checked and approved by a human before a professional appears in a single search result.\n\n## Ratings after\n\nEvery completed job can be rated. Ratings are recomputed from visible reviews, so moderation is honest by construction.",
            ],
        ];

        foreach ($posts as $index => $data) {
            BlogPost::query()->firstOrCreate(
                ['slug' => Str::slug($data['title'])],
                [
                    'blog_category_id' => $categories[$data['category']]->id,
                    'author_id' => $author?->id,
                    'title' => $data['title'],
                    'excerpt' => $data['excerpt'],
                    'body' => $data['body'],
                    'tags' => ['home', 'guide'],
                    'is_featured' => $data['is_featured'],
                    'is_published' => true,
                    'published_at' => now()->subDays($index + 1),
                ],
            );
        }
    }
}
