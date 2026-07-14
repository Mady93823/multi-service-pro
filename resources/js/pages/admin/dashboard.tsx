import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AdminLayout from '@/layouts/admin-layout';
import { useMoney } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import {
    type BookingsDayPoint,
    type BreadcrumbItem,
    type DashboardTiles,
    type LeaderboardRow,
    type RevenueDayPoint,
    type TopServiceRow,
} from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Bar, BarChart, CartesianGrid, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

interface CityRow {
    id: number;
    name: string;
    is_active: boolean;
    zones: number;
    bookings: number;
    gmv: number;
}

interface Props {
    tiles: DashboardTiles;
    bookings_per_day: BookingsDayPoint[];
    revenue_per_day: RevenueDayPoint[];
    top_services: TopServiceRow[];
    leaderboard: LeaderboardRow[];
    by_city: CityRow[];
}

const shortDate = (value: string) => {
    const date = new Date(`${value}T00:00:00`);

    // Intl throws RangeError on an invalid Date, which unmounts the whole page.
    return Number.isNaN(date.getTime()) ? value : new Intl.DateTimeFormat(undefined, { day: 'numeric', month: 'short' }).format(date);
};

const compact = (value: number) => new Intl.NumberFormat('en-IN', { notation: 'compact' }).format(value);

/** Recharts injects these; typed locally to stay strict without recharts' generics. */
interface TipEntry {
    name?: unknown;
    value?: unknown;
    color?: string;
}

function ChartTip({
    active,
    label,
    payload,
    format,
    formatLabel,
}: {
    active?: boolean;
    label?: unknown;
    payload?: readonly TipEntry[];
    format: (value: number) => string;
    /** Labels are dates on the time-series charts but service names on top services — only date charts pass shortDate. */
    formatLabel?: (label: string) => string;
}) {
    if (!active || !payload || payload.length === 0) {
        return null;
    }

    return (
        <div className="bg-popover rounded-md border px-3 py-2 text-xs shadow-md">
            <p className="text-foreground mb-1 font-medium">
                {typeof label === 'string' ? (formatLabel ? formatLabel(label) : label) : String(label ?? '')}
            </p>
            {payload.map((entry, i) => (
                <p key={i} className="text-muted-foreground flex items-center gap-1.5">
                    <span className="inline-block size-2 rounded-[2px]" style={{ background: entry.color }} aria-hidden />
                    {String(entry.name ?? '')}: <span className="text-foreground font-medium">{format(Number(entry.value ?? 0))}</span>
                </p>
            ))}
        </div>
    );
}

function Tile({ label, value, sub }: { label: string; value: string; sub?: string }) {
    return (
        <Card>
            <CardContent className="p-4">
                <p className="text-muted-foreground text-xs">{label}</p>
                <p className="mt-1 text-2xl font-semibold">{value}</p>
                {sub ? <p className="text-muted-foreground mt-0.5 text-xs">{sub}</p> : null}
            </CardContent>
        </Card>
    );
}

function ChartCard({ title, reportHref, children }: { title: string; reportHref?: string; children: React.ReactNode }) {
    const t = useTrans();

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">{title}</CardTitle>
                {reportHref ? (
                    <Link href={reportHref} className="text-muted-foreground text-xs underline-offset-2 hover:underline">
                        {t('View report')}
                    </Link>
                ) : null}
            </CardHeader>
            <CardContent>{children}</CardContent>
        </Card>
    );
}

