import { AddressPinPicker, type PinPoint } from '@/components/maps/address-pin-picker';
import { ApprovalStatusBadge, DocumentStatusBadge, useDocumentTypeLabels } from '@/components/provider/provider-badges';
import { defaultWorkingHours, WorkingHoursEditor } from '@/components/provider/working-hours-editor';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import ProviderLayout from '@/layouts/provider-layout';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem, type ProviderDocument, type ProviderDocumentType, type ProviderProfile, type WorkingHours } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { ExternalLink, Upload } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface CategoryOption {
    id: number;
    name: string;
    parent_id: number | null;
}

interface OnboardingProps {
    profile: ProviderProfile | null;
    categories: CategoryOption[];
}

type ProfileForm = {
    bio: string;
    experience_years: string;
    base_lat: number | null;
    base_lng: number | null;
    service_radius_km: number;
    working_hours: WorkingHours;
    category_ids: number[];
};

const DOCUMENT_TYPES: ProviderDocumentType[] = ['id_proof', 'address_proof', 'photo', 'certificate'];

function StatusBanner({ profile }: { profile: ProviderProfile | null }) {
    const t = useTrans();

    if (profile === null) {
        return (
            <div className="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-200">
                {t('Welcome! Complete your profile and upload your documents to start receiving jobs.')}
            </div>
        );
    }

    if (profile.approval_status === 'pending') {
        return (
            <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                {profile.is_complete
                    ? t('Your profile is under review. We will notify you once it is approved.')
                    : t('Finish your profile — base location, working hours, and at least one category are required for review.')}
            </div>
        );
    }

    if (profile.approval_status === 'rejected') {
        return (
            <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                <p className="font-medium">{t('Your application was rejected.')}</p>
                {profile.approval_note !== null && <p className="mt-1">{profile.approval_note}</p>}
                <p className="mt-1">{t('Update your details or documents to resubmit for review.')}</p>
            </div>
        );
    }

    if (profile.approval_status === 'suspended') {
        return (
            <div className="bg-muted rounded-xl border p-4 text-sm">
                <p className="font-medium">{t('Your account is suspended.')}</p>
                {profile.approval_note !== null && <p className="text-muted-foreground mt-1">{profile.approval_note}</p>}
                <p className="text-muted-foreground mt-1">{t('Contact support for help.')}</p>
            </div>
        );
    }

    return null;
}

function DocumentRow({ type, document }: { type: ProviderDocumentType; document: ProviderDocument | undefined }) {
    const t = useTrans();
    const typeLabels = useDocumentTypeLabels();
    const [file, setFile] = useState<File | null>(null);
    const [uploading, setUploading] = useState(false);

    const upload = () => {
        if (file === null) {
            return;
        }

        router.post(
            route('provider.documents.store'),
            { type, file },
            {
                forceFormData: true,
                preserveScroll: true,
                onStart: () => setUploading(true),
                onFinish: () => setUploading(false),
                onSuccess: () => setFile(null),
            },
        );
    };

    return (
        <div className="flex flex-col gap-2 rounded-xl border p-4 sm:flex-row sm:items-center sm:justify-between">
            <div className="space-y-1">
                <div className="flex items-center gap-2">
                    <span className="text-sm font-medium">{typeLabels[type]}</span>
                    {type !== 'certificate' ? null : <span className="text-muted-foreground text-xs">({t('optional')})</span>}
                    {document !== undefined && <DocumentStatusBadge status={document.status} />}
                </div>
                {document?.status === 'rejected' && document.reject_reason !== null && (
                    <p className="text-destructive text-sm">{document.reject_reason}</p>
                )}
                {document !== undefined && (
                    <a
                        href={document.url}
                        target="_blank"
                        rel="noreferrer"
                        className="text-muted-foreground inline-flex items-center gap-1 text-xs underline"
                    >
                        <ExternalLink className="h-3 w-3" />
                        {t('View uploaded file')}
                    </a>
                )}
            </div>
            <div className="flex items-center gap-2">
                <Input
                    type="file"
                    accept=".jpg,.jpeg,.png,.webp,.pdf"
                    onChange={(e) => setFile(e.target.files?.[0] ?? null)}
                    className="w-56"
                    aria-label={typeLabels[type]}
                />
                <Button type="button" variant="outline" disabled={file === null || uploading} onClick={upload}>
                    <Upload className="h-4 w-4" />
                    {document === undefined ? t('Upload') : t('Replace')}
                </Button>
            </div>
        </div>
    );
}

