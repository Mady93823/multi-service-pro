import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useRecaptcha } from '@/hooks/use-recaptcha';
import AuthLayout from '@/layouts/auth-layout';
import { useTrans } from '@/lib/i18n';

type RegisterForm = {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    referral_code: string;
};

interface RegisterProps {
    referrals_enabled: boolean;
    /** Pre-filled from a ?ref=CODE share link. */
    referral_code: string;
}

export default function Register({ referrals_enabled: referralsEnabled, referral_code: prefilledCode }: RegisterProps) {
    const t = useTrans();
    const recaptcha = useRecaptcha('register');

    const { data, setData, post, transform, processing, errors, reset } = useForm<RegisterForm>({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        referral_code: prefilledCode,
    });

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

                    {referralsEnabled && (
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
                        {t('Create account')}
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
