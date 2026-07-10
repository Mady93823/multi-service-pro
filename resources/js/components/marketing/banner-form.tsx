import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { useTrans } from '@/lib/i18n';
import { type Banner, type BannerPlacement } from '@/types';
import { Link, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

type BannerFormData = {
    _method?: string;
    title: string;
    link_url: string;
    placement: BannerPlacement;
    sort_order: number;
    starts_at: string;
    ends_at: string;
    is_active: boolean;
    image: File | null;
};

interface BannerFormProps {
    banner?: Banner;
}

export function BannerForm({ banner }: BannerFormProps) {
    const isEdit = banner !== undefined;
    const t = useTrans();

    const { data, setData, post, processing, errors, transform } = useForm<BannerFormData>({
        ...(isEdit ? { _method: 'put' } : {}),
        title: banner?.title ?? '',
        link_url: banner?.link_url ?? '',
        placement: banner?.placement ?? 'home_hero',
        sort_order: banner?.sort_order ?? 0,
        starts_at: banner?.starts_at ?? '',
        ends_at: banner?.ends_at ?? '',
        is_active: banner?.is_active ?? true,
        image: null,
    });

    transform((current) => ({
        ...current,
        link_url: current.link_url !== '' ? current.link_url : null,
        starts_at: current.starts_at !== '' ? current.starts_at : null,
        ends_at: current.ends_at !== '' ? current.ends_at : null,
    }));

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        // Files require multipart, and multipart requires POST + _method.
        if (isEdit) {
            post(route('admin.banners.update', banner.id), { forceFormData: true });
        } else {
            post(route('admin.banners.store'), { forceFormData: true });
        }
    };

    return (
        <form onSubmit={submit} className="max-w-xl space-y-6">
            <div className="grid gap-2">
                <Label htmlFor="title">{t('Title')}</Label>
                <Input id="title" value={data.title} onChange={(e) => setData('title', e.target.value)} required autoFocus />
                <InputError message={errors.title} />
            </div>

            <div className="grid grid-cols-2 gap-4">
                <div className="grid gap-2">
                    <Label htmlFor="placement">{t('Placement')}</Label>
                    <Select value={data.placement} onValueChange={(value) => setData('placement', value as BannerPlacement)}>
                        <SelectTrigger id="placement">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="home_hero">{t('Home hero')}</SelectItem>
                            <SelectItem value="home_strip">{t('Home strip')}</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError message={errors.placement} />
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
                <Label htmlFor="link_url">{t('Link URL')}</Label>
                <Input id="link_url" type="url" value={data.link_url} onChange={(e) => setData('link_url', e.target.value)} placeholder="https://" />
                <InputError message={errors.link_url} />
            </div>

            <div className="grid grid-cols-2 gap-4">
                <div className="grid gap-2">
                    <Label htmlFor="starts_at">{t('Starts')}</Label>
                    <Input id="starts_at" type="datetime-local" value={data.starts_at} onChange={(e) => setData('starts_at', e.target.value)} />
                    <InputError message={errors.starts_at} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="ends_at">{t('Ends')}</Label>
                    <Input id="ends_at" type="datetime-local" value={data.ends_at} onChange={(e) => setData('ends_at', e.target.value)} />
                    <InputError message={errors.ends_at} />
                </div>
            </div>

            <div className="flex items-center gap-3">
                <Switch id="is_active" checked={data.is_active} onCheckedChange={(checked) => setData('is_active', checked)} />
                <Label htmlFor="is_active">{t('Active')}</Label>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="image">{t('Image')}</Label>
                {banner?.image_url && <img src={banner.image_url} alt="" className="h-24 rounded object-cover" />}
                <Input id="image" type="file" accept="image/*" onChange={(e) => setData('image', e.target.files?.[0] ?? null)} />
                <p className="text-muted-foreground text-xs">{t('JPG, PNG or WebP up to 4 MB. Hero banners look best at 1600×500.')}</p>
                <InputError message={errors.image} />
            </div>

            <div className="flex gap-2">
                <Button type="submit" disabled={processing}>
                    {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                    {isEdit ? t('Save changes') : t('Create banner')}
                </Button>
                <Button asChild variant="outline">
                    <Link href={route('admin.banners.index')}>{t('Cancel')}</Link>
                </Button>
            </div>
        </form>
    );
}
