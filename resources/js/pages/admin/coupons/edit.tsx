import { CouponForm } from '@/components/marketing/coupon-form';
import AdminLayout from '@/layouts/admin-layout';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem, type Coupon } from '@/types';
import { Head } from '@inertiajs/react';

interface EditCouponProps {
    coupon: Coupon;
}

export default function EditCoupon({ coupon }: EditCouponProps) {
    const t = useTrans();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('Coupons'), href: '/admin/coupons' },
        { title: coupon.code, href: `/admin/coupons/${coupon.id}/edit` },
    ];

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Edit coupon')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold">{t('Edit coupon')}</h1>
                    <p className="text-muted-foreground text-sm">{t('Redeemed :count times.', { count: coupon.usages_count ?? 0 })}</p>
                </div>
                <CouponForm coupon={coupon} />
            </div>
        </AdminLayout>
    );
}
