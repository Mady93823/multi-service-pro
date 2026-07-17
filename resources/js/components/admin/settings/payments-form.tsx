import SaveButton from '@/components/admin/settings/save-button';
import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useTrans } from '@/lib/i18n';
import { Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export interface PaymentsValues {
    tax_label: string;
    tax_percent: number;
    pay_after_service: boolean;
    wallet_enabled: boolean;
    offline_enabled: boolean;
    offline_instructions: string;
    razorpay_key_id: string;
    stripe_publishable_key: string;
    payu_key: string;
    payu_mode: string;
    paypal_client_id: string;
    paypal_webhook_id: string;
    paypal_mode: string;
    razorpay_key_secret_set: boolean;
    razorpay_webhook_secret_set: boolean;
    stripe_secret_key_set: boolean;
    stripe_webhook_secret_set: boolean;
    payu_salt_set: boolean;
    paypal_client_secret_set: boolean;
}

/** The write-only secrets: the server sends `*_set`, never the value. */
type SecretField =
    | 'razorpay_key_secret'
    | 'razorpay_webhook_secret'
    | 'stripe_secret_key'
    | 'stripe_webhook_secret'
    | 'payu_salt'
    | 'paypal_client_secret';

type PaymentsForm = {
    tax_label: string;
    tax_percent: number;
    pay_after_service: boolean;
    wallet_enabled: boolean;
    offline_enabled: boolean;
    offline_instructions: string;
    razorpay_key_id: string;
    stripe_publishable_key: string;
    payu_key: string;
    payu_mode: string;
    paypal_client_id: string;
    paypal_webhook_id: string;
    paypal_mode: string;
    razorpay_key_secret: string;
    razorpay_webhook_secret: string;
    stripe_secret_key: string;
    stripe_webhook_secret: string;
    payu_salt: string;
    paypal_client_secret: string;
    remove_razorpay_key_secret: boolean;
    remove_razorpay_webhook_secret: boolean;
    remove_stripe_secret_key: boolean;
    remove_stripe_webhook_secret: boolean;
    remove_payu_salt: boolean;
    remove_paypal_client_secret: boolean;
};

export default function PaymentsForm({ values }: { values: PaymentsValues }) {
    const t = useTrans();

    const { data, setData, put, processing, errors } = useForm<PaymentsForm>({
        tax_label: values.tax_label,
        tax_percent: values.tax_percent,
        pay_after_service: values.pay_after_service,
        wallet_enabled: values.wallet_enabled,
        offline_enabled: values.offline_enabled,
        offline_instructions: values.offline_instructions,
        razorpay_key_id: values.razorpay_key_id,
        stripe_publishable_key: values.stripe_publishable_key,
        payu_key: values.payu_key,
        payu_mode: values.payu_mode,
        paypal_client_id: values.paypal_client_id,
        paypal_webhook_id: values.paypal_webhook_id,
        paypal_mode: values.paypal_mode,
        // Secrets start blank on every load — blank means "keep what is stored".
        razorpay_key_secret: '',
        razorpay_webhook_secret: '',
        stripe_secret_key: '',
        stripe_webhook_secret: '',
        payu_salt: '',
        paypal_client_secret: '',
        remove_razorpay_key_secret: false,
        remove_razorpay_webhook_secret: false,
        remove_stripe_secret_key: false,
        remove_stripe_webhook_secret: false,
        remove_payu_salt: false,
        remove_paypal_client_secret: false,
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
                <label className="flex items-center justify-between gap-4 text-sm">
                    <span>
                        <span className="font-medium">{t('Bank transfer')}</span>
                        <span className="text-muted-foreground block">
                            {t('Let customers transfer the money themselves. Their booking waits until you verify the transfer.')}
                        </span>
                    </span>
                    <Switch checked={data.offline_enabled} onCheckedChange={(checked) => setData('offline_enabled', checked)} />
                </label>

                <div className="grid gap-2">
                    <Label htmlFor="offline_instructions">{t('Transfer instructions')}</Label>
                    <Textarea
                        id="offline_instructions"
                        value={data.offline_instructions}
                        onChange={(e) => setData('offline_instructions', e.target.value)}
                        placeholder={t('Shown above your bank details at checkout.')}
                        rows={3}
                    />
                    <InputError message={errors.offline_instructions} />
                    <p className="text-muted-foreground text-xs">
                        <Link href={route('admin.bank-accounts.index')} className="underline">
                            {t('Bank accounts')}
                        </Link>{' '}
                        {t('— at least one active account is needed before this method appears at checkout.')}
                    </p>
                </div>
            </div>

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

            <div className="space-y-4 rounded-lg border p-4">
                <h3 className="text-sm font-medium">{t('PayU')}</h3>
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="payu_key">{t('Merchant key')}</Label>
                        <Input id="payu_key" value={data.payu_key} onChange={(e) => setData('payu_key', e.target.value)} autoComplete="off" />
                        <InputError message={errors.payu_key} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="payu_mode">{t('Mode')}</Label>
                        <select
                            id="payu_mode"
                            className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                            value={data.payu_mode}
                            onChange={(e) => setData('payu_mode', e.target.value)}
                        >
                            <option value="test">{t('Test')}</option>
                            <option value="live">{t('Live')}</option>
                        </select>
                        <InputError message={errors.payu_mode} />
                    </div>
                </div>
                {secretField('payu_salt', t('Merchant salt'), values.payu_salt_set)}
            </div>

            <div className="space-y-4 rounded-lg border p-4">
                <h3 className="text-sm font-medium">{t('PayPal')}</h3>
                <p className="text-muted-foreground text-xs">{t('PayPal cannot settle INR — use it on international installs.')}</p>
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="paypal_client_id">{t('Client ID')}</Label>
                        <Input
                            id="paypal_client_id"
                            value={data.paypal_client_id}
                            onChange={(e) => setData('paypal_client_id', e.target.value)}
                            autoComplete="off"
                        />
                        <InputError message={errors.paypal_client_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="paypal_mode">{t('Mode')}</Label>
                        <select
                            id="paypal_mode"
                            className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                            value={data.paypal_mode}
                            onChange={(e) => setData('paypal_mode', e.target.value)}
                        >
                            <option value="sandbox">{t('Sandbox')}</option>
                            <option value="live">{t('Live')}</option>
                        </select>
                        <InputError message={errors.paypal_mode} />
                    </div>
                </div>
                {secretField('paypal_client_secret', t('Client secret'), values.paypal_client_secret_set)}
                <div className="grid gap-2">
                    <Label htmlFor="paypal_webhook_id">{t('Webhook ID')}</Label>
                    <Input
                        id="paypal_webhook_id"
                        value={data.paypal_webhook_id}
                        onChange={(e) => setData('paypal_webhook_id', e.target.value)}
                        autoComplete="off"
                    />
                    <InputError message={errors.paypal_webhook_id} />
                    <p className="text-muted-foreground text-xs">
                        {t('From your PayPal developer dashboard — webhooks are ignored until it is set.')}
                    </p>
                </div>
            </div>

            <SaveButton processing={processing} />
        </form>
    );
}
