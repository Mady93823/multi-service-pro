import SaveButton from '@/components/admin/settings/save-button';
import InputError from '@/components/input-error';
import { MediaPicker } from '@/components/media/media-picker';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useTrans } from '@/lib/i18n';
import { type MediaAsset } from '@/types';
import { useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

/** A type alias, not an interface: Inertia's useForm needs an implicit index signature. */
export type SeoValues = {
    meta_title: string;
    meta_description: string;
    og_image_url: string;
    sitemap_enabled: boolean;
    schema_enabled: boolean;
    robots_extra: string;
};

export default function SeoForm({ values }: { values: SeoValues }) {
    const t = useTrans();
    const [asset, setAsset] = useState<MediaAsset | null>(null);

    const { data, setData, put, processing, errors } = useForm<SeoValues>({
        meta_title: values.meta_title,
        meta_description: values.meta_description,
        og_image_url: values.og_image_url,
        sitemap_enabled: values.sitemap_enabled,
        schema_enabled: values.schema_enabled,
        robots_extra: values.robots_extra,
    });

    // The OG image is stored as a URL: it is read by crawlers, not by our own
    // media pipeline, so the library gives us a URL and that is all we keep.
    const pick = (picked: MediaAsset | null) => {
        setAsset(picked);
        setData('og_image_url', picked?.url ?? '');
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('admin.settings.update', 'seo'), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <div className="grid gap-2">
                <Label htmlFor="meta_title">{t('Default page title')}</Label>
                <Input id="meta_title" value={data.meta_title} onChange={(e) => setData('meta_title', e.target.value)} maxLength={70} />
                <InputError message={errors.meta_title} />
                <p className="text-muted-foreground text-xs">{t('Used when a page, service or post has no title of its own.')}</p>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="meta_description">{t('Default description')}</Label>
                <Textarea
                    id="meta_description"
                    value={data.meta_description}
                    onChange={(e) => setData('meta_description', e.target.value)}
                    maxLength={200}
                    rows={3}
                />
                <InputError message={errors.meta_description} />
            </div>

            <div className="grid gap-2">
                <Label>{t('Share image')}</Label>
                <MediaPicker value={asset} onChange={pick} currentUrl={data.og_image_url || null} error={errors.og_image_url} />
                <p className="text-muted-foreground text-xs">{t('Shown when someone shares a link to this site.')}</p>
            </div>

            <label className="flex items-center justify-between gap-4 text-sm">
                <span>
                    <span className="font-medium">{t('Sitemap')}</span>
                    <span className="text-muted-foreground block">{t('Publish /sitemap.xml and point robots.txt at it.')}</span>
                </span>
                <Switch checked={data.sitemap_enabled} onCheckedChange={(checked) => setData('sitemap_enabled', checked)} />
            </label>

            <label className="flex items-center justify-between gap-4 text-sm">
                <span>
                    <span className="font-medium">{t('Structured data')}</span>
                    <span className="text-muted-foreground block">{t('schema.org tags for the business, its services and blog posts.')}</span>
                </span>
                <Switch checked={data.schema_enabled} onCheckedChange={(checked) => setData('schema_enabled', checked)} />
            </label>

            <div className="grid gap-2">
                <Label htmlFor="robots_extra">{t('Extra robots.txt rules')}</Label>
                <Textarea
                    id="robots_extra"
                    value={data.robots_extra}
                    onChange={(e) => setData('robots_extra', e.target.value)}
                    rows={4}
                    className="font-mono text-sm"
                />
                <InputError message={errors.robots_extra} />
                <p className="text-muted-foreground text-xs">
                    {t('Appended to the generated file. The admin, provider and checkout paths are already excluded.')}
                </p>
            </div>

            <SaveButton processing={processing} />
        </form>
    );
}
