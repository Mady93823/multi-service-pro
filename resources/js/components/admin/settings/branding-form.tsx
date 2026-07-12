import SaveButton from '@/components/admin/settings/save-button';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export interface BrandingValues {
    app_name: string;
    primary_color: string | null;
    logo_url: string | null;
}

type BrandingForm = {
    _method: string;
    app_name: string;
    primary_color: string;
    logo: File | null;
    remove_logo: boolean;
};

export default function BrandingForm({ values }: { values: BrandingValues }) {
    const t = useTrans();

    const { data, setData, post, processing, errors, transform } = useForm<BrandingForm>({
        _method: 'put',
        app_name: values.app_name,
        primary_color: values.primary_color ?? '',
        logo: null,
        remove_logo: false,
    });

    transform((current) => ({
        ...current,
        primary_color: current.primary_color === '' ? null : current.primary_color,
    }));

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('admin.settings.update', 'branding'), { forceFormData: true, preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <div className="grid gap-2">
                <Label htmlFor="app_name">{t('Platform name')}</Label>
                <Input id="app_name" value={data.app_name} onChange={(e) => setData('app_name', e.target.value)} required />
                <InputError message={errors.app_name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="logo">{t('Logo')}</Label>
                {values.logo_url && !data.remove_logo && (
                    <img src={values.logo_url} alt="" className="h-12 w-fit rounded border object-contain p-1" />
                )}
                <Input
                    id="logo"
                    type="file"
                    accept="image/png,image/jpeg,image/webp,image/svg+xml"
                    onChange={(e) => setData('logo', e.target.files?.[0] ?? null)}
                />
                <InputError message={errors.logo} />
                {values.logo_url && (
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox checked={data.remove_logo} onCheckedChange={(checked) => setData('remove_logo', checked === true)} />
                        {t('Remove current logo')}
                    </label>
                )}
            </div>

            <div className="grid gap-2">
                <Label htmlFor="primary_color">{t('Primary color')}</Label>
                <div className="flex items-center gap-2">
                    <input
                        type="color"
                        aria-label={t('Pick primary color')}
                        value={data.primary_color !== '' ? data.primary_color : '#171717'}
                        onChange={(e) => setData('primary_color', e.target.value)}
                        className="h-9 w-9 cursor-pointer rounded border bg-transparent p-1"
                    />
                    <Input
                        id="primary_color"
                        value={data.primary_color}
                        onChange={(e) => setData('primary_color', e.target.value)}
                        placeholder={t('Theme default')}
                        className="w-40"
                    />
                    {data.primary_color !== '' && (
                        <Button type="button" variant="ghost" size="sm" onClick={() => setData('primary_color', '')}>
                            {t('Reset')}
                        </Button>
                    )}
                </div>
                <InputError message={errors.primary_color} />
            </div>

            <SaveButton processing={processing} />
        </form>
    );
}
