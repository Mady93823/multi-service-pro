import AppHeaderLayout from '@/layouts/app/app-header-layout';
import { type BreadcrumbItem, type NavItem } from '@/types';
import { LayoutGrid, Search } from 'lucide-react';

const navItems: NavItem[] = [
    {
        title: 'Dashboard',
        url: '/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Services',
        url: '/services',
        icon: Search,
    },
];

interface CustomerLayoutProps {
    children: React.ReactNode;
    breadcrumbs?: BreadcrumbItem[];
}

export default function CustomerLayout({ children, breadcrumbs }: CustomerLayoutProps) {
    return (
        <AppHeaderLayout breadcrumbs={breadcrumbs} navItems={navItems} homeHref="/dashboard">
            {children}
        </AppHeaderLayout>
    );
}
