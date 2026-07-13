import InputError from '@/components/input-error';
import { MediaPicker } from '@/components/media/media-picker';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import AdminLayout from '@/layouts/admin-layout';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem, type MediaAsset, type Testimonial } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Star, Trash2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

export default function TestimonialsIndex({ testimonials }: { testimonials: Testimonial[] }) {
    const t = useTrans();
    const [editing, setEditing] = useState<Testimonial | null | undefined>(undefined);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('Testimonials'), href: '/admin/testimonials' },
    ];

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Testimonials')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">{t('Testimonials')}</h1>
                    <Button onClick={() => setEditing(null)}>
                        <Plus className="h-4 w-4" />
                        {t('Add testimonial')}
                    </Button>
                </div>
                <p className="text-muted-foreground text-sm">
                    {t('Shown on the storefront home. A real review can be promoted from the Reviews queue in one click.')}
                </p>

                {testimonials.length === 0 ? (
                    <div className="text-muted-foreground rounded-xl border border-dashed py-16 text-center text-sm">{t('No testimonials yet.')}</div>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {testimonials.map((testimonial) => (
                            <Card key={testimonial.id}>
                                <CardContent className="space-y-3 pt-6">
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="flex items-center gap-3">
                                            {testimonial.avatar_url !== null && (
                                                <img src={testimonial.avatar_url} alt="" className="h-9 w-9 rounded-full object-cover" />
                                            )}
                                            <div>
                                                <p className="text-sm font-medium">{testimonial.name}</p>
                                                {testimonial.role !== null && <p className="text-muted-foreground text-xs">{testimonial.role}</p>}
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-1">
                                            <Button variant="ghost" size="icon" aria-label={t('Edit')} onClick={() => setEditing(testimonial)}>
                                                <Pencil className="h-4 w-4" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={t('Delete')}
                                                onClick={() =>
                                                    router.delete(route('admin.testimonials.destroy', testimonial.id), {
                                                        preserveScroll: true,
                                                    })
                                                }
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>

                                    {testimonial.rating !== null && (
                                        <div className="flex gap-0.5">
                                            {Array.from({ length: testimonial.rating }).map((_, index) => (
                                                <Star key={index} className="h-3.5 w-3.5 fill-current text-amber-500" />
                                            ))}
                                        </div>
                                    )}

                                    <p className="text-muted-foreground text-sm">“{testimonial.quote}”</p>

                                    <div className="flex gap-2">
                                        {!testimonial.is_active && <Badge variant="outline">{t('Hidden')}</Badge>}
                                        {testimonial.from_review && <Badge variant="secondary">{t('From a review')}</Badge>}
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>

            {editing !== undefined && <TestimonialDialog key={editing?.id ?? 'new'} testimonial={editing} onClose={() => setEditing(undefined)} />}
        </AdminLayout>
    );
}

function TestimonialDialog({ testimonial, onClose }: { testimonial: Testimonial | null; onClose: () => void }) {
    const t = useTrans();
    const [asset, setAsset] = useState<MediaAsset | null>(null);

    const { data, setData, post, put, processing, errors } = useForm({
        name: testimonial?.name ?? '',
        role: testimonial?.role ?? '',
        quote: testimonial?.quote ?? '',
        rating: testimonial?.rating ?? 5,
        sort_order: testimonial?.sort_order ?? 0,
        is_active: testimonial?.is_active ?? true,
        media_asset_id: null as number | null,
    });

    const pick = (picked: MediaAsset | null) => {
        setAsset(picked);
        setData('media_asset_id', picked?.id ?? null);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        const options = { preserveScroll: true, onSuccess: () => onClose() };

        if (testimonial === null) {
            post(route('admin.testimonials.store'), options);
        } else {
            put(route('admin.testimonials.update', testimonial.id), options);
        }
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{testimonial === null ? t('Add testimonial') : t('Edit testimonial')}</DialogTitle>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="name">{t('Name')}</Label>
                            <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="role">{t('Role')}</Label>
                            <Input id="role" value={data.role} onChange={(e) => setData('role', e.target.value)} />
                            <InputError message={errors.role} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="quote">{t('Quote')}</Label>
                        <Textarea id="quote" value={data.quote} onChange={(e) => setData('quote', e.target.value)} rows={4} required />
                        <InputError message={errors.quote} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="rating">{t('Rating')}</Label>
                            <Input
                                id="rating"
                                type="number"
                                min={1}
                                max={5}
                                value={data.rating}
                                onChange={(e) => setData('rating', Number(e.target.value))}
                            />
                            <InputError message={errors.rating} />
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
                    </div>

                    <div className="grid gap-2">
                        <Label>{t('Photo')}</Label>
                        <MediaPicker value={asset} onChange={pick} currentUrl={testimonial?.avatar_url ?? null} error={errors.media_asset_id} />
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
