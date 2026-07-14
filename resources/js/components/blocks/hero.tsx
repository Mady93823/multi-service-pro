import { Container } from '@/components/site/section';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTrans } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/react';
import { BadgeCheck, CalendarClock, Search, ShieldCheck } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

export interface HeroProps {
    heading: string;
    subheading: string | null;
    image_url: string | null;
    show_search: boolean;
    cta_label: string | null;
    cta_url: string | null;
    align: string;
}

/**
 * The first screen.
 *
 * It used to be a rounded box inside the page's padded column, with a `text-3xl`
 * heading and a bare input — which is to say, a form on a card. It now bleeds to
 * the edge of the viewport, carries a display heading, and puts the search where
 * the eye actually lands.
 *
 * The assurance row is **not invented copy**: it names three things this
 * platform enforces — KYC-verified providers (M05), a job-start OTP and
 * pay-after-service (M04/M08), and the live map (M07). Marketing claims we
 * could not make; features we can.
 */
export function HeroBlock({ heading, subheading, image_url, show_search, cta_label, cta_url, align }: HeroProps) {
    const t = useTrans();
    const [term, setTerm] = useState('');
    const centered = align !== 'left';
    const hasImage = image_url !== null;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        router.get(route('catalog.index'), term !== '' ? { search: term } : {});
    };

    const assurances = [
        { icon: BadgeCheck, label: t('Verified, background-checked professionals') },
        { icon: ShieldCheck, label: t('Secure payment, or pay after the job') },
        { icon: CalendarClock, label: t('Track your professional on a live map') },
    ];

    return (
        <section className={cn('relative isolate overflow-hidden', hasImage ? 'text-white' : 'bg-surface')}>
            {hasImage ? (
                <>
                    <img src={image_url} alt="" className="absolute inset-0 -z-10 h-full w-full object-cover" />
                    {/* Two scrims: one across, so the text side stays readable on a busy photo; one up, so the search bar has a floor. */}
                    <div className="absolute inset-0 -z-10 bg-gradient-to-r from-black/80 via-black/55 to-black/25" />
                    <div className="absolute inset-0 -z-10 bg-gradient-to-t from-black/50 to-transparent" />
                </>
            ) : (
                <>
                    <div className="from-primary/12 absolute inset-0 -z-10 bg-gradient-to-br via-transparent to-transparent" />
                    <div className="bg-primary/10 absolute -top-24 -right-24 -z-10 h-96 w-96 rounded-full blur-3xl" />
                    <div className="bg-highlight/10 absolute -bottom-32 -left-24 -z-10 h-96 w-96 rounded-full blur-3xl" />
                </>
            )}

            <Container className="py-20 sm:py-28">
                <div className={cn('max-w-3xl', centered && 'mx-auto text-center')}>
                    <h1 className="text-4xl leading-[1.1] font-bold tracking-tight sm:text-5xl lg:text-6xl">{heading}</h1>

                    {subheading !== null && (
                        <p className={cn('mt-5 max-w-2xl text-lg', centered && 'mx-auto', hasImage ? 'text-white/85' : 'text-muted-foreground')}>
                            {subheading}
                        </p>
                    )}

                    {show_search && (
                        <form
                            onSubmit={submit}
                            className={cn(
                                'bg-background mt-8 flex w-full max-w-xl items-center gap-2 rounded-2xl p-2 shadow-xl ring-1 ring-black/5',
                                centered && 'mx-auto',
                            )}
                        >
                            <Search className="text-muted-foreground ml-2 h-5 w-5 shrink-0" aria-hidden />
                            <Input
                                value={term}
                                onChange={(e) => setTerm(e.target.value)}
                                placeholder={t('What do you need help with?')}
                                aria-label={t('Search services')}
                                className="text-foreground h-11 border-0 bg-transparent px-1 shadow-none focus-visible:ring-0 dark:bg-transparent"
                            />
                            <Button type="submit" size="lg" className="h-11 shrink-0 rounded-xl px-5">
                                {t('Search')}
                            </Button>
                        </form>
                    )}

                    {cta_label !== null && cta_url !== null && (
                        <Button asChild size="lg" className={cn('mt-8 h-12 rounded-xl px-7 text-base shadow-lg', show_search && 'mt-4')}>
                            <a href={cta_url}>{cta_label}</a>
                        </Button>
                    )}

                    <ul
                        className={cn(
                            'mt-10 flex flex-wrap gap-x-6 gap-y-3 text-sm',
                            centered && 'justify-center',
                            hasImage ? 'text-white/85' : 'text-muted-foreground',
                        )}
                    >
                        {assurances.map(({ icon: Icon, label }) => (
                            <li key={label} className="flex items-center gap-2">
                                <Icon className={cn('h-4 w-4 shrink-0', hasImage ? 'text-white' : 'text-primary')} aria-hidden />
                                {label}
                            </li>
                        ))}
                    </ul>
                </div>
            </Container>
        </section>
    );
}
