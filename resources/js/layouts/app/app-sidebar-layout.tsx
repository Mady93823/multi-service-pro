import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { BrandTheme } from '@/components/brand-theme';
import { ImpersonationBanner } from '@/components/impersonation-banner';
import { useFlashToast } from '@/hooks/use-flash-toast';
import { useNotifications } from '@/hooks/use-notifications';
import { type BreadcrumbItem, type NavItem } from '@/types';

interface AppSidebarLayoutProps {
    children: React.ReactNode;
    breadcrumbs?: BreadcrumbItem[];
    navItems?: NavItem[];
    homeHref?: string;
}

export default function AppSidebarLayout({ children, breadcrumbs = [], navItems, homeHref }: AppSidebarLayoutProps) {
    useFlashToast();
    useNotifications();

    return (
        <AppShell variant="sidebar">
            <BrandTheme />
            <AppSidebar navItems={navItems} homeHref={homeHref} />
            <AppContent variant="sidebar">
                <ImpersonationBanner />
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
            </AppContent>
        </AppShell>
    );
}
