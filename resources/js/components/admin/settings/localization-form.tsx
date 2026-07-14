import SaveButton from '@/components/admin/settings/save-button';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/lib/i18n';
import { Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export interface LocalizationValues {
    timezone: string;
    locale: string;
}

const timezones: string[] = Intl.supportedValuesOf('timeZone');

/** Currency moved to its own screen in M24 — the code and its formatting are edited together. */
export default function LocalizationForm({ values }: { values: LocalizationValues }) {
    const t = useTrans();

    const { data, setData, put, processing, errors } = useForm({ ...values });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('admin.settings.update', 'localization'), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <div className="grid gap-2">
                <Label htmlFor="timezone">{t('Timezone')}</Label>
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
                <p className="text-muted-foreground text-xs">{t('Every booking slot, report and invoice date is shown in this zone.')}</p>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="locale">{t('Default language code')}</Label>
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

            <p className="text-muted-foreground text-xs">
                <Link href={route('admin.settings.edit', 'currency')} className="underline">
                    {t('Currency')}
                </Link>{' '}
                {t('— the currency and the way amounts are printed live on their own screen.')}
            </p>

            <SaveButton processing={processing} />
        </form>
    );
}
