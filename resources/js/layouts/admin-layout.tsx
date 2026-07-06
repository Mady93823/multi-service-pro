import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { type BreadcrumbItem, type NavItem } from '@/types';
import { FolderTree, LayoutGrid, Wrench } from 'lucide-react';

const navItems: NavItem[] = [
    {
        title: 'Dashboard',
        url: '/admin/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Categories',
        url: '/admin/categories',
        icon: FolderTree,
    },
    {
        title: 'Services',
        url: '/admin/services',
        icon: Wrench,
    },
];

interface AdminLayoutProps {
    children: React.ReactNode;
    breadcrumbs?: BreadcrumbItem[];
}

export default function AdminLayout({ children, breadcrumbs }: AdminLayoutProps) {
    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs} navItems={navItems} homeHref="/admin/dashboard">
            {children}
        </AppSidebarLayout>
    );
}
