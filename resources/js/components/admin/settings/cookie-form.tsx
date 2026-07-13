import SaveButton from '@/components/admin/settings/save-button';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useTrans } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export interface CookieValues {
    enabled: boolean;
    message: string | null;
    accept_label: string | null;
    decline_label: string | null;
    policy_slug: string | null;
    /** Published pages the privacy-link picker offers — not a setting. */
    pages: { value: string; label: string }[];
}

export default function CookieForm({ values }: { values: CookieValues }) {
    const pages = values.pages;
    const t = useTrans();

    const { data, setData, put, processing, errors } = useForm({
        enabled: values.enabled,
        message: values.message ?? '',
        accept_label: values.accept_label ?? '',
        decline_label: values.decline_label ?? '',
        policy_slug: values.policy_slug ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('admin.settings.update', 'cookie'), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <label className="flex items-center justify-between gap-4 text-sm">
                <span>
                    <span className="font-medium">{t('Show the cookie banner')}</span>
                    <span className="text-muted-foreground block">{t('The visitor’s choice is stored in their browser only.')}</span>
                </span>
                <Switch checked={data.enabled} onCheckedChange={(checked) => setData('enabled', checked)} />
            </label>

            <div className="grid gap-2">
                <Label htmlFor="message">{t('Banner message')}</Label>
                <Textarea id="message" value={data.message} onChange={(e) => setData('message', e.target.value)} rows={3} />
                <InputError message={errors.message} />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="accept_label">{t('Accept button')}</Label>
                    <Input id="accept_label" value={data.accept_label} onChange={(e) => setData('accept_label', e.target.value)} />
                    <InputError message={errors.accept_label} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="decline_label">{t('Decline button')}</Label>
                    <Input
                        id="decline_label"
                        value={data.decline_label}
                        onChange={(e) => setData('decline_label', e.target.value)}
                        placeholder={t('Leave blank to hide it')}
                    />
                    <InputError message={errors.decline_label} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="policy_slug">{t('Privacy page')}</Label>
                <select
                    id="policy_slug"
                    value={data.policy_slug}
                    onChange={(e) => setData('policy_slug', e.target.value)}
                    className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                >
                    <option value="">{t('No link')}</option>
                    {pages.map((page) => (
                        <option key={page.value} value={page.value}>
                            {page.label}
                        </option>
                    ))}
                </select>
                <InputError message={errors.policy_slug} />
            </div>

            <SaveButton processing={processing} />
        </form>
    );
}
