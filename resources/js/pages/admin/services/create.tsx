import { ServiceForm } from '@/components/catalog/service-form';
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem, type Category, type Service } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Services', href: '/admin/services' },
    { title: 'New', href: '/admin/services/create' },
];

interface CreateServiceProps {
    categories: Category[];
    relatable: Service[];
}

export default function CreateService({ categories, relatable }: CreateServiceProps) {
    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="New Service" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">New service</h1>
                <ServiceForm categories={categories} relatable={relatable} />
            </div>
        </AdminLayout>
    );
}
