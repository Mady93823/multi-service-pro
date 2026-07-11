import { FaqForm } from '@/components/cms/faq-form';
import AdminLayout from '@/layouts/admin-layout';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

export default function AdminFaqCreate() {
    const t = useTrans();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('FAQs'), href: '/admin/faqs' },
        { title: t('New FAQ'), href: '/admin/faqs/create' },
    ];

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('New FAQ')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">{t('New FAQ')}</h1>
                <FaqForm />
            </div>
        </AdminLayout>
    );
}
