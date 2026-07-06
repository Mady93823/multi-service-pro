import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { type BreadcrumbItem, type NavItem } from '@/types';
import { LayoutGrid } from 'lucide-react';

const navItems: NavItem[] = [
    {
        title: 'Dashboard',
        url: '/provider/dashboard',
        icon: LayoutGrid,
    },
];

interface ProviderLayoutProps {
    children: React.ReactNode;
    breadcrumbs?: BreadcrumbItem[];
}

export default function ProviderLayout({ children, breadcrumbs }: ProviderLayoutProps) {
    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs} navItems={navItems} homeHref="/provider/dashboard">
            {children}
        </AppSidebarLayout>
    );
}