export default function ProviderOnboarding({ profile, categories }: OnboardingProps) {
    const t = useTrans();

    const breadcrumbs: BreadcrumbItem[] = [{ title: t('Profile'), href: '/provider/onboarding' }];

    const { data, setData, put, processing, errors } = useForm<ProfileForm>({
        bio: profile?.bio ?? '',
        experience_years: profile?.experience_years === null || profile === null ? '' : String(profile.experience_years),
        base_lat: profile?.base_lat ?? null,
        base_lng: profile?.base_lng ?? null,
        service_radius_km: profile?.service_radius_km ?? 10,
        working_hours: profile?.working_hours ?? defaultWorkingHours(),
        category_ids: profile?.categories?.map((category) => category.id) ?? [],
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('provider.profile.update'), { preserveScroll: true });
    };

    const toggleCategory = (id: number, checked: boolean) => {
        setData('category_ids', checked ? [...data.category_ids, id] : data.category_ids.filter((current) => current !== id));
    };

    const pin: PinPoint | null = data.base_lat === null || data.base_lng === null ? null : { lat: data.base_lat, lng: data.base_lng };

    const workingHourErrors = Object.entries(errors)
        .filter(([key]) => key.startsWith('working_hours'))
        .map(([, message]) => message);

    const roots = categories.filter((category) => category.parent_id === null);
    const childrenOf = (rootId: number) => categories.filter((category) => category.parent_id === rootId);

    const documents = profile?.documents ?? [];

    return (
        <ProviderLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Profile')} />
            <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">{t('Your professional profile')}</h1>
                    {profile !== null && <ApprovalStatusBadge status={profile.approval_status} />}
                </div>

                <StatusBanner profile={profile} />

                <form onSubmit={submit}>
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Profile details')}</CardTitle>
                            <CardDescription>{t('Customers see your bio and experience; dispatch uses your location and hours.')}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div className="grid gap-2">
                                <Label htmlFor="bio">{t('Bio')}</Label>
                                <Textarea
                                    id="bio"
                                    value={data.bio}
                                    onChange={(e) => setData('bio', e.target.value)}
                                    placeholder={t('Tell customers about your skills and experience…')}
                                    rows={3}
                                />
                                {errors.bio !== undefined && <p className="text-destructive text-sm">{errors.bio}</p>}
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="experience_years">{t('Years of experience')}</Label>
                                    <Input
                                        id="experience_years"
                                        type="number"
                                        min={0}
                                        max={60}
                                        value={data.experience_years}
                                        onChange={(e) => setData('experience_years', e.target.value)}
                                    />
                                    {errors.experience_years !== undefined && <p className="text-destructive text-sm">{errors.experience_years}</p>}
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="service_radius_km">{t('Service radius (km)')}</Label>
                                    <Input
                                        id="service_radius_km"
                                        type="number"
                                        min={1}
                                        max={100}
                                        value={data.service_radius_km}
                                        onChange={(e) => setData('service_radius_km', Number(e.target.value))}
                                    />
                                    {errors.service_radius_km !== undefined && <p className="text-destructive text-sm">{errors.service_radius_km}</p>}
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label>{t('What can you do?')}</Label>
                                <div className="grid gap-2 sm:grid-cols-2">
                                    {roots.map((root) => (
                                        <div key={root.id} className="space-y-2 rounded-xl border p-3">
                                            <label className="flex items-center gap-2 text-sm font-medium">
                                                <Checkbox
                                                    checked={data.category_ids.includes(root.id)}
                                                    onCheckedChange={(checked) => toggleCategory(root.id, checked === true)}
                                                />
                                                {root.name}
                                            </label>
                                            {childrenOf(root.id).map((child) => (
                                                <label key={child.id} className="ml-6 flex items-center gap-2 text-sm">
                                                    <Checkbox
                                                        checked={data.category_ids.includes(child.id)}
                                                        onCheckedChange={(checked) => toggleCategory(child.id, checked === true)}
                                                    />
                                                    {child.name}
                                                </label>
                                            ))}
                                        </div>
                                    ))}
                                </div>
                                {errors.category_ids !== undefined && <p className="text-destructive text-sm">{errors.category_ids}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label>{t('Working hours')}</Label>
                                <WorkingHoursEditor value={data.working_hours} onChange={(value) => setData('working_hours', value)} />
                                {workingHourErrors.map((message) => (
                                    <p key={message} className="text-destructive text-sm">
                                        {message}
                                    </p>
                                ))}
                            </div>

                            <div className="grid gap-2">
                                <Label>{t('Base location')}</Label>
                                <p className="text-muted-foreground text-sm">{t('Jobs are offered within your service radius of this point.')}</p>
                                <AddressPinPicker
                                    value={pin}
                                    onChange={(point) => {
                                        setData((current) => ({ ...current, base_lat: point.lat, base_lng: point.lng }));
                                    }}
                                />
                                {(errors.base_lat !== undefined || errors.base_lng !== undefined) && (
                                    <p className="text-destructive text-sm">{errors.base_lat ?? errors.base_lng}</p>
                                )}
                            </div>

                            <Button type="submit" disabled={processing}>
                                {t('Save profile')}
                            </Button>
                        </CardContent>
                    </Card>
                </form>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('Documents')}</CardTitle>
                        <CardDescription>{t('Clear photos or PDFs, 4 MB max. We review every upload.')}</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {DOCUMENT_TYPES.map((type) => (
                            <DocumentRow key={type} type={type} document={documents.find((document) => document.type === type)} />
                        ))}
                    </CardContent>
                </Card>
            </div>
        </ProviderLayout>
    );
}
