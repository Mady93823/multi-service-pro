import SaveButton from '@/components/admin/settings/save-button';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

/** A type alias, not an interface: Inertia's useForm needs an implicit index signature, and an interface has none. */
export type AnalyticsValues = {
    ga4_id: string;
    gtm_id: string;
    meta_pixel_id: string;
};

export default function AnalyticsForm({ values }: { values: AnalyticsValues }) {
    const t = useTrans();

    const { data, setData, put, processing, errors } = useForm<AnalyticsValues>({
        ga4_id: values.ga4_id,
        gtm_id: values.gtm_id,
        meta_pixel_id: values.meta_pixel_id,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('admin.settings.update', 'analytics'), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <p className="text-muted-foreground text-sm">
                {t('Measurement IDs only — the tags themselves ship with the app. Nothing loads until a visitor accepts the cookie banner.')}
            </p>

            <div className="grid gap-2">
                <Label htmlFor="ga4_id">{t('Google Analytics 4')}</Label>
                <Input id="ga4_id" value={data.ga4_id} onChange={(e) => setData('ga4_id', e.target.value)} placeholder="G-XXXXXXXXXX" />
                <InputError message={errors.ga4_id} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="gtm_id">{t('Google Tag Manager')}</Label>
                <Input id="gtm_id" value={data.gtm_id} onChange={(e) => setData('gtm_id', e.target.value)} placeholder="GTM-XXXXXXX" />
                <InputError message={errors.gtm_id} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="meta_pixel_id">{t('Meta Pixel')}</Label>
                <Input
                    id="meta_pixel_id"
                    value={data.meta_pixel_id}
                    onChange={(e) => setData('meta_pixel_id', e.target.value)}
                    placeholder="123456789012345"
                />
                <InputError message={errors.meta_pixel_id} />
            </div>

            <SaveButton processing={processing} />
        </form>
    );
}
