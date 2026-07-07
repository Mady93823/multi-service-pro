import { ConfirmDelete } from '@/components/confirm-delete';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import CustomerLayout from '@/layouts/customer-layout';
import { useTrans } from '@/lib/i18n';
import { type Address, type AddressLabel, type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { MapPin, Pencil, Plus, Star } from 'lucide-react';

interface AddressesIndexProps {
    addresses: Address[];
}

export default function AddressesIndex({ addresses }: AddressesIndexProps) {
    const t = useTrans();

    const labelNames: Record<AddressLabel, string> = {
        home: t('Home'),
        work: t('Work'),
        other: t('Other'),
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/dashboard' },
        { title: t('My addresses'), href: '/addresses' },
    ];

    return (
        <CustomerLayout breadcrumbs={breadcrumbs}>
            <Head title={t('My addresses')} />
            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">{t('My addresses')}</h1>
                    <Button asChild>
                        <Link href={route('addresses.create')}>
                            <Plus className="h-4 w-4" />
                            {t('Add address')}
                        </Link>
                    </Button>
                </div>

                {addresses.length === 0 && (
                    <Card>
                        <CardContent className="text-muted-foreground flex flex-col items-center gap-2 py-10 text-center">
                            <MapPin className="h-8 w-8" />
                            <p>{t('No saved addresses yet. Add one to book services faster.')}</p>
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-4 sm:grid-cols-2">
                    {addresses.map((address) => (
                        <Card key={address.id}>
                            <CardHeader className="pb-2">
                                <CardTitle className="flex flex-wrap items-center gap-2 text-base">
                                    {labelNames[address.label]}
                                    {address.is_default && <Badge>{t('Default')}</Badge>}
                                    {address.zone ? (
                                        <Badge variant="secondary">{address.zone.name}</Badge>
                                    ) : (
                                        <Badge variant="outline">{t('Outside service area')}</Badge>
                                    )}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="text-muted-foreground text-sm">
                                <p>{address.line1}</p>
                                {address.line2 && <p>{address.line2}</p>}
                                <p>
                                    {address.city} — {address.postal_code}
                                </p>
                            </CardContent>
                            <CardFooter className="flex gap-1">
                                {!address.is_default && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => router.put(route('addresses.default', address.id), {}, { preserveScroll: true })}
                                    >
                                        <Star className="h-4 w-4" />
                                        {t('Make default')}
                                    </Button>
                                )}
                                <div className="ml-auto flex gap-1">
                                    <Button asChild variant="ghost" size="icon" aria-label={t('Edit address')}>
                                        <Link href={route('addresses.edit', address.id)}>
                                            <Pencil className="h-4 w-4" />
                                        </Link>
                                    </Button>
                                    <ConfirmDelete
                                        title={t('Delete address?')}
                                        description={t('“:line” will be removed from your address book.', { line: address.line1 })}
                                        deleteUrl={route('addresses.destroy', address.id)}
                                    />
                                </div>
                            </CardFooter>
                        </Card>
                    ))}
                </div>
            </div>
        </CustomerLayout>
    );
}
