import SaveButton from '@/components/admin/settings/save-button';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export interface TrackingValues {
    ping_interval_seconds: number;
    min_move_meters: number;
    max_accuracy_meters: number;
    stale_after_seconds: number;
    points_retention_days: number;
}

export default function TrackingForm({ values }: { values: TrackingValues }) {
    const t = useTrans();

    const { data, setData, put, processing, errors } = useForm({ ...values });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('admin.settings.update', 'tracking'), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="ping_interval_seconds">{t('Position update every (seconds)')}</Label>
                    <Input
                        id="ping_interval_seconds"
                        type="number"
                        min={1}
                        max={60}
                        value={data.ping_interval_seconds}
                        onChange={(e) => setData('ping_interval_seconds', Number(e.target.value))}
                        required
                    />
                    <p className="text-muted-foreground text-xs">{t('Lower is smoother on the map and heavier on the battery.')}</p>
                    <InputError message={errors.ping_interval_seconds} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="min_move_meters">{t('Ignore movement under (metres)')}</Label>
                    <Input
                        id="min_move_meters"
                        type="number"
                        min={0}
                        max={500}
                        value={data.min_move_meters}
                        onChange={(e) => setData('min_move_meters', Number(e.target.value))}
                        required
                    />
                    <InputError message={errors.min_move_meters} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="max_accuracy_meters">{t('Drop fixes less accurate than (metres)')}</Label>
                    <Input
                        id="max_accuracy_meters"
                        type="number"
                        min={10}
                        max={1000}
                        value={data.max_accuracy_meters}
                        onChange={(e) => setData('max_accuracy_meters', Number(e.target.value))}
                        required
                    />
                    <InputError message={errors.max_accuracy_meters} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="stale_after_seconds">{t('Treat the position as stale after (seconds)')}</Label>
                    <Input
                        id="stale_after_seconds"
                        type="number"
                        min={10}
                        max={600}
                        value={data.stale_after_seconds}
                        onChange={(e) => setData('stale_after_seconds', Number(e.target.value))}
                        required
                    />
                    <p className="text-muted-foreground text-xs">{t('The customer’s map falls back to polling past this.')}</p>
                    <InputError message={errors.stale_after_seconds} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="points_retention_days">{t('Keep route history for (days)')}</Label>
                <Input
                    id="points_retention_days"
                    type="number"
                    min={1}
                    max={365}
                    value={data.points_retention_days}
                    onChange={(e) => setData('points_retention_days', Number(e.target.value))}
                    className="w-40"
                    required
                />
                <InputError message={errors.points_retention_days} />
            </div>

            <SaveButton processing={processing} />
        </form>
    );
}
