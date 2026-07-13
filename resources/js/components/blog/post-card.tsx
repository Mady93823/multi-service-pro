import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { useTrans } from '@/lib/i18n';
import { type BlogPost } from '@/types';
import { Link } from '@inertiajs/react';
import { Newspaper } from 'lucide-react';

export function formatPostDate(iso: string | null): string | null {
    return iso === null ? null : new Intl.DateTimeFormat(undefined, { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(iso));
}

export function PostCard({ post, showAuthor = true }: { post: BlogPost; showAuthor?: boolean }) {
    const t = useTrans();
    const published = formatPostDate(post.published_at);

    return (
        <Link href={route('blog.show', post.slug)} className="group">
            <Card className="h-full overflow-hidden py-0 transition-shadow group-hover:shadow-md">
                {post.cover_url !== null ? (
                    <img src={post.cover_url} alt="" className="h-44 w-full object-cover" />
                ) : (
                    <div className="bg-muted flex h-44 w-full items-center justify-center">
                        <Newspaper className="text-muted-foreground h-8 w-8" />
                    </div>
                )}
                <CardContent className="space-y-2 p-4">
                    <div className="flex flex-wrap items-center gap-2">
                        {post.category && <Badge variant="secondary">{post.category.name}</Badge>}
                        {published !== null && <span className="text-muted-foreground text-xs">{published}</span>}
                    </div>
                    <h3 className="font-medium">{post.title}</h3>
                    {post.excerpt !== null && <p className="text-muted-foreground line-clamp-2 text-sm">{post.excerpt}</p>}
                    {showAuthor && post.author && <p className="text-muted-foreground text-xs">{t('By :author', { author: post.author })}</p>}
                </CardContent>
            </Card>
        </Link>
    );
}
