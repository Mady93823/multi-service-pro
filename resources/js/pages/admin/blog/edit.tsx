import { PostForm } from '@/components/blog/post-form';
import AdminLayout from '@/layouts/admin-layout';
import { useTrans } from '@/lib/i18n';
import { type BlogCategory, type BlogPost, type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

export default function AdminBlogEdit({ post, categories }: { post: BlogPost; categories: BlogCategory[] }) {
    const t = useTrans();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('Blog'), href: '/admin/blog' },
        { title: post.title, href: `/admin/blog/${post.id}/edit` },
    ];

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={post.title} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">{t('Edit post')}</h1>
                <PostForm post={post} categories={categories} />
            </div>
        </AdminLayout>
    );
}
