import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import AdminLayout from '@/layouts/admin-layout';
import { useTrans } from '@/lib/i18n';
import {
    type BreadcrumbItem,
    type NotificationChannelInfo,
    type NotificationEventInfo,
    type NotificationMatrix,
    type NotificationPreferenceRow,
} from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { Megaphone } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface NotificationsIndexProps {
    events: NotificationEventInfo[];
    channels: NotificationChannelInfo[];
    matrix: NotificationMatrix;
    segments: string[];
}

export default function NotificationsIndex({ events, channels, matrix, segments }: NotificationsIndexProps) {
    const t = useTrans();
    const [state, setState] = useState<NotificationMatrix>(matrix);

    const { transform, put, processing } = useForm<{ preferences: NotificationPreferenceRow[] }>({ preferences: [] });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('Notifications'), href: '/admin/notifications' },
    ];

    const toggle = (event: string, channel: string, enabled: boolean) =>
        setState((current) => ({ ...current, [event]: { ...current[event], [channel]: enabled } }));

    const save: FormEventHandler = (e) => {
        e.preventDefault();

        // transform() runs at submit time. Setting the form data from `state`
        // and posting on the next line would send the previous render's matrix
        // (M18's landmine).
        transform(() => ({
            preferences: events.flatMap((event) =>
                channels.map((channel) => ({
                    event: event.key,
                    channel: channel.key,
                    enabled: state[event.key]?.[channel.key] ?? false,
                })),
            ),
        }));

        put(route('admin.notifications.update'), { preserveScroll: true });
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Notifications')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between gap-4">
                    <h1 className="text-xl font-semibold">{t('Notifications')}</h1>
                    <AnnouncementDialog segments={segments} />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">{t('What gets sent, and how')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="text-muted-foreground mb-4 text-sm">
                            {t(
                                'The in-app feed and the live bell are always on — they are the record of what happened. A user can still turn any of these off for themselves.',
                            )}
                        </p>

                        <form onSubmit={save} className="space-y-4">
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>{t('Event')}</TableHead>
                                            {channels.map((channel) => (
                                                <TableHead key={channel.key} className="text-center">
                                                    <span className="inline-flex items-center gap-1">
                                                        {channel.label}
                                                        {!channel.available && <Badge variant="outline">{t('Not set up')}</Badge>}
                                                    </span>
                                                </TableHead>
                                            ))}
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {events.map((event) => (
                                            <TableRow key={event.key}>
                                                <TableCell>
                                                    <p className="font-medium">{event.label}</p>
                                                    <p className="text-muted-foreground text-xs">{event.description}</p>
                                                </TableCell>
                                                {channels.map((channel) => (
                                                    <TableCell key={channel.key} className="text-center">
                                                        <Switch
                                                            checked={state[event.key]?.[channel.key] ?? false}
                                                            onCheckedChange={(checked) => toggle(event.key, channel.key, checked)}
                                                            aria-label={`${event.label} — ${channel.label}`}
                                                        />
                                                    </TableCell>
                                                ))}
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>

                            <div className="flex items-center gap-3">
                                <Button type="submit" disabled={processing}>
                                    {t('Save')}
                                </Button>
                                <Link href={route('admin.email-templates.index')} className="text-muted-foreground text-sm underline">
                                    {t('Email templates')}
                                </Link>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}

function AnnouncementDialog({ segments }: { segments: string[] }) {
    const t = useTrans();
    const [open, setOpen] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        segment: segments[0] ?? 'all',
        title: '',
        message: '',
        url: '',
    });

    const segmentLabel = (segment: string) =>
        segment === 'customers' ? t('Customers') : segment === 'providers' ? t('Professionals') : t('Everyone');

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('admin.notifications.announce'), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setOpen(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <Megaphone className="h-4 w-4" />
                    {t('Send announcement')}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('Send announcement')}</DialogTitle>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="segment">{t('Send to')}</Label>
                        <Select value={data.segment} onValueChange={(value) => setData('segment', value)}>
                            <SelectTrigger id="segment">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {segments.map((segment) => (
                                    <SelectItem key={segment} value={segment}>
                                        {segmentLabel(segment)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.segment} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="title">{t('Title')}</Label>
                        <Input id="title" value={data.title} onChange={(e) => setData('title', e.target.value)} maxLength={100} required />
                        <InputError message={errors.title} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="message">{t('Message')}</Label>
                        <Textarea
                            id="message"
                            value={data.message}
                            onChange={(e) => setData('message', e.target.value)}
                            maxLength={500}
                            rows={4}
                            required
                        />
                        <InputError message={errors.message} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="url">{t('Link (optional)')}</Label>
                        <Input id="url" value={data.url} onChange={(e) => setData('url', e.target.value)} placeholder="https://" />
                        <InputError message={errors.url} />
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setOpen(false)}>
                            {t('Cancel')}
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {t('Send')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
