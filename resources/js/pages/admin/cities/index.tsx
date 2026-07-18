import { ConfirmDelete } from '@/components/confirm-delete';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AdminLayout from '@/layouts/admin-layout';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem, type City } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';

interface CitiesIndexProps {
    cities: City[];
}

export default function CitiesIndex({ cities }: CitiesIndexProps) {
    const t = useTrans();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('Cities'), href: '/admin/cities' },
    ];

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Cities')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">{t('Cities')}</h1>
                        <p className="text-muted-foreground text-sm">{t('Zones belong to a city. Switching a city off closes the whole town.')}</p>
                    </div>
                    <Button asChild>
                        <Link href={route('admin.cities.create')}>
                            <Plus className="h-4 w-4" />
                            {t('New city')}
                        </Link>
                    </Button>
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{t('City')}</TableHead>
                                <TableHead>{t('Timezone')}</TableHead>
                                <TableHead className="text-center">{t('Zones')}</TableHead>
                                <TableHead className="text-center">{t('Bookings')}</TableHead>
                                <TableHead>{t('Status')}</TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {cities.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={6} className="text-muted-foreground py-8 text-center">
                                        {t('No cities yet. Add the first town you serve.')}
                                    </TableCell>
                                </TableRow>
                            )}
                            {cities.map((city) => (
                                <TableRow key={city.id}>
                                    <TableCell className="font-medium">
                                        {city.name}
                                        {city.state ? <span className="text-muted-foreground"> · {city.state}</span> : null}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">{city.timezone}</TableCell>
                                    <TableCell className="text-center">
                                        <Link className="underline-offset-4 hover:underline" href={route('admin.zones.index')}>
                                            {city.zones_count ?? 0}
                                        </Link>
                                    </TableCell>
                                    <TableCell className="text-center">{city.bookings_count ?? 0}</TableCell>
                                    <TableCell>
                                        <div className="flex flex-wrap gap-1">
                                            <Badge variant={city.is_active ? 'default' : 'outline'}>
                                                {city.is_active ? t('Active') : t('Hidden')}
                                            </Badge>
                                            {!city.cash_enabled && <Badge variant="secondary">{t('Online only')}</Badge>}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex justify-end gap-1">
                                            <Button asChild variant="ghost" size="icon" aria-label={t('Edit city')}>
                                                <Link href={route('admin.cities.edit', city.id)}>
                                                    <Pencil className="h-4 w-4" />
                                                </Link>
                                            </Button>
                                            <ConfirmDelete
                                                title={t('Delete city?')}
                                                description={t(
                                                    '“:name” can only be deleted while it has no zones. To stop serving a city that has traded, switch it off instead — its bookings stay.',
                                                    { name: city.name },
                                                )}
                                                deleteUrl={route('admin.cities.destroy', city.id)}
                                            />
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </AdminLayout>
    );
}
