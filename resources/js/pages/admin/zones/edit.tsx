import { ZoneForm } from '@/components/zones/zone-form';
import AdminLayout from '@/layouts/admin-layout';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem, type City, type Zone } from '@/types';
import { Head } from '@inertiajs/react';

interface ZoneEditProps {
    zone: Zone;
    cities: City[];
}

export default function ZoneEdit({ zone, cities }: ZoneEditProps) {
    const t = useTrans();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('Zones'), href: '/admin/zones' },
        { title: zone.name, href: `/admin/zones/${zone.id}/edit` },
    ];

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Edit zone')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">{t('Edit zone')}</h1>
                <ZoneForm zone={zone} cities={cities} />
            </div>
        </AdminLayout>
    );
}
