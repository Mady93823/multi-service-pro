import { ReplyBox } from '@/components/support/reply-box';
import { TicketStatusBadge } from '@/components/support/ticket-status-badge';
import { TicketThread } from '@/components/support/ticket-thread';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import CustomerLayout from '@/layouts/customer-layout';
import ProviderLayout from '@/layouts/provider-layout';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem, type SharedData, type SupportTicket, type SupportTicketMessage } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import { type ComponentType } from 'react';

interface SupportShowProps {
    ticket: SupportTicket;
    messages: SupportTicketMessage[];
    can_reply: boolean;
}

interface LayoutProps {
    children: React.ReactNode;
    breadcrumbs?: BreadcrumbItem[];
}

function layoutForRoles(roles: string[]): ComponentType<LayoutProps> {
    return roles.includes('provider') ? ProviderLayout : CustomerLayout;
}

export default function SupportShow({ ticket, messages, can_reply }: SupportShowProps) {
    const t = useTrans();
    const { auth } = usePage<SharedData>().props;
    const Layout = layoutForRoles(auth.roles);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Help & support'), href: '/support/tickets' },
        { title: ticket.code, href: `/support/tickets/${ticket.id}` },
    ];

    return (
        <Layout breadcrumbs={breadcrumbs}>
            <Head title={ticket.code} />
            <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-4 p-4">
                <Card>
                    <CardHeader>
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p className="text-muted-foreground font-mono text-xs">{ticket.code}</p>
                                <CardTitle className="text-lg">{ticket.subject}</CardTitle>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    {ticket.category_label}
                                    {ticket.booking != null && (
                                        <>
                                            {' · '}
                                            {auth.roles.includes('provider') ? (
                                                // Providers have no booking-show page; the code alone is enough context.
                                                <span className="font-mono text-xs">{ticket.booking.code}</span>
                                            ) : (
                                                <Link href={route('bookings.show', ticket.booking.id)} className="text-primary hover:underline">
                                                    {ticket.booking.code}
                                                </Link>
                                            )}
                                        </>
                                    )}
                                </p>
                            </div>
                            <TicketStatusBadge ticket={ticket} />
                        </div>
                    </CardHeader>
                    {(ticket.status === 'resolved' || ticket.status === 'closed') && ticket.resolution_note !== null && (
                        <CardContent className="pt-0">
                            <div className="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
                                <p className="font-medium">{t('Resolution')}</p>
                                <p className="mt-1 whitespace-pre-wrap">{ticket.resolution_note}</p>
                            </div>
                        </CardContent>
                    )}
                </Card>

                <TicketThread messages={messages} viewerIsStaff={false} />

                {can_reply ? (
                    <Card>
                        <CardContent className="pt-6">
                            <ReplyBox action={route('support.tickets.reply', ticket.id)} />
                        </CardContent>
                    </Card>
                ) : (
                    <div className="text-muted-foreground flex items-center justify-center gap-2 rounded-md border border-dashed p-4 text-sm">
                        <Lock className="h-4 w-4" />
                        {t('This ticket is closed. Raise a new ticket if you need more help.')}
                    </div>
                )}
            </div>
        </Layout>
    );
}
