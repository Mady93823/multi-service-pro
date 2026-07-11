import { Pagination } from '@/components/catalog/pagination';
import { ConfirmDelete } from '@/components/confirm-delete';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AdminLayout from '@/layouts/admin-layout';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem, type Faq, type Paginated } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';

interface AdminFaqsIndexProps {
    faqs: Paginated<Faq>;
}

export default function AdminFaqsIndex({ faqs }: AdminFaqsIndexProps) {
    const t = useTrans();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('FAQs'), href: '/admin/faqs' },
    ];

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('FAQs')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">{t('FAQs')}</h1>
                    <Button asChild size="sm">
                        <Link href={route('admin.faqs.create')}>
                            <Plus className="h-4 w-4" />
                            {t('New FAQ')}
                        </Link>
                    </Button>
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-14">{t('Order')}</TableHead>
                                <TableHead>{t('Question')}</TableHead>
                                <TableHead>{t('Status')}</TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {faqs.data.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={4} className="text-muted-foreground py-8 text-center">
                                        {t('No FAQs yet.')}
                                    </TableCell>
                                </TableRow>
                            )}
                            {faqs.data.map((faq) => (
                                <TableRow key={faq.id}>
                                    <TableCell className="text-muted-foreground tabular-nums">{faq.sort_order}</TableCell>
                                    <TableCell className="font-medium">{faq.question}</TableCell>
                                    <TableCell>
                                        {faq.is_active ? (
                                            <Badge className="bg-emerald-600 text-white">{t('Active')}</Badge>
                                        ) : (
                                            <Badge variant="outline">{t('Inactive')}</Badge>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex justify-end gap-1">
                                            <Button asChild variant="ghost" size="icon" aria-label={t('Edit FAQ')}>
                                                <Link href={route('admin.faqs.edit', faq.id)}>
                                                    <Pencil className="h-4 w-4" />
                                                </Link>
                                            </Button>
                                            <ConfirmDelete
                                                title={t('Delete FAQ?')}
                                                description={t('“:question” will be removed from the storefront.', { question: faq.question })}
                                                deleteUrl={route('admin.faqs.destroy', faq.id)}
                                            />
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                <Pagination meta={faqs.meta} links={faqs.links} />
            </div>
        </AdminLayout>
    );
}
