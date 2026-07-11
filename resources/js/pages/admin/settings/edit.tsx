import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import AdminLayout from '@/layouts/admin-layout';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle, Plus, Trash2 } from 'lucide-react';
import { FormEventHandler } from 'react';

type SettingsForm = {
    _method: string;
    app_name: string;
    primary_color: string;
    currency: string;
    timezone: string;
    locale: string;
    logo: File | null;
    remove_logo: boolean;
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
    tax_label: string;
    tax_percent: number;
    pay_after_service: boolean;
    wallet_enabled: boolean;
    payment_timeout_minutes: number;
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
    commission_percent: number;
    payouts_enabled: boolean;
    payout_min_amount: number;
    payout_hold_days: number;
    invoice_prefix: string;
    invoice_company_name: string;
    invoice_gstin: string;
    invoice_address: string;
    invoice_state: string;
    reviews_enabled: boolean;
    reviews_max_photos: number;
    referrals_enabled: boolean;
    referrals_reward_amount: number;
    support_max_attachments: number;
    support_canned_responses: { title: string; body: string }[];
};

/** The four write-only secrets: the server sends `*_set`, never the value. */
type SecretField = 'razorpay_key_secret' | 'razorpay_webhook_secret' | 'stripe_secret_key' | 'stripe_webhook_secret';

interface SettingsEditProps {
    values: {
        app_name: string;
        primary_color: string | null;
        currency: string;
        timezone: string;
        locale: string;
        logo_url: string | null;
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
        tax_label: string;
        tax_percent: number;
        pay_after_service: boolean;
        wallet_enabled: boolean;
        payment_timeout_minutes: number;
        razorpay_key_id: string;
        stripe_publishable_key: string;
        razorpay_key_secret_set: boolean;
        razorpay_webhook_secret_set: boolean;
        stripe_secret_key_set: boolean;
        stripe_webhook_secret_set: boolean;
        commission_percent: number;
        payouts_enabled: boolean;
        payout_min_amount: number;
        payout_hold_days: number;
        invoice_prefix: string;
        invoice_company_name: string | null;
        invoice_gstin: string | null;
        invoice_address: string | null;
        invoice_state: string | null;
        reviews_enabled: boolean;
        reviews_max_photos: number;
        referrals_enabled: boolean;
        referrals_reward_amount: number;
        support_max_attachments: number;
        support_canned_responses: { title: string; body: string }[];
    };
}

const timezones: string[] = Intl.supportedValuesOf('timeZone');
const currencies: string[] = Intl.supportedValuesOf('currency');

