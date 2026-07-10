import { BannerForm } from '@/components/marketing/banner-form';
import AdminLayout from '@/layouts/admin-layout';
import { useTrans } from '@/lib/i18n';
import { type Banner, type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

interface EditBannerProps {
    banner: Banner;
}

export default function EditBanner({ banner }: EditBannerProps) {
    const t = useTrans();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('Banners'), href: '/admin/banners' },
        { title: banner.title, href: `/admin/banners/${banner.id}/edit` },
    ];

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Edit banner')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">{t('Edit banner')}</h1>
                <BannerForm banner={banner} />
            </div>
        </AdminLayout>
    );
}
