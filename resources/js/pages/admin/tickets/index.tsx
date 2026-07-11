import { TicketStatusBadge } from '@/components/support/ticket-status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AdminLayout from '@/layouts/admin-layout';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem, type Paginated, type SupportTicket } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

interface AdminTicketsProps {
    tickets: Paginated<SupportTicket>;
    filters: { status: string | null; priority: string | null; assigned: string | null };
}

export default function AdminTickets({ tickets, filters }: AdminTicketsProps) {
    const t = useTrans();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('Support'), href: '/admin/tickets' },
    ];

    const dateFormat = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' });

    const applyFilters = (next: Partial<AdminTicketsProps['filters']>) => {
        const merged = { ...filters, ...next };
        router.get(
            route('admin.tickets.index'),
            {
                ...(merged.status === null ? {} : { status: merged.status }),
                ...(merged.priority === null ? {} : { priority: merged.priority }),
                ...(merged.assigned === null ? {} : { assigned: merged.assigned }),
            },
            { preserveState: true, replace: true },
        );
    };

    const statusOptions: { value: string | null; label: string }[] = [
        { value: null, label: t('All') },
        { value: 'open', label: t('Open') },
        { value: 'pending', label: t('Pending') },
        { value: 'resolved', label: t('Resolved') },
        { value: 'closed', label: t('Closed') },
    ];

    const assignedOptions: { value: string | null; label: string }[] = [
        { value: null, label: t('Everyone') },
        { value: 'me', label: t('Mine') },
        { value: 'unassigned', label: t('Unassigned') },
    ];

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Support')} />
            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h1 className="text-xl font-semibold">{t('Support tickets')}</h1>
                    <div className="flex flex-wrap items-center gap-1">
                        {statusOptions.map((option) => (
                            <Button
                                key={option.label}
                                size="sm"
                                variant={filters.status === option.value ? 'default' : 'outline'}
                                onClick={() => applyFilters({ status: option.value })}
                            >
                                {option.label}
                            </Button>
                        ))}
                        <span className="text-muted-foreground mx-1 text-xs">{t('Assigned')}</span>
                        {assignedOptions.map((option) => (
                            <Button
                                key={option.label}
                                size="sm"
                                variant={filters.assigned === option.value ? 'default' : 'outline'}
                                onClick={() => applyFilters({ assigned: option.value })}
                            >
                                {option.label}
                            </Button>
                        ))}
                    </div>
                </div>

                <Card>
                    <CardContent className="p-0">
                        {tickets.data.length === 0 ? (
                            <p className="text-muted-foreground p-6 text-center text-sm">{t('No tickets match these filters.')}</p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>{t('Ticket')}</TableHead>
                                        <TableHead>{t('Subject')}</TableHead>
                                        <TableHead>{t('Priority')}</TableHead>
                                        <TableHead>{t('Status')}</TableHead>
                                        <TableHead>{t('Assignee')}</TableHead>
                                        <TableHead className="text-right">{t('Last update')}</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {tickets.data.map((ticket) => (
                                        <TableRow key={ticket.id}>
                                            <TableCell className="font-mono text-xs">
                                                <Link href={route('admin.tickets.show', ticket.id)} className="text-primary hover:underline">
                                                    {ticket.code}
                                                </Link>
                                            </TableCell>
                                            <TableCell className="max-w-sm">
                                                <Link href={route('admin.tickets.show', ticket.id)} className="hover:underline">
                                                    <span className="line-clamp-1 font-medium">{ticket.subject}</span>
                                                </Link>
                                                <span className="text-muted-foreground text-xs">
                                                    {ticket.user?.name}
                                                    {ticket.booking != null && <> · {ticket.booking.code}</>}
                                                </span>
                                            </TableCell>
                                            <TableCell className="text-sm">{ticket.priority_label}</TableCell>
                                            <TableCell>
                                                <TicketStatusBadge ticket={ticket} />
                                            </TableCell>
                                            <TableCell className="text-sm">{ticket.assignee?.name ?? '—'}</TableCell>
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
        </AdminLayout>
    );
}
