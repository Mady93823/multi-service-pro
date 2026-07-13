import { formatPostDate } from '@/components/blog/post-card';
import { Pagination } from '@/components/catalog/pagination';
import { ConfirmDelete } from '@/components/confirm-delete';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AdminLayout from '@/layouts/admin-layout';
import { useTrans } from '@/lib/i18n';
import { type BlogPost, type BreadcrumbItem, type Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ExternalLink, Pencil, Plus, Search } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

export default function AdminBlogIndex({ posts, search }: { posts: Paginated<BlogPost>; search: string }) {
    const t = useTrans();
    const [term, setTerm] = useState(search);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('Blog'), href: '/admin/blog' },
    ];

    const submitSearch: FormEventHandler = (e) => {
        e.preventDefault();
        router.get(route('admin.blog.index'), term !== '' ? { search: term } : {}, { preserveState: true, replace: true });
    };

    /** Published, scheduled and draft are three different things — say which. */
    const status = (post: BlogPost) => {
        if (!post.is_published || post.published_at === null) {
            return <Badge variant="outline">{t('Draft')}</Badge>;
        }

        if (new Date(post.published_at) > new Date()) {
            return <Badge className="bg-amber-600 text-white">{t('Scheduled')}</Badge>;
        }

        return <Badge className="bg-emerald-600 text-white">{t('Published')}</Badge>;
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Blog')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h1 className="text-xl font-semibold">{t('Blog')}</h1>
                    <div className="flex gap-2">
                        <form onSubmit={submitSearch} className="flex gap-2">
                            <Input value={term} onChange={(e) => setTerm(e.target.value)} placeholder={t('Search posts...')} className="w-56" />
                            <Button type="submit" variant="outline" size="icon" aria-label={t('Search')}>
                                <Search className="h-4 w-4" />
                            </Button>
                        </form>
                        <Button asChild size="sm">
                            <Link href={route('admin.blog.create')}>
                                <Plus className="h-4 w-4" />
                                {t('New post')}
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{t('Title')}</TableHead>
                                <TableHead>{t('Category')}</TableHead>
                                <TableHead>{t('Status')}</TableHead>
                                <TableHead>{t('Date')}</TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {posts.data.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={5} className="text-muted-foreground py-8 text-center">
                                        {t('No posts yet.')}
                                    </TableCell>
                                </TableRow>
                            )}
                            {posts.data.map((post) => (
                                <TableRow key={post.id}>
                                    <TableCell className="font-medium">
                                        {post.title}
                                        {post.is_featured && (
                                            <Badge variant="secondary" className="ml-2">
                                                {t('Featured')}
                                            </Badge>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">{post.category?.name ?? '—'}</TableCell>
                                    <TableCell>{status(post)}</TableCell>
                                    <TableCell className="text-muted-foreground text-sm">{formatPostDate(post.published_at) ?? '—'}</TableCell>
                                    <TableCell>
                                        <div className="flex justify-end gap-1">
                                            <Button asChild variant="ghost" size="icon" aria-label={t('View post')}>
                                                <a href={`/blog/${post.slug}`} target="_blank" rel="noreferrer">
                                                    <ExternalLink className="h-4 w-4" />
                                                </a>
                                            </Button>
                                            <Button asChild variant="ghost" size="icon" aria-label={t('Edit post')}>
                                                <Link href={route('admin.blog.edit', post.id)}>
                                                    <Pencil className="h-4 w-4" />
                                                </Link>
                                            </Button>
                                            <ConfirmDelete
                                                title={t('Delete post?')}
                                                description={t('“:title” and its public URL will be removed.', { title: post.title })}
                                                deleteUrl={route('admin.blog.destroy', post.id)}
                                            />
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                <Pagination meta={posts.meta} links={posts.links} />
            </div>
        </AdminLayout>
    );
}