export default function SettingsEdit({ values }: SettingsEditProps) {
    const t = useTrans();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('Settings'), href: '/admin/settings' },
    ];

    const { data, setData, post, processing, errors, transform } = useForm<SettingsForm>({
        _method: 'put',
        app_name: values.app_name,
        primary_color: values.primary_color ?? '',
        currency: values.currency,
        timezone: values.timezone,
        locale: values.locale,
        logo: null,
        remove_logo: false,
        booking_code_prefix: values.booking_code_prefix,
        slot_minutes: values.slot_minutes,
        day_starts: values.day_starts,
        day_ends: values.day_ends,
        lead_time_hours: values.lead_time_hours,
        max_days_ahead: values.max_days_ahead,
        job_otp_required: values.job_otp_required,
        free_cancel_hours: values.free_cancel_hours,
        cancellation_fee_type: values.cancellation_fee_type,
        cancellation_fee_value: values.cancellation_fee_value,
        reschedule_min_hours: values.reschedule_min_hours,
        tax_label: values.tax_label,
        tax_percent: values.tax_percent,
        pay_after_service: values.pay_after_service,
        wallet_enabled: values.wallet_enabled,
        payment_timeout_minutes: values.payment_timeout_minutes,
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
        commission_percent: values.commission_percent,
        payouts_enabled: values.payouts_enabled,
        payout_min_amount: values.payout_min_amount,
        payout_hold_days: values.payout_hold_days,
        invoice_prefix: values.invoice_prefix,
        invoice_company_name: values.invoice_company_name ?? '',
        invoice_gstin: values.invoice_gstin ?? '',
        invoice_address: values.invoice_address ?? '',
        invoice_state: values.invoice_state ?? '',
        reviews_enabled: values.reviews_enabled,
        reviews_max_photos: values.reviews_max_photos,
        referrals_enabled: values.referrals_enabled,
        referrals_reward_amount: values.referrals_reward_amount,
        support_max_attachments: values.support_max_attachments,
        support_canned_responses: values.support_canned_responses,
    });

    transform((current) => ({
        ...current,
        primary_color: current.primary_color === '' ? null : current.primary_color,
    }));

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
        post(route('admin.settings.update'), { forceFormData: true, preserveScroll: true });
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Settings')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">{t('Settings')}</h1>

                <form onSubmit={submit} className="max-w-2xl space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Branding')}</CardTitle>
                            <CardDescription>{t('Name, logo and accent color shown across the platform.')}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div className="grid gap-2">
                                <Label htmlFor="app_name">{t('Platform name')}</Label>
                                <Input id="app_name" value={data.app_name} onChange={(e) => setData('app_name', e.target.value)} required />
                                <InputError message={errors.app_name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="logo">{t('Logo')}</Label>
                                {values.logo_url && !data.remove_logo && (
                                    <img src={values.logo_url} alt="" className="h-12 w-fit rounded border object-contain p-1" />
                                )}
                                <Input
                                    id="logo"
                                    type="file"
                                    accept="image/png,image/jpeg,image/webp,image/svg+xml"
                                    onChange={(e) => setData('logo', e.target.files?.[0] ?? null)}
                                />
                                <InputError message={errors.logo} />
                                {values.logo_url && (
                                    <label className="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            checked={data.remove_logo}
                                            onCheckedChange={(checked) => setData('remove_logo', checked === true)}
                                        />
                                        {t('Remove current logo')}
                                    </label>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="primary_color">{t('Primary color')}</Label>
                                <div className="flex items-center gap-2">
                                    <input
                                        type="color"
                                        aria-label={t('Pick primary color')}
                                        value={data.primary_color !== '' ? data.primary_color : '#171717'}
                                        onChange={(e) => setData('primary_color', e.target.value)}
                                        className="h-9 w-9 cursor-pointer rounded border bg-transparent p-1"
                                    />
                                    <Input
                                        id="primary_color"
                                        value={data.primary_color}
                                        onChange={(e) => setData('primary_color', e.target.value)}
                                        placeholder={t('Theme default')}
                                        className="w-40"
                                    />
                                    {data.primary_color !== '' && (
                                        <Button type="button" variant="ghost" size="sm" onClick={() => setData('primary_color', '')}>
                                            {t('Reset')}
                                        </Button>
                                    )}
                                </div>
                                <InputError message={errors.primary_color} />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Localization')}</CardTitle>
                            <CardDescription>{t('Currency, timezone and language defaults.')}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div className="grid gap-2">
                                <Label htmlFor="currency">{t('Currency')}</Label>
                                <Input
                                    id="currency"
                                    list="currency-options"
                                    value={data.currency}
                                    onChange={(e) => setData('currency', e.target.value.toUpperCase())}
                                    className="w-40"
                                    required
                                />
                                <datalist id="currency-options">
                                    {currencies.map((code) => (
                                        <option key={code} value={code} />
                                    ))}
                                </datalist>
                                <InputError message={errors.currency} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="timezone">{t('Timezone')}</Label>
                                <Input
                                    id="timezone"
                                    list="timezone-options"
                                    value={data.timezone}
                                    onChange={(e) => setData('timezone', e.target.value)}
                                    className="w-72"
                                    required
                                />
                                <datalist id="timezone-options">
                                    {timezones.map((zone) => (
                                        <option key={zone} value={zone} />
                                    ))}
                                </datalist>
                                <InputError message={errors.timezone} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="locale">{t('Default language code')}</Label>
                                <Input
                                    id="locale"
                                    value={data.locale}
                                    onChange={(e) => setData('locale', e.target.value)}
                                    className="w-40"
                                    placeholder="en"
                                    required
                                />
                                <InputError message={errors.locale} />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Booking')}</CardTitle>
                            <CardDescription>{t('Slot grid, booking window, job start code and cancellation rules.')}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
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
                                    <Input
                                        id="day_starts"
                                        type="time"
                                        value={data.day_starts}
                                        onChange={(e) => setData('day_starts', e.target.value)}
                                        required
                                    />
                                    <InputError message={errors.day_starts} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="day_ends">{t('Day ends')}</Label>
                                    <Input
                                        id="day_ends"
                                        type="time"
                                        value={data.day_ends}
                                        onChange={(e) => setData('day_ends', e.target.value)}
                                        required
                                    />
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

                            <div className="grid gap-2">
                                <Label htmlFor="reschedule_min_hours">{t('Reschedule locked (hours before visit)')}</Label>
                                <Input
                                    id="reschedule_min_hours"
                                    type="number"
                                    min={0}
                                    max={168}
                                    value={data.reschedule_min_hours}
                                    onChange={(e) => setData('reschedule_min_hours', Number(e.target.value))}
                                    className="w-40"
                                    required
                                />
                                <InputError message={errors.reschedule_min_hours} />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Taxes')}</CardTitle>
                            <CardDescription>{t('Applied to every booking and shown on invoices.')}</CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="tax_label">{t('Tax label')}</Label>
                                <Input
                                    id="tax_label"
                                    value={data.tax_label}
                                    onChange={(e) => setData('tax_label', e.target.value)}
                                    maxLength={20}
                                    required
                                />
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
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Payments')}</CardTitle>
                            <CardDescription>
                                {t(
                                    'Which methods customers may use, and the gateway credentials. A gateway appears at checkout only once its keys are saved.',
                                )}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <label className="flex items-center justify-between gap-4 text-sm">
                                <span>
                                    <span className="font-medium">{t('Pay after service')}</span>
                                    <span className="text-muted-foreground block">
                                        {t('Let customers book now and pay the professional afterwards.')}
                                    </span>
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

                            <div className="grid gap-2">
                                <Label htmlFor="payment_timeout_minutes">{t('Unpaid booking expires after (minutes)')}</Label>
                                <Input
                                    id="payment_timeout_minutes"
                                    type="number"
                                    min={5}
                                    max={1440}
                                    value={data.payment_timeout_minutes}
                                    onChange={(e) => setData('payment_timeout_minutes', Number(e.target.value))}
                                    className="w-40"
                                    required
                                />
                                <InputError message={errors.payment_timeout_minutes} />
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
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Commission and payouts')}</CardTitle>
                            <CardDescription>
                                {t(
                                    'The platform’s cut of each completed job, and how professionals withdraw what they have earned. A category can override the rate.',
                                )}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div className="grid gap-2">
                                <Label htmlFor="commission_percent">{t('Commission (%)')}</Label>
                                <Input
                                    id="commission_percent"
                                    type="number"
                                    min={0}
                                    max={100}
                                    step="0.01"
                                    value={data.commission_percent}
                                    onChange={(e) => setData('commission_percent', Number(e.target.value))}
                                    className="w-40"
                                    required
                                />
                                <InputError message={errors.commission_percent} />
                            </div>

                            <label className="flex items-center justify-between gap-4 text-sm">
                                <span>
                                    <span className="font-medium">{t('Payout requests')}</span>
                                    <span className="text-muted-foreground block">{t('Let professionals withdraw their available balance.')}</span>
                                </span>
                                <Switch checked={data.payouts_enabled} onCheckedChange={(checked) => setData('payouts_enabled', checked)} />
                            </label>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="payout_min_amount">{t('Minimum payout amount')}</Label>
                                    <Input
                                        id="payout_min_amount"
                                        type="number"
                                        min={0}
                                        step="0.01"
                                        value={data.payout_min_amount}
                                        onChange={(e) => setData('payout_min_amount', Number(e.target.value))}
                                        required
                                    />
                                    <InputError message={errors.payout_min_amount} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="payout_hold_days">{t('Hold earnings for (days)')}</Label>
                                    <Input
                                        id="payout_hold_days"
                                        type="number"
                                        min={0}
                                        max={90}
                                        value={data.payout_hold_days}
                                        onChange={(e) => setData('payout_hold_days', Number(e.target.value))}
                                        required
                                    />
                                    <p className="text-muted-foreground text-xs">
                                        {t('The window in which a refund can still cancel a completed job’s earning.')}
                                    </p>
                                    <InputError message={errors.payout_hold_days} />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Invoice')}</CardTitle>
                            <CardDescription>{t('Printed on every tax invoice. Leave the company name blank to use the app name.')}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="invoice_prefix">{t('Invoice number prefix')}</Label>
                                    <Input
                                        id="invoice_prefix"
                                        value={data.invoice_prefix}
                                        onChange={(e) => setData('invoice_prefix', e.target.value)}
                                        required
                                    />
                                    <InputError message={errors.invoice_prefix} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="invoice_company_name">{t('Company name')}</Label>
                                    <Input
                                        id="invoice_company_name"
                                        value={data.invoice_company_name}
                                        onChange={(e) => setData('invoice_company_name', e.target.value)}
                                    />
                                    <InputError message={errors.invoice_company_name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="invoice_gstin">{t('GSTIN')}</Label>
                                    <Input
                                        id="invoice_gstin"
                                        value={data.invoice_gstin}
                                        onChange={(e) => setData('invoice_gstin', e.target.value)}
                                        placeholder="22AAAAA0000A1Z5"
                                    />
                                    <InputError message={errors.invoice_gstin} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="invoice_state">{t('State')}</Label>
                                    <Input id="invoice_state" value={data.invoice_state} onChange={(e) => setData('invoice_state', e.target.value)} />
                                    <InputError message={errors.invoice_state} />
                                </div>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="invoice_address">{t('Registered address')}</Label>
                                <Input
                                    id="invoice_address"
                                    value={data.invoice_address}
                                    onChange={(e) => setData('invoice_address', e.target.value)}
                                />
                                <InputError message={errors.invoice_address} />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Reviews')}</CardTitle>
                            <CardDescription>{t('Customer ratings on completed bookings, shown on service pages.')}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
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
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Referrals')}</CardTitle>
                            <CardDescription>{t('Wallet credit for customers whose invited friends complete a first booking.')}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <label className="flex items-center justify-between gap-4 text-sm">
                                <span>
                                    <span className="font-medium">{t('Enable referral program')}</span>
                                    <span className="text-muted-foreground block">
                                        {t('Hides the refer & earn card and the sign-up code field when off.')}
                                    </span>
                                </span>
                                <Switch checked={data.referrals_enabled} onCheckedChange={(checked) => setData('referrals_enabled', checked)} />
                            </label>

                            <div className="grid gap-2">
                                <Label htmlFor="referrals_reward_amount">{t('Reward amount')}</Label>
                                <Input
                                    id="referrals_reward_amount"
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    value={data.referrals_reward_amount}
                                    onChange={(e) => setData('referrals_reward_amount', Number(e.target.value))}
                                    className="w-40"
                                    required
                                />
                                <p className="text-muted-foreground text-xs">{t('Set to 0 to pause payouts without hiding the program.')}</p>
                                <InputError message={errors.referrals_reward_amount} />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Support')}</CardTitle>
                            <CardDescription>{t('Helpdesk limits and the canned responses the reply box offers.')}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div className="grid gap-2">
                                <Label htmlFor="support_max_attachments">{t('Attachments per message')}</Label>
                                <Input
                                    id="support_max_attachments"
                                    type="number"
                                    min={0}
                                    max={10}
                                    value={data.support_max_attachments}
                                    onChange={(e) => setData('support_max_attachments', Number(e.target.value))}
                                    className="w-40"
                                    required
                                />
                                <p className="text-muted-foreground text-xs">{t('Set to 0 to disable ticket attachments.')}</p>
                                <InputError message={errors.support_max_attachments} />
                            </div>

                            <div className="grid gap-3">
                                <Label>{t('Canned responses')}</Label>
                                {data.support_canned_responses.map((response, index) => (
                                    <div key={index} className="grid gap-2 rounded-md border p-3">
                                        <div className="flex items-center gap-2">
                                            <Input
                                                value={response.title}
                                                placeholder={t('Title')}
                                                maxLength={100}
                                                onChange={(e) =>
                                                    setData(
                                                        'support_canned_responses',
                                                        data.support_canned_responses.map((item, i) =>
                                                            i === index ? { ...item, title: e.target.value } : item,
                                                        ),
                                                    )
                                                }
                                            />
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                aria-label={t('Remove response')}
                                                onClick={() =>
                                                    setData(
                                                        'support_canned_responses',
                                                        data.support_canned_responses.filter((_, i) => i !== index),
                                                    )
                                                }
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </div>
                                        <textarea
                                            value={response.body}
                                            placeholder={t('Response text')}
                                            rows={2}
                                            maxLength={2000}
                                            className="border-input bg-background w-full rounded-md border px-3 py-2 text-sm"
                                            onChange={(e) =>
                                                setData(
                                                    'support_canned_responses',
                                                    data.support_canned_responses.map((item, i) =>
                                                        i === index ? { ...item, body: e.target.value } : item,
                                                    ),
                                                )
                                            }
                                        />
                                        <InputError message={errors[`support_canned_responses.${index}.title` as keyof typeof errors]} />
                                        <InputError message={errors[`support_canned_responses.${index}.body` as keyof typeof errors]} />
                                    </div>
                                ))}
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="w-fit"
                                    disabled={data.support_canned_responses.length >= 20}
                                    onClick={() => setData('support_canned_responses', [...data.support_canned_responses, { title: '', body: '' }])}
                                >
                                    <Plus className="mr-1 h-4 w-4" />
                                    {t('Add response')}
                                </Button>
                                <InputError message={errors.support_canned_responses} />
                            </div>
                        </CardContent>
                    </Card>

                    <Button type="submit" disabled={processing}>
                        {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                        {t('Save settings')}
                    </Button>
                </form>
            </div>
        </AdminLayout>
    );
}
