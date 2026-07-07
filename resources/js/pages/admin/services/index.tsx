import { Pagination } from '@/components/catalog/pagination';
import { PriceLabel } from '@/components/catalog/price-label';
import { ConfirmDelete } from '@/components/confirm-delete';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AdminLayout from '@/layouts/admin-layout';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem, type Category, type Paginated, type Service } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ImageIcon, Pencil, Plus, Search } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

const ALL = 'all';

interface ServicesIndexProps {
    services: Paginated<Service>;
    categories: Category[];
    filters: { search: string; category_id: number | null };
}

export default function ServicesIndex({ services, categories, filters }: ServicesIndexProps) {
    const t = useTrans();
    const [search, setSearch] = useState(filters.search);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('Services'), href: '/admin/services' },
    ];
    const [categoryId, setCategoryId] = useState(filters.category_id?.toString() ?? ALL);

    const applyFilters = (nextSearch: string, nextCategoryId: string) => {
        router.get(
            route('admin.services.index'),
            {
                ...(nextSearch !== '' ? { search: nextSearch } : {}),
                ...(nextCategoryId !== ALL ? { category_id: nextCategoryId } : {}),
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const submitSearch: FormEventHandler = (e) => {
        e.preventDefault();
        applyFilters(search, categoryId);
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Services')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">{t('Services')}</h1>
                    <Button asChild>
                        <Link href={route('admin.services.create')}>
                            <Plus className="h-4 w-4" />
                            {t('New service')}
                        </Link>
                    </Button>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <form onSubmit={submitSearch} className="flex items-center gap-2">
                        <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder={t('Search services...')} className="w-64" />
                        <Button type="submit" variant="outline" size="icon" aria-label={t('Search')}>
                            <Search className="h-4 w-4" />
                        </Button>
                    </form>
                    <Select
                        value={categoryId}
                        onValueChange={(value) => {
                            setCategoryId(value);
                            applyFilters(search, value);
                        }}
                    >
                        <SelectTrigger className="w-56">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>{t('All categories')}</SelectItem>
                            {categories.map((category) => (
                                <SelectItem key={category.id} value={category.id.toString()}>
                                    {category.parent_id !== null ? `— ${category.name}` : category.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{t('Service')}</TableHead>
                                <TableHead>{t('Category')}</TableHead>
                                <TableHead>{t('Price')}</TableHead>
                                <TableHead className="text-center">{t('Add-ons')}</TableHead>
                                <TableHead>{t('Status')}</TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {services.data.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={6} className="text-muted-foreground py-8 text-center">
                                        {t('No services found.')}
                                    </TableCell>
                                </TableRow>
                            )}
                            {services.data.map((service) => (
                                <TableRow key={service.id}>
                                    <TableCell>
                                        <div className="flex items-center gap-3">
                                            {service.image_thumb_url ? (
                                                <img src={service.image_thumb_url} alt="" className="h-9 w-9 rounded object-cover" />
                                            ) : (
                                                <div className="bg-muted flex h-9 w-9 items-center justify-center rounded">
                                                    <ImageIcon className="text-muted-foreground/50 h-4 w-4" />
                                                </div>
                                            )}
                                            <div>
                                                <span className="font-medium">{service.name}</span>
                                                {service.is_featured && (
                                                    <Badge variant="secondary" className="ml-2">
                                                        {t('Featured')}
                                                    </Badge>
                                                )}
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">{service.category?.name}</TableCell>
                                    <TableCell>
                                        <PriceLabel price={service.price} pricingType={service.pricing_type} />
                                    </TableCell>
                                    <TableCell className="text-center">{service.addons_count ?? 0}</TableCell>
                                    <TableCell>
                                        <Badge variant={service.is_active ? 'default' : 'outline'}>{service.is_active ? t('Active') : t('Hidden')}</Badge>
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex justify-end gap-1">
                                            <Button asChild variant="ghost" size="icon" aria-label={t('Edit service')}>
                                                <Link href={route('admin.services.edit', service.id)}>
                                                    <Pencil className="h-4 w-4" />
                                                </Link>
                                            </Button>
                                            <ConfirmDelete
                                                title={t('Delete service?')}
                                                description={t('“:name” will be removed from the catalog.', { name: service.name })}
                                                deleteUrl={route('admin.services.destroy', service.id)}
                                            />
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                <Pagination meta={services.meta} links={services.links} />
            </div>
        </AdminLayout>
    );
}
