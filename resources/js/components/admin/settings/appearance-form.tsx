import SaveButton from '@/components/admin/settings/save-button';
import InputError from '@/components/input-error';
import { MediaPicker } from '@/components/media/media-picker';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useTrans } from '@/lib/i18n';
import { type MediaAsset } from '@/types';
import { useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

export interface AppearanceValues {
    header_variant: string;
    sticky_header: boolean;
    footer_variant: string;
    footer_about: string | null;
    copyright: string | null;
    contact_email: string | null;
    contact_phone: string | null;
    contact_address: string | null;
    login_headline: string | null;
    login_subcopy: string | null;
    login_image_url: string | null;
}

export default function AppearanceForm({ values }: { values: AppearanceValues }) {
    const t = useTrans();
    const [asset, setAsset] = useState<MediaAsset | null>(null);

    const { data, setData, put, processing, errors } = useForm({
        header_variant: values.header_variant,
        sticky_header: values.sticky_header,
        footer_variant: values.footer_variant,
        footer_about: values.footer_about ?? '',
        copyright: values.copyright ?? '',
        contact_email: values.contact_email ?? '',
        contact_phone: values.contact_phone ?? '',
        contact_address: values.contact_address ?? '',
        login_headline: values.login_headline ?? '',
        login_subcopy: values.login_subcopy ?? '',
        login_image_url: values.login_image_url ?? '',
    });

    const pick = (picked: MediaAsset | null) => {
        setAsset(picked);
        setData('login_image_url', picked?.url ?? '');
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('admin.settings.update', 'appearance'), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <div className="grid gap-2">
                <Label htmlFor="header_variant">{t('Header layout')}</Label>
                <Select value={data.header_variant} onValueChange={(value) => setData('header_variant', value)}>
                    <SelectTrigger id="header_variant" className="w-56">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="classic">{t('Classic — links beside the logo')}</SelectItem>
                        <SelectItem value="centered">{t('Centered — links under the logo')}</SelectItem>
                        <SelectItem value="minimal">{t('Minimal — logo and actions only')}</SelectItem>
                    </SelectContent>
                </Select>
                <InputError message={errors.header_variant} />
            </div>

            <label className="flex items-center justify-between gap-4 text-sm">
                <span>
                    <span className="font-medium">{t('Sticky header')}</span>
                    <span className="text-muted-foreground block">{t('Keeps the header visible while the visitor scrolls.')}</span>
                </span>
                <Switch checked={data.sticky_header} onCheckedChange={(checked) => setData('sticky_header', checked)} />
            </label>

            <div className="grid gap-2">
                <Label htmlFor="footer_variant">{t('Footer layout')}</Label>
                <Select value={data.footer_variant} onValueChange={(value) => setData('footer_variant', value)}>
                    <SelectTrigger id="footer_variant" className="w-56">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="columns">{t('Columns — menus, about and contact')}</SelectItem>
                        <SelectItem value="simple">{t('Simple — one row of links')}</SelectItem>
                    </SelectContent>
                </Select>
                <p className="text-muted-foreground text-xs">{t('Footer columns come from the footer menus.')}</p>
                <InputError message={errors.footer_variant} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="footer_about">{t('About blurb')}</Label>
                <Textarea id="footer_about" value={data.footer_about} onChange={(e) => setData('footer_about', e.target.value)} rows={3} />
                <InputError message={errors.footer_about} />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="contact_email">{t('Contact email')}</Label>
                    <Input id="contact_email" type="email" value={data.contact_email} onChange={(e) => setData('contact_email', e.target.value)} />
                    <InputError message={errors.contact_email} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="contact_phone">{t('Contact phone')}</Label>
                    <Input id="contact_phone" value={data.contact_phone} onChange={(e) => setData('contact_phone', e.target.value)} />
                    <InputError message={errors.contact_phone} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="contact_address">{t('Contact address')}</Label>
                <Textarea id="contact_address" value={data.contact_address} onChange={(e) => setData('contact_address', e.target.value)} rows={2} />
                <InputError message={errors.contact_address} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="copyright">{t('Copyright line')}</Label>
                <Input
                    id="copyright"
                    value={data.copyright}
                    onChange={(e) => setData('copyright', e.target.value)}
                    placeholder={t('Leave blank to show the year and site name')}
                />
                <InputError message={errors.copyright} />
            </div>

            <div className="space-y-4 border-t pt-6">
                <p className="text-sm font-medium">{t('Login & registration page')}</p>

                <div className="grid gap-2">
                    <Label htmlFor="login_headline">{t('Headline')}</Label>
                    <Input id="login_headline" value={data.login_headline} onChange={(e) => setData('login_headline', e.target.value)} />
                    <InputError message={errors.login_headline} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="login_subcopy">{t('Sub-copy')}</Label>
                    <Textarea id="login_subcopy" value={data.login_subcopy} onChange={(e) => setData('login_subcopy', e.target.value)} rows={2} />
                    <InputError message={errors.login_subcopy} />
                </div>

                <div className="grid gap-2">
                    <Label>{t('Side image')}</Label>
                    <MediaPicker value={asset} onChange={pick} currentUrl={data.login_image_url || null} error={errors.login_image_url} />
                    {data.login_image_url !== '' && (
                        <Button type="button" variant="ghost" size="sm" className="w-fit" onClick={() => pick(null)}>
                            {t('Remove image')}
                        </Button>
                    )}
                    <p className="text-muted-foreground text-xs">{t('Leaving all three blank keeps the plain, centered login card.')}</p>
                </div>
            </div>

            <SaveButton processing={processing} />
        </form>
    );
}
