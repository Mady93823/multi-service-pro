import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useTrans } from '@/lib/i18n';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';

/** The current URL carries the query string; nav URLs never do. */
function isOnTrail(currentUrl: string, url: string): boolean {
    const path = currentUrl.split('?')[0];

    return path === url || path.startsWith(`${url}/`);
}

/** A group is "on" when the page sits under it, or under any of its children. */
function groupIsOpen(currentUrl: string, item: NavItem): boolean {
    return isOnTrail(currentUrl, item.url) || (item.children ?? []).some((child) => isOnTrail(currentUrl, child.url));
}

export function NavMain({ items = [] }: { items: NavItem[] }) {
    const page = usePage();
    const t = useTrans();

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>{t('Platform')}</SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) =>
                    item.children && item.children.length > 0 ? (
                        <Collapsible key={item.title} asChild defaultOpen={groupIsOpen(page.url, item)} className="group/collapsible">
                            <SidebarMenuItem>
                                <CollapsibleTrigger asChild>
                                    <SidebarMenuButton tooltip={item.title} isActive={groupIsOpen(page.url, item)}>
                                        {item.icon && <item.icon />}
                                        <span>{item.title}</span>
                                        <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                                    </SidebarMenuButton>
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <SidebarMenuSub>
                                        {item.children.map((child) => (
                                            <SidebarMenuSubItem key={child.title}>
                                                <SidebarMenuSubButton asChild isActive={isOnTrail(page.url, child.url)}>
                                                    <Link href={child.url} prefetch>
                                                        <span>{child.title}</span>
                                                    </Link>
                                                </SidebarMenuSubButton>
                                            </SidebarMenuSubItem>
                                        ))}
                                    </SidebarMenuSub>
                                </CollapsibleContent>
                            </SidebarMenuItem>
                        </Collapsible>
                    ) : (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton asChild tooltip={item.title} isActive={isOnTrail(page.url, item.url)}>
                                <Link href={item.url} prefetch>
                                    {item.icon && <item.icon />}
                                    <span>{item.title}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    ),
                )}
            </SidebarMenu>
        </SidebarGroup>
    );
}
