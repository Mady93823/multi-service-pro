import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem, type NavItem } from '@/types';
import {
    CalendarClock,
    CreditCard,
    FileSpreadsheet,
    FileText,
    Image,
    LayoutGrid,
    LifeBuoy,
    Map,
    Megaphone,
    Settings,
    ShoppingBag,
    Star,
    UsersRound,
    Wrench,
} from 'lucide-react';

interface AdminLayoutProps {
    children: React.ReactNode;
    breadcrumbs?: BreadcrumbItem[];
}

/**
 * Grouped, collapsible admin navigation (M17). A group's `url` is the trail it
 * owns — the sub-menu auto-expands when the current page sits under it. A group
 * with a single child is a plain item, not a group.
 */
export default function AdminLayout({ children, breadcrumbs }: AdminLayoutProps) {
    const t = useTrans();

    const navItems: NavItem[] = [
        {
            title: t('Dashboard'),
            url: '/admin/dashboard',
            icon: LayoutGrid,
        },
        {
            title: t('Bookings'),
            url: '/admin/bookings',
            icon: CalendarClock,
        },
        {
            title: t('Catalog'),
            url: '/admin/catalog',
            icon: Wrench,
            children: [
                { title: t('Categories'), url: '/admin/categories' },
                { title: t('Services'), url: '/admin/services' },
            ],
        },
        {
            title: t('Providers'),
            url: '/admin/providers',
            icon: UsersRound,
        },
        {
            title: t('Customers'),
            url: '/admin/customers',
            icon: ShoppingBag,
        },
        {
            title: t('Payments'),
            url: '/admin/payments',
            icon: CreditCard,
            children: [
                { title: t('All payments'), url: '/admin/payments' },
                { title: t('Bank accounts'), url: '/admin/bank-accounts' },
                { title: t('Payouts'), url: '/admin/payouts' },
            ],
        },
        {
            title: t('Zones'),
            url: '/admin/zones',
            icon: Map,
        },
        {
            title: t('Marketing'),
            url: '/admin/marketing',
            icon: Megaphone,
            children: [
                { title: t('Coupons'), url: '/admin/coupons' },
                { title: t('Banners'), url: '/admin/banners' },
                { title: t('Testimonials'), url: '/admin/testimonials' },
                { title: t('Sponsors'), url: '/admin/sponsors' },
                { title: t('Popups'), url: '/admin/popups' },
                { title: t('Subscribers'), url: '/admin/subscribers' },
            ],
        },
        {
            title: t('Reviews'),
            url: '/admin/reviews',
            icon: Star,
        },
        {
            title: t('Support'),
            url: '/admin/tickets',
            icon: LifeBuoy,
        },
        {
            title: t('Content'),
            url: '/admin/content',
            icon: FileText,
            children: [
                { title: t('Menus'), url: '/admin/menus' },
                { title: t('Pages'), url: '/admin/pages' },
                { title: t('Blog'), url: '/admin/blog' },
                { title: t('Blog categories'), url: '/admin/blog/categories' },
                { title: t('FAQs'), url: '/admin/faqs' },
                { title: t('Languages'), url: '/admin/languages' },
            ],
        },
        {
            title: t('Media'),
            url: '/admin/media',
            icon: Image,
        },
        {
            title: t('Reports'),
            url: '/admin/reports',
            icon: FileSpreadsheet,
            children: [
                { title: t('Bookings'), url: '/admin/reports/bookings' },
                { title: t('Earnings'), url: '/admin/reports/earnings' },
                { title: t('Services'), url: '/admin/reports/services' },
                { title: t('Providers'), url: '/admin/reports/providers' },
                { title: t('Activity log'), url: '/admin/activity' },
            ],
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
