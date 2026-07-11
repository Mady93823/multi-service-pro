import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem, type NavItem } from '@/types';
import {
    Banknote,
    CalendarClock,
    CircleHelp,
    FileSpreadsheet,
    FileText,
    FolderTree,
    Image,
    Languages,
    LayoutGrid,
    Map,
    ScrollText,
    Settings,
    Star,
    TicketPercent,
    UsersRound,
    Wrench,
} from 'lucide-react';

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
            title: t('Pages'),
            url: '/admin/pages',
            icon: FileText,
        },
        {
            title: t('FAQs'),
            url: '/admin/faqs',
            icon: CircleHelp,
        },
        {
            title: t('Languages'),
            url: '/admin/languages',
            icon: Languages,
        },
        {
            title: t('Reports'),
            url: '/admin/reports/bookings',
            icon: FileSpreadsheet,
        },
        {
            title: t('Activity log'),
            url: '/admin/activity',
            icon: ScrollText,
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
