import AppLogoIcon from '@/components/app-logo-icon';
import CitySwitcher from '@/components/site/city-switcher';
import { Container } from '@/components/site/section';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { useTrans } from '@/lib/i18n';
import { homeUrl } from '@/lib/roles';
import { cn } from '@/lib/utils';
import { type SharedData, type SiteMenuLink } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ChevronDown, Menu, ShoppingCart, UserRound } from 'lucide-react';
import { useState } from 'react';

/**
 * Storefront header (M19). Links come from the `header` menu — the admin owns
 * them; the variant and stickiness come from the Appearance settings group.
 *
 * The nav collapses into a sheet below `lg`. It used to stay inline at every
 * width, which on a phone meant the menu, the city switcher, the cart and two
 * auth buttons all fighting over 360 pixels.
 */
export default function SiteHeader() {
    const { auth, name, cart, site, branding } = usePage<SharedData>().props;
    const t = useTrans();
    const [open, setOpen] = useState(false);

    const links = site.menus.header ?? [];
    const { header_variant: variant, sticky_header: sticky } = site.appearance;

    const brand = (
        <Link href="/" prefetch className="flex shrink-0 items-center gap-2.5">
            {branding.logo_url !== null ? (
                <img src={branding.logo_url} alt={name} className="h-8 w-auto max-w-40 object-contain" />
            ) : (
                <>
                    <span className="bg-primary text-primary-foreground flex h-9 w-9 items-center justify-center rounded-xl">
                        <AppLogoIcon className="h-5 w-5 fill-current" />
                    </span>
                    <span className="text-lg font-semibold tracking-tight">{name}</span>
                </>
            )}
        </Link>
    );

    const nav = links.length > 0 && (
        <nav className={cn('hidden items-center gap-1 text-sm lg:flex', variant === 'centered' && 'justify-center')}>
            {links.map((link) => (
                <MenuLink key={`${link.label}-${link.url}`} link={link} />
            ))}
        </nav>
    );

    const actions = (
        <div className="flex items-center gap-1.5">
            <div className="hidden sm:block">
                <CitySwitcher />
            </div>

            <Button asChild variant="ghost" size="icon" className="relative">
                <Link href={route('cart.show')} aria-label={t('Your cart')}>
                    <ShoppingCart className="h-[1.15rem] w-[1.15rem]" />
                    {cart.count > 0 && (
                        <span className="bg-highlight text-highlight-foreground ring-background absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] font-bold ring-2">
                            {cart.count}
                        </span>
                    )}
                </Link>
            </Button>

            {auth.user ? (
                <Button asChild variant="outline" size="sm" className="hidden gap-2 sm:inline-flex">
                    <Link href={homeUrl(auth.roles)}>
                        <UserRound className="h-4 w-4" />
                        {t('Dashboard')}
                    </Link>
                </Button>
            ) : (
                <>
                    {/* Provider status is chosen at signup only — shown to guests. */}
                    <Button asChild variant="ghost" size="sm" className="hidden lg:inline-flex">
                        <Link href={route('provider.join')}>{t('Become a provider')}</Link>
                    </Button>
                    <Button asChild variant="ghost" size="sm" className="hidden sm:inline-flex">
                        <Link href={route('login')}>{t('Log in')}</Link>
                    </Button>
                    <Button asChild size="sm" className="hidden shadow-sm sm:inline-flex">
                        <Link href={route('register')}>{t('Sign up')}</Link>
                    </Button>
                </>
            )}

            <MobileMenu links={links} open={open} onOpenChange={setOpen} />
        </div>
    );

    return (
        <header
            className={cn(
                'border-border/70 bg-background/85 supports-[backdrop-filter]:bg-background/70 border-b backdrop-blur-md',
                sticky && 'sticky top-0 z-40',
            )}
        >
            {variant === 'centered' ? (
                <Container className="flex flex-col items-center gap-2 py-3">
                    <div className="flex w-full items-center justify-between">
                        {brand}
                        {actions}
                    </div>
                    {nav}
                </Container>
            ) : (
                <Container className="flex h-16 items-center gap-8">
                    {brand}
                    {variant === 'classic' && nav}
                    <div className="ml-auto">{actions}</div>
                </Container>
            )}
        </header>
    );
}

