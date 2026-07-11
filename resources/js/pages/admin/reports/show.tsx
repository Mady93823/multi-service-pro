import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AdminLayout from '@/layouts/admin-layout';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem, type NativePaginated, type ReportFiltersState, type ReportInfo, type ReportRow } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useState } from 'react';

interface Props {
    report: ReportInfo;
    filters: ReportFiltersState;
    rows: NativePaginated<ReportRow>;
    sync_limit: number;
}

const REPORT_TABS = ['bookings', 'earnings', 'services', 'providers'] as const;

function cell(value: string | number | null): string {
    if (value === null || value === '') {
        return '—';
    }

    if (typeof value === 'number') {
        return new Intl.NumberFormat('en-IN', { maximumFractionDigits: 2 }).format(value);
    }

    return value;
}

export default function ReportShow({ report, filters, rows }: Props) {
    const t = useTrans();

    const tabLabels: Record<(typeof REPORT_TABS)[number], string> = {
        bookings: t('Bookings'),
        earnings: t('Earnings'),
        services: t('Services'),
        providers: t('Providers'),
    };

    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');
    const [status, setStatus] = useState(filters.status ?? 'all');

    const query = () => {
        const params: Record<string, string> = {};
        if (from !== '') params.from = from;
        if (to !== '') params.to = to;
        if (status !== 'all') params.status = status;

        return params;
    };

    const apply = (event: React.FormEvent) => {
        event.preventDefault();
        router.get(`/admin/reports/${report.slug}`, query(), { preserveState: true, preserveScroll: true });
    };

    const exportUrl = () => {
        const params = new URLSearchParams(query());
        const suffix = params.size > 0 ? `?${params.toString()}` : '';

        return `/admin/reports/${report.slug}/export${suffix}`;
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Reports'), href: '/admin/reports/bookings' },
        { title: report.title, href: `/admin/reports/${report.slug}` },
    ];

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={report.title} />
            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center gap-2">
                    {REPORT_TABS.map((slug) => (
                        <Link
                            key={slug}
                            href={`/admin/reports/${slug}`}
                            className={`rounded-md px-3 py-1.5 text-sm ${
                                slug === report.slug ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted'
                            }`}
                        >
                            {tabLabels[slug]}
                        </Link>
                    ))}
                </div>

                <Card>
                    <CardContent className="p-4">
                        <form onSubmit={apply} className="flex flex-wrap items-end gap-3">
                            <div className="grid gap-1.5">
                                <Label htmlFor="from">{t('From')}</Label>
                                <Input id="from" type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="w-40" />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="to">{t('To')}</Label>
                                <Input id="to" type="date" value={to} onChange={(e) => setTo(e.target.value)} className="w-40" />
                            </div>
                            {report.status_options.length > 0 && (
                                <div className="grid gap-1.5">
                                    <Label>{t('Status')}</Label>
                                    <Select value={status} onValueChange={setStatus}>
                                        <SelectTrigger className="w-44">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">{t('All statuses')}</SelectItem>
                                            {report.status_options.map((option) => (
                                                <SelectItem key={option} value={option}>
                                                    {option.replaceAll('_', ' ')}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            )}
                            <Button type="submit" variant="secondary">
                                {t('Apply')}
                            </Button>
                            <Button asChild variant="outline">
                                {/* Plain anchor, not an Inertia Link — the response is a file stream. */}
                                <a href={exportUrl()}>
                                    <Download aria-hidden />
                                    {t('Export CSV')}
                                </a>
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-0">
                        {rows.data.length === 0 ? (
                            <p className="text-muted-foreground py-12 text-center text-sm">{t('Nothing matches these filters.')}</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            {report.columns.map((column) => (
                                                <TableHead key={column.key}>{column.label}</TableHead>
                                            ))}
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {rows.data.map((row, i) => (
                                            <TableRow key={i}>
                                                {report.columns.map((column) => (
                                                    <TableCell
                                                        key={column.key}
                                                        className={typeof row[column.key] === 'number' ? 'text-right tabular-nums' : ''}
                                                    >
                                                        {cell(row[column.key] ?? null)}
                                                    </TableCell>
                                                ))}
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {(rows.prev_page_url !== null || rows.next_page_url !== null) && (
                    <div className="flex items-center justify-between text-sm">
                        {rows.prev_page_url !== null ? (
                            <Link href={rows.prev_page_url} preserveScroll className="text-primary hover:underline">
                                {t('Previous')}
                            </Link>
                        ) : (
                            <span />
                        )}
                        {rows.next_page_url !== null && (
                            <Link href={rows.next_page_url} preserveScroll className="text-primary hover:underline">
                                {t('Next')}
                            </Link>
                        )}
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
