import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem, type NavItem } from '@/types';
import { Briefcase, LayoutGrid, UserRound, Wallet } from 'lucide-react';

interface ProviderLayoutProps {
    children: React.ReactNode;
    breadcrumbs?: BreadcrumbItem[];
}

export default function ProviderLayout({ children, breadcrumbs }: ProviderLayoutProps) {
    const t = useTrans();

    const navItems: NavItem[] = [
        {
            title: t('Dashboard'),
            url: '/provider/dashboard',
            icon: LayoutGrid,
        },
        {
            title: t('Jobs'),
            url: '/provider/jobs',
            icon: Briefcase,
        },
        {
            title: t('Earnings'),
            url: '/provider/earnings',
            icon: Wallet,
        },
        {
            title: t('Profile'),
            url: '/provider/onboarding',
            icon: UserRound,
        },
    ];

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs} navItems={navItems} homeHref="/provider/dashboard">
            {children}
        </AppSidebarLayout>
    );
}
