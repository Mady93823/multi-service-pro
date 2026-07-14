import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AdminLayout from '@/layouts/admin-layout';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { AlertTriangle, RefreshCw } from 'lucide-react';
import { useState } from 'react';

interface HealthCheck {
    key: string;
    label: string;
    value: string;
    status: string;
}

interface ScheduledTask {
    command: string;
    expression: string;
    next_run: string | null;
}

interface SystemIndexProps {
    about: { version: string; php: string; laravel: string; timezone: string; locale: string };
    checks: HealthCheck[];
    scheduler: {
        last_run: string | null;
        is_stale: boolean;
        cron_line: string;
        tasks: ScheduledTask[];
    };
}

export default function SystemIndex({ about, checks, scheduler }: SystemIndexProps) {
    const t = useTrans();
    const [updating, setUpdating] = useState(false);
    const { flash } = usePage<SharedData>().props;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('System'), href: '/admin/system' },
    ];

    const badge = (status: string) => {
        if (status === 'error') {
            return <Badge variant="destructive">{t('Needs attention')}</Badge>;
        }

        if (status === 'warning') {
            return <Badge variant="outline">{t('Check')}</Badge>;
        }

        return <Badge variant="secondary">{t('OK')}</Badge>;
    };

    const runUpdate = () => {
        setUpdating(true);
        router.post(route('admin.system.update'), {}, { preserveScroll: true, onFinish: () => setUpdating(false) });
    };

    const lastRun = scheduler.last_run === null ? t('Never') : new Date(scheduler.last_run).toLocaleString();

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('System')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">{t('System')}</h1>

                {scheduler.is_stale && (
                    <div className="border-destructive/50 flex items-start gap-3 rounded-lg border px-4 py-3 text-sm">
                        <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                        <div>
                            <p className="font-medium">{t('The task scheduler is not running.')}</p>
                            <p className="text-muted-foreground">
                                {t(
                                    'Payouts will not release, unpaid bookings will not expire and exports will not be cleaned up. Add this line to your crontab:',
                                )}
                            </p>
                            <code className="bg-muted mt-2 block overflow-x-auto rounded p-2 text-xs">{scheduler.cron_line}</code>
                        </div>
                    </div>
                )}

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">{t('Health')}</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            {checks.map((check) => (
                                <div key={check.key} className="flex items-center justify-between gap-3 text-sm">
                                    <span className="text-muted-foreground">{check.label}</span>
                                    <span className="flex items-center gap-2">
                                        <span className="font-medium">{check.value}</span>
                                        {badge(check.status)}
                                    </span>
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">{t('About')}</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">{t('Version')}</span>
                                <span className="font-medium">{about.version}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">{t('Laravel')}</span>
                                <span className="font-medium">{about.laravel}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">{t('PHP')}</span>
                                <span className="font-medium">{about.php}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">{t('Timezone')}</span>
                                <span className="font-medium">{about.timezone}</span>
                            </div>

                            <div className="pt-3">
                                <Button variant="outline" onClick={runUpdate} disabled={updating}>
                                    <RefreshCw className="h-4 w-4" />
                                    {t('Run update')}
                                </Button>
                                <p className="text-muted-foreground mt-2 text-xs">
                                    {t(
                                        'Runs migrations and clears caches — the same thing you would run from a terminal after uploading a new release.',
                                    )}
                                </p>
                            </div>

                            {flash.update_output !== undefined && flash.update_output !== null && (
                                <pre className="bg-muted mt-2 max-h-48 overflow-auto rounded p-3 text-xs whitespace-pre-wrap">
                                    {flash.update_output}
                                </pre>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">{t('Scheduled tasks')}</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <p className="text-muted-foreground text-sm">
                            {t('Last run')}: <span className="font-medium">{lastRun}</span>
                        </p>

                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>{t('Task')}</TableHead>
                                        <TableHead>{t('Schedule')}</TableHead>
                                        <TableHead>{t('Next run')}</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {scheduler.tasks.map((task) => (
                                        <TableRow key={`${task.command}-${task.expression}`}>
                                            <TableCell className="font-mono text-xs">{task.command}</TableCell>
                                            <TableCell className="font-mono text-xs">{task.expression}</TableCell>
                                            <TableCell className="text-muted-foreground text-xs">
                                                {task.next_run === null ? '—' : new Date(task.next_run).toLocaleString()}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        <div>
                            <p className="text-muted-foreground text-xs">{t('Cron line')}</p>
                            <code className="bg-muted mt-1 block overflow-x-auto rounded p-2 text-xs">{scheduler.cron_line}</code>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
