import { LeafletMap } from '@/components/maps/leaflet-map';
import { ApprovalStatusBadge, DocumentStatusBadge, useApprovalStatusLabels, useDocumentTypeLabels } from '@/components/provider/provider-badges';
import { useDayLabels, WEEK_DAYS } from '@/components/provider/working-hours-editor';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AdminLayout from '@/layouts/admin-layout';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem, type ProviderDocument, type ProviderProfile } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Check, ExternalLink, X } from 'lucide-react';
import { FormEventHandler, useState } from 'react';
import { Marker } from 'react-leaflet';

interface AdminProviderShowProps {
    provider: {
        id: number;
        name: string;
        email: string;
        phone: string | null;
        joined_at: string | null;
    };
    profile: ProviderProfile | null;
}

function RejectDocumentDialog({ document }: { document: ProviderDocument }) {
    const t = useTrans();
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors } = useForm({
        status: 'rejected',
        reject_reason: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('admin.provider-documents.review', document.id), {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    <X className="h-4 w-4" />
                    {t('Reject')}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('Reject document')}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor={`reject-${document.id}`}>{t('Reason')}</Label>
                        <Textarea
                            id={`reject-${document.id}`}
                            value={data.reject_reason}
                            onChange={(e) => setData('reject_reason', e.target.value)}
                            rows={2}
                        />
                        {errors.reject_reason !== undefined && <p className="text-destructive text-sm">{errors.reject_reason}</p>}
                    </div>
                    <DialogFooter>
                        <Button type="submit" variant="destructive" disabled={processing}>
                            {t('Reject document')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function AdminProviderShow({ provider, profile }: AdminProviderShowProps) {
    const t = useTrans();
    const statusLabels = useApprovalStatusLabels();
    const typeLabels = useDocumentTypeLabels();
    const dayLabels = useDayLabels();
    const pageErrors = usePage().props.errors;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('Providers'), href: '/admin/providers' },
        { title: provider.name, href: `/admin/providers/${provider.id}` },
    ];

    const review = useForm({
        status: profile?.approval_status ?? 'pending',
        note: '',
    });

    const submitReview: FormEventHandler = (e) => {
        e.preventDefault();
        review.post(route('admin.providers.review', provider.id), { preserveScroll: true });
    };

    const approveDocument = (document: ProviderDocument) => {
        router.post(route('admin.provider-documents.review', document.id), { status: 'approved' }, { preserveScroll: true });
    };

    const documents = profile?.documents ?? [];
    const blackouts = profile?.blackouts ?? [];

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={provider.name} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h1 className="text-xl font-semibold">{provider.name}</h1>
                        <p className="text-muted-foreground text-sm">
                            {provider.email}
                            {provider.phone !== null && ` · ${provider.phone}`}
                            {provider.joined_at !== null && ` · ${t('Joined :date', { date: provider.joined_at })}`}
                        </p>
                    </div>
                    {profile !== null && <ApprovalStatusBadge status={profile.approval_status} />}
                </div>

                {profile === null ? (
                    <Card>
                        <CardContent className="text-muted-foreground py-8 text-center text-sm">
                            {t('This provider has not started onboarding yet.')}
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 lg:grid-cols-3">
                        <div className="space-y-4 lg:col-span-2">
                            <Card>
                                <CardHeader>
                                    <CardTitle>{t('Profile')}</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {profile.bio !== null && <p className="text-sm">{profile.bio}</p>}
                                    <div className="text-muted-foreground grid gap-2 text-sm sm:grid-cols-3">
                                        <div>
                                            <p className="text-foreground font-medium">{t('Experience')}</p>
                                            <p>
                                                {profile.experience_years === null
                                                    ? '—'
                                                    : t(':years years', { years: String(profile.experience_years) })}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-foreground font-medium">{t('Service radius')}</p>
                                            <p>{profile.service_radius_km} km</p>
                                        </div>
                                        <div>
                                            <p className="text-foreground font-medium">{t('Availability')}</p>
                                            <p>{profile.is_online ? t('Online') : t('Offline')}</p>
                                        </div>
                                    </div>
                                    <div className="flex flex-wrap gap-1.5">
                                        {(profile.categories ?? []).map((category) => (
                                            <Badge key={category.id} variant="secondary">
                                                {category.name}
                                            </Badge>
                                        ))}
                                    </div>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="space-y-1 text-sm">
                                            <p className="font-medium">{t('Working hours')}</p>
                                            {WEEK_DAYS.map((day) => {
                                                const entry = profile.working_hours?.[day];

                                                return (
                                                    <div key={day} className="text-muted-foreground flex justify-between">
                                                        <span>{dayLabels[day]}</span>
                                                        <span>
                                                            {entry === undefined || entry.off ? t('Day off') : `${entry.start} – ${entry.end}`}
                                                        </span>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                        <div className="space-y-1 text-sm">
                                            <p className="font-medium">{t('Time off')}</p>
                                            {blackouts.length === 0 && <p className="text-muted-foreground">{t('No time off planned.')}</p>}
                                            {blackouts.map((blackout) => (
                                                <p key={blackout.id} className="text-muted-foreground">
                                                    {blackout.starts_label} — {blackout.ends_label}
                                                    {blackout.reason !== null && ` · ${blackout.reason}`}
                                                </p>
                                            ))}
                                        </div>
                                    </div>
                                    {profile.base_lat !== null && profile.base_lng !== null && (
                                        <LeafletMap center={[profile.base_lat, profile.base_lng]} zoom={13} className="h-64">
                                            <Marker position={[profile.base_lat, profile.base_lng]} />
                                        </LeafletMap>
                                    )}
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>{t('Documents')}</CardTitle>
                                    <CardDescription>{t('Review each KYC document before approving the provider.')}</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    {documents.length === 0 && <p className="text-muted-foreground text-sm">{t('No documents uploaded yet.')}</p>}
                                    {documents.map((document) => (
                                        <div
                                            key={document.id}
                                            className="flex flex-col gap-2 rounded-xl border p-4 sm:flex-row sm:items-center sm:justify-between"
                                        >
                                            <div className="space-y-1">
                                                <div className="flex items-center gap-2">
                                                    <span className="text-sm font-medium">{typeLabels[document.type]}</span>
                                                    <DocumentStatusBadge status={document.status} />
                                                </div>
                                                {document.reject_reason !== null && (
                                                    <p className="text-destructive text-sm">{document.reject_reason}</p>
                                                )}
                                                <a
                                                    href={document.url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="text-muted-foreground inline-flex items-center gap-1 text-xs underline"
                                                >
                                                    <ExternalLink className="h-3 w-3" />
                                                    {t('View uploaded file')}
                                                </a>
                                            </div>
                                            {document.status === 'pending' && (
                                                <div className="flex items-center gap-2">
                                                    <Button variant="outline" size="sm" onClick={() => approveDocument(document)}>
                                                        <Check className="h-4 w-4" />
                                                        {t('Approve')}
                                                    </Button>
                                                    <RejectDocumentDialog document={document} />
                                                </div>
                                            )}
                                        </div>
                                    ))}
                                </CardContent>
                            </Card>
                        </div>

                        <div className="space-y-4">
                            <Card>
                                <CardHeader>
                                    <CardTitle>{t('Review')}</CardTitle>
                                    <CardDescription>
                                        {profile.is_complete
                                            ? t('Profile is complete and ready for a decision.')
                                            : t('Profile is incomplete — approval is blocked until the provider finishes it.')}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <form onSubmit={submitReview} className="space-y-4">
                                        <div className="grid gap-2">
                                            <Label>{t('Decision')}</Label>
                                            <Select
                                                value={review.data.status}
                                                onValueChange={(value) => review.setData('status', value as typeof review.data.status)}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="pending">{statusLabels.pending}</SelectItem>
                                                    <SelectItem value="approved">{statusLabels.approved}</SelectItem>
                                                    <SelectItem value="rejected">{statusLabels.rejected}</SelectItem>
                                                    <SelectItem value="suspended">{statusLabels.suspended}</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="note">{t('Note to the provider')}</Label>
                                            <Textarea
                                                id="note"
                                                value={review.data.note}
                                                onChange={(e) => review.setData('note', e.target.value)}
                                                rows={3}
                                                placeholder={t('Required when rejecting or suspending.')}
                                            />
                                            {(review.errors.note !== undefined || pageErrors.note !== undefined) && (
                                                <p className="text-destructive text-sm">{review.errors.note ?? pageErrors.note}</p>
                                            )}
                                            {pageErrors.status !== undefined && <p className="text-destructive text-sm">{pageErrors.status}</p>}
                                        </div>
                                        <Button type="submit" disabled={review.processing} className="w-full">
                                            {t('Save decision')}
                                        </Button>
                                    </form>
                                </CardContent>
                            </Card>

                            {profile.approval_note !== null && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle>{t('Last review note')}</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <p className="text-muted-foreground text-sm">{profile.approval_note}</p>
                                    </CardContent>
                                </Card>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
