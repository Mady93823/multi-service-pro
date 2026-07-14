import { CityForm } from '@/components/cities/city-form';
import AdminLayout from '@/layouts/admin-layout';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

interface CityCreateProps {
    timezones: string[];
}

export default function CityCreate({ timezones }: CityCreateProps) {
    const t = useTrans();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('Cities'), href: '/admin/cities' },
        { title: t('New city'), href: '/admin/cities/create' },
    ];

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('New city')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">{t('New city')}</h1>
                <CityForm timezones={timezones} />
            </div>
        </AdminLayout>
    );
}
