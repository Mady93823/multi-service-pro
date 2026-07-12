import SaveButton from '@/components/admin/settings/save-button';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { useTrans } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export interface BookingValues {
    booking_code_prefix: string;
    slot_minutes: number;
    day_starts: string;
    day_ends: string;
    lead_time_hours: number;
    max_days_ahead: number;
    job_otp_required: boolean;
    free_cancel_hours: number;
    cancellation_fee_type: string;
    cancellation_fee_value: number;
    reschedule_min_hours: number;
    payment_timeout_minutes: number;
}

export default function BookingForm({ values }: { values: BookingValues }) {
    const t = useTrans();

    const { data, setData, put, processing, errors } = useForm({ ...values });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('admin.settings.update', 'booking'), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <div className="grid gap-2">
                <Label htmlFor="booking_code_prefix">{t('Booking code prefix')}</Label>
                <Input
                    id="booking_code_prefix"
                    value={data.booking_code_prefix}
                    onChange={(e) => setData('booking_code_prefix', e.target.value.toUpperCase())}
                    className="w-40"
                    maxLength={8}
                    required
                />
                <InputError message={errors.booking_code_prefix} />
            </div>

            <div className="grid gap-4 sm:grid-cols-3">
                <div className="grid gap-2">
                    <Label htmlFor="slot_minutes">{t('Slot length (minutes)')}</Label>
                    <Input
                        id="slot_minutes"
                        type="number"
                        min={15}
                        max={480}
                        value={data.slot_minutes}
                        onChange={(e) => setData('slot_minutes', Number(e.target.value))}
                        required
                    />
                    <InputError message={errors.slot_minutes} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="day_starts">{t('Day starts')}</Label>
                    <Input id="day_starts" type="time" value={data.day_starts} onChange={(e) => setData('day_starts', e.target.value)} required />
                    <InputError message={errors.day_starts} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="day_ends">{t('Day ends')}</Label>
                    <Input id="day_ends" type="time" value={data.day_ends} onChange={(e) => setData('day_ends', e.target.value)} required />
                    <InputError message={errors.day_ends} />
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="lead_time_hours">{t('Earliest booking (hours from now)')}</Label>
                    <Input
                        id="lead_time_hours"
                        type="number"
                        min={0}
                        max={72}
                        value={data.lead_time_hours}
                        onChange={(e) => setData('lead_time_hours', Number(e.target.value))}
                        required
                    />
                    <InputError message={errors.lead_time_hours} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="max_days_ahead">{t('Bookable days ahead')}</Label>
                    <Input
                        id="max_days_ahead"
                        type="number"
                        min={1}
                        max={60}
                        value={data.max_days_ahead}
                        onChange={(e) => setData('max_days_ahead', Number(e.target.value))}
                        required
                    />
                    <InputError message={errors.max_days_ahead} />
                </div>
            </div>

            <label className="flex items-center justify-between gap-4 text-sm">
                <span>
                    <span className="font-medium">{t('Require job start code')}</span>
                    <span className="text-muted-foreground block">
                        {t('The professional must enter the customer’s 4-digit code to start the job.')}
                    </span>
                </span>
                <Switch checked={data.job_otp_required} onCheckedChange={(checked) => setData('job_otp_required', checked)} />
            </label>

            <div className="grid gap-4 sm:grid-cols-3">
                <div className="grid gap-2">
                    <Label htmlFor="free_cancel_hours">{t('Free cancellation until (hours before)')}</Label>
                    <Input
                        id="free_cancel_hours"
                        type="number"
                        min={0}
                        max={168}
                        value={data.free_cancel_hours}
                        onChange={(e) => setData('free_cancel_hours', Number(e.target.value))}
                        required
                    />
                    <InputError message={errors.free_cancel_hours} />
                </div>
                <div className="grid gap-2">
                    <Label>{t('Cancellation fee type')}</Label>
                    <Select value={data.cancellation_fee_type} onValueChange={(value) => setData('cancellation_fee_type', value)}>
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="percent">{t('Percent of total')}</SelectItem>
                            <SelectItem value="flat">{t('Flat amount')}</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError message={errors.cancellation_fee_type} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="cancellation_fee_value">{t('Cancellation fee value')}</Label>
                    <Input
                        id="cancellation_fee_value"
                        type="number"
                        min={0}
                        step="0.01"
                        value={data.cancellation_fee_value}
                        onChange={(e) => setData('cancellation_fee_value', Number(e.target.value))}
                        required
                    />
                    <InputError message={errors.cancellation_fee_value} />
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="reschedule_min_hours">{t('Reschedule locked (hours before visit)')}</Label>
                    <Input
                        id="reschedule_min_hours"
                        type="number"
                        min={0}
                        max={168}
                        value={data.reschedule_min_hours}
                        onChange={(e) => setData('reschedule_min_hours', Number(e.target.value))}
                        required
                    />
                    <InputError message={errors.reschedule_min_hours} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="payment_timeout_minutes">{t('Unpaid booking expires after (minutes)')}</Label>
                    <Input
                        id="payment_timeout_minutes"
                        type="number"
                        min={5}
                        max={1440}
                        value={data.payment_timeout_minutes}
                        onChange={(e) => setData('payment_timeout_minutes', Number(e.target.value))}
                        required
                    />
                    <InputError message={errors.payment_timeout_minutes} />
                </div>
            </div>

            <SaveButton processing={processing} />
        </form>
    );
}
