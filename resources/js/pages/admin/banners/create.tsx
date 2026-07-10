import { BannerForm } from '@/components/marketing/banner-form';
import AdminLayout from '@/layouts/admin-layout';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

export default function CreateBanner() {
    const t = useTrans();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('Banners'), href: '/admin/banners' },
        { title: t('New'), href: '/admin/banners/create' },
    ];

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('New banner')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">{t('New banner')}</h1>
                <BannerForm />
            </div>
        </AdminLayout>
    );
}
