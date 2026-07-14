import SaveButton from '@/components/admin/settings/save-button';
import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { useTrans } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { FormEventHandler } from 'react';

export interface RecaptchaValues {
    site_key: string;
    secret_key_set: boolean;
    on_register: boolean;
    on_login: boolean;
    on_contact: boolean;
    on_ticket: boolean;
}

type RecaptchaForm = {
    site_key: string;
    secret_key: string;
    remove_secret_key: boolean;
    on_register: boolean;
    on_login: boolean;
    on_contact: boolean;
    on_ticket: boolean;
};

export default function RecaptchaForm({ values }: { values: RecaptchaValues }) {
    const t = useTrans();

    const { data, setData, put, processing, errors } = useForm<RecaptchaForm>({
        site_key: values.site_key,
        secret_key: '',
        remove_secret_key: false,
        on_register: values.on_register,
        on_login: values.on_login,
        on_contact: values.on_contact,
        on_ticket: values.on_ticket,
    });

    const configured = data.site_key !== '' && (values.secret_key_set || data.secret_key !== '');

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('admin.settings.update', 'recaptcha'), { preserveScroll: true });
    };

    const formSwitch = (field: 'on_register' | 'on_login' | 'on_contact' | 'on_ticket', label: string) => (
        <label className="flex items-center justify-between gap-4 text-sm">
            <span className="font-medium">{label}</span>
            <Switch checked={data[field]} onCheckedChange={(checked) => setData(field, checked)} />
        </label>
    );

    return (
        <form onSubmit={submit} className="space-y-6">
            {!configured && (
                <p className="flex items-start gap-2 rounded-md border px-3 py-2 text-sm">
                    <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                    {t(
                        'Without both keys, reCaptcha stays off no matter which forms are ticked — a visitor is never asked to prove themselves to a service you have not set up.',
                    )}
                </p>
            )}

            <div className="grid gap-2">
                <Label htmlFor="site_key">{t('Site key')}</Label>
                <Input id="site_key" value={data.site_key} onChange={(e) => setData('site_key', e.target.value)} autoComplete="off" />
                <InputError message={errors.site_key} />
                <p className="text-muted-foreground text-xs">{t('Public by design — it is sent to the browser.')}</p>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="secret_key">{t('Secret key')}</Label>
                <Input
                    id="secret_key"
                    type="password"
                    autoComplete="off"
                    value={data.secret_key}
                    disabled={data.remove_secret_key}
                    onChange={(e) => setData('secret_key', e.target.value)}
                    placeholder={values.secret_key_set ? t('Saved — leave blank to keep it') : t('Not set')}
                />
                <InputError message={errors.secret_key} />
                {values.secret_key_set && (
                    <label className="text-muted-foreground flex items-center gap-2 text-sm">
                        <Checkbox checked={data.remove_secret_key} onCheckedChange={(checked) => setData('remove_secret_key', checked === true)} />
                        {t('Remove this secret')}
                    </label>
                )}
            </div>

            <div className="space-y-4 rounded-lg border p-4">
                <h3 className="text-sm font-medium">{t('Protect these forms')}</h3>
                {formSwitch('on_register', t('Sign up'))}
                {formSwitch('on_login', t('Log in'))}
                {formSwitch('on_contact', t('Contact form'))}
                {formSwitch('on_ticket', t('New support ticket'))}
            </div>

            <SaveButton processing={processing} />
        </form>
    );
}
