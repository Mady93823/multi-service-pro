import SaveButton from '@/components/admin/settings/save-button';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { useTrans } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export interface ReferralsValues {
    referrals_enabled: boolean;
    referrals_reward_amount: number;
}

export default function ReferralsForm({ values }: { values: ReferralsValues }) {
    const t = useTrans();

    const { data, setData, put, processing, errors } = useForm({ ...values });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('admin.settings.update', 'referrals'), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <label className="flex items-center justify-between gap-4 text-sm">
                <span>
                    <span className="font-medium">{t('Enable referral program')}</span>
                    <span className="text-muted-foreground block">{t('Hides the refer & earn card and the sign-up code field when off.')}</span>
                </span>
                <Switch checked={data.referrals_enabled} onCheckedChange={(checked) => setData('referrals_enabled', checked)} />
            </label>

            <div className="grid gap-2">
                <Label htmlFor="referrals_reward_amount">{t('Reward amount')}</Label>
                <Input
                    id="referrals_reward_amount"
                    type="number"
                    min={0}
                    step="0.01"
                    value={data.referrals_reward_amount}
                    onChange={(e) => setData('referrals_reward_amount', Number(e.target.value))}
                    className="w-40"
                    required
                />
                <p className="text-muted-foreground text-xs">{t('Set to 0 to pause payouts without hiding the program.')}</p>
                <InputError message={errors.referrals_reward_amount} />
            </div>

            <SaveButton processing={processing} />
        </form>
    );
}
