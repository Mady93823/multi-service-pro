import SaveButton from '@/components/admin/settings/save-button';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { useTrans } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export interface ReviewsValues {
    reviews_enabled: boolean;
    reviews_max_photos: number;
}

export default function ReviewsForm({ values }: { values: ReviewsValues }) {
    const t = useTrans();

    const { data, setData, put, processing, errors } = useForm({ ...values });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('admin.settings.update', 'reviews'), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <label className="flex items-center justify-between gap-4 text-sm">
                <span>
                    <span className="font-medium">{t('Enable reviews')}</span>
                    <span className="text-muted-foreground block">{t('Turning this off hides all reviews and stops new ones.')}</span>
                </span>
                <Switch checked={data.reviews_enabled} onCheckedChange={(checked) => setData('reviews_enabled', checked)} />
            </label>

            <div className="grid gap-2">
                <Label htmlFor="reviews_max_photos">{t('Photos per review')}</Label>
                <Input
                    id="reviews_max_photos"
                    type="number"
                    min={0}
                    max={10}
                    value={data.reviews_max_photos}
                    onChange={(e) => setData('reviews_max_photos', Number(e.target.value))}
                    className="w-40"
                    required
                />
                <p className="text-muted-foreground text-xs">{t('Set to 0 to disable review photos.')}</p>
                <InputError message={errors.reviews_max_photos} />
            </div>

            <SaveButton processing={processing} />
        </form>
    );
}
