import SaveButton from '@/components/admin/settings/save-button';
import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTrans } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export interface SmsValues {
    gateway: string;
    msg91_sender: string;
    msg91_route: string;
    twilio_sid: string;
    twilio_from: string;
    msg91_auth_key_set: boolean;
    twilio_token_set: boolean;
}

type SmsForm = {
    gateway: string;
    msg91_auth_key: string;
    msg91_sender: string;
    msg91_route: string;
    twilio_sid: string;
    twilio_token: string;
    twilio_from: string;
    remove_msg91_auth_key: boolean;
    remove_twilio_token: boolean;
};

type SecretField = 'msg91_auth_key' | 'twilio_token';

export default function SmsForm({ values }: { values: SmsValues }) {
    const t = useTrans();

    const { data, setData, put, processing, errors } = useForm<SmsForm>({
        gateway: values.gateway,
        msg91_auth_key: '',
        msg91_sender: values.msg91_sender,
        msg91_route: values.msg91_route,
        twilio_sid: values.twilio_sid,
        twilio_token: '',
        twilio_from: values.twilio_from,
        remove_msg91_auth_key: false,
        remove_twilio_token: false,
    });

    const secretField = (field: SecretField, label: string, isSet: boolean) => {
        const removeField = `remove_${field}` as const;
        const removing = data[removeField];

        return (
            <div className="grid gap-2">
                <Label htmlFor={field}>{label}</Label>
                <Input
                    id={field}
                    type="password"
                    autoComplete="off"
                    value={data[field]}
                    disabled={removing}
                    onChange={(e) => setData(field, e.target.value)}
                    placeholder={isSet ? t('Saved — leave blank to keep it') : t('Not set')}
                />
                <InputError message={errors[field]} />
                {isSet && (
                    <label className="text-muted-foreground flex items-center gap-2 text-sm">
                        <Checkbox checked={removing} onCheckedChange={(checked) => setData(removeField, checked === true)} />
                        {t('Remove this secret')}
                    </label>
                )}
            </div>
        );
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('admin.settings.update', 'sms'), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <div className="grid gap-2">
                <Label htmlFor="gateway">{t('Gateway')}</Label>
                <Select value={data.gateway} onValueChange={(value) => setData('gateway', value)}>
                    <SelectTrigger id="gateway" className="w-48">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="none">{t('No SMS')}</SelectItem>
                        <SelectItem value="msg91">MSG91</SelectItem>
                        <SelectItem value="twilio">Twilio</SelectItem>
                    </SelectContent>
                </Select>
                <InputError message={errors.gateway} />
                <p className="text-muted-foreground text-xs">
                    {t('A message is only sent when the chosen gateway is fully configured. Nothing breaks while it is not.')}
                </p>
            </div>

            {data.gateway === 'msg91' && (
                <div className="space-y-4 rounded-lg border p-4">
                    <h3 className="text-sm font-medium">MSG91</h3>
                    {secretField('msg91_auth_key', t('Auth key'), values.msg91_auth_key_set)}
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="msg91_sender">{t('Sender ID')}</Label>
                            <Input id="msg91_sender" value={data.msg91_sender} onChange={(e) => setData('msg91_sender', e.target.value)} />
                            <InputError message={errors.msg91_sender} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="msg91_route">{t('Route')}</Label>
                            <Input id="msg91_route" value={data.msg91_route} onChange={(e) => setData('msg91_route', e.target.value)} />
                            <InputError message={errors.msg91_route} />
                        </div>
                    </div>
                </div>
            )}

            {data.gateway === 'twilio' && (
                <div className="space-y-4 rounded-lg border p-4">
                    <h3 className="text-sm font-medium">Twilio</h3>
                    <div className="grid gap-2">
                        <Label htmlFor="twilio_sid">{t('Account SID')}</Label>
                        <Input id="twilio_sid" value={data.twilio_sid} onChange={(e) => setData('twilio_sid', e.target.value)} autoComplete="off" />
                        <InputError message={errors.twilio_sid} />
                    </div>
                    {secretField('twilio_token', t('Auth token'), values.twilio_token_set)}
                    <div className="grid gap-2">
                        <Label htmlFor="twilio_from">{t('From number')}</Label>
                        <Input
                            id="twilio_from"
                            value={data.twilio_from}
                            onChange={(e) => setData('twilio_from', e.target.value)}
                            placeholder="+15551234567"
                        />
                        <InputError message={errors.twilio_from} />
                    </div>
                </div>
            )}

            <SaveButton processing={processing} />
        </form>
    );
}
