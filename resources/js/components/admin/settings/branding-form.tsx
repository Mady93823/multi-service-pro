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
    favicon_url: string | null;
    /** What the theme falls back to when the colour is cleared. */
    default_color: string;
}

type BrandingForm = {
    _method: string;
    app_name: string;
    primary_color: string;
    logo: File | null;
    remove_logo: boolean;
    favicon: File | null;
    remove_favicon: boolean;
};

/** A handful of ready-made accents, so an operator with no palette still lands somewhere deliberate. */
const PRESETS = ['#4f46e5', '#2563eb', '#0d9488', '#7c3aed', '#db2777', '#ea580c', '#16a34a', '#0f172a'];

export default function BrandingForm({ values }: { values: BrandingValues }) {
    const t = useTrans();

    const { data, setData, post, processing, errors, transform } = useForm<BrandingForm>({
        _method: 'put',
        app_name: values.app_name,
        primary_color: values.primary_color ?? '',
        logo: null,
        remove_logo: false,
        favicon: null,
        remove_favicon: false,
    });

    transform((current) => ({
        ...current,
        primary_color: current.primary_color === '' ? null : current.primary_color,
    }));

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('admin.settings.update', 'branding'), { forceFormData: true, preserveScroll: true });
    };

    const swatch = data.primary_color !== '' ? data.primary_color : values.default_color;

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
                <Label htmlFor="favicon">{t('Favicon')}</Label>
                <p className="text-muted-foreground text-sm">
                    {t('The small icon in the browser tab. Leave it empty and we draw one from your name and colour.')}
                </p>

                {values.favicon_url && !data.remove_favicon ? (
                    <img src={values.favicon_url} alt="" className="h-10 w-10 rounded border object-contain p-1" />
                ) : (
                    // The same mark the site actually serves — this is a live preview,
                    // not a mock-up of one.
                    <span
                        className="flex h-10 w-10 items-center justify-center rounded-xl text-lg font-bold"
                        style={{ backgroundColor: swatch, color: readableOn(swatch) }}
                        aria-hidden
                    >
                        {(data.app_name.trim().charAt(0) || 'U').toUpperCase()}
                    </span>
                )}

                <Input
                    id="favicon"
                    type="file"
                    accept="image/png,image/x-icon,image/svg+xml,image/webp"
                    onChange={(e) => setData('favicon', e.target.files?.[0] ?? null)}
                />
                <InputError message={errors.favicon} />

                {values.favicon_url && (
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox checked={data.remove_favicon} onCheckedChange={(checked) => setData('remove_favicon', checked === true)} />
                        {t('Remove current favicon')}
                    </label>
                )}
            </div>

            <div className="grid gap-2">
                <Label htmlFor="primary_color">{t('Primary color')}</Label>
                <p className="text-muted-foreground text-sm">{t('Used for buttons, links and highlights across the whole platform.')}</p>

                <div className="flex items-center gap-2">
                    <input
                        type="color"
                        aria-label={t('Pick primary color')}
                        value={swatch}
                        onChange={(e) => setData('primary_color', e.target.value)}
                        className="h-9 w-9 cursor-pointer rounded border bg-transparent p-1"
                    />
                    <Input
                        id="primary_color"
                        value={data.primary_color}
                        onChange={(e) => setData('primary_color', e.target.value)}
                        placeholder={t('Theme default')}
                        className="w-40 font-mono"
                    />
                    {data.primary_color !== '' && (
                        <Button type="button" variant="ghost" size="sm" onClick={() => setData('primary_color', '')}>
                            {t('Reset')}
                        </Button>
                    )}
                </div>

                <div className="flex flex-wrap gap-2 pt-1">
                    {PRESETS.map((preset) => (
                        <button
                            key={preset}
                            type="button"
                            aria-label={preset}
                            onClick={() => setData('primary_color', preset)}
                            style={{ backgroundColor: preset }}
                            className={`h-7 w-7 rounded-full ring-offset-2 transition ${
                                data.primary_color.toLowerCase() === preset ? 'ring-foreground ring-2' : 'hover:ring-foreground/30 hover:ring-2'
                            }`}
                        />
                    ))}
                </div>

                <InputError message={errors.primary_color} />
            </div>

            <SaveButton processing={processing} />
        </form>
    );
}

/** Mirrors `App\Support\BrandMark::foregroundFor()` — same rule, both ends. */
function readableOn(hex: string): string {
    if (!/^#[0-9a-fA-F]{6}$/.test(hex)) {
        return '#ffffff';
    }

    const channel = (value: number): number => {
        const c = value / 255;

        return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
    };

    const luminance =
        0.2126 * channel(parseInt(hex.slice(1, 3), 16)) +
        0.7152 * channel(parseInt(hex.slice(3, 5), 16)) +
        0.0722 * channel(parseInt(hex.slice(5, 7), 16));

    return luminance > 0.45 ? '#14141a' : '#ffffff';
}
