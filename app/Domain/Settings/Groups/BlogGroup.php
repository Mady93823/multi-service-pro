<?php

namespace App\Domain\Settings\Groups;

class BlogGroup extends SettingsGroup
{
    public function key(): string
    {
        return 'blog';
    }

    public function label(): string
    {
        return __('Blog');
    }

    public function description(): string
    {
        return __('The public blog: switch it off entirely, or tune how posts are listed.');
    }

    public function keys(): array
    {
        return ['blog.enabled', 'blog.posts_per_page', 'blog.show_author', 'blog.related_count'];
    }

    public function rules(array $input): array
    {
        return [
            'blog_enabled' => ['boolean'],
            'blog_posts_per_page' => ['required', 'integer', 'min:1', 'max:50'],
            'blog_show_author' => ['boolean'],
            'blog_related_count' => ['required', 'integer', 'min:0', 'max:12'],
        ];
    }

    public function values(): array
    {
        return [
            'blog_enabled' => $this->settings->boolean('blog.enabled', true),
            'blog_posts_per_page' => $this->settings->integer('blog.posts_per_page', 9),
            'blog_show_author' => $this->settings->boolean('blog.show_author', true),
            'blog_related_count' => $this->settings->integer('blog.related_count', 3),
        ];
    }

    public function apply(array $data, array $files = []): void
    {
        $this->settings->set('blog.enabled', $this->toggle($data, 'blog_enabled'));
        $this->settings->set('blog.posts_per_page', $data['blog_posts_per_page']);
        $this->settings->set('blog.show_author', $this->toggle($data, 'blog_show_author'));
        $this->settings->set('blog.related_count', $data['blog_related_count']);
    }
}
