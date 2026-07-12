import SaveButton from '@/components/admin/settings/save-button';
import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { useTrans } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export interface PaymentsValues {
    tax_label: string;
    tax_percent: number;
    pay_after_service: boolean;
    wallet_enabled: boolean;
    razorpay_key_id: string;
    stripe_publishable_key: string;
    razorpay_key_secret_set: boolean;
    razorpay_webhook_secret_set: boolean;
    stripe_secret_key_set: boolean;
    stripe_webhook_secret_set: boolean;
}

/** The four write-only secrets: the server sends `*_set`, never the value. */
type SecretField = 'razorpay_key_secret' | 'razorpay_webhook_secret' | 'stripe_secret_key' | 'stripe_webhook_secret';

type PaymentsForm = {
    tax_label: string;
    tax_percent: number;
    pay_after_service: boolean;
    wallet_enabled: boolean;
    razorpay_key_id: string;
    stripe_publishable_key: string;
    razorpay_key_secret: string;
    razorpay_webhook_secret: string;
    stripe_secret_key: string;
    stripe_webhook_secret: string;
    remove_razorpay_key_secret: boolean;
    remove_razorpay_webhook_secret: boolean;
    remove_stripe_secret_key: boolean;
    remove_stripe_webhook_secret: boolean;
};

export default function PaymentsForm({ values }: { values: PaymentsValues }) {
    const t = useTrans();

    const { data, setData, put, processing, errors } = useForm<PaymentsForm>({
        tax_label: values.tax_label,
        tax_percent: values.tax_percent,
        pay_after_service: values.pay_after_service,
        wallet_enabled: values.wallet_enabled,
        razorpay_key_id: values.razorpay_key_id,
        stripe_publishable_key: values.stripe_publishable_key,
        // Secrets start blank on every load — blank means "keep what is stored".
        razorpay_key_secret: '',
        razorpay_webhook_secret: '',
        stripe_secret_key: '',
        stripe_webhook_secret: '',
        remove_razorpay_key_secret: false,
        remove_razorpay_webhook_secret: false,
        remove_stripe_secret_key: false,
        remove_stripe_webhook_secret: false,
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
        put(route('admin.settings.update', 'payments'), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="tax_label">{t('Tax label')}</Label>
                    <Input id="tax_label" value={data.tax_label} onChange={(e) => setData('tax_label', e.target.value)} maxLength={20} required />
                    <InputError message={errors.tax_label} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="tax_percent">{t('Tax percent')}</Label>
                    <Input
                        id="tax_percent"
                        type="number"
                        min={0}
                        max={100}
                        step="0.01"
                        value={data.tax_percent}
                        onChange={(e) => setData('tax_percent', Number(e.target.value))}
                        required
                    />
                    <InputError message={errors.tax_percent} />
                </div>
            </div>

            <label className="flex items-center justify-between gap-4 text-sm">
                <span>
                    <span className="font-medium">{t('Pay after service')}</span>
                    <span className="text-muted-foreground block">{t('Let customers book now and pay the professional afterwards.')}</span>
                </span>
                <Switch checked={data.pay_after_service} onCheckedChange={(checked) => setData('pay_after_service', checked)} />
            </label>

            <label className="flex items-center justify-between gap-4 text-sm">
                <span>
                    <span className="font-medium">{t('Wallet payments')}</span>
                    <span className="text-muted-foreground block">
                        {t('Let customers pay from their wallet balance. Refunds land in the wallet either way.')}
                    </span>
                </span>
                <Switch checked={data.wallet_enabled} onCheckedChange={(checked) => setData('wallet_enabled', checked)} />
            </label>

            <div className="space-y-4 rounded-lg border p-4">
                <h3 className="text-sm font-medium">{t('Razorpay')}</h3>
                <div className="grid gap-2">
                    <Label htmlFor="razorpay_key_id">{t('Key ID')}</Label>
                    <Input
                        id="razorpay_key_id"
                        value={data.razorpay_key_id}
                        onChange={(e) => setData('razorpay_key_id', e.target.value)}
                        autoComplete="off"
                    />
                    <InputError message={errors.razorpay_key_id} />
                </div>
                {secretField('razorpay_key_secret', t('Key secret'), values.razorpay_key_secret_set)}
                {secretField('razorpay_webhook_secret', t('Webhook secret'), values.razorpay_webhook_secret_set)}
            </div>

            <div className="space-y-4 rounded-lg border p-4">
                <h3 className="text-sm font-medium">{t('Stripe')}</h3>
                <div className="grid gap-2">
                    <Label htmlFor="stripe_publishable_key">{t('Publishable key')}</Label>
                    <Input
                        id="stripe_publishable_key"
                        value={data.stripe_publishable_key}
                        onChange={(e) => setData('stripe_publishable_key', e.target.value)}
                        autoComplete="off"
                    />
                    <InputError message={errors.stripe_publishable_key} />
                </div>
                {secretField('stripe_secret_key', t('Secret key'), values.stripe_secret_key_set)}
                {secretField('stripe_webhook_secret', t('Webhook secret'), values.stripe_webhook_secret_set)}
            </div>

            <SaveButton processing={processing} />
        </form>
    );
}
