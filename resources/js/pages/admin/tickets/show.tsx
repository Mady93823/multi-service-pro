import InputError from '@/components/input-error';
import { ReplyBox } from '@/components/support/reply-box';
import { TicketStatusBadge } from '@/components/support/ticket-status-badge';
import { TicketThread } from '@/components/support/ticket-thread';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AdminLayout from '@/layouts/admin-layout';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem, type CannedResponse, type SupportTicket, type SupportTicketMessage } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { CheckCheck, Lock } from 'lucide-react';
import { useState } from 'react';

interface AdminTicketShowProps {
    ticket: SupportTicket;
    messages: SupportTicketMessage[];
    canned_responses: CannedResponse[];
    admins: { id: number; name: string }[];
}

export default function AdminTicketShow({ ticket, messages, canned_responses, admins }: AdminTicketShowProps) {
    const t = useTrans();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('Support'), href: '/admin/tickets' },
        { title: ticket.code, href: `/admin/tickets/${ticket.id}` },
    ];

    const dateFormat = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' });
    const closed = ticket.status === 'closed';

    const assign = (value: string) => {
        router.post(route('admin.tickets.assign', ticket.id), { assigned_to: value === 'none' ? null : Number(value) }, { preserveScroll: true });
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={ticket.code} />
            <div className="flex flex-1 flex-col gap-4 p-4 lg:flex-row">
                <div className="flex min-w-0 flex-1 flex-col gap-4">
                    <Card>
                        <CardHeader>
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <p className="text-muted-foreground font-mono text-xs">{ticket.code}</p>
                                    <CardTitle className="text-lg">{ticket.subject}</CardTitle>
                                </div>
                                <TicketStatusBadge ticket={ticket} />
                            </div>
                        </CardHeader>
                        {ticket.resolution_note !== null && (
                            <CardContent className="pt-0">
                                <div className="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
                                    <p className="font-medium">{t('Resolution')}</p>
                                    <p className="mt-1 whitespace-pre-wrap">{ticket.resolution_note}</p>
                                </div>
                            </CardContent>
                        )}
                    </Card>

                    <TicketThread messages={messages} viewerIsStaff />

                    {closed ? (
                        <div className="text-muted-foreground flex items-center justify-center gap-2 rounded-md border border-dashed p-4 text-sm">
                            <Lock className="h-4 w-4" />
                            {t('Closed tickets are read-only.')}
                        </div>
                    ) : (
                        <Card>
                            <CardContent className="pt-6">
                                <ReplyBox action={route('admin.tickets.reply', ticket.id)} cannedResponses={canned_responses} />
                            </CardContent>
                        </Card>
                    )}
                </div>

                <div className="flex w-full flex-col gap-4 lg:w-80">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">{t('Details')}</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-2 text-sm">
                            <p>
                                <span className="text-muted-foreground">{t('Requester')}: </span>
                                {ticket.user?.name}
                                <span className="text-muted-foreground block text-xs">{ticket.user?.email}</span>
                            </p>
                            <p>
                                <span className="text-muted-foreground">{t('Category')}: </span>
                                {ticket.category_label}
                            </p>
                            <p>
                                <span className="text-muted-foreground">{t('Priority')}: </span>
                                {ticket.priority_label}
                            </p>
                            {ticket.booking != null && (
                                <p>
                                    <span className="text-muted-foreground">{t('Booking')}: </span>
                                    <Link href={route('admin.bookings.show', ticket.booking.id)} className="text-primary hover:underline">
                                        {ticket.booking.code}
                                    </Link>
                                </p>
                            )}
                            {ticket.created_at !== null && (
                                <p>
                                    <span className="text-muted-foreground">{t('Opened')}: </span>
                                    {dateFormat.format(new Date(ticket.created_at))}
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    {!closed && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">{t('Assignment')}</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-3">
                                <Select value={ticket.assignee != null ? String(ticket.assignee.id) : 'none'} onValueChange={assign}>
                                    <SelectTrigger>
                                        <SelectValue placeholder={t('Unassigned')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">{t('Unassigned')}</SelectItem>
                                        {admins.map((admin) => (
                                            <SelectItem key={admin.id} value={String(admin.id)}>
                                                {admin.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </CardContent>
                        </Card>
                    )}

                    {!closed && <TicketActions ticket={ticket} />}
                </div>
            </div>
        </AdminLayout>
    );
}

function TicketActions({ ticket }: { ticket: SupportTicket }) {
    const t = useTrans();
    const [resolveOpen, setResolveOpen] = useState(false);
    const [closeOpen, setCloseOpen] = useState(false);

    const resolve = useForm({ resolution_note: ticket.resolution_note ?? '' });
    const close = useForm({ resolution_note: '' });

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">{t('Actions')}</CardTitle>
            </CardHeader>
            <CardContent className="grid gap-2">
                {ticket.status !== 'resolved' && (
                    <Dialog open={resolveOpen} onOpenChange={setResolveOpen}>
                        <DialogTrigger asChild>
                            <Button variant="outline">
                                <CheckCheck className="mr-1 h-4 w-4" />
                                {t('Mark resolved')}
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>{t('Resolve this ticket?')}</DialogTitle>
                                <DialogDescription>{t('The requester is notified and can still reply to reopen it.')}</DialogDescription>
                            </DialogHeader>
                            <div className="grid gap-2">
                                <Label htmlFor="resolve-note">{t('Resolution note')}</Label>
                                <Textarea
                                    id="resolve-note"
                                    value={resolve.data.resolution_note}
                                    onChange={(event) => resolve.setData('resolution_note', event.target.value)}
                                />
                                <InputError message={resolve.errors.resolution_note} />
                            </div>
                            <DialogFooter>
                                <Button variant="outline" onClick={() => setResolveOpen(false)} disabled={resolve.processing}>
                                    {t('Cancel')}
                                </Button>
                                <Button
                                    disabled={resolve.processing}
                                    onClick={() =>
                                        resolve.post(route('admin.tickets.resolve', ticket.id), {
                                            preserveScroll: true,
                                            onSuccess: () => setResolveOpen(false),
                                        })
                                    }
                                >
                                    {t('Resolve')}
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                )}

                <Dialog open={closeOpen} onOpenChange={setCloseOpen}>
                    <DialogTrigger asChild>
                        <Button variant="destructive">
                            <Lock className="mr-1 h-4 w-4" />
                            {t('Close ticket')}
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>{t('Close this ticket?')}</DialogTitle>
                            <DialogDescription>{t('Closing is final — nobody can reply afterwards.')}</DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-2">
                            <Label htmlFor="close-note">{t('Resolution note (optional)')}</Label>
                            <Textarea
                                id="close-note"
                                value={close.data.resolution_note}
                                onChange={(event) => close.setData('resolution_note', event.target.value)}
                            />
                            <InputError message={close.errors.resolution_note} />
                        </div>
                        <DialogFooter>
                            <Button variant="outline" onClick={() => setCloseOpen(false)} disabled={close.processing}>
                                {t('Cancel')}
                            </Button>
                            <Button
                                variant="destructive"
                                disabled={close.processing}
                                onClick={() =>
                                    close.post(route('admin.tickets.close', ticket.id), {
                                        preserveScroll: true,
                                        onSuccess: () => setCloseOpen(false),
                                    })
                                }
                            >
                                {t('Close ticket')}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </CardContent>
        </Card>
    );
}
