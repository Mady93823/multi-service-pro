import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Settings', href: '/admin/settings' },
];

type SettingsForm = {
    _method: string;
    app_name: string;
    primary_color: string;
    currency: string;
    timezone: string;
    locale: string;
    logo: File | null;
    remove_logo: boolean;
};

interface SettingsEditProps {
    values: {
        app_name: string;
        primary_color: string | null;
        currency: string;
        timezone: string;
        locale: string;
        logo_url: string | null;
    };
}

const timezones: string[] = Intl.supportedValuesOf('timeZone');
const currencies: string[] = Intl.supportedValuesOf('currency');

export default function SettingsEdit({ values }: SettingsEditProps) {
    const { data, setData, post, processing, errors, transform } = useForm<SettingsForm>({
        _method: 'put',
        app_name: values.app_name,
        primary_color: values.primary_color ?? '',
        currency: values.currency,
        timezone: values.timezone,
        locale: values.locale,
        logo: null,
        remove_logo: false,
    });

    transform((current) => ({
        ...current,
        primary_color: current.primary_color === '' ? null : current.primary_color,
    }));

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('admin.settings.update'), { forceFormData: true, preserveScroll: true });
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Settings" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">Settings</h1>

                <form onSubmit={submit} className="max-w-2xl space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Branding</CardTitle>
                            <CardDescription>Name, logo and accent color shown across the platform.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div className="grid gap-2">
                                <Label htmlFor="app_name">Platform name</Label>
                                <Input id="app_name" value={data.app_name} onChange={(e) => setData('app_name', e.target.value)} required />
                                <InputError message={errors.app_name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="logo">Logo</Label>
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
                                        Remove current logo
                                    </label>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="primary_color">Primary color</Label>
                                <div className="flex items-center gap-2">
                                    <input
                                        type="color"
                                        aria-label="Pick primary color"
                                        value={data.primary_color !== '' ? data.primary_color : '#171717'}
                                        onChange={(e) => setData('primary_color', e.target.value)}
                                        className="h-9 w-9 cursor-pointer rounded border bg-transparent p-1"
                                    />
                                    <Input
                                        id="primary_color"
                                        value={data.primary_color}
                                        onChange={(e) => setData('primary_color', e.target.value)}
                                        placeholder="Theme default"
                                        className="w-40"
                                    />
                                    {data.primary_color !== '' && (
                                        <Button type="button" variant="ghost" size="sm" onClick={() => setData('primary_color', '')}>
                                            Reset
                                        </Button>
                                    )}
                                </div>
                                <InputError message={errors.primary_color} />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Localization</CardTitle>
                            <CardDescription>Currency, timezone and language defaults.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div className="grid gap-2">
                                <Label htmlFor="currency">Currency</Label>
                                <Input
                                    id="currency"
                                    list="currency-options"
                                    value={data.currency}
                                    onChange={(e) => setData('currency', e.target.value.toUpperCase())}
                                    className="w-40"
                                    required
                                />
                                <datalist id="currency-options">
                                    {currencies.map((code) => (
                                        <option key={code} value={code} />
                                    ))}
                                </datalist>
                                <InputError message={errors.currency} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="timezone">Timezone</Label>
                                <Input
                                    id="timezone"
                                    list="timezone-options"
                                    value={data.timezone}
                                    onChange={(e) => setData('timezone', e.target.value)}
                                    className="w-72"
                                    required
                                />
                                <datalist id="timezone-options">
                                    {timezones.map((zone) => (
                                        <option key={zone} value={zone} />
                                    ))}
                                </datalist>
                                <InputError message={errors.timezone} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="locale">Default language code</Label>
                                <Input
                                    id="locale"
                                    value={data.locale}
                                    onChange={(e) => setData('locale', e.target.value)}
                                    className="w-40"
                                    placeholder="en"
                                    required
                                />
                                <InputError message={errors.locale} />
                            </div>
                        </CardContent>
                    </Card>

                    <Button type="submit" disabled={processing}>
                        {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                        Save settings
                    </Button>
                </form>
            </div>
        </AdminLayout>
    );
}
