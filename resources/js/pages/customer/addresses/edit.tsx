import { AddressForm } from '@/components/addresses/address-form';
import CustomerLayout from '@/layouts/customer-layout';
import { useTrans } from '@/lib/i18n';
import { type Address, type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

interface AddressEditProps {
    address: Address;
}

export default function AddressEdit({ address }: AddressEditProps) {
    const t = useTrans();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/dashboard' },
        { title: t('My addresses'), href: '/addresses' },
        { title: t('Edit address'), href: `/addresses/${address.id}/edit` },
    ];

    return (
        <CustomerLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Edit address')} />
            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">{t('Edit address')}</h1>
                <AddressForm address={address} />
            </div>
        </CustomerLayout>
    );
}
