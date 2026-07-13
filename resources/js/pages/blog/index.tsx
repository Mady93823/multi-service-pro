import { PostCard } from '@/components/blog/post-card';
import { Pagination } from '@/components/catalog/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import PublicLayout from '@/layouts/public-layout';
import { useTrans } from '@/lib/i18n';
import { type BlogCategory, type BlogPost, type Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Rss, Search } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface BlogIndexProps {
    posts: Paginated<BlogPost>;
    categories: BlogCategory[];
    featured: BlogPost[];
    search: string;
    category: string;
    show_author: boolean;
}

export default function BlogIndex({ posts, categories, featured, search, category, show_author }: BlogIndexProps) {
    const t = useTrans();
    const [term, setTerm] = useState(search);

    const filter = (next: { search?: string; category?: string }) => {
        router.get(route('blog.index'), { search: next.search ?? term, category: next.category ?? category }, { preserveState: true, replace: true });
    };

    const submitSearch: FormEventHandler = (e) => {
        e.preventDefault();
        filter({});
    };

    const lead = featured[0] ?? null;

    return (
        <PublicLayout>
            <Head title={t('Blog')} />

            <section className="flex flex-wrap items-end justify-between gap-4 py-8">
                <div>
                    <h1 className="text-3xl font-semibold tracking-tight">{t('Blog')}</h1>
                    <p className="text-muted-foreground mt-1">{t('Tips, guides and news from our professionals.')}</p>
                </div>
                <Button asChild variant="outline" size="sm">
                    <a href={route('blog.feed')} target="_blank" rel="noreferrer">
                        <Rss className="h-4 w-4" />
                        {t('RSS')}
                    </a>
                </Button>
            </section>

            {lead !== null && search === '' && category === '' && (
                <section className="pb-6">
                    <Link href={route('blog.show', lead.slug)} className="group grid gap-6 md:grid-cols-2">
                        {lead.cover_hero_url !== null && <img src={lead.cover_hero_url} alt="" className="h-64 w-full rounded-2xl object-cover" />}
                        <div className="flex flex-col justify-center gap-3">
                            <Badge className="w-fit">{t('Featured')}</Badge>
                            <h2 className="text-2xl font-semibold tracking-tight group-hover:underline">{lead.title}</h2>
                            {lead.excerpt !== null && <p className="text-muted-foreground">{lead.excerpt}</p>}
                        </div>
                    </Link>
                </section>
            )}

            <section className="flex flex-wrap items-center gap-2 pb-6">
                <form onSubmit={submitSearch} className="flex gap-2">
                    <Input value={term} onChange={(e) => setTerm(e.target.value)} placeholder={t('Search posts...')} className="w-56" />
                    <Button type="submit" variant="outline" size="icon" aria-label={t('Search')}>
                        <Search className="h-4 w-4" />
                    </Button>
                </form>

                <Button variant={category === '' ? 'default' : 'outline'} size="sm" onClick={() => filter({ category: '' })}>
                    {t('All')}
                </Button>
                {categories.map((item) => (
                    <Button
                        key={item.id}
                        variant={category === item.slug ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => filter({ category: item.slug })}
                    >
                        {item.name}
                    </Button>
                ))}
            </section>

            {posts.data.length === 0 ? (
                <div className="text-muted-foreground rounded-xl border border-dashed py-16 text-center text-sm">{t('No posts yet.')}</div>
            ) : (
                <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    {posts.data.map((post) => (
                        <PostCard key={post.id} post={post} showAuthor={show_author} />
                    ))}
                </div>
            )}

            <div className="py-6">
                <Pagination meta={posts.meta} links={posts.links} />
            </div>
        </PublicLayout>
    );
}
