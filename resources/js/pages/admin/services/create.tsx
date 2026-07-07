import { ServiceForm } from '@/components/catalog/service-form';
import AdminLayout from '@/layouts/admin-layout';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem, type Category, type Service } from '@/types';
import { Head } from '@inertiajs/react';

interface CreateServiceProps {
    categories: Category[];
    relatable: Service[];
}

export default function CreateService({ categories, relatable }: CreateServiceProps) {
    const t = useTrans();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('Services'), href: '/admin/services' },
        { title: t('New'), href: '/admin/services/create' },
    ];

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('New service')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">{t('New service')}</h1>
                <ServiceForm categories={categories} relatable={relatable} />
            </div>
        </AdminLayout>
    );
}
