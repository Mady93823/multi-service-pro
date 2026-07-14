import { ConfirmDelete } from '@/components/confirm-delete';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AdminLayout from '@/layouts/admin-layout';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem, type City, type Zone } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';

interface ZonesIndexProps {
    zones: Zone[];
    cities: City[];
}

export default function ZonesIndex({ zones, cities }: ZonesIndexProps) {
    const t = useTrans();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('Zones'), href: '/admin/zones' },
    ];

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Zones')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">{t('Zones')}</h1>
                    <Button asChild>
                        <Link href={route('admin.zones.create')}>
                            <Plus className="h-4 w-4" />
                            {t('New zone')}
                        </Link>
                    </Button>
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{t('Zone name')}</TableHead>
                                <TableHead>{t('City')}</TableHead>
                                <TableHead className="text-center">{t('Services')}</TableHead>
                                <TableHead className="text-center">{t('Addresses')}</TableHead>
                                <TableHead>{t('Status')}</TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {zones.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={6} className="text-muted-foreground py-8 text-center">
                                        {cities.length === 0
                                            ? t('Add a city first — every zone belongs to one.')
                                            : t('No zones yet. Draw the first service area on the map.')}
                                    </TableCell>
                                </TableRow>
                            )}
                            {zones.map((zone) => (
                                <TableRow key={zone.id}>
                                    <TableCell className="font-medium">{zone.name}</TableCell>
                                    <TableCell className="text-muted-foreground">{zone.city_name}</TableCell>
                                    <TableCell className="text-center">{zone.services_count ?? 0}</TableCell>
                                    <TableCell className="text-center">{zone.addresses_count ?? 0}</TableCell>
                                    <TableCell>
                                        <Badge variant={zone.is_active ? 'default' : 'outline'}>{zone.is_active ? t('Active') : t('Hidden')}</Badge>
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex justify-end gap-1">
                                            <Button asChild variant="ghost" size="icon" aria-label={t('Edit zone')}>
                                                <Link href={route('admin.zones.edit', zone.id)}>
                                                    <Pencil className="h-4 w-4" />
                                                </Link>
                                            </Button>
                                            <ConfirmDelete
                                                title={t('Delete zone?')}
                                                description={t(
                                                    '“:name” will be removed. Addresses inside it are kept and rechecked against the remaining zones.',
                                                    { name: zone.name },
                                                )}
                                                deleteUrl={route('admin.zones.destroy', zone.id)}
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
