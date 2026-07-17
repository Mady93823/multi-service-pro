import { Section, SectionHeading } from '@/components/site/section';
import { Button } from '@/components/ui/button';
import PublicLayout from '@/layouts/public-layout';
import { useTrans } from '@/lib/i18n';
import { Head, Link } from '@inertiajs/react';
import { ArrowRight, BadgeCheck, CalendarClock, MapPinned, ShieldCheck, Star, Wallet } from 'lucide-react';
import { type ComponentType } from 'react';

interface BecomeProviderProps {
    /** Live top-level service lines, so the pitch shows real work. */
    categories: string[];
}

/**
 * Provider recruitment page (M19). Every claim maps to a feature that actually
 * ships — verification (M05 KYC), location-based job offers (M06), live tracking
 * (M07), the earnings ledger and payouts (M09). The CTA preselects the provider
 * role on the ordinary register form; there is no second signup flow.
 */
export default function BecomeProvider({ categories }: BecomeProviderProps) {
    const t = useTrans();

    const joinHref = route('register', { as: 'provider' });

    return (
        <PublicLayout bleed>
            <Head title={t('Become a provider')} />

            {/* Hero */}
            <Section tone="contrast" spacing="lg" aria-label={t('Become a provider')}>
                <div className="mx-auto grid max-w-3xl gap-6 text-center">
                    <p className="text-highlight text-xs font-semibold tracking-[0.14em] uppercase">{t('Grow with us')}</p>
                    <h1 className="text-4xl font-bold tracking-tight sm:text-5xl">{t('Earn on your own schedule')}</h1>
                    <p className="text-background/70 mx-auto max-w-2xl text-lg">
                        {t('Join the network of verified professionals. Get jobs near you, track your earnings, and get paid — all from one app.')}
                    </p>
                    <div className="mt-2 flex flex-wrap items-center justify-center gap-3">
                        <Button asChild size="lg" className="gap-2">
                            <Link href={joinHref}>
                                {t('Get started')}
                                <ArrowRight className="h-4 w-4" />
                            </Link>
                        </Button>
                        <Button
                            asChild
                            size="lg"
                            variant="outline"
                            className="border-background/30 text-background hover:bg-background/10 bg-transparent"
                        >
                            <Link href={route('login')}>{t('I already have an account')}</Link>
                        </Button>
                    </div>
                </div>
            </Section>

            {/* How it works */}
            <Section spacing="lg">
                <SectionHeading
                    align="center"
                    eyebrow={t('How it works')}
                    title={t('Start earning in three steps')}
                    description={t('No paperwork queues. Sign up, get verified, and start accepting jobs.')}
                />
                <div className="grid gap-6 sm:grid-cols-3">
                    {[
                        { n: '1', title: t('Create your account'), body: t('Tell us your trade, working hours and the area you cover.') },
                        { n: '2', title: t('Get verified'), body: t('Upload your ID and documents. Our team reviews and approves you.') },
                        {
                            n: '3',
                            title: t('Accept jobs & get paid'),
                            body: t('Receive job offers near you, complete them, and withdraw your earnings.'),
                        },
                    ].map((step) => (
                        <div key={step.n} className="bg-card card-lift rounded-2xl border p-6">
                            <span className="bg-primary text-primary-foreground flex h-10 w-10 items-center justify-center rounded-xl text-lg font-bold">
                                {step.n}
                            </span>
                            <h3 className="mt-4 text-lg font-semibold">{step.title}</h3>
                            <p className="text-muted-foreground mt-1.5 text-sm">{step.body}</p>
                        </div>
                    ))}
                </div>
            </Section>

            {/* Why join */}
            <Section tone="surface" spacing="lg">
                <SectionHeading align="center" eyebrow={t('Why join')} title={t('Everything you need to run your work')} />
                <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <Benefit
                        icon={MapPinned}
                        title={t('Jobs near you')}
                        body={t('We match you to bookings inside the area you cover — no chasing leads.')}
                    />
                    <Benefit
                        icon={CalendarClock}
                        title={t('Work when you want')}
                        body={t('Set your hours, go online when you are free, and block out days off.')}
                    />
                    <Benefit
                        icon={Wallet}
                        title={t('Transparent earnings')}
                        body={t('Every job, fee and payout is itemised in your earnings ledger.')}
                    />
                    <Benefit
                        icon={MapPinned}
                        title={t('Built-in navigation')}
                        body={t('Customers see you on a live map on the way — fewer calls asking where you are.')}
                    />
                    <Benefit icon={Star} title={t('Build your reputation')} body={t('Ratings and reviews help you win more work over time.')} />
                    <Benefit icon={ShieldCheck} title={t('Verified & trusted')} body={t('A verified badge tells customers you passed our checks.')} />
                </div>
            </Section>

            {/* What you can offer */}
            {categories.length > 0 && (
                <Section spacing="lg">
                    <SectionHeading align="center" eyebrow={t('Categories')} title={t('What you can offer')} />
                    <div className="flex flex-wrap justify-center gap-2.5">
                        {categories.map((name) => (
                            <span key={name} className="bg-card inline-flex items-center gap-1.5 rounded-full border px-4 py-2 text-sm font-medium">
                                <BadgeCheck className="text-primary h-4 w-4" />
                                {name}
                            </span>
                        ))}
                    </div>
                </Section>
            )}

            {/* Final CTA */}
            <Section tone="brand" spacing="lg">
                <div className="mx-auto max-w-2xl text-center">
                    <h2 className="text-2xl font-semibold tracking-tight sm:text-3xl">{t('Ready to start earning?')}</h2>
                    <p className="text-muted-foreground mx-auto mt-3 max-w-xl">
                        {t('Create your provider account today. It takes a few minutes to sign up.')}
                    </p>
                    <Button asChild size="lg" className="mt-6 gap-2">
                        <Link href={joinHref}>
                            {t('Become a provider')}
                            <ArrowRight className="h-4 w-4" />
                        </Link>
                    </Button>
                </div>
            </Section>
        </PublicLayout>
    );
}

function Benefit({ icon: Icon, title, body }: { icon: ComponentType<{ className?: string }>; title: string; body: string }) {
    return (
        <div className="flex gap-4">
            <span className="bg-primary/10 text-primary flex h-11 w-11 shrink-0 items-center justify-center rounded-xl">
                <Icon className="h-5 w-5" />
            </span>
            <div>
                <h3 className="font-semibold">{title}</h3>
                <p className="text-muted-foreground mt-1 text-sm">{body}</p>
            </div>
        </div>
    );
}
