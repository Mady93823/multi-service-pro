import { AddressForm } from '@/components/addresses/address-form';
import CustomerLayout from '@/layouts/customer-layout';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

export default function AddressCreate() {
    const t = useTrans();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/dashboard' },
        { title: t('My addresses'), href: '/addresses' },
        { title: t('Add address'), href: '/addresses/create' },
    ];

    return (
        <CustomerLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Add address')} />
            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">{t('Add address')}</h1>
                <AddressForm />
            </div>
        </CustomerLayout>
    );
}
