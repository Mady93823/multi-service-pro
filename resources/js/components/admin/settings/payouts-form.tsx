import SaveButton from '@/components/admin/settings/save-button';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { useTrans } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export interface PayoutsValues {
    commission_percent: number;
    payouts_enabled: boolean;
    payout_min_amount: number;
    payout_hold_days: number;
}

export default function PayoutsForm({ values }: { values: PayoutsValues }) {
    const t = useTrans();

    const { data, setData, put, processing, errors } = useForm({ ...values });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('admin.settings.update', 'payouts'), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <div className="grid gap-2">
                <Label htmlFor="commission_percent">{t('Commission (%)')}</Label>
                <Input
                    id="commission_percent"
                    type="number"
                    min={0}
                    max={100}
                    step="0.01"
                    value={data.commission_percent}
                    onChange={(e) => setData('commission_percent', Number(e.target.value))}
                    className="w-40"
                    required
                />
                <InputError message={errors.commission_percent} />
            </div>

            <label className="flex items-center justify-between gap-4 text-sm">
                <span>
                    <span className="font-medium">{t('Payout requests')}</span>
                    <span className="text-muted-foreground block">{t('Let professionals withdraw their available balance.')}</span>
                </span>
                <Switch checked={data.payouts_enabled} onCheckedChange={(checked) => setData('payouts_enabled', checked)} />
            </label>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="payout_min_amount">{t('Minimum payout amount')}</Label>
                    <Input
                        id="payout_min_amount"
                        type="number"
                        min={0}
                        step="0.01"
                        value={data.payout_min_amount}
                        onChange={(e) => setData('payout_min_amount', Number(e.target.value))}
                        required
                    />
                    <InputError message={errors.payout_min_amount} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="payout_hold_days">{t('Hold earnings for (days)')}</Label>
                    <Input
                        id="payout_hold_days"
                        type="number"
                        min={0}
                        max={90}
                        value={data.payout_hold_days}
                        onChange={(e) => setData('payout_hold_days', Number(e.target.value))}
                        required
                    />
                    <p className="text-muted-foreground text-xs">{t('The window in which a refund can still cancel a completed job’s earning.')}</p>
                    <InputError message={errors.payout_hold_days} />
                </div>
            </div>

            <SaveButton processing={processing} />
        </form>
    );
}
