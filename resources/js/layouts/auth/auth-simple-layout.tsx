import AppLogoIcon from '@/components/app-logo-icon';
import { useTrans } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';

interface AuthLayoutProps {
    children: React.ReactNode;
    name?: string;
    title?: string;
    description?: string;
}

/**
 * The auth shell for every login / register / password screen.
 *
 * It is a two-column split: a brand panel on the left (from `lg` up) and the
 * form on the right. The panel is admin-owned (M19) — a headline, sub-copy and
 * image set in Appearance fill it. When none of that is set the panel falls
 * back to the brand colour, the logo and a neutral, translatable tagline, so a
 * fresh white-label install still gets a finished-looking login instead of a
 * bare card. Below `lg` the panel is hidden and the form takes the screen.
 */
export default function AuthSimpleLayout({ children, title, description }: AuthLayoutProps) {
    const t = useTrans();
    const { name, site, branding } = usePage<SharedData>().props;
    const { login_headline: headline, login_subcopy: subcopy, login_image_url: image } = site.appearance;

    const hasCopy = headline !== null || subcopy !== null;

    const trust = [t('Verified professionals'), t('Live tracking'), t('Secure payments')];

    return (
        <div className="bg-background grid min-h-svh lg:grid-cols-2">
            {/* Brand panel — decorative, so hidden from the reading order below lg. */}
            <aside className="text-primary-foreground relative hidden flex-col justify-between overflow-hidden p-12 lg:flex">
                {image !== null ? (
                    <>
                        <img src={image} alt="" className="absolute inset-0 h-full w-full object-cover" />
                        <div className="absolute inset-0 bg-gradient-to-t from-black/75 via-black/40 to-black/20" />
                    </>
                ) : (
                    <>
                        <div className="bg-primary absolute inset-0" />
                        {/* Soft light so a flat brand fill reads as a designed surface. */}
                        <div className="bg-highlight/25 absolute -top-24 -right-24 h-96 w-96 rounded-full blur-3xl" />
                        <div className="absolute -bottom-32 -left-16 h-96 w-96 rounded-full bg-white/10 blur-3xl" />
                    </>
                )}

                <BrandLockup logoUrl={branding.logo_url} name={name} invert />

                <div className="relative space-y-5">
                    <div className="space-y-3">
                        <h2 className="max-w-md text-3xl font-semibold tracking-tight text-balance">
                            {headline ?? t('Trusted home services, on demand.')}
                        </h2>
                        {subcopy !== null && <p className="text-primary-foreground/80 max-w-md">{subcopy}</p>}
                    </div>

                    {/* Neutral product facts — only when the admin has not written their own. */}
                    {!hasCopy && (
                        <ul className="flex flex-wrap gap-2">
                            {trust.map((item) => (
                                <li
                                    key={item}
                                    className="ring-primary-foreground/20 inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1.5 text-sm font-medium ring-1"
                                >
                                    <ShieldCheck className="h-3.5 w-3.5" />
                                    {item}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </aside>

            {/* Form column */}
            <div className="flex flex-col justify-center px-6 py-10 sm:px-10">
                <div className="mx-auto w-full max-w-sm">
                    <div className="mb-8 flex flex-col gap-6">
                        {/* Brand is on the panel at lg; on smaller screens this is the only mark. */}
                        <div className="lg:hidden">
                            <BrandLockup logoUrl={branding.logo_url} name={name} />
                        </div>

                        <div className="space-y-1.5">
                            <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
                            {description !== undefined && description !== '' && <p className="text-muted-foreground text-sm">{description}</p>}
                        </div>
                    </div>

                    {children}
                </div>
            </div>
        </div>
    );
}

function BrandLockup({ logoUrl, name, invert = false }: { logoUrl: string | null; name: string; invert?: boolean }) {
    return (
        <Link href={route('home')} className="relative inline-flex items-center gap-2.5 font-semibold">
            {logoUrl !== null ? (
                <img src={logoUrl} alt={name} className={cn('h-8 w-auto max-w-40 object-contain', invert && 'brightness-0 invert')} />
            ) : (
                <>
                    <span
                        className={cn(
                            'flex h-9 w-9 items-center justify-center rounded-xl',
                            invert ? 'bg-white/15 text-current' : 'bg-primary text-primary-foreground',
                        )}
                    >
                        <AppLogoIcon className="h-5 w-5 fill-current" />
                    </span>
                    <span className="text-lg tracking-tight">{name}</span>
                </>
            )}
        </Link>
    );
}
