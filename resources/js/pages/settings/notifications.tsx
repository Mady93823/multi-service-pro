import HeadingSmall from '@/components/heading-small';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { useTrans } from '@/lib/i18n';
import {
    type BreadcrumbItem,
    type NotificationChannelInfo,
    type NotificationEventInfo,
    type NotificationMatrix,
    type NotificationPreferenceRow,
} from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface NotificationSettingsProps {
    events: NotificationEventInfo[];
    channels: NotificationChannelInfo[];
    matrix: NotificationMatrix;
}

export default function NotificationSettings({ events, channels, matrix }: NotificationSettingsProps) {
    const t = useTrans();
    const [state, setState] = useState<NotificationMatrix>(matrix);

    const { transform, put, processing, recentlySuccessful } = useForm<{ preferences: NotificationPreferenceRow[] }>({
        preferences: [],
    });

    const breadcrumbs: BreadcrumbItem[] = [{ title: t('Notification settings'), href: '/settings/notifications' }];

    const toggle = (event: string, channel: string, enabled: boolean) =>
        setState((current: NotificationMatrix) => ({ ...current, [event]: { ...current[event], [channel]: enabled } }));

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        transform(() => ({
            preferences: events.flatMap((event) =>
                channels.map((channel) => ({
                    event: event.key,
                    channel: channel.key,
                    enabled: state[event.key]?.[channel.key] ?? false,
                })),
            ),
        }));

        put(route('notifications.update'), { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Notification settings')} />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title={t('Notifications')}
                        description={t('Choose how we reach you. Your in-app notifications always stay on, so you never lose the record.')}
                    />

                    <form onSubmit={submit} className="space-y-6">
                        {events.map((event) => (
                            <div key={event.key} className="space-y-3 rounded-lg border p-4">
                                <div>
                                    <p className="font-medium">{event.label}</p>
                                    <p className="text-muted-foreground text-sm">{event.description}</p>
                                </div>

                                <div className="grid gap-2 sm:grid-cols-3">
                                    {channels.map((channel) => (
                                        <label key={channel.key} className="flex items-center justify-between gap-3 text-sm">
                                            <span className="flex items-center gap-2">
                                                {channel.label}
                                                {!channel.available && <Badge variant="outline">{t('Unavailable')}</Badge>}
                                            </span>
                                            <Switch
                                                checked={state[event.key]?.[channel.key] ?? false}
                                                disabled={!channel.available}
                                                onCheckedChange={(checked) => toggle(event.key, channel.key, checked)}
                                                aria-label={`${event.label} — ${channel.label}`}
                                            />
                                        </label>
                                    ))}
                                </div>
                            </div>
                        ))}

                        <div className="flex items-center gap-4">
                            <Button type="submit" disabled={processing}>
                                {t('Save')}
                            </Button>
                            {recentlySuccessful && <p className="text-muted-foreground text-sm">{t('Saved.')}</p>}
                        </div>
                    </form>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
