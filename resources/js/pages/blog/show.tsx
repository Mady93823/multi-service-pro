import { formatPostDate, PostCard } from '@/components/blog/post-card';
import { SeoHead } from '@/components/seo/seo-head';
import { Badge } from '@/components/ui/badge';
import PublicLayout from '@/layouts/public-layout';
import { useTrans } from '@/lib/i18n';
import { type BlogPost, type SeoMetaProps } from '@/types';

interface BlogShowProps {
    post: BlogPost;
    /** Server-sanitized by MarkdownRenderer (html_input: strip) — the only HTML source here. */
    html: string;
    related: BlogPost[];
    show_author: boolean;
    meta: SeoMetaProps;
    /** Article JSON-LD, or null when structured data is switched off (M24). */
    schema: Record<string, unknown> | null;
}

export default function BlogShow({ post, html, related, show_author, meta, schema }: BlogShowProps) {
    const t = useTrans();
    const published = formatPostDate(post.published_at);

    return (
        <PublicLayout>
            {/* M24: one meta component for every public page — Open Graph, Twitter, canonical, JSON-LD. */}
            <SeoHead meta={meta} schema={schema} />

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
