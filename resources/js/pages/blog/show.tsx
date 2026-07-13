import { formatPostDate, PostCard } from '@/components/blog/post-card';
import { Badge } from '@/components/ui/badge';
import PublicLayout from '@/layouts/public-layout';
import { useTrans } from '@/lib/i18n';
import { type BlogPost } from '@/types';
import { Head } from '@inertiajs/react';

interface BlogShowProps {
    post: BlogPost;
    /** Server-sanitized by MarkdownRenderer (html_input: strip) — the only HTML source here. */
    html: string;
    related: BlogPost[];
    show_author: boolean;
    meta: { title: string; description: string | null; image: string | null; url: string };
}

export default function BlogShow({ post, html, related, show_author, meta }: BlogShowProps) {
    const t = useTrans();
    const published = formatPostDate(post.published_at);

    return (
        <PublicLayout>
            <Head title={meta.title}>
                {/* Open Graph + Twitter cards; M24's SEO layer reuses the same fields. */}
                {meta.description !== null && <meta name="description" content={meta.description} />}
                <meta property="og:type" content="article" />
                <meta property="og:title" content={meta.title} />
                {meta.description !== null && <meta property="og:description" content={meta.description} />}
                <meta property="og:url" content={meta.url} />
                {meta.image !== null && <meta property="og:image" content={meta.image} />}
                <meta name="twitter:card" content={meta.image !== null ? 'summary_large_image' : 'summary'} />
            </Head>

            <article className="mx-auto w-full max-w-3xl py-8">
                <div className="flex flex-wrap items-center gap-2">
                    {post.category && <Badge variant="secondary">{post.category.name}</Badge>}
                    {published !== null && <span className="text-muted-foreground text-xs">{published}</span>}
                    {show_author && post.author && <span className="text-muted-foreground text-xs">{t('By :author', { author: post.author })}</span>}
                </div>

                <h1 className="mt-3 text-3xl font-semibold tracking-tight">{post.title}</h1>

                {post.cover_hero_url !== null && <img src={post.cover_hero_url} alt="" className="mt-6 h-72 w-full rounded-2xl object-cover" />}

                <div
                    className="prose prose-neutral dark:prose-invert mt-8 max-w-none"
                    // Safe: produced by the server-side sanitizing renderer, never from client input.
                    dangerouslySetInnerHTML={{ __html: html }}
                />

                {post.tags.length > 0 && (
                    <div className="mt-8 flex flex-wrap gap-2">
                        {post.tags.map((tag) => (
                            <Badge key={tag} variant="outline">
                                {tag}
                            </Badge>
                        ))}
                    </div>
                )}
            </article>

            {related.length > 0 && (
                <section className="space-y-4 py-8">
                    <h2 className="text-lg font-semibold">{t('Keep reading')}</h2>
                    <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        {related.map((item) => (
                            <PostCard key={item.id} post={item} showAuthor={show_author} />
                        ))}
                    </div>
                </section>
            )}
        </PublicLayout>
    );
}
