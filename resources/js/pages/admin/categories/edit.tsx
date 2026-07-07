import { CategoryForm } from '@/components/catalog/category-form';
import AdminLayout from '@/layouts/admin-layout';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem, type Category } from '@/types';
import { Head } from '@inertiajs/react';

interface EditCategoryProps {
    category: Category;
    parents: Category[];
}

export default function EditCategory({ category, parents }: EditCategoryProps) {
    const t = useTrans();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('Categories'), href: '/admin/categories' },
        { title: category.name, href: route('admin.categories.edit', category.id) },
    ];

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Edit :name', { name: category.name })} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">{t('Edit category')}</h1>
                <CategoryForm parents={parents} category={category} />
            </div>
        </AdminLayout>
    );
}
