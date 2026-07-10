import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem, type NavItem } from '@/types';
import { Banknote, CalendarClock, FolderTree, Image, LayoutGrid, Map, Settings, Star, TicketPercent, UsersRound, Wrench } from 'lucide-react';

interface AdminLayoutProps {
    children: React.ReactNode;
    breadcrumbs?: BreadcrumbItem[];
}

export default function AdminLayout({ children, breadcrumbs }: AdminLayoutProps) {
    const t = useTrans();

    const navItems: NavItem[] = [
        {
            title: t('Dashboard'),
            url: '/admin/dashboard',
            icon: LayoutGrid,
        },
        {
            title: t('Categories'),
            url: '/admin/categories',
            icon: FolderTree,
        },
        {
            title: t('Services'),
            url: '/admin/services',
            icon: Wrench,
        },
        {
            title: t('Zones'),
            url: '/admin/zones',
            icon: Map,
        },
        {
            title: t('Bookings'),
            url: '/admin/bookings',
            icon: CalendarClock,
        },
        {
            title: t('Providers'),
            url: '/admin/providers',
            icon: UsersRound,
        },
        {
            title: t('Payouts'),
            url: '/admin/payouts',
            icon: Banknote,
        },
        {
            title: t('Reviews'),
            url: '/admin/reviews',
            icon: Star,
        },
        {
            title: t('Coupons'),
            url: '/admin/coupons',
            icon: TicketPercent,
        },
        {
            title: t('Banners'),
            url: '/admin/banners',
            icon: Image,
        },
        {
            title: t('Settings'),
            url: '/admin/settings',
            icon: Settings,
        },
    ];

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs} navItems={navItems} homeHref="/admin/dashboard">
            {children}
        </AppSidebarLayout>
    );
}
