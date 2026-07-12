import SaveButton from '@/components/admin/settings/save-button';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { useTrans } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export interface DispatchValues {
    dispatch_mode: string;
    dispatch_offer_timeout_seconds: number;
    dispatch_max_rounds: number;
    dispatch_auto: boolean;
}

export default function DispatchForm({ values }: { values: DispatchValues }) {
    const t = useTrans();

    const { data, setData, put, processing, errors } = useForm({ ...values });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('admin.settings.update', 'dispatch'), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <div className="grid gap-2">
                <Label>{t('Offer strategy')}</Label>
                <Select value={data.dispatch_mode} onValueChange={(value) => setData('dispatch_mode', value)}>
                    <SelectTrigger className="w-60">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="nearest">{t('Nearest professional first')}</SelectItem>
                        <SelectItem value="broadcast">{t('Broadcast — first to accept wins')}</SelectItem>
                    </SelectContent>
                </Select>
                <InputError message={errors.dispatch_mode} />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="dispatch_offer_timeout_seconds">{t('Offer expires after (seconds)')}</Label>
                    <Input
                        id="dispatch_offer_timeout_seconds"
                        type="number"
                        min={15}
                        max={600}
                        value={data.dispatch_offer_timeout_seconds}
                        onChange={(e) => setData('dispatch_offer_timeout_seconds', Number(e.target.value))}
                        required
                    />
                    <p className="text-muted-foreground text-xs">{t('Re-offering needs a queue worker running.')}</p>
                    <InputError message={errors.dispatch_offer_timeout_seconds} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="dispatch_max_rounds">{t('Maximum offer rounds')}</Label>
                    <Input
                        id="dispatch_max_rounds"
                        type="number"
                        min={1}
                        max={20}
                        value={data.dispatch_max_rounds}
                        onChange={(e) => setData('dispatch_max_rounds', Number(e.target.value))}
                        required
                    />
                    <InputError message={errors.dispatch_max_rounds} />
                </div>
            </div>

            <label className="flex items-center justify-between gap-4 text-sm">
                <span>
                    <span className="font-medium">{t('Dispatch automatically')}</span>
                    <span className="text-muted-foreground block">{t('Off means an admin runs dispatch by hand from the booking screen.')}</span>
                </span>
                <Switch checked={data.dispatch_auto} onCheckedChange={(checked) => setData('dispatch_auto', checked)} />
            </label>

            <SaveButton processing={processing} />
        </form>
    );
}
