import { Pagination } from '@/components/catalog/pagination';
import { ConfirmDelete } from '@/components/confirm-delete';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AdminLayout from '@/layouts/admin-layout';
import { useMoney } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem, type Coupon, type Paginated } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';

interface AdminCouponsIndexProps {
    coupons: Paginated<Coupon>;
}

export default function AdminCouponsIndex({ coupons }: AdminCouponsIndexProps) {
    const t = useTrans();
    const money = useMoney();
    const { errors } = usePage().props;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('Coupons'), href: '/admin/coupons' },
    ];

    const discountLabel = (coupon: Coupon) =>
        coupon.type === 'percent'
            ? `${Number(coupon.value)}%${coupon.max_discount !== null ? ` (${t('max')} ${money(coupon.max_discount)})` : ''}`
            : money(coupon.value);

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Coupons')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">{t('Coupons')}</h1>
                    <Button asChild size="sm">
                        <Link href={route('admin.coupons.create')}>
                            <Plus className="h-4 w-4" />
                            {t('New coupon')}
                        </Link>
                    </Button>
                </div>

                <InputError message={errors.coupon} />

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{t('Code')}</TableHead>
                                <TableHead>{t('Discount')}</TableHead>
                                <TableHead>{t('Rules')}</TableHead>
                                <TableHead>{t('Used')}</TableHead>
                                <TableHead>{t('Status')}</TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {coupons.data.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={6} className="text-muted-foreground py-8 text-center">
                                        {t('No coupons yet.')}
                                    </TableCell>
                                </TableRow>
                            )}
                            {coupons.data.map((coupon) => (
                                <TableRow key={coupon.id}>
                                    <TableCell className="font-mono font-medium">{coupon.code}</TableCell>
                                    <TableCell>{discountLabel(coupon)}</TableCell>
                                    <TableCell className="text-muted-foreground text-sm">
                                        {[
                                            coupon.min_order !== null ? t('min :amount', { amount: money(coupon.min_order) }) : null,
                                            coupon.first_order_only ? t('first order') : null,
                                            coupon.usage_limit !== null ? t(':count uses total', { count: coupon.usage_limit }) : null,
                                            coupon.per_user_limit !== null ? t(':count per customer', { count: coupon.per_user_limit }) : null,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ') || '—'}
                                    </TableCell>
                                    <TableCell>{coupon.usages_count ?? 0}</TableCell>
                                    <TableCell>
                                        {coupon.is_active ? (
                                            <Badge className="bg-emerald-600 text-white">{t('Active')}</Badge>
                                        ) : (
                                            <Badge variant="outline">{t('Inactive')}</Badge>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex justify-end gap-1">
                                            <Button asChild variant="ghost" size="icon" aria-label={t('Edit coupon')}>
                                                <Link href={route('admin.coupons.edit', coupon.id)}>
                                                    <Pencil className="h-4 w-4" />
                                                </Link>
                                            </Button>
                                            <ConfirmDelete
                                                title={t('Delete coupon?')}
                                                description={t('“:code” will be removed. Used coupons cannot be deleted — deactivate them instead.', {
                                                    code: coupon.code,
                                                })}
                                                deleteUrl={route('admin.coupons.destroy', coupon.id)}
                                            />
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                <Pagination meta={coupons.meta} links={coupons.links} />
            </div>
        </AdminLayout>
    );
}
