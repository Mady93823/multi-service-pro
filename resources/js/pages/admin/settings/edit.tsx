import AppearanceForm, { type AppearanceValues } from '@/components/admin/settings/appearance-form';
import BookingForm, { type BookingValues } from '@/components/admin/settings/booking-form';
import BrandingForm, { type BrandingValues } from '@/components/admin/settings/branding-form';
import CookieForm, { type CookieValues } from '@/components/admin/settings/cookie-form';
import CustomCodeForm, { type CustomCodeValues } from '@/components/admin/settings/custom-code-form';
import DispatchForm, { type DispatchValues } from '@/components/admin/settings/dispatch-form';
import FeaturesForm, { type FeaturesValues } from '@/components/admin/settings/features-form';
import InvoiceForm, { type InvoiceValues } from '@/components/admin/settings/invoice-form';
import LocalizationForm, { type LocalizationValues } from '@/components/admin/settings/localization-form';
import PaymentsForm, { type PaymentsValues } from '@/components/admin/settings/payments-form';
import PayoutsForm, { type PayoutsValues } from '@/components/admin/settings/payouts-form';
import ReferralsForm, { type ReferralsValues } from '@/components/admin/settings/referrals-form';
import ReviewsForm, { type ReviewsValues } from '@/components/admin/settings/reviews-form';
import SocialForm, { type SocialValues } from '@/components/admin/settings/social-form';
import SupportForm, { type SupportValues } from '@/components/admin/settings/support-form';
import TrackingForm, { type TrackingValues } from '@/components/admin/settings/tracking-form';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AdminLayout from '@/layouts/admin-layout';
import { useTrans } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

interface SettingsGroupLink {
    key: string;
    label: string;
}

interface SettingsEditProps {
    group: string;
    title: string;
    description: string;
    groups: SettingsGroupLink[];
    /** Shape depends on the group — the server sends exactly what its form needs (D24). */
    values: Record<string, unknown>;
}

/**
 * One screen per settings group (ADR D24). The server decides which group is
 * being edited and sends only that group's values; each form posts back only
 * its own fields, so a new key in one group can never 422 another group's save.
 */
export default function SettingsEdit({ group, title, description, groups, values }: SettingsEditProps) {
    const t = useTrans();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('Settings'), href: '/admin/settings' },
        { title, href: `/admin/settings/${group}` },
    ];

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={`${t('Settings')} — ${title}`} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">{t('Settings')}</h1>

                <div className="flex flex-col gap-6 lg:flex-row">
                    <nav className="flex shrink-0 gap-1 overflow-x-auto lg:w-52 lg:flex-col lg:overflow-visible">
                        {groups.map((item) => (
                            <Link
                                key={item.key}
                                href={route('admin.settings.edit', item.key)}
                                prefetch
                                className={cn(
                                    'rounded-md px-3 py-2 text-sm whitespace-nowrap transition-colors',
                                    item.key === group ? 'bg-muted font-medium' : 'text-muted-foreground hover:bg-muted/60',
                                )}
                            >
                                {item.label}
                            </Link>
                        ))}
                    </nav>

                    <Card className="max-w-2xl flex-1">
                        <CardHeader>
                            <CardTitle>{title}</CardTitle>
                            <CardDescription>{description}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {group === 'branding' && <BrandingForm values={values as unknown as BrandingValues} />}
                            {group === 'appearance' && <AppearanceForm values={values as unknown as AppearanceValues} />}
                            {group === 'social' && <SocialForm values={values as unknown as SocialValues} />}
                            {group === 'localization' && <LocalizationForm values={values as unknown as LocalizationValues} />}
                            {group === 'features' && <FeaturesForm values={values as unknown as FeaturesValues} />}
                            {group === 'booking' && <BookingForm values={values as unknown as BookingValues} />}
                            {group === 'dispatch' && <DispatchForm values={values as unknown as DispatchValues} />}
                            {group === 'tracking' && <TrackingForm values={values as unknown as TrackingValues} />}
                            {group === 'payments' && <PaymentsForm values={values as unknown as PaymentsValues} />}
                            {group === 'payouts' && <PayoutsForm values={values as unknown as PayoutsValues} />}
                            {group === 'invoice' && <InvoiceForm values={values as unknown as InvoiceValues} />}
                            {group === 'reviews' && <ReviewsForm values={values as unknown as ReviewsValues} />}
                            {group === 'referrals' && <ReferralsForm values={values as unknown as ReferralsValues} />}
                            {group === 'support' && <SupportForm values={values as unknown as SupportValues} />}
                            {group === 'cookie' && <CookieForm values={values as unknown as CookieValues} />}
                            {group === 'custom_code' && <CustomCodeForm values={values as unknown as CustomCodeValues} />}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AdminLayout>
    );
}
