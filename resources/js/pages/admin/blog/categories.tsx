import { ConfirmDelete } from '@/components/confirm-delete';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AdminLayout from '@/layouts/admin-layout';
import { useTrans } from '@/lib/i18n';
import { type BlogCategory, type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

export default function AdminBlogCategories({ categories }: { categories: BlogCategory[] }) {
    const t = useTrans();
    const [editing, setEditing] = useState<BlogCategory | null | undefined>(undefined);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('Blog'), href: '/admin/blog' },
        { title: t('Blog categories'), href: '/admin/blog/categories' },
    ];

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Blog categories')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">{t('Blog categories')}</h1>
                    <Button size="sm" onClick={() => setEditing(null)}>
                        <Plus className="h-4 w-4" />
                        {t('New category')}
                    </Button>
                </div>
                <p className="text-muted-foreground text-sm">{t('Deleting a category keeps its posts — they become uncategorised.')}</p>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{t('Name')}</TableHead>
                                <TableHead>{t('Slug')}</TableHead>
                                <TableHead>{t('Posts')}</TableHead>
                                <TableHead>{t('Status')}</TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {categories.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={5} className="text-muted-foreground py-8 text-center">
                                        {t('No categories yet.')}
                                    </TableCell>
                                </TableRow>
                            )}
                            {categories.map((category) => (
                                <TableRow key={category.id}>
                                    <TableCell className="font-medium">{category.name}</TableCell>
                                    <TableCell className="text-muted-foreground font-mono text-xs">{category.slug}</TableCell>
                                    <TableCell>{category.posts_count ?? 0}</TableCell>
                                    <TableCell>
                                        {category.is_active ? (
                                            <Badge variant="secondary">{t('Active')}</Badge>
                                        ) : (
                                            <Badge variant="outline">{t('Off')}</Badge>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex justify-end gap-1">
                                            <Button variant="ghost" size="icon" aria-label={t('Edit')} onClick={() => setEditing(category)}>
                                                <Pencil className="h-4 w-4" />
                                            </Button>
                                            <ConfirmDelete
                                                title={t('Delete category?')}
                                                description={t('Its posts stay, without a category.')}
                                                deleteUrl={route('admin.blog.categories.destroy', category.id)}
                                            />
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </div>

            {editing !== undefined && <CategoryDialog key={editing?.id ?? 'new'} category={editing} onClose={() => setEditing(undefined)} />}
        </AdminLayout>
    );
}

function CategoryDialog({ category, onClose }: { category: BlogCategory | null; onClose: () => void }) {
    const t = useTrans();

    const { data, setData, post, put, processing, errors } = useForm({
        name: category?.name ?? '',
        slug: category?.slug ?? '',
        description: category?.description ?? '',
        sort_order: category?.sort_order ?? 0,
        is_active: category?.is_active ?? true,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        const options = { preserveScroll: true, onSuccess: () => onClose() };

        if (category === null) {
            post(route('admin.blog.categories.store'), options);
        } else {
            put(route('admin.blog.categories.update', category.id), options);
        }
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{category === null ? t('New category') : t('Edit category')}</DialogTitle>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="name">{t('Name')}</Label>
                        <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="slug">{t('Slug')}</Label>
                        <Input id="slug" value={data.slug} onChange={(e) => setData('slug', e.target.value)} placeholder={t('Auto from name')} />
                        <InputError message={errors.slug} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="description">{t('Description')}</Label>
                        <Input id="description" value={data.description} onChange={(e) => setData('description', e.target.value)} />
                        <InputError message={errors.description} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="sort_order">{t('Sort order')}</Label>
                        <Input
                            id="sort_order"
                            type="number"
                            min={0}
                            value={data.sort_order}
                            onChange={(e) => setData('sort_order', Number(e.target.value))}
                        />
                        <InputError message={errors.sort_order} />
                    </div>

                    <label className="flex items-center justify-between gap-4 text-sm">
                        <span className="font-medium">{t('Active')}</span>
                        <Switch checked={data.is_active} onCheckedChange={(checked) => setData('is_active', checked)} />
                    </label>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>
                            {t('Cancel')}
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {t('Save')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
