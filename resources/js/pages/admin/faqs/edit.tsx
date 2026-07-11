import { FaqForm } from '@/components/cms/faq-form';
import AdminLayout from '@/layouts/admin-layout';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem, type Faq } from '@/types';
import { Head } from '@inertiajs/react';

interface AdminFaqEditProps {
    faq: Faq;
}

export default function AdminFaqEdit({ faq }: AdminFaqEditProps) {
    const t = useTrans();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('FAQs'), href: '/admin/faqs' },
        { title: t('Edit FAQ'), href: `/admin/faqs/${faq.id}/edit` },
    ];

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Edit FAQ')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">{t('Edit FAQ')}</h1>
                <FaqForm faq={faq} />
            </div>
        </AdminLayout>
    );
}
