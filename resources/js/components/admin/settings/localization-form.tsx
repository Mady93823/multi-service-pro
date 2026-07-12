import SaveButton from '@/components/admin/settings/save-button';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export interface LocalizationValues {
    currency: string;
    timezone: string;
    locale: string;
}

const timezones: string[] = Intl.supportedValuesOf('timeZone');
const currencies: string[] = Intl.supportedValuesOf('currency');

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
                <Label htmlFor="currency">{t('Currency')}</Label>
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

            <SaveButton processing={processing} />
        </form>
    );
}
