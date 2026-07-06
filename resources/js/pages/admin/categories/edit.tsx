import { CategoryForm } from '@/components/catalog/category-form';
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem, type Category } from '@/types';
import { Head } from '@inertiajs/react';

interface EditCategoryProps {
    category: Category;
    parents: Category[];
}

export default function EditCategory({ category, parents }: EditCategoryProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/admin/dashboard' },
        { title: 'Categories', href: '/admin/categories' },
        { title: category.name, href: route('admin.categories.edit', category.id) },
    ];

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${category.name}`} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">Edit category</h1>
                <CategoryForm parents={parents} category={category} />
            </div>
        </AdminLayout>
    );
}
