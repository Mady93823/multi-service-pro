<?php echo '<?xml version="1.0" encoding="UTF-8"?>'."\n"; ?>
{{-- RSS 2.0. Everything interpolated here is escaped by Blade; the post body
     never appears — a feed carries the excerpt and a link, not markup. --}}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>{{ $title }}</title>
        <link>{{ route('blog.index') }}</link>
        <description>{{ __('Latest posts from :name', ['name' => $title]) }}</description>
        <language>{{ str_replace('_', '-', app()->getLocale()) }}</language>
        <atom:link href="{{ route('blog.feed') }}" rel="self" type="application/rss+xml"/>
        @foreach ($posts as $post)
            <item>
                <title>{{ $post->title }}</title>
                <link>{{ route('blog.show', $post->slug) }}</link>
                <guid isPermaLink="true">{{ route('blog.show', $post->slug) }}</guid>
                <pubDate>{{ $post->published_at?->toRfc2822String() }}</pubDate>
                <description>{{ $post->excerpt }}</description>
            </item>
        @endforeach
    </channel>
</rss>
