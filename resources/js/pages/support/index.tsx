import { TicketStatusBadge } from '@/components/support/ticket-status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import CustomerLayout from '@/layouts/customer-layout';
import ProviderLayout from '@/layouts/provider-layout';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem, type Paginated, type SharedData, type SupportTicket } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { LifeBuoy, Plus } from 'lucide-react';
import { type ComponentType } from 'react';

interface SupportIndexProps {
    tickets: Paginated<SupportTicket>;
}

interface LayoutProps {
    children: React.ReactNode;
    breadcrumbs?: BreadcrumbItem[];
}

// Same idiom as the notifications page: one screen, shell picked by role.
// Admins never land here — they have their own queue at /admin/tickets.
function layoutForRoles(roles: string[]): ComponentType<LayoutProps> {
    return roles.includes('provider') ? ProviderLayout : CustomerLayout;
}

export default function SupportIndex({ tickets }: SupportIndexProps) {
    const t = useTrans();
    const { auth } = usePage<SharedData>().props;
    const Layout = layoutForRoles(auth.roles);

    const breadcrumbs: BreadcrumbItem[] = [{ title: t('Help & support'), href: '/support/tickets' }];

    const dateFormat = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' });

    return (
        <Layout breadcrumbs={breadcrumbs}>
            <Head title={t('Help & support')} />
            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">{t('Help & support')}</h1>
                    <Button asChild>
                        <Link href={route('support.tickets.create')}>
                            <Plus className="mr-1 h-4 w-4" />
                            {t('New ticket')}
                        </Link>
                    </Button>
                </div>

                <Card>
                    <CardContent className="p-0">
                        {tickets.data.length === 0 ? (
                            <div className="text-muted-foreground flex flex-col items-center gap-2 p-10 text-center text-sm">
                                <LifeBuoy className="h-8 w-8" />
                                <p>{t('No support tickets yet. Raise one and our team will help you out.')}</p>
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>{t('Ticket')}</TableHead>
                                        <TableHead>{t('Subject')}</TableHead>
                                        <TableHead>{t('Status')}</TableHead>
                                        <TableHead className="text-right">{t('Last update')}</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {tickets.data.map((ticket) => (
                                        <TableRow key={ticket.id}>
                                            <TableCell className="font-mono text-xs">
                                                <Link href={route('support.tickets.show', ticket.id)} className="text-primary hover:underline">
                                                    {ticket.code}
                                                </Link>
                                            </TableCell>
                                            <TableCell className="max-w-sm">
                                                <Link href={route('support.tickets.show', ticket.id)} className="hover:underline">
                                                    <span className="line-clamp-1 font-medium">{ticket.subject}</span>
                                                </Link>
                                                <span className="text-muted-foreground text-xs">{ticket.category_label}</span>
                                            </TableCell>
                                            <TableCell>
                                                <TicketStatusBadge ticket={ticket} />
                                            </TableCell>
                                            <TableCell className="text-muted-foreground text-right text-sm">
                                                {ticket.last_reply_at !== null && dateFormat.format(new Date(ticket.last_reply_at))}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>

                {(tickets.links.prev !== null || tickets.links.next !== null) && (
                    <div className="flex items-center justify-between text-sm">
                        {tickets.links.prev !== null ? (
                            <Link href={tickets.links.prev} preserveScroll className="text-primary hover:underline">
                                {t('Previous')}
                            </Link>
                        ) : (
                            <span />
                        )}
                        {tickets.links.next !== null && (
                            <Link href={tickets.links.next} preserveScroll className="text-primary hover:underline">
                                {t('Next')}
                            </Link>
                        )}
                    </div>
                )}
            </div>
        </Layout>
    );
}
