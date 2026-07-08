import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem, type NavItem } from '@/types';
import { CalendarClock, FolderTree, LayoutGrid, Map, Settings, Wrench } from 'lucide-react';

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
