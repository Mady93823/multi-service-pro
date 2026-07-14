import SaveButton from '@/components/admin/settings/save-button';
import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTrans } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export interface IntegrationsValues {
    fcm_credentials_set: boolean;
    google_maps_key_set: boolean;
}

type IntegrationsForm = {
    fcm_credentials: string;
    google_maps_key: string;
    remove_fcm_credentials: boolean;
    remove_google_maps_key: boolean;
};

export default function IntegrationsForm({ values }: { values: IntegrationsValues }) {
    const t = useTrans();

    const { data, setData, put, processing, errors } = useForm<IntegrationsForm>({
        // Write-only: blank means "keep what is stored" (M08).
        fcm_credentials: '',
        google_maps_key: '',
        remove_fcm_credentials: false,
        remove_google_maps_key: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('admin.settings.update', 'integrations'), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <p className="text-muted-foreground text-sm">
                {t('All optional. With none of these set, the platform still boots, browses, books and takes cash.')}
            </p>

            <div className="grid gap-2">
                <Label htmlFor="fcm_credentials">{t('Firebase service account (JSON)')}</Label>
                <Textarea
                    id="fcm_credentials"
                    value={data.fcm_credentials}
                    disabled={data.remove_fcm_credentials}
                    onChange={(e) => setData('fcm_credentials', e.target.value)}
                    rows={5}
                    className="font-mono text-xs"
                    placeholder={values.fcm_credentials_set ? t('Saved — leave blank to keep it') : t('Not set')}
                />
                <InputError message={errors.fcm_credentials} />
                <p className="text-muted-foreground text-xs">{t('Turns on push notifications. Until it is set, push simply never sends.')}</p>
                {values.fcm_credentials_set && (
                    <label className="text-muted-foreground flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={data.remove_fcm_credentials}
                            onCheckedChange={(checked) => setData('remove_fcm_credentials', checked === true)}
                        />
                        {t('Remove this secret')}
                    </label>
                )}
            </div>

            <div className="grid gap-2">
                <Label htmlFor="google_maps_key">{t('Google Maps API key')}</Label>
                <Input
                    id="google_maps_key"
                    type="password"
                    autoComplete="off"
                    value={data.google_maps_key}
                    disabled={data.remove_google_maps_key}
                    onChange={(e) => setData('google_maps_key', e.target.value)}
                    placeholder={values.google_maps_key_set ? t('Saved — leave blank to keep it') : t('Not set')}
                />
                <InputError message={errors.google_maps_key} />
                <p className="text-muted-foreground text-xs">{t('Optional. Maps and tracking run on OpenStreetMap without it.')}</p>
                {values.google_maps_key_set && (
                    <label className="text-muted-foreground flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={data.remove_google_maps_key}
                            onCheckedChange={(checked) => setData('remove_google_maps_key', checked === true)}
                        />
                        {t('Remove this secret')}
                    </label>
                )}
            </div>

            <SaveButton processing={processing} />
        </form>
    );
}
