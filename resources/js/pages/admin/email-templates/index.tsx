import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AdminLayout from '@/layouts/admin-layout';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, Pencil } from 'lucide-react';

interface TemplateEvent {
    key: string;
    label: string;
    description: string;
    has_template: boolean;
    is_enabled: boolean;
}

interface EmailTemplatesIndexProps {
    events: TemplateEvent[];
    mail_configured: boolean;
}

export default function EmailTemplatesIndex({ events, mail_configured: mailConfigured }: EmailTemplatesIndexProps) {
    const t = useTrans();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('Email templates'), href: '/admin/email-templates' },
    ];

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Email templates')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">{t('Email templates')}</h1>
                <p className="text-muted-foreground text-sm">
                    {t('Every email has a built-in version. A template only replaces the wording — remove it and the built-in email comes back.')}
                </p>

                {!mailConfigured && (
                    <p className="flex items-start gap-2 rounded-md border px-3 py-2 text-sm">
                        <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                        {t('No SMTP server is configured, so no email is being sent yet.')}
                        <Link href={route('admin.settings.edit', 'mail')} className="underline">
                            {t('Email settings')}
                        </Link>
                    </p>
                )}

                <div className="grid gap-3">
                    {events.map((event) => (
                        <Card key={event.key}>
                            <CardContent className="flex items-center justify-between gap-4 py-4">
                                <div className="min-w-0">
                                    <div className="flex items-center gap-2">
                                        <p className="font-medium">{event.label}</p>
                                        {!event.has_template && <Badge variant="outline">{t('Built-in')}</Badge>}
                                        {event.has_template && event.is_enabled && <Badge variant="secondary">{t('Custom')}</Badge>}
                                        {event.has_template && !event.is_enabled && <Badge variant="outline">{t('Custom, off')}</Badge>}
                                    </div>
                                    <p className="text-muted-foreground truncate text-sm">{event.description}</p>
                                </div>
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={route('admin.email-templates.edit', event.key)}>
                                        <Pencil className="h-4 w-4" />
                                        {t('Edit')}
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        </AdminLayout>
    );
}