/**
 * A child list renders as a hover panel on a pointer device and as an indented
 * group inside the mobile sheet: the menu is content, and a dropdown that needs
 * JavaScript to be reachable is worse than two links.
 */
function MenuLink({ link }: { link: SiteMenuLink }) {
    if (link.children.length === 0) {
        return (
            <Link
                href={link.url}
                className="text-muted-foreground hover:text-foreground hover:bg-accent rounded-lg px-3 py-2 font-medium transition-colors"
            >
                {link.label}
            </Link>
        );
    }

    return (
        <div className="group relative">
            <span className="text-muted-foreground group-hover:text-foreground group-hover:bg-accent flex cursor-default items-center gap-1 rounded-lg px-3 py-2 font-medium transition-colors">
                {link.label}
                <ChevronDown className="h-3.5 w-3.5 transition-transform group-hover:rotate-180" aria-hidden />
            </span>
            {/* The pt-2 is the bridge: without it the pointer crosses a dead gap and the panel closes under it. */}
            <div className="invisible absolute top-full left-0 z-50 pt-2 opacity-0 transition group-hover:visible group-hover:opacity-100">
                <div className="bg-popover min-w-48 rounded-xl border p-1.5 shadow-lg">
                    {link.children.map((child) => (
                        <Link
                            key={`${child.label}-${child.url}`}
                            href={child.url}
                            className="hover:bg-accent block rounded-lg px-3 py-2 text-sm font-medium"
                        >
                            {child.label}
                        </Link>
                    ))}
                </div>
            </div>
        </div>
    );
}

function MobileMenu({ links, open, onOpenChange }: { links: SiteMenuLink[]; open: boolean; onOpenChange: (open: boolean) => void }) {
    const { auth, name, site } = usePage<SharedData>().props;
    const t = useTrans();

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetTrigger asChild>
                <Button variant="ghost" size="icon" className="lg:hidden" aria-label={t('Menu')}>
                    <Menu className="h-5 w-5" />
                </Button>
            </SheetTrigger>

            <SheetContent side="right" className="flex w-80 flex-col p-0">
                <SheetHeader className="border-b px-6 py-5">
                    <SheetTitle className="text-left">{name}</SheetTitle>
                </SheetHeader>

                <div className="flex flex-1 flex-col gap-1 overflow-y-auto px-4 py-4">
                    {site.cities.length > 1 && (
                        <div className="px-2 pb-3">
                            <CitySwitcher />
                        </div>
                    )}

                    {links.map((link) => (
                        <div key={`${link.label}-${link.url}`}>
                            <Link
                                href={link.url}
                                onClick={() => onOpenChange(false)}
                                className="hover:bg-accent block rounded-lg px-3 py-2.5 font-medium"
                            >
                                {link.label}
                            </Link>

                            {link.children.map((child) => (
                                <Link
                                    key={`${child.label}-${child.url}`}
                                    href={child.url}
                                    onClick={() => onOpenChange(false)}
                                    className="text-muted-foreground hover:text-foreground hover:bg-accent block rounded-lg py-2 pr-3 pl-6 text-sm"
                                >
                                    {child.label}
                                </Link>
                            ))}
                        </div>
                    ))}
                </div>

                <div className="flex flex-col gap-2 border-t px-6 py-5">
                    {auth.user ? (
                        <Button asChild onClick={() => onOpenChange(false)}>
                            <Link href={homeUrl(auth.roles)}>{t('Dashboard')}</Link>
                        </Button>
                    ) : (
                        <>
                            <Button asChild onClick={() => onOpenChange(false)}>
                                <Link href={route('register')}>{t('Sign up')}</Link>
                            </Button>
                            <Button asChild variant="outline" onClick={() => onOpenChange(false)}>
                                <Link href={route('login')}>{t('Log in')}</Link>
                            </Button>
                            <Button asChild variant="ghost" size="sm" onClick={() => onOpenChange(false)}>
                                <Link href={route('provider.join')}>{t('Become a provider')}</Link>
                            </Button>
                        </>
                    )}
                </div>
            </SheetContent>
        </Sheet>
    );
}
