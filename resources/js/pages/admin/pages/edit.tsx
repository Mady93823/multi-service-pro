import { PageForm } from '@/components/cms/page-form';
import AdminLayout from '@/layouts/admin-layout';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem, type CmsPage } from '@/types';
import { Head } from '@inertiajs/react';

interface AdminPageEditProps {
    page: CmsPage;
}

export default function AdminPageEdit({ page }: AdminPageEditProps) {
    const t = useTrans();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('Pages'), href: '/admin/pages' },
        { title: page.title, href: `/admin/pages/${page.id}/edit` },
    ];

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Edit page')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">{t('Edit page')}</h1>
                <PageForm page={page} />
            </div>
        </AdminLayout>
    );
}
