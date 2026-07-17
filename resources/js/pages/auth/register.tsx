import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle, ShoppingBag, Wrench } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useRecaptcha } from '@/hooks/use-recaptcha';
import AuthLayout from '@/layouts/auth-layout';
import { useTrans } from '@/lib/i18n';
import { cn } from '@/lib/utils';

type Role = 'customer' | 'provider';

type RegisterForm = {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    role: Role;
    referral_code: string;
};

interface RegisterProps {
    referrals_enabled: boolean;
    /** Pre-filled from a ?ref=CODE share link. */
    referral_code: string;
    /** Preselected role, from ?as=provider on the "Become a provider" pitch. */
    role_intent: Role;
}

export default function Register({ referrals_enabled: referralsEnabled, referral_code: prefilledCode, role_intent: roleIntent }: RegisterProps) {
    const t = useTrans();
    const recaptcha = useRecaptcha('register');

    const { data, setData, post, transform, processing, errors, reset } = useForm<RegisterForm>({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        role: roleIntent,
        referral_code: prefilledCode,
    });

    const isProvider = data.role === 'provider';

    const submit: FormEventHandler = async (e) => {
        e.preventDefault();

        const token = await recaptcha();
        transform((current) => ({ ...current, recaptcha_token: token }));

        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <AuthLayout title={t('Create an account')} description={t('Enter your details below to create your account')}>
            <Head title={t('Register')} />
            <form className="flex flex-col gap-6" onSubmit={submit}>
                <div className="grid gap-6">
                    {/* Role is chosen at signup and only at signup — there is no
                        later upgrade path (product decision). */}
                    <fieldset className="grid gap-2">
                        <legend className="mb-2 text-sm font-medium">{t('I want to')}</legend>
                        <div className="grid grid-cols-2 gap-3" role="radiogroup">
                            <RoleOption
                                selected={!isProvider}
                                onSelect={() => setData('role', 'customer')}
                                icon={<ShoppingBag className="h-5 w-5" />}
                                title={t('Book services')}
                                subtitle={t('Hire trusted professionals')}
                                disabled={processing}
                            />
                            <RoleOption
                                selected={isProvider}
                                onSelect={() => setData('role', 'provider')}
                                icon={<Wrench className="h-5 w-5" />}
                                title={t('Offer services')}
                                subtitle={t('Earn as a professional')}
                                disabled={processing}
                            />
                        </div>
                        {isProvider && (
                            <p className="text-muted-foreground text-xs">
                                {t('After signing up you will complete a short verification before you can take jobs.')}
                            </p>
                        )}
                    </fieldset>

                    <div className="grid gap-2">
                        <Label htmlFor="name">{t('Name')}</Label>
                        <Input
                            id="name"
                            type="text"
                            required
                            autoFocus
                            tabIndex={1}
                            autoComplete="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            disabled={processing}
                            placeholder={t('Full name')}
                        />
                        <InputError message={errors.name} className="mt-2" />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="email">{t('Email address')}</Label>
                        <Input
                            id="email"
                            type="email"
                            required
                            tabIndex={2}
                            autoComplete="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            disabled={processing}
                            placeholder="email@example.com"
                        />
                        <InputError message={errors.email} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password">{t('Password')}</Label>
                        <Input
                            id="password"
                            type="password"
                            required
                            tabIndex={3}
                            autoComplete="new-password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            disabled={processing}
                            placeholder={t('Password')}
                        />
                        <InputError message={errors.password} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password_confirmation">{t('Confirm password')}</Label>
                        <Input
                            id="password_confirmation"
                            type="password"
                            required
                            tabIndex={4}
                            autoComplete="new-password"
                            value={data.password_confirmation}
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            disabled={processing}
                            placeholder={t('Confirm password')}
                        />
                        <InputError message={errors.password_confirmation} />
                    </div>

                    {/* Referral is a customer growth loop — hidden for provider signups. */}
                    {referralsEnabled && !isProvider && (
                        <div className="grid gap-2">
                            <Label htmlFor="referral_code">{t('Referral code (optional)')}</Label>
                            <Input
                                id="referral_code"
                                type="text"
                                tabIndex={5}
                                value={data.referral_code}
                                onChange={(e) => setData('referral_code', e.target.value.toUpperCase())}
                                disabled={processing}
                                placeholder={t('Got a code from a friend?')}
                                className="font-mono uppercase"
                            />
                            <InputError message={errors.referral_code} />
                        </div>
                    )}

                    <Button type="submit" className="mt-2 w-full" tabIndex={5} disabled={processing}>
                        {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                        {isProvider ? t('Create provider account') : t('Create account')}
                    </Button>
                </div>

                <div className="text-muted-foreground text-center text-sm">
                    {t('Already have an account?')}{' '}
                    <TextLink href={route('login')} tabIndex={6}>
                        {t('Log in')}
                    </TextLink>
                </div>
            </form>
        </AuthLayout>
    );
}

function RoleOption({
    selected,
    onSelect,
    icon,
    title,
    subtitle,
    disabled,
}: {
    selected: boolean;
    onSelect: () => void;
    icon: React.ReactNode;
    title: string;
    subtitle: string;
    disabled?: boolean;
}) {
    return (
        <button
            type="button"
            role="radio"
            aria-checked={selected}
            onClick={onSelect}
            disabled={disabled}
            className={cn(
                'flex flex-col items-start gap-1.5 rounded-xl border p-3 text-left transition-colors',
                'focus-visible:ring-ring focus-visible:ring-2 focus-visible:outline-none',
                selected ? 'border-primary bg-primary/5 ring-primary/20 ring-1' : 'border-border hover:border-primary/40 hover:bg-accent/50',
                disabled && 'cursor-not-allowed opacity-60',
            )}
        >
            <span className={cn('flex h-9 w-9 items-center justify-center rounded-lg', selected ? 'bg-primary text-primary-foreground' : 'bg-muted')}>
                {icon}
            </span>
            <span className="text-sm font-semibold">{title}</span>
            <span className="text-muted-foreground text-xs">{subtitle}</span>
        </button>
    );
}