export default function AdminDashboard({ tiles, bookings_per_day, revenue_per_day, top_services, leaderboard, by_city }: Props) {
    const t = useTrans();
    const money = useMoney();

    const breadcrumbs: BreadcrumbItem[] = [{ title: t('Dashboard'), href: '/admin/dashboard' }];

    const series1 = 'var(--chart-1)';
    const series2 = 'var(--chart-2)';
    const grid = 'var(--border)';
    const ink = 'var(--muted-foreground)';

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Admin Dashboard')} />
            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6">
                    <Tile
                        label={t('Bookings today')}
                        value={String(tiles.bookings_today)}
                        sub={t(':count this week', { count: String(tiles.bookings_week) })}
                    />
                    <Tile label={t('GMV this month')} value={money(tiles.gmv_month)} sub={t('Completed bookings')} />
                    <Tile label={t('Commission this month')} value={money(tiles.commission_month)} sub={t('Platform earnings')} />
                    <Tile label={t('Open jobs')} value={String(tiles.open_jobs)} sub={t('Assigned to in-progress')} />
                    <Tile
                        label={t('Pending payouts')}
                        value={money(tiles.pending_payouts_amount)}
                        sub={t(':count requests', { count: String(tiles.pending_payouts_count) })}
                    />
                    <Tile
                        label={t('Providers online')}
                        value={String(tiles.providers_online)}
                        sub={t(':count pending KYC', { count: String(tiles.providers_pending_kyc) })}
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <ChartCard title={t('Bookings — last 30 days')} reportHref="/admin/reports/bookings">
                        <ResponsiveContainer width="100%" height={260}>
                            <BarChart data={bookings_per_day} margin={{ top: 4, right: 4, bottom: 0, left: -16 }}>
                                <CartesianGrid vertical={false} stroke={grid} />
                                <XAxis
                                    dataKey="date"
                                    tickFormatter={shortDate}
                                    tick={{ fill: ink, fontSize: 11 }}
                                    tickLine={false}
                                    axisLine={{ stroke: grid }}
                                    minTickGap={24}
                                />
                                <YAxis allowDecimals={false} tick={{ fill: ink, fontSize: 11 }} tickLine={false} axisLine={false} />
                                <Tooltip
                                    cursor={{ fill: 'var(--muted)', opacity: 0.4 }}
                                    content={<ChartTip format={(v) => String(v)} formatLabel={shortDate} />}
                                />
                                <Bar dataKey="bookings" name={t('Bookings')} fill={series1} radius={[4, 4, 0, 0]} maxBarSize={18} />
                            </BarChart>
                        </ResponsiveContainer>
                    </ChartCard>

                    <ChartCard title={t('Revenue — last 30 days')} reportHref="/admin/reports/earnings">
                        <ResponsiveContainer width="100%" height={260}>
                            <LineChart data={revenue_per_day} margin={{ top: 4, right: 4, bottom: 0, left: -8 }}>
                                <CartesianGrid vertical={false} stroke={grid} />
                                <XAxis
                                    dataKey="date"
                                    tickFormatter={shortDate}
                                    tick={{ fill: ink, fontSize: 11 }}
                                    tickLine={false}
                                    axisLine={{ stroke: grid }}
                                    minTickGap={24}
                                />
                                <YAxis tickFormatter={compact} tick={{ fill: ink, fontSize: 11 }} tickLine={false} axisLine={false} />
                                <Tooltip cursor={{ stroke: grid }} content={<ChartTip format={money} formatLabel={shortDate} />} />
                                <Line
                                    type="monotone"
                                    dataKey="gross"
                                    name={t('Gross (GMV)')}
                                    stroke={series1}
                                    strokeWidth={2}
                                    dot={false}
                                    activeDot={{ r: 4 }}
                                />
                                <Line
                                    type="monotone"
                                    dataKey="commission"
                                    name={t('Commission')}
                                    stroke={series2}
                                    strokeWidth={2}
                                    dot={false}
                                    activeDot={{ r: 4 }}
                                />
                            </LineChart>
                        </ResponsiveContainer>
                        <div className="text-muted-foreground mt-2 flex items-center gap-4 text-xs">
                            <span className="flex items-center gap-1.5">
                                <span className="inline-block h-0.5 w-4 rounded" style={{ background: series1 }} aria-hidden />
                                {t('Gross (GMV)')}
                            </span>
                            <span className="flex items-center gap-1.5">
                                <span className="inline-block h-0.5 w-4 rounded" style={{ background: series2 }} aria-hidden />
                                {t('Commission')}
                            </span>
                        </div>
                    </ChartCard>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <ChartCard title={t('Top services — last 30 days')} reportHref="/admin/reports/services">
                        {top_services.length === 0 ? (
                            <p className="text-muted-foreground py-10 text-center text-sm">{t('No completed bookings in this window yet.')}</p>
                        ) : (
                            <ResponsiveContainer width="100%" height={Math.max(120, top_services.length * 40)}>
                                <BarChart data={top_services} layout="vertical" margin={{ top: 4, right: 48, bottom: 0, left: 8 }}>
                                    <CartesianGrid horizontal={false} stroke={grid} />
                                    <XAxis
                                        type="number"
                                        tickFormatter={compact}
                                        tick={{ fill: ink, fontSize: 11 }}
                                        tickLine={false}
                                        axisLine={false}
                                    />
                                    <YAxis
                                        type="category"
                                        dataKey="service"
                                        width={140}
                                        tick={{ fill: ink, fontSize: 11 }}
                                        tickLine={false}
                                        axisLine={{ stroke: grid }}
                                    />
                                    <Tooltip cursor={{ fill: 'var(--muted)', opacity: 0.4 }} content={<ChartTip format={money} />} />
                                    <Bar dataKey="revenue" name={t('Revenue')} fill={series1} radius={[0, 4, 4, 0]} maxBarSize={16} />
                                </BarChart>
                            </ResponsiveContainer>
                        )}
                    </ChartCard>

                    <ChartCard title={t('Provider leaderboard')} reportHref="/admin/reports/providers">
                        {leaderboard.length === 0 ? (
                            <p className="text-muted-foreground py-10 text-center text-sm">{t('No approved providers yet.')}</p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>{t('Provider')}</TableHead>
                                        <TableHead className="text-right">{t('Jobs')}</TableHead>
                                        <TableHead className="text-right">{t('Rating')}</TableHead>
                                        <TableHead className="text-right">{t('Gross')}</TableHead>
                                        <TableHead className="text-right">{t('Net')}</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {leaderboard.map((row) => (
                                        <TableRow key={row.id}>
                                            <TableCell className="font-medium">{row.name}</TableCell>
                                            <TableCell className="text-right tabular-nums">{row.jobs_completed}</TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {row.rating_count > 0 ? `${row.rating_avg.toFixed(2)} (${row.rating_count})` : '—'}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">{money(row.gross)}</TableCell>
                                            <TableCell className="text-right tabular-nums">{money(row.net)}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </ChartCard>

                    {/* M25: how each town is trading. A city with no bookings is a row of
                        zeroes, not a missing row — that is the answer to "how is the launch going". */}
                    <ChartCard title={t('Cities (last 30 days)')} reportHref="/admin/cities">
                        {by_city.length === 0 ? (
                            <p className="text-muted-foreground py-10 text-center text-sm">{t('No cities yet.')}</p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>{t('City')}</TableHead>
                                        <TableHead className="text-right">{t('Zones')}</TableHead>
                                        <TableHead className="text-right">{t('Bookings')}</TableHead>
                                        <TableHead className="text-right">{t('Completed value')}</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {by_city.map((row) => (
                                        <TableRow key={row.id}>
                                            <TableCell className="font-medium">
                                                {row.name}
                                                {!row.is_active && <span className="text-muted-foreground ml-2 text-xs">{t('Hidden')}</span>}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">{row.zones}</TableCell>
                                            <TableCell className="text-right tabular-nums">{row.bookings}</TableCell>
                                            <TableCell className="text-right tabular-nums">{money(row.gmv)}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </ChartCard>
                </div>
            </div>
        </AdminLayout>
    );
}
