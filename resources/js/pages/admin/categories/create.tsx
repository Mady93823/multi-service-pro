import { CategoryForm } from '@/components/catalog/category-form';
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem, type Category } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Categories', href: '/admin/categories' },
    { title: 'New', href: '/admin/categories/create' },
];

interface CreateCategoryProps {
    parents: Category[];
}

export default function CreateCategory({ parents }: CreateCategoryProps) {
    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="New Category" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">New category</h1>
                <CategoryForm parents={parents} />
            </div>
        </AdminLayout>
    );
}
