import AppHeaderLayout from '@/layouts/app/app-header-layout';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem, type NavItem } from '@/types';
import { CalendarClock, LayoutGrid, LifeBuoy, MapPin, Search, Wallet } from 'lucide-react';

interface CustomerLayoutProps {
    children: React.ReactNode;
    breadcrumbs?: BreadcrumbItem[];
}

export default function CustomerLayout({ children, breadcrumbs }: CustomerLayoutProps) {
    const t = useTrans();

    const navItems: NavItem[] = [
        {
            title: t('Dashboard'),
            url: '/dashboard',
            icon: LayoutGrid,
        },
        {
            title: t('Services'),
            url: '/services',
            icon: Search,
        },
        {
            title: t('My bookings'),
            url: '/bookings',
            icon: CalendarClock,
        },
        {
            title: t('My addresses'),
            url: '/addresses',
            icon: MapPin,
        },
        // Always shown: refunds land in the wallet even when paying *with* the
        // wallet is switched off, so the customer must always be able to see it.
        {
            title: t('Wallet'),
            url: '/wallet',
            icon: Wallet,
        },
        {
            title: t('Help'),
            url: '/support/tickets',
            icon: LifeBuoy,
        },
    ];

    return (
        <AppHeaderLayout breadcrumbs={breadcrumbs} navItems={navItems} homeHref="/dashboard">
            {children}
        </AppHeaderLayout>
    );
}
