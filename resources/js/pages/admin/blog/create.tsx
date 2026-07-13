import { PostForm } from '@/components/blog/post-form';
import AdminLayout from '@/layouts/admin-layout';
import { useTrans } from '@/lib/i18n';
import { type BlogCategory, type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

export default function AdminBlogCreate({ categories }: { categories: BlogCategory[] }) {
    const t = useTrans();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('Blog'), href: '/admin/blog' },
        { title: t('New post'), href: '/admin/blog/create' },
    ];

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('New post')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">{t('New post')}</h1>
                <PostForm categories={categories} />
            </div>
        </AdminLayout>
    );
}
