import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { useFlashToast } from '@/hooks/use-flash-toast';
import { useTrans } from '@/lib/i18n';
import { homeUrl } from '@/lib/roles';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';

interface PublicLayoutProps {
    children: React.ReactNode;
}

/**
 * Guest-safe storefront layout: works logged out; logged-in users get a
 * link back to their role dashboard.
 */
export default function PublicLayout({ children }: PublicLayoutProps) {
    useFlashToast();

    const { auth, name } = usePage<SharedData>().props;
    const t = useTrans();

    return (
        <div className="bg-background text-foreground flex min-h-screen flex-col">
            <header className="border-sidebar-border/80 border-b">
                <div className="mx-auto flex h-16 w-full items-center gap-6 px-4 md:max-w-7xl">
                    <Link href="/" prefetch className="flex items-center gap-2 font-semibold">
                        <AppLogoIcon className="h-6 w-6 fill-current" />
                        <span>{name}</span>
                    </Link>

                    <nav className="flex items-center gap-4 text-sm">
                        <Link href={route('catalog.index')} className="text-muted-foreground hover:text-foreground">
                            {t('Services')}
                        </Link>
                    </nav>

                    <div className="ml-auto flex items-center gap-2">
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
                </div>
            </header>

            <main className="mx-auto w-full flex-1 px-4 py-8 md:max-w-7xl">{children}</main>

            <footer className="border-sidebar-border/80 text-muted-foreground border-t py-6 text-center text-sm">{name}</footer>
        </div>
    );
}
