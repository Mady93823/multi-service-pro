import InputError from '@/components/input-error';
import { MediaPicker } from '@/components/media/media-picker';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import AdminLayout from '@/layouts/admin-layout';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem, type MediaAsset, type Popup } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface Option {
    value: string;
    label: string;
}

export default function PopupsIndex({ popups, audiences }: { popups: Popup[]; audiences: Option[] }) {
    const t = useTrans();
    const [editing, setEditing] = useState<Popup | null | undefined>(undefined);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('Popups'), href: '/admin/popups' },
    ];

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Popups')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">{t('Popups')}</h1>
                    <Button onClick={() => setEditing(null)}>
                        <Plus className="h-4 w-4" />
                        {t('New popup')}
                    </Button>
                </div>
                <p className="text-muted-foreground text-sm">
                    {t('The newest live popup matching the visitor is shown. Frequency decides how often the same browser sees it again.')}
                </p>

                {popups.length === 0 ? (
                    <div className="text-muted-foreground rounded-xl border border-dashed py-16 text-center text-sm">{t('No popups yet.')}</div>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2">
                        {popups.map((popup) => (
                            <Card key={popup.id}>
                                <CardContent className="space-y-3 pt-6">
                                    <div className="flex items-start justify-between gap-2">
                                        <div>
                                            <p className="font-medium">{popup.title}</p>
                                            <p className="text-muted-foreground text-xs">
                                                {popup.audience_label} · {t('every :days days', { days: String(popup.frequency_days) })}
                                            </p>
                                        </div>
                                        <div className="flex gap-1">
                                            <Button variant="ghost" size="icon" aria-label={t('Edit')} onClick={() => setEditing(popup)}>
                                                <Pencil className="h-4 w-4" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={t('Delete')}
                                                onClick={() => router.delete(route('admin.popups.destroy', popup.id), { preserveScroll: true })}
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>

                                    {popup.body !== null && <p className="text-muted-foreground line-clamp-2 text-sm">{popup.body}</p>}

                                    <div className="flex flex-wrap gap-2">
                                        {popup.is_active ? (
                                            <Badge variant="secondary">{t('Active')}</Badge>
                                        ) : (
                                            <Badge variant="outline">{t('Off')}</Badge>
                                        )}
                                        {popup.starts_at !== null && <Badge variant="outline">{t('From :date', { date: popup.starts_at })}</Badge>}
                                        {popup.ends_at !== null && <Badge variant="outline">{t('Until :date', { date: popup.ends_at })}</Badge>}
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>

            {editing !== undefined && (
                <PopupDialog key={editing?.id ?? 'new'} popup={editing} audiences={audiences} onClose={() => setEditing(undefined)} />
            )}
        </AdminLayout>
    );
}

function PopupDialog({ popup, audiences, onClose }: { popup: Popup | null; audiences: Option[]; onClose: () => void }) {
    const t = useTrans();
    const [asset, setAsset] = useState<MediaAsset | null>(null);

    const { data, setData, post, put, processing, errors } = useForm({
        title: popup?.title ?? '',
        body: popup?.body ?? '',
        link_url: popup?.link_url ?? '',
        link_label: popup?.link_label ?? '',
        audience: popup?.audience ?? 'everyone',
        frequency_days: popup?.frequency_days ?? 7,
        starts_at: popup?.starts_at ?? '',
        ends_at: popup?.ends_at ?? '',
        is_active: popup?.is_active ?? true,
        media_asset_id: null as number | null,
    });

    const pick = (picked: MediaAsset | null) => {
        setAsset(picked);
        setData('media_asset_id', picked?.id ?? null);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        const options = { preserveScroll: true, onSuccess: () => onClose() };

        if (popup === null) {
            post(route('admin.popups.store'), options);
        } else {
            put(route('admin.popups.update', popup.id), options);
        }
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{popup === null ? t('New popup') : t('Edit popup')}</DialogTitle>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="title">{t('Title')}</Label>
                        <Input id="title" value={data.title} onChange={(e) => setData('title', e.target.value)} required />
                        <InputError message={errors.title} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="body">{t('Body (markdown)')}</Label>
                        <Textarea id="body" value={data.body} onChange={(e) => setData('body', e.target.value)} rows={4} />
                        <InputError message={errors.body} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="link_url">{t('Button link')}</Label>
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
                            <Label htmlFor="link_label">{t('Button label')}</Label>
                            <Input id="link_label" value={data.link_label} onChange={(e) => setData('link_label', e.target.value)} />
                            <InputError message={errors.link_label} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label>{t('Image')}</Label>
                        <MediaPicker value={asset} onChange={pick} currentUrl={popup?.image_url ?? null} error={errors.media_asset_id} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="audience">{t('Audience')}</Label>
                            <Select value={data.audience} onValueChange={(value) => setData('audience', value)}>
                                <SelectTrigger id="audience">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {audiences.map((audience) => (
                                        <SelectItem key={audience.value} value={audience.value}>
                                            {audience.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.audience} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="frequency_days">{t('Show again after (days)')}</Label>
                            <Input
                                id="frequency_days"
                                type="number"
                                min={0}
                                max={365}
                                value={data.frequency_days}
                                onChange={(e) => setData('frequency_days', Number(e.target.value))}
                            />
                            <p className="text-muted-foreground text-xs">{t('0 shows it on every visit.')}</p>
                            <InputError message={errors.frequency_days} />
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="starts_at">{t('Starts')}</Label>
                            <Input id="starts_at" type="date" value={data.starts_at} onChange={(e) => setData('starts_at', e.target.value)} />
                            <InputError message={errors.starts_at} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="ends_at">{t('Ends')}</Label>
                            <Input id="ends_at" type="date" value={data.ends_at} onChange={(e) => setData('ends_at', e.target.value)} />
                            <InputError message={errors.ends_at} />
                        </div>
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
