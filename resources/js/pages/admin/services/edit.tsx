import { ServiceForm } from '@/components/catalog/service-form';
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem, type Category, type Service } from '@/types';
import { Head } from '@inertiajs/react';

interface EditServiceProps {
    service: Service;
    categories: Category[];
    relatable: Service[];
}

export default function EditService({ service, categories, relatable }: EditServiceProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/admin/dashboard' },
        { title: 'Services', href: '/admin/services' },
        { title: service.name, href: route('admin.services.edit', service.id) },
    ];

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${service.name}`} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">Edit service</h1>
                <ServiceForm categories={categories} relatable={relatable} service={service} />
            </div>
        </AdminLayout>
    );
}
