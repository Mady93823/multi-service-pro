import { useDayLabels, WEEK_DAYS } from '@/components/provider/working-hours-editor';
import { ReviewCard } from '@/components/reviews/review-card';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import ProviderLayout from '@/layouts/provider-layout';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem, type ProviderProfile, type Review } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Briefcase, CalendarOff, Pencil, Star, Trash2, Wrench } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface ProviderDashboardProps {
    profile: ProviderProfile;
    pending_offers: number;
    active_jobs: number;
    recent_reviews: Review[];
}

function AddBlackoutDialog() {
    const t = useTrans();
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        starts_on: '',
        ends_on: '',
        reason: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('provider.blackouts.store'), {
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
                <Button variant="outline" size="sm">
                    <CalendarOff className="h-4 w-4" />
                    {t('Add time off')}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('Add time off')}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="starts_on">{t('From')}</Label>
                            <Input id="starts_on" type="date" value={data.starts_on} onChange={(e) => setData('starts_on', e.target.value)} />
                            {errors.starts_on !== undefined && <p className="text-destructive text-sm">{errors.starts_on}</p>}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="ends_on">{t('To')}</Label>
                            <Input id="ends_on" type="date" value={data.ends_on} onChange={(e) => setData('ends_on', e.target.value)} />
                            {errors.ends_on !== undefined && <p className="text-destructive text-sm">{errors.ends_on}</p>}
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="reason">{t('Reason (optional)')}</Label>
                        <Textarea id="reason" value={data.reason} onChange={(e) => setData('reason', e.target.value)} rows={2} />
                        {errors.reason !== undefined && <p className="text-destructive text-sm">{errors.reason}</p>}
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={processing}>
                            {t('Save')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function ProviderDashboard({
    profile,
    pending_offers: pendingOffers,
    active_jobs: activeJobs,
    recent_reviews: recentReviews,
}: ProviderDashboardProps) {
    const t = useTrans();
    const dayLabels = useDayLabels();

    const breadcrumbs: BreadcrumbItem[] = [{ title: t('Dashboard'), href: '/provider/dashboard' }];

    const toggleOnline = () => {
        router.post(route('provider.availability.online'), {}, { preserveScroll: true });
    };

    const blackouts = profile.blackouts ?? [];

    return (
        <ProviderLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Dashboard')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">{t('Dashboard')}</h1>
                    <Button asChild variant="outline" size="sm">
                        <Link href={route('provider.onboarding')}>
                            <Pencil className="h-4 w-4" />
                            {t('Edit profile')}
                        </Link>
                    </Button>
                </div>

                <Card>
                    <CardContent className="flex items-center justify-between py-4">
                        <div>
                            <p className="font-medium">{profile.is_online ? t('You are online') : t('You are offline')}</p>
                            <p className="text-muted-foreground text-sm">
                                {profile.is_online
                                    ? t('New job offers will reach you while you are online.')
                                    : t('Go online to start receiving job offers.')}
                            </p>
                        </div>
                        <Switch checked={profile.is_online} onCheckedChange={toggleOnline} aria-label={t('Online')} />
                    </CardContent>
                </Card>

                <div className="grid gap-4 sm:grid-cols-3">
                    <Card>
                        <CardContent className="flex items-center gap-3 py-4">
                            <Wrench className="text-muted-foreground h-8 w-8" />
                            <div>
                                <p className="text-2xl font-semibold">{profile.jobs_completed}</p>
                                <p className="text-muted-foreground text-sm">{t('Jobs completed')}</p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="flex items-center gap-3 py-4">
                            <Star className="text-muted-foreground h-8 w-8" />
                            <div>
                                <p className="text-2xl font-semibold">{profile.rating_count === 0 ? '—' : profile.rating_avg.toFixed(1)}</p>
                                <p className="text-muted-foreground text-sm">{t(':count ratings', { count: String(profile.rating_count) })}</p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="flex items-center gap-3 py-4">
                            <div>
                                <p className="text-2xl font-semibold">{profile.service_radius_km} km</p>
                                <p className="text-muted-foreground text-sm">{t('Service radius')}</p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle>{t('Time off')}</CardTitle>
                                <CardDescription>{t('No job offers on these days.')}</CardDescription>
                            </div>
                            <AddBlackoutDialog />
                        </CardHeader>
                        <CardContent className="space-y-2">
                            {blackouts.length === 0 && <p className="text-muted-foreground text-sm">{t('No time off planned.')}</p>}
                            {blackouts.map((blackout) => (
                                <div key={blackout.id} className="flex items-center justify-between rounded-xl border p-3">
                                    <div>
                                        <p className="text-sm font-medium">
                                            {blackout.starts_label} — {blackout.ends_label}
                                        </p>
                                        {blackout.reason !== null && <p className="text-muted-foreground text-sm">{blackout.reason}</p>}
                                    </div>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Remove')}
                                        onClick={() => router.delete(route('provider.blackouts.destroy', blackout.id), { preserveScroll: true })}
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </Button>
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Working hours')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-1 text-sm">
                                {WEEK_DAYS.map((day) => {
                                    const entry = profile.working_hours?.[day];

                                    return (
                                        <div key={day} className="flex justify-between">
                                            <span>{dayLabels[day]}</span>
                                            <span className="text-muted-foreground">
                                                {entry === undefined || entry.off ? t('Day off') : `${entry.start} – ${entry.end}`}
                                            </span>
                                        </div>
                                    );
                                })}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('Recent reviews')}</CardTitle>
                        <CardDescription>{t('What customers said about your latest jobs.')}</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {recentReviews.length === 0 && <p className="text-muted-foreground text-sm">{t('No reviews yet.')}</p>}
                        {recentReviews.map((review) => (
                            <ReviewCard key={review.id} review={review} />
                        ))}
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="flex flex-wrap items-center justify-between gap-3 py-4">
                        <div className="flex items-center gap-3">
                            <Briefcase className="text-muted-foreground h-8 w-8" />
                            <div>
                                <p className="font-medium">
                                    {pendingOffers > 0 ? t(':count new job offers', { count: String(pendingOffers) }) : t('No new offers right now.')}
                                </p>
                                <p className="text-muted-foreground text-sm">{t(':count active jobs', { count: String(activeJobs) })}</p>
                            </div>
                        </div>
                        <Button asChild variant={pendingOffers > 0 ? 'default' : 'outline'} size="sm">
                            <Link href={route('provider.jobs.index')}>{t('Go to jobs')}</Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </ProviderLayout>
    );
}
