import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AdminLayout from '@/layouts/admin-layout';
import { useTrans } from '@/lib/i18n';
import { type ActivityLogRow, type BreadcrumbItem, type NativePaginated } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

interface Props {
    logs: NativePaginated<ActivityLogRow>;
    actions: string[];
    filters: { action: string };
}

function contextSummary(context: Record<string, unknown> | null): string {
    if (context === null) {
        return '';
    }

    return Object.entries(context)
        .filter(([, value]) => value !== null && value !== '')
        .map(([key, value]) => `${key}: ${Array.isArray(value) ? value.join(', ') : String(value)}`)
        .join(' · ');
}

export default function ActivityIndex({ logs, actions, filters }: Props) {
    const t = useTrans();

    const breadcrumbs: BreadcrumbItem[] = [{ title: t('Activity log'), href: '/admin/activity' }];

    const filter = (action: string) => {
        router.get('/admin/activity', action === 'all' ? {} : { action }, { preserveState: true, preserveScroll: true });
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Activity log')} />
            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex items-end gap-3">
                    <div className="grid gap-1.5">
                        <Label>{t('Action')}</Label>
                        <Select value={filters.action === '' ? 'all' : filters.action} onValueChange={filter}>
                            <SelectTrigger className="w-56">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{t('All actions')}</SelectItem>
                                {actions.map((action) => (
                                    <SelectItem key={action} value={action}>
                                        {action}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <Card>
                    <CardContent className="p-0">
                        {logs.data.length === 0 ? (
                            <p className="text-muted-foreground py-12 text-center text-sm">{t('No admin activity recorded yet.')}</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>{t('When')}</TableHead>
                                            <TableHead>{t('Admin')}</TableHead>
                                            <TableHead>{t('Action')}</TableHead>
                                            <TableHead>{t('Subject')}</TableHead>
                                            <TableHead>{t('Details')}</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {logs.data.map((log) => (
                                            <TableRow key={log.id}>
                                                <TableCell className="whitespace-nowrap tabular-nums">{log.created_at}</TableCell>
                                                <TableCell>{log.actor ?? '—'}</TableCell>
                                                <TableCell>
                                                    <Badge variant="outline">{log.action}</Badge>
                                                </TableCell>
                                                <TableCell className="whitespace-nowrap">
                                                    {log.subject_type !== null ? `${log.subject_type} #${log.subject_id ?? ''}` : '—'}
                                                </TableCell>
                                                <TableCell className="text-muted-foreground max-w-96 truncate" title={contextSummary(log.context)}>
                                                    {contextSummary(log.context) || '—'}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {(logs.prev_page_url !== null || logs.next_page_url !== null) && (
                    <div className="flex items-center justify-between text-sm">
                        {logs.prev_page_url !== null ? (
                            <Link href={logs.prev_page_url} preserveScroll className="text-primary hover:underline">
                                {t('Previous')}
                            </Link>
                        ) : (
                            <span />
                        )}
                        {logs.next_page_url !== null && (
                            <Link href={logs.next_page_url} preserveScroll className="text-primary hover:underline">
                                {t('Next')}
                            </Link>
                        )}
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
