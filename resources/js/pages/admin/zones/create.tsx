import { ZoneForm } from '@/components/zones/zone-form';
import AdminLayout from '@/layouts/admin-layout';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem, type City } from '@/types';
import { Head } from '@inertiajs/react';

interface ZoneCreateProps {
    cities: City[];
}

export default function ZoneCreate({ cities }: ZoneCreateProps) {
    const t = useTrans();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('Zones'), href: '/admin/zones' },
        { title: t('New zone'), href: '/admin/zones/create' },
    ];

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('New zone')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">{t('New zone')}</h1>
                <ZoneForm cities={cities} />
            </div>
        </AdminLayout>
    );
}
