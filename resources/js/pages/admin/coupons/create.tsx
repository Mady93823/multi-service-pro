import { CouponForm } from '@/components/marketing/coupon-form';
import AdminLayout from '@/layouts/admin-layout';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

export default function CreateCoupon() {
    const t = useTrans();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('Coupons'), href: '/admin/coupons' },
        { title: t('New'), href: '/admin/coupons/create' },
    ];

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('New coupon')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">{t('New coupon')}</h1>
                <CouponForm />
            </div>
        </AdminLayout>
    );
}
