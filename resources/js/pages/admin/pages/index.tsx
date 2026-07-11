import { Pagination } from '@/components/catalog/pagination';
import { ConfirmDelete } from '@/components/confirm-delete';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AdminLayout from '@/layouts/admin-layout';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem, type CmsPage, type Paginated } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ExternalLink, Pencil, Plus } from 'lucide-react';

interface AdminPagesIndexProps {
    pages: Paginated<CmsPage>;
}

export default function AdminPagesIndex({ pages }: AdminPagesIndexProps) {
    const t = useTrans();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('Pages'), href: '/admin/pages' },
    ];

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Pages')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">{t('Pages')}</h1>
                    <Button asChild size="sm">
                        <Link href={route('admin.pages.create')}>
                            <Plus className="h-4 w-4" />
                            {t('New page')}
                        </Link>
                    </Button>
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{t('Title')}</TableHead>
                                <TableHead>{t('Slug')}</TableHead>
                                <TableHead>{t('Status')}</TableHead>
                                <TableHead>{t('Footer')}</TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {pages.data.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={5} className="text-muted-foreground py-8 text-center">
                                        {t('No pages yet.')}
                                    </TableCell>
                                </TableRow>
                            )}
                            {pages.data.map((page) => (
                                <TableRow key={page.id}>
                                    <TableCell className="font-medium">{page.title}</TableCell>
                                    <TableCell className="text-muted-foreground font-mono text-xs">/p/{page.slug}</TableCell>
                                    <TableCell>
                                        {page.is_published ? (
                                            <Badge className="bg-emerald-600 text-white">{t('Published')}</Badge>
                                        ) : (
                                            <Badge variant="outline">{t('Draft')}</Badge>
                                        )}
                                    </TableCell>
                                    <TableCell>{page.show_in_footer ? t('Yes') : '—'}</TableCell>
                                    <TableCell>
                                        <div className="flex justify-end gap-1">
                                            {page.is_published && (
                                                <Button asChild variant="ghost" size="icon" aria-label={t('View page')}>
                                                    <a href={`/p/${page.slug}`} target="_blank" rel="noreferrer">
                                                        <ExternalLink className="h-4 w-4" />
                                                    </a>
                                                </Button>
                                            )}
                                            <Button asChild variant="ghost" size="icon" aria-label={t('Edit page')}>
                                                <Link href={route('admin.pages.edit', page.id)}>
                                                    <Pencil className="h-4 w-4" />
                                                </Link>
                                            </Button>
                                            <ConfirmDelete
                                                title={t('Delete page?')}
                                                description={t('“:title” and its public URL will be removed.', { title: page.title })}
                                                deleteUrl={route('admin.pages.destroy', page.id)}
                                            />
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                <Pagination meta={pages.meta} links={pages.links} />
            </div>
        </AdminLayout>
    );
}
