import AppLogoIcon from '@/components/app-logo-icon';
import CitySwitcher from '@/components/site/city-switcher';
import { Button } from '@/components/ui/button';
import { useTrans } from '@/lib/i18n';
import { homeUrl } from '@/lib/roles';
import { cn } from '@/lib/utils';
import { type SharedData, type SiteMenuLink } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ShoppingCart } from 'lucide-react';

/**
 * Storefront header (M19). Links come from the `header` menu — the admin owns
 * them; the variant and stickiness come from the Appearance settings group.
 */
export default function SiteHeader() {
    const { auth, name, cart, site } = usePage<SharedData>().props;
    const t = useTrans();

    const links = site.menus.header ?? [];
    const { header_variant: variant, sticky_header: sticky } = site.appearance;

    const brand = (
        <Link href="/" prefetch className="flex items-center gap-2 font-semibold">
            <AppLogoIcon className="h-6 w-6 fill-current" />
            <span>{name}</span>
        </Link>
    );

    const nav = links.length > 0 && (
        <nav className={cn('flex items-center gap-4 text-sm', variant === 'centered' && 'justify-center')}>
            {links.map((link) => (
                <MenuLink key={`${link.label}-${link.url}`} link={link} />
            ))}
        </nav>
    );

    const actions = (
        <div className="flex items-center gap-2">
            <CitySwitcher />
            <Button asChild variant="ghost" size="sm" className="relative">
                <Link href={route('cart.show')} aria-label={t('Your cart')}>
                    <ShoppingCart className="h-4 w-4" />
                    {cart.count > 0 && (
                        <span className="bg-primary text-primary-foreground absolute -top-1 -right-1 flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] font-semibold">
                            {cart.count}
                        </span>
                    )}
                </Link>
            </Button>
            {auth.user ? (
                <Button asChild variant="outline" size="sm">
                    <Link href={homeUrl(auth.roles)}>{t('Dashboard')}</Link>
                </Button>
            ) : (
                <>
                    <Button asChild variant="ghost" size="sm">
                        <Link href={route('login')}>{t('Log in')}</Link>
                    </Button>
                    <Button asChild size="sm">
                        <Link href={route('register')}>{t('Sign up')}</Link>
                    </Button>
                </>
            )}
        </div>
    );

    return (
        <header className={cn('bg-background border-sidebar-border/80 border-b', sticky && 'sticky top-0 z-40')}>
            {variant === 'centered' ? (
                <div className="mx-auto flex w-full flex-col items-center gap-2 px-4 py-3 md:max-w-7xl">
                    <div className="flex w-full items-center justify-between">
                        {brand}
                        {actions}
                    </div>
                    {nav}
                </div>
            ) : (
                <div className="mx-auto flex h-16 w-full items-center gap-6 px-4 md:max-w-7xl">
                    {brand}
                    {variant === 'classic' && nav}
                    <div className="ml-auto">{actions}</div>
                </div>
            )}
        </header>
    );
}

/**
 * A child list renders as a plain inline group: the menu is content, and a
 * dropdown that needs JavaScript to be reachable is worse than two links.
 */
function MenuLink({ link }: { link: SiteMenuLink }) {
    if (link.children.length === 0) {
        return (
            <Link href={link.url} className="text-muted-foreground hover:text-foreground">
                {link.label}
            </Link>
        );
    }

    return (
        <div className="group relative">
            <span className="text-muted-foreground group-hover:text-foreground cursor-default">{link.label}</span>
            <div className="bg-popover invisible absolute top-full left-0 z-50 min-w-40 rounded-md border p-1 opacity-0 shadow-md transition group-hover:visible group-hover:opacity-100">
                {link.children.map((child) => (
                    <Link key={`${child.label}-${child.url}`} href={child.url} className="hover:bg-accent block rounded px-2 py-1.5 text-sm">
                        {child.label}
                    </Link>
                ))}
            </div>
        </div>
    );
}
