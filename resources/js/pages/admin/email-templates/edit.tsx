import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import AdminLayout from '@/layouts/admin-layout';
import { postJson } from '@/lib/http';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { AlertTriangle, Send, Trash2 } from 'lucide-react';
import { FormEventHandler, useEffect, useState } from 'react';

interface TemplateEvent {
    key: string;
    label: string;
    description: string;
    variables: string[];
}

interface EmailTemplateEditProps {
    event: TemplateEvent;
    template: { subject: string; body: string; is_enabled: boolean } | null;
    mail_configured: boolean;
}

interface Preview {
    subject: string;
    html: string;
}

export default function EmailTemplateEdit({ event, template, mail_configured: mailConfigured }: EmailTemplateEditProps) {
    const t = useTrans();
    const [preview, setPreview] = useState<Preview | null>(null);

    const { data, setData, put, processing, errors } = useForm({
        subject: template?.subject ?? '',
        body: template?.body ?? '',
        is_enabled: template?.is_enabled ?? true,
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('Email templates'), href: '/admin/email-templates' },
        { title: event.label, href: `/admin/email-templates/${event.key}` },
    ];

    // The preview renders on the server, through the very same markdown path a
    // real send uses (D20/D25) — what you see here is what lands in the inbox.
    useEffect(() => {
        if (data.subject === '' && data.body === '') {
            setPreview(null);

            return;
        }

        const timer = window.setTimeout(() => {
            postJson<Preview>(route('admin.email-templates.preview', event.key), { subject: data.subject, body: data.body })
                .then(setPreview)
                .catch(() => setPreview(null));
        }, 400);

        return () => window.clearTimeout(timer);
    }, [data.subject, data.body, event.key]);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('admin.email-templates.update', event.key), { preserveScroll: true });
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={event.label} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold">{event.label}</h1>
                        <p className="text-muted-foreground text-sm">{event.description}</p>
                    </div>
                    {template !== null && (
                        <Button variant="outline" onClick={() => router.delete(route('admin.email-templates.destroy', event.key))}>
                            <Trash2 className="h-4 w-4" />
                            {t('Use the built-in email')}
                        </Button>
                    )}
                </div>

                {!mailConfigured && (
                    <p className="flex items-start gap-2 rounded-md border px-3 py-2 text-sm">
                        <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                        {t('No SMTP server is configured, so no email is being sent yet.')}
                        <Link href={route('admin.settings.edit', 'mail')} className="underline">
                            {t('Email settings')}
                        </Link>
                    </p>
                )}

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">{t('Template')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submit} className="space-y-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="subject">{t('Subject')}</Label>
                                    <Input id="subject" value={data.subject} onChange={(e) => setData('subject', e.target.value)} required />
                                    <InputError message={errors.subject} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="body">{t('Body')}</Label>
                                    <Textarea
                                        id="body"
                                        value={data.body}
                                        onChange={(e) => setData('body', e.target.value)}
                                        rows={12}
                                        className="font-mono text-sm"
                                        required
                                    />
                                    <InputError message={errors.body} />
                                    <p className="text-muted-foreground text-xs">{t('Markdown. Raw HTML is stripped.')}</p>
                                </div>

                                <div className="flex flex-wrap gap-1">
                                    {event.variables.map((variable) => (
                                        <Badge
                                            key={variable}
                                            variant="outline"
                                            className="cursor-pointer font-mono text-xs"
                                            onClick={() => setData('body', `${data.body}{{ ${variable} }}`)}
                                        >
                                            {`{{ ${variable} }}`}
                                        </Badge>
                                    ))}
                                </div>

                                <label className="flex items-center justify-between gap-4 text-sm">
                                    <span>
                                        <span className="font-medium">{t('Use this template')}</span>
                                        <span className="text-muted-foreground block">{t('Switch it off to send the built-in email instead.')}</span>
                                    </span>
                                    <Switch checked={data.is_enabled} onCheckedChange={(checked) => setData('is_enabled', checked)} />
                                </label>

                                <div className="flex items-center gap-3">
                                    <Button type="submit" disabled={processing}>
                                        {t('Save')}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={!mailConfigured}
                                        onClick={() => router.post(route('admin.email-templates.test', event.key), {}, { preserveScroll: true })}
                                    >
                                        <Send className="h-4 w-4" />
                                        {t('Send test to me')}
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">{t('Preview')}</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {preview === null ? (
                                <p className="text-muted-foreground text-sm">{t('Write a subject and a body to see the preview.')}</p>
                            ) : (
                                <>
                                    <p className="text-sm font-medium">{preview.subject}</p>
                                    <div
                                        className="prose prose-sm dark:prose-invert max-w-none rounded-md border p-4"
                                        dangerouslySetInnerHTML={{ __html: preview.html }}
                                    />
                                </>
                            )}
                            <p className="text-muted-foreground text-xs">{t('Placeholders are filled with sample values.')}</p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AdminLayout>
    );
}
