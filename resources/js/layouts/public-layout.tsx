import { BrandTheme } from '@/components/brand-theme';
import { ImpersonationBanner } from '@/components/impersonation-banner';
import { Analytics } from '@/components/site/analytics';
import { CookieBanner } from '@/components/site/cookie-banner';
import { CustomCode } from '@/components/site/custom-code';
import { PopupModal } from '@/components/site/popup-modal';
import { Container } from '@/components/site/section';
import SiteFooter from '@/components/site/site-footer';
import SiteHeader from '@/components/site/site-header';
import { useFlashToast } from '@/hooks/use-flash-toast';

interface PublicLayoutProps {
    children: React.ReactNode;
    /**
     * Hand the full width to the page.
     *
     * The shell used to clamp *everything* to one padded column, which meant no
     * section could ever take a tinted band or bleed to the edge of the screen —
     * the single biggest reason the storefront read as a form and not as a site.
     * Pages built from `Section` (the home page, and anything block-based) own
     * their own bands and ask for this; everything else keeps the padded column.
     */
    bleed?: boolean;
}

/**
 * Guest-safe storefront layout: works logged out; logged-in users get a
 * link back to their role dashboard.
 *
 * Header, footer, cookie banner and custom code are all admin-owned (M19) —
 * this shell only decides where they sit.
 */
export default function PublicLayout({ children, bleed = false }: PublicLayoutProps) {
    useFlashToast();

    return (
        <div className="bg-background text-foreground flex min-h-screen flex-col">
            <BrandTheme />
            <ImpersonationBanner />
            <SiteHeader />

            <main className="flex-1">{bleed ? children : <Container className="py-8 sm:py-10">{children}</Container>}</main>

            <SiteFooter />
            <PopupModal />
            <CookieBanner />
            <CustomCode />
            {/* M24: measurement tags, and only once the banner (if any) allows it. */}
            <Analytics />
        </div>
    );
}
