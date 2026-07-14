import SaveButton from '@/components/admin/settings/save-button';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTrans } from '@/lib/i18n';
import { router, useForm } from '@inertiajs/react';
import { AlertTriangle, Send } from 'lucide-react';
import { FormEventHandler } from 'react';

export interface MailValues {
    host: string;
    port: number;
    username: string;
    encryption: string;
    from_address: string;
    from_name: string;
    password_set: boolean;
    configured: boolean;
}

type MailForm = {
    host: string;
    port: number;
    username: string;
    password: string;
    encryption: string;
    from_address: string;
    from_name: string;
    remove_password: boolean;
};

export default function MailForm({ values }: { values: MailValues }) {
    const t = useTrans();

    const { data, setData, put, processing, errors } = useForm<MailForm>({
        host: values.host,
        port: values.port,
        username: values.username,
        // Write-only: blank means "keep the stored password".
        password: '',
        encryption: values.encryption,
        from_address: values.from_address,
        from_name: values.from_name,
        remove_password: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('admin.settings.update', 'mail'), { preserveScroll: true });
    };

    const sendTest = () => router.post(route('admin.settings.mail.test'), {}, { preserveScroll: true });

    return (
        <form onSubmit={submit} className="space-y-6">
            {!values.configured && (
                <p className="flex items-start gap-2 rounded-md border px-3 py-2 text-sm">
                    <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                    {t('Email is off. Until a host and a from-address are saved, the platform sends no email — everything else still works.')}
                </p>
            )}

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="host">{t('SMTP host')}</Label>
                    <Input id="host" value={data.host} onChange={(e) => setData('host', e.target.value)} placeholder="smtp.example.com" />
                    <InputError message={errors.host} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="port">{t('Port')}</Label>
                    <Input
                        id="port"
                        type="number"
                        min={1}
                        max={65535}
                        value={data.port}
                        onChange={(e) => setData('port', Number(e.target.value))}
                        required
                    />
                    <InputError message={errors.port} />
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="username">{t('Username')}</Label>
                    <Input id="username" value={data.username} onChange={(e) => setData('username', e.target.value)} autoComplete="off" />
                    <InputError message={errors.username} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="password">{t('Password')}</Label>
                    <Input
                        id="password"
                        type="password"
                        autoComplete="off"
                        value={data.password}
                        disabled={data.remove_password}
                        onChange={(e) => setData('password', e.target.value)}
                        placeholder={values.password_set ? t('Saved — leave blank to keep it') : t('Not set')}
                    />
                    <InputError message={errors.password} />
                    {values.password_set && (
                        <label className="text-muted-foreground flex items-center gap-2 text-sm">
                            <Checkbox checked={data.remove_password} onCheckedChange={(checked) => setData('remove_password', checked === true)} />
                            {t('Remove this secret')}
                        </label>
                    )}
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="encryption">{t('Encryption')}</Label>
                <Select value={data.encryption} onValueChange={(value) => setData('encryption', value)}>
                    <SelectTrigger id="encryption" className="w-40">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="tls">TLS</SelectItem>
                        <SelectItem value="ssl">SSL</SelectItem>
                        <SelectItem value="none">{t('None')}</SelectItem>
                    </SelectContent>
                </Select>
                <InputError message={errors.encryption} />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="from_address">{t('From address')}</Label>
                    <Input
                        id="from_address"
                        type="email"
                        value={data.from_address}
                        onChange={(e) => setData('from_address', e.target.value)}
                        placeholder="hello@example.com"
                    />
                    <InputError message={errors.from_address} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="from_name">{t('From name')}</Label>
                    <Input
                        id="from_name"
                        value={data.from_name}
                        onChange={(e) => setData('from_name', e.target.value)}
                        placeholder={t('Your brand name')}
                    />
                    <InputError message={errors.from_name} />
                </div>
            </div>

            <div className="flex items-center gap-3">
                <SaveButton processing={processing} />
                <Button type="button" variant="outline" onClick={sendTest} disabled={!values.configured}>
                    <Send className="h-4 w-4" />
                    {t('Send test email')}
                </Button>
            </div>
            <p className="text-muted-foreground text-xs">
                {t('The test goes to your own address, and is sent right away so you see the real error.')}
            </p>
        </form>
    );
}
