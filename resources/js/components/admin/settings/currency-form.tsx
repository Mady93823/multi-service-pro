import SaveButton from '@/components/admin/settings/save-button';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { formatMoney } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

/** A type alias, not an interface: Inertia's useForm needs an implicit index signature. */
export type CurrencyValues = {
    code: string;
    symbol: string;
    position: string;
    decimals: number;
    grouping: string;
};

export default function CurrencyForm({ values }: { values: CurrencyValues }) {
    const t = useTrans();

    const { data, setData, put, processing, errors } = useForm<CurrencyValues>({
        code: values.code,
        symbol: values.symbol,
        position: values.position,
        decimals: values.decimals,
        grouping: values.grouping,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('admin.settings.update', 'currency'), { preserveScroll: true });
    };

    // The same formatter the whole storefront uses, so the preview cannot lie.
    const preview = formatMoney(123456.5, {
        currency: data.code,
        locale: 'en',
        timezone: 'UTC',
        symbol: data.symbol,
        position: data.position,
        decimals: data.decimals,
        grouping: data.grouping,
    });

    return (
        <form onSubmit={submit} className="space-y-6">
            <p className="text-muted-foreground text-sm">
                {t('One currency per install. This decides how amounts are printed — it never converts anything.')}
            </p>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="code">{t('Currency code')}</Label>
                    <Input id="code" value={data.code} onChange={(e) => setData('code', e.target.value.toUpperCase())} maxLength={3} required />
                    <InputError message={errors.code} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="symbol">{t('Symbol')}</Label>
                    <Input id="symbol" value={data.symbol} onChange={(e) => setData('symbol', e.target.value)} maxLength={5} required />
                    <InputError message={errors.symbol} />
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-3">
                <div className="grid gap-2">
                    <Label htmlFor="position">{t('Symbol position')}</Label>
                    <Select value={data.position} onValueChange={(value) => setData('position', value)}>
                        <SelectTrigger id="position">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="before">{t('Before the amount')}</SelectItem>
                            <SelectItem value="after">{t('After the amount')}</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError message={errors.position} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="decimals">{t('Decimals')}</Label>
                    <Select value={String(data.decimals)} onValueChange={(value) => setData('decimals', Number(value))}>
                        <SelectTrigger id="decimals">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="0">0</SelectItem>
                            <SelectItem value="1">1</SelectItem>
                            <SelectItem value="2">2</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError message={errors.decimals} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="grouping">{t('Digit grouping')}</Label>
                    <Select value={data.grouping} onValueChange={(value) => setData('grouping', value)}>
                        <SelectTrigger id="grouping">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="indian">{t('Indian (1,00,000)')}</SelectItem>
                            <SelectItem value="western">{t('Western (100,000)')}</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError message={errors.grouping} />
                </div>
            </div>

            <div className="rounded-lg border p-4">
                <p className="text-muted-foreground text-xs">{t('Preview')}</p>
                <p className="text-lg font-semibold">{preview}</p>
            </div>

            <SaveButton processing={processing} />
        </form>
    );
}
