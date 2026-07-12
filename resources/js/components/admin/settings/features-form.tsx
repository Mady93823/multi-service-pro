import SaveButton from '@/components/admin/settings/save-button';
import { Switch } from '@/components/ui/switch';
import { useTrans } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export interface FeaturesValues {
    otp_required: boolean;
}

export default function FeaturesForm({ values }: { values: FeaturesValues }) {
    const t = useTrans();

    const { data, setData, put, processing } = useForm({ ...values });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('admin.settings.update', 'features'), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <label className="flex items-center justify-between gap-4 text-sm">
                <span>
                    <span className="font-medium">{t('Require phone OTP at sign-up')}</span>
                    <span className="text-muted-foreground block">
                        {t('Needs Firebase phone authentication configured. Leave off to sign customers up with a password only.')}
                    </span>
                </span>
                <Switch checked={data.otp_required} onCheckedChange={(checked) => setData('otp_required', checked)} />
            </label>

            <SaveButton processing={processing} />
        </form>
    );
}
