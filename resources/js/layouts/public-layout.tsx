import { ImpersonationBanner } from '@/components/impersonation-banner';
import { CookieBanner } from '@/components/site/cookie-banner';
import { CustomCode } from '@/components/site/custom-code';
import SiteFooter from '@/components/site/site-footer';
import SiteHeader from '@/components/site/site-header';
import { useFlashToast } from '@/hooks/use-flash-toast';

interface PublicLayoutProps {
    children: React.ReactNode;
}

/**
 * Guest-safe storefront layout: works logged out; logged-in users get a
 * link back to their role dashboard.
 *
 * Header, footer, cookie banner and custom code are all admin-owned (M19) —
 * this shell only decides where they sit.
 */
export default function PublicLayout({ children }: PublicLayoutProps) {
    useFlashToast();

    return (
        <div className="bg-background text-foreground flex min-h-screen flex-col">
            <ImpersonationBanner />
            <SiteHeader />

            <main className="mx-auto w-full flex-1 px-4 py-8 md:max-w-7xl">{children}</main>

            <SiteFooter />
            <CookieBanner />
            <CustomCode />
        </div>
    );
}
