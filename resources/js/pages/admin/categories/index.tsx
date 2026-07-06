import { ConfirmDelete } from '@/components/confirm-delete';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem, type Category } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { CornerDownRight, Pencil, Plus } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Categories', href: '/admin/categories' },
];

interface CategoriesIndexProps {
    categories: Category[];
}

function CategoryRow({ category, child = false }: { category: Category; child?: boolean }) {
    return (
        <TableRow>
            <TableCell>
                <div className="flex items-center gap-2">
                    {child && <CornerDownRight className="text-muted-foreground h-4 w-4" />}
                    {category.icon_url && <img src={category.icon_url} alt="" className="h-6 w-6 rounded object-cover" />}
                    <span className={child ? '' : 'font-medium'}>{category.name}</span>
                </div>
            </TableCell>
            <TableCell className="text-muted-foreground">{category.slug}</TableCell>
            <TableCell className="text-center">{category.services_count ?? 0}</TableCell>
            <TableCell className="text-center">{category.sort_order}</TableCell>
            <TableCell>
                <Badge variant={category.is_active ? 'default' : 'outline'}>{category.is_active ? 'Active' : 'Hidden'}</Badge>
            </TableCell>
            <TableCell>
                <div className="flex justify-end gap-1">
                    <Button asChild variant="ghost" size="icon" aria-label="Edit category">
                        <Link href={route('admin.categories.edit', category.id)}>
                            <Pencil className="h-4 w-4" />
                        </Link>
                    </Button>
                    <ConfirmDelete
                        title="Delete category?"
                        description={`"${category.name}" will be removed from the catalog. Sub-categories and services must be moved first.`}
                        deleteUrl={route('admin.categories.destroy', category.id)}
                    />
                </div>
            </TableCell>
        </TableRow>
    );
}

export default function CategoriesIndex({ categories }: CategoriesIndexProps) {
    const { errors } = usePage().props;

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Categories" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Categories</h1>
                    <Button asChild>
                        <Link href={route('admin.categories.create')}>
                            <Plus className="h-4 w-4" />
                            New category
                        </Link>
                    </Button>
                </div>

                <InputError message={(errors as Record<string, string>).category} />

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Slug</TableHead>
                                <TableHead className="text-center">Services</TableHead>
                                <TableHead className="text-center">Sort</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {categories.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={6} className="text-muted-foreground py-8 text-center">
                                        No categories yet. Create the first one.
                                    </TableCell>
                                </TableRow>
                            )}
                            {categories.map((category) => (
                                <>
                                    <CategoryRow key={category.id} category={category} />
                                    {category.children?.map((childCategory) => <CategoryRow key={childCategory.id} category={childCategory} child />)}
                                </>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </AdminLayout>
    );
}
