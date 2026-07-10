import { ConfirmDelete } from '@/components/confirm-delete';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AdminLayout from '@/layouts/admin-layout';
import { useTrans } from '@/lib/i18n';
import { type Banner, type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';

interface AdminBannersIndexProps {
    banners: Banner[];
}

export default function AdminBannersIndex({ banners }: AdminBannersIndexProps) {
    const t = useTrans();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('Banners'), href: '/admin/banners' },
    ];

    const placementLabels: Record<Banner['placement'], string> = {
        home_hero: t('Home hero'),
        home_strip: t('Home strip'),
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Banners')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">{t('Banners')}</h1>
                    <Button asChild size="sm">
                        <Link href={route('admin.banners.create')}>
                            <Plus className="h-4 w-4" />
                            {t('New banner')}
                        </Link>
                    </Button>
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{t('Preview')}</TableHead>
                                <TableHead>{t('Title')}</TableHead>
                                <TableHead>{t('Placement')}</TableHead>
                                <TableHead>{t('Schedule')}</TableHead>
                                <TableHead>{t('Status')}</TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {banners.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={6} className="text-muted-foreground py-8 text-center">
                                        {t('No banners yet.')}
                                    </TableCell>
                                </TableRow>
                            )}
                            {banners.map((banner) => (
                                <TableRow key={banner.id}>
                                    <TableCell>
                                        {banner.image_url !== null ? (
                                            <img src={banner.image_url} alt="" className="h-10 w-20 rounded object-cover" />
                                        ) : (
                                            <div className="from-primary/60 to-primary flex h-10 w-20 items-center justify-center rounded bg-gradient-to-r text-[10px] text-white">
                                                {t('No image')}
                                            </div>
                                        )}
                                    </TableCell>
                                    <TableCell className="font-medium">{banner.title}</TableCell>
                                    <TableCell>{placementLabels[banner.placement]}</TableCell>
                                    <TableCell className="text-muted-foreground text-sm">
                                        {banner.starts_at === null && banner.ends_at === null
                                            ? t('Always on')
                                            : `${banner.starts_at ?? '…'} → ${banner.ends_at ?? '…'}`}
                                    </TableCell>
                                    <TableCell>
                                        {banner.is_active ? (
                                            <Badge className="bg-emerald-600 text-white">{t('Active')}</Badge>
                                        ) : (
                                            <Badge variant="outline">{t('Inactive')}</Badge>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex justify-end gap-1">
                                            <Button asChild variant="ghost" size="icon" aria-label={t('Edit banner')}>
                                                <Link href={route('admin.banners.edit', banner.id)}>
                                                    <Pencil className="h-4 w-4" />
                                                </Link>
                                            </Button>
                                            <ConfirmDelete
                                                title={t('Delete banner?')}
                                                description={t('“:title” will be removed from the storefront.', { title: banner.title })}
                                                deleteUrl={route('admin.banners.destroy', banner.id)}
                                            />
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </AdminLayout>
    );
}
