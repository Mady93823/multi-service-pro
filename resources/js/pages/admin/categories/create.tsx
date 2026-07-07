import { CategoryForm } from '@/components/catalog/category-form';
import AdminLayout from '@/layouts/admin-layout';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem, type Category } from '@/types';
import { Head } from '@inertiajs/react';

interface CreateCategoryProps {
    parents: Category[];
}

export default function CreateCategory({ parents }: CreateCategoryProps) {
    const t = useTrans();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('Categories'), href: '/admin/categories' },
        { title: t('New'), href: '/admin/categories/create' },
    ];

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('New category')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">{t('New category')}</h1>
                <CategoryForm parents={parents} />
            </div>
        </AdminLayout>
    );
}
