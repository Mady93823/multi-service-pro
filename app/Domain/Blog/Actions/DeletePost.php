<?php

namespace App\Domain\Blog\Actions;

use App\Models\BlogPost;

class DeletePost
{
    public function handle(BlogPost $post): void
    {
        $post->delete();
    }
}
