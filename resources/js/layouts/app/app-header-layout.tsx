import { AppContent } from '@/components/app-content';
import { AppHeader } from '@/components/app-header';
import { AppShell } from '@/components/app-shell';
import { useFlashToast } from '@/hooks/use-flash-toast';
import { type BreadcrumbItem, type NavItem } from '@/types';

interface AppHeaderLayoutProps {
    children: React.ReactNode;
    breadcrumbs?: BreadcrumbItem[];
    navItems?: NavItem[];
    homeHref?: string;
}

export default function AppHeaderLayout({ children, breadcrumbs, navItems, homeHref }: AppHeaderLayoutProps) {
    useFlashToast();

    return (
        <AppShell>
            <AppHeader breadcrumbs={breadcrumbs} navItems={navItems} homeHref={homeHref} />
            <AppContent>{children}</AppContent>
        </AppShell>
    );
}
