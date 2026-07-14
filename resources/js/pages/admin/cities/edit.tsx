import { CityForm } from '@/components/cities/city-form';
import AdminLayout from '@/layouts/admin-layout';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem, type City } from '@/types';
import { Head } from '@inertiajs/react';

interface CityEditProps {
    city: City;
    timezones: string[];
}

export default function CityEdit({ city, timezones }: CityEditProps) {
    const t = useTrans();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('Cities'), href: '/admin/cities' },
        { title: city.name, href: `/admin/cities/${city.id}/edit` },
    ];

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Edit city')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">{t('Edit city')}</h1>
                <CityForm city={city} timezones={timezones} />
            </div>
        </AdminLayout>
    );
}
