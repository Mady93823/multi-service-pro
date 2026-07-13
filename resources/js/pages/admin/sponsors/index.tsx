import InputError from '@/components/input-error';
import { MediaPicker } from '@/components/media/media-picker';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import AdminLayout from '@/layouts/admin-layout';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem, type MediaAsset, type Sponsor } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

export default function SponsorsIndex({ sponsors }: { sponsors: Sponsor[] }) {
    const t = useTrans();
    const [editing, setEditing] = useState<Sponsor | null | undefined>(undefined);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('Sponsors'), href: '/admin/sponsors' },
    ];

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Sponsors')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">{t('Sponsors')}</h1>
                    <Button onClick={() => setEditing(null)}>
                        <Plus className="h-4 w-4" />
                        {t('Add sponsor')}
                    </Button>
                </div>
                <p className="text-muted-foreground text-sm">{t('Partner logos shown in the storefront strip.')}</p>

                {sponsors.length === 0 ? (
                    <div className="text-muted-foreground rounded-xl border border-dashed py-16 text-center text-sm">{t('No sponsors yet.')}</div>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {sponsors.map((sponsor) => (
                            <Card key={sponsor.id}>
                                <CardContent className="space-y-3 pt-6">
                                    {sponsor.logo_url !== null && <img src={sponsor.logo_url} alt="" className="h-10 object-contain" />}
                                    <p className="truncate text-sm font-medium">{sponsor.name}</p>
                                    <div className="flex items-center justify-between">
                                        {sponsor.is_active ? (
                                            <Badge variant="secondary">{t('Live')}</Badge>
                                        ) : (
                                            <Badge variant="outline">{t('Hidden')}</Badge>
                                        )}
                                        <div className="flex gap-1">
                                            <Button variant="ghost" size="icon" aria-label={t('Edit')} onClick={() => setEditing(sponsor)}>
                                                <Pencil className="h-4 w-4" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={t('Delete')}
                                                onClick={() => router.delete(route('admin.sponsors.destroy', sponsor.id), { preserveScroll: true })}
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>

            {editing !== undefined && <SponsorDialog key={editing?.id ?? 'new'} sponsor={editing} onClose={() => setEditing(undefined)} />}
        </AdminLayout>
    );
}

function SponsorDialog({ sponsor, onClose }: { sponsor: Sponsor | null; onClose: () => void }) {
    const t = useTrans();
    const [asset, setAsset] = useState<MediaAsset | null>(null);

    const { data, setData, post, put, processing, errors } = useForm({
        name: sponsor?.name ?? '',
        link_url: sponsor?.link_url ?? '',
        sort_order: sponsor?.sort_order ?? 0,
        is_active: sponsor?.is_active ?? true,
        media_asset_id: null as number | null,
    });

    const pick = (picked: MediaAsset | null) => {
        setAsset(picked);
        setData('media_asset_id', picked?.id ?? null);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        const options = { preserveScroll: true, onSuccess: () => onClose() };

        if (sponsor === null) {
            post(route('admin.sponsors.store'), options);
        } else {
            put(route('admin.sponsors.update', sponsor.id), options);
        }
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{sponsor === null ? t('Add sponsor') : t('Edit sponsor')}</DialogTitle>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="name">{t('Name')}</Label>
                        <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="link_url">{t('Link')}</Label>
                        <Input
                            id="link_url"
                            type="url"
                            value={data.link_url}
                            onChange={(e) => setData('link_url', e.target.value)}
                            placeholder="https://"
                        />
                        <InputError message={errors.link_url} />
                    </div>

                    <div className="grid gap-2">
                        <Label>{t('Logo')}</Label>
                        <MediaPicker value={asset} onChange={pick} currentUrl={sponsor?.logo_url ?? null} error={errors.media_asset_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="sort_order">{t('Sort order')}</Label>
                        <Input
                            id="sort_order"
                            type="number"
                            min={0}
                            value={data.sort_order}
                            onChange={(e) => setData('sort_order', Number(e.target.value))}
                            className="w-32"
                        />
                        <InputError message={errors.sort_order} />
                    </div>

                    <label className="flex items-center justify-between gap-4 text-sm">
                        <span className="font-medium">{t('Show on the storefront')}</span>
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
